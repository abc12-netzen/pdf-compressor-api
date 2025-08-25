;(($) => {
  // Prevent multiple execution
  if (window.pdfCompressorInitialized) {
    return
  }
  window.pdfCompressorInitialized = true

  function getPdfCompressorAjax() {
    // Try multiple sources for the AJAX data
    return window.pdfCompressorAjax || window.pdfCompressorPro || (window.wp && window.wp.pdfCompressor) || {}
  }

  function initializeWithRetry(maxRetries = 5, delay = 500) {
    let retries = 0

    function tryInitialize() {
      const pdfCompressorAjax = getPdfCompressorAjax()

      console.log("[v0] PDF Compressor initialization attempt", retries + 1, {
        ajaxurl: pdfCompressorAjax.ajaxurl,
        hasNonce: !!pdfCompressorAjax.nonce,
        maxFileSize: pdfCompressorAjax.max_file_size,
        fullObject: pdfCompressorAjax,
      })

      // Check if we have the required data
      if (pdfCompressorAjax.ajaxurl && pdfCompressorAjax.nonce) {
        console.log("[v0] PDF Compressor initialized successfully")
        initializePdfCompressor(pdfCompressorAjax)
        return
      }

      // If we don't have the data and haven't exceeded max retries, try again
      if (retries < maxRetries) {
        retries++
        console.log("[v0] AJAX data not ready, retrying in", delay, "ms")
        setTimeout(tryInitialize, delay)
        return
      }

      console.warn("[PDF Compressor] AJAX data not available after retries, using fallback")
      const fallbackAjax = {
        ajaxurl: "/wp-admin/admin-ajax.php",
        nonce: "fallback_nonce",
        max_file_size: 50 * 1024 * 1024,
        allowed_types: ["application/pdf"],
        messages: {
          invalid_file: "Please select a valid PDF file.",
          file_too_large: "File size exceeds 50MB limit.",
          compression_failed: "Compression failed. Please try again.",
          compression_success: "PDF compressed successfully!",
        },
      }
      initializePdfCompressor(fallbackAjax)
    }

    tryInitialize()
  }

  function initializePdfCompressor(pdfCompressorAjax) {
    let selectedFile = null
    let isProcessing = false

    // File input and upload area elements
    const $fileInput = $("#pdf-file-input")
    const $uploadArea = $("#file-upload-area")
    const $browseButton = $("#browse-button")
    const $compressionOptions = $("#compression-options")
    const $fileInfo = $("#file-info")
    const $actionButtons = $("#action-buttons")
    const $progressContainer = $("#progress-container")
    const $resultContainer = $("#result-container")
    const $errorMessage = $("#error-message")

    // Browse button click handler - only bind once
    $browseButton.off("click.pdfCompressor").on("click.pdfCompressor", (e) => {
      e.preventDefault()
      e.stopPropagation()
      if (!isProcessing) {
        $fileInput.trigger("click")
      }
    })

    // File input change handler
    $fileInput.off("change.pdfCompressor").on("change.pdfCompressor", (e) => {
      const file = e.target.files[0]
      if (file) {
        handleFileSelection(file)
      }
    })

    // Drag and drop handlers
    $uploadArea.off("dragover.pdfCompressor").on("dragover.pdfCompressor", function (e) {
      e.preventDefault()
      e.stopPropagation()
      if (!isProcessing) {
        $(this).addClass("dragover")
      }
    })

    $uploadArea.off("dragleave.pdfCompressor").on("dragleave.pdfCompressor", function (e) {
      e.preventDefault()
      e.stopPropagation()
      $(this).removeClass("dragover")
    })

    $uploadArea.off("drop.pdfCompressor").on("drop.pdfCompressor", function (e) {
      e.preventDefault()
      e.stopPropagation()
      $(this).removeClass("dragover")

      if (!isProcessing) {
        const files = e.originalEvent.dataTransfer.files
        if (files.length > 0) {
          handleFileSelection(files[0])
        }
      }
    })

    // Custom size radio handler
    $('input[name="target_size"]')
      .off("change.pdfCompressor")
      .on("change.pdfCompressor", function () {
        const $customInput = $("#custom-size-input")
        if ($(this).val() === "custom") {
          $customInput.show()
        } else {
          $customInput.hide()
        }
      })

    // Compress button handler
    $("#compress-button")
      .off("click.pdfCompressor")
      .on("click.pdfCompressor", () => {
        if (!isProcessing && selectedFile) {
          compressPDF()
        }
      })

    // Reset button handler
    $("#reset-button")
      .off("click.pdfCompressor")
      .on("click.pdfCompressor", () => {
        resetForm()
      })

    // Download button handler
    $(document)
      .off("click.pdfCompressor", "#download-button")
      .on("click.pdfCompressor", "#download-button", function () {
        const downloadUrl = $(this).data("download-url")
        if (downloadUrl) {
          window.location.href = downloadUrl
        }
      })

    function handleFileSelection(file) {
      // Validate file
      if (!validateFile(file)) {
        return
      }

      selectedFile = file

      // Show file info
      $("#file-name").text(file.name)
      $("#file-size").text(formatFileSize(file.size))

      // Show relevant sections
      $fileInfo.show()
      $actionButtons.show()

      // Hide other sections
      $resultContainer.hide()
      $errorMessage.hide()
      $progressContainer.hide()
    }

    function validateFile(file) {
      // Check file type
      if (file.type !== "application/pdf") {
        showError(pdfCompressorAjax.messages?.invalid_file || "Invalid file type. Please select a PDF file.")
        return false
      }

      // Check file size
      const maxSize = pdfCompressorAjax.max_file_size || 10 * 1024 * 1024 // Default 10MB
      if (file.size > maxSize) {
        showError(pdfCompressorAjax.messages?.file_too_large || "File is too large.")
        return false
      }

      return true
    }

    function compressPDF() {
      if (isProcessing) return

      isProcessing = true

      // Get target size
      const targetSizeRadio = $('input[name="target_size"]:checked').val()
      let targetSize

      if (targetSizeRadio === "custom") {
        targetSize = Number.parseInt($("#custom-size").val())
        if (!targetSize || targetSize < 50 || targetSize > 5000) {
          showError("Please enter a valid custom size between 50KB and 5000KB")
          isProcessing = false
          return
        }
      } else {
        targetSize = Number.parseInt(targetSizeRadio)
      }

      // Show progress
      $progressContainer.show()
      $actionButtons.hide()
      $errorMessage.hide()
      $resultContainer.hide()

      // Simulate progress
      let progress = 0
      const progressInterval = setInterval(() => {
        progress += Math.random() * 15
        if (progress > 90) progress = 90
        $("#progress-fill").css("width", progress + "%")
      }, 500)

      // Prepare form data
      const formData = new FormData()
      formData.append("action", "compress_pdf")
      formData.append("nonce", pdfCompressorAjax.nonce)
      formData.append("pdf_file", selectedFile)
      formData.append("target_size", targetSize)

      // Send AJAX request
      $.ajax({
        url: pdfCompressorAjax.ajaxurl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
          console.log("[v0] AJAX success response:", response)
          console.log("[v0] Response type:", typeof response)
          console.log("[v0] Response success:", response.success)
          console.log("[v0] Response data:", response.data)
          console.log("[v0] Full response structure:", JSON.stringify(response, null, 2))

          clearInterval(progressInterval)
          $("#progress-fill").css("width", "100%")

          setTimeout(() => {
            if (response.success) {
              console.log("[v0] Showing success result")
              showResult(response.data)
            } else {
              console.log("[v0] Showing error:", response.data)
              showError(response.data || pdfCompressorAjax.messages?.compression_failed || "Compression failed")
            }
            isProcessing = false
          }, 1000)
        },
        error: (xhr, status, error) => {
          console.error("[v0] AJAX error details:", {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error,
            readyState: xhr.readyState,
          })

          try {
            const errorResponse = JSON.parse(xhr.responseText)
            console.error("[v0] Parsed error response:", errorResponse)
          } catch (e) {
            console.error("[v0] Could not parse error response as JSON")
          }

          clearInterval(progressInterval)
          showError(pdfCompressorAjax.messages?.compression_failed || "Compression failed")
          isProcessing = false
        },
      })
    }

    function showResult(data) {
      $progressContainer.hide()

      // Update result info
      $("#original-size").text(formatFileSize(data.original_size))
      $("#compressed-size").text(formatFileSize(data.compressed_size))
      $("#compression-ratio").text(data.compression_ratio + "%")

      // Set download URL
      $("#download-button").data("download-url", data.download_url)

      $resultContainer.show()
    }

    function showError(message) {
      $progressContainer.hide()
      $actionButtons.show()
      $errorMessage.text(message).show()
      isProcessing = false
    }

    function resetForm() {
      selectedFile = null
      isProcessing = false

      // Reset file input
      $fileInput.val("")

      // Hide sections that should be hidden on reset
      $fileInfo.hide()
      $actionButtons.hide()
      $progressContainer.hide()
      $resultContainer.hide()
      $errorMessage.hide()

      // Reset to default selection (150KB)
      $('input[name="target_size"][value="150"]').prop("checked", true)
      $("#custom-size-input").hide()
      $("#custom-size").val("")

      // Reset progress
      $("#progress-fill").css("width", "0%")
    }

    function formatFileSize(bytes) {
      const units = ["B", "KB", "MB", "GB"]
      let size = bytes
      let unitIndex = 0

      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024
        unitIndex++
      }

      return Math.round(size * 100) / 100 + " " + units[unitIndex]
    }
  }

  initializeWithRetry()
})(window.jQuery)

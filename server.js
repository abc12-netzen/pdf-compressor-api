const express = require("express")
const multer = require("multer")
const { exec } = require("child_process")
const fs = require("fs")
const path = require("path")
const cors = require("cors")

const app = express()
const PORT = process.env.PORT || 3000

// Enable CORS for WordPress integration
app.use(cors())
app.use(express.json())

// Configure multer for file uploads
const upload = multer({
  dest: "uploads/",
  limits: {
    fileSize: 50 * 1024 * 1024, // 50MB limit
  },
  fileFilter: (req, file, cb) => {
    if (file.mimetype === "application/pdf") {
      cb(null, true)
    } else {
      cb(new Error("Only PDF files are allowed"))
    }
  },
})

// Compression presets
const COMPRESSION_PRESETS = {
  50: { quality: "screen", dpi: 36, imageQuality: 8, colorConversion: "Gray", downsample: true, ultraAggressive: true }, // Ultra Aggressive (50KB target)
  75: {
    quality: "screen",
    dpi: 42,
    imageQuality: 12,
    colorConversion: "Gray",
    downsample: true,
    ultraAggressive: true,
  }, // Super Aggressive (75KB target)
  100: {
    quality: "screen",
    dpi: 48,
    imageQuality: 15,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: true,
  }, // Aggressive (100KB target)
  150: {
    quality: "screen",
    dpi: 60,
    imageQuality: 20,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: false,
  }, // 150KB target
  180: {
    quality: "screen",
    dpi: 72,
    imageQuality: 25,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: false,
  }, // 180KB target
  400: {
    quality: "ebook",
    dpi: 96,
    imageQuality: 35,
    colorConversion: "RGB",
    downsample: false,
    ultraAggressive: false,
  }, // 400KB target
  custom: {
    quality: "default",
    dpi: 150,
    imageQuality: 50,
    colorConversion: "RGB",
    downsample: false,
    ultraAggressive: false,
  }, // Custom target
}

function compressPDF(inputPath, outputPath, targetSize, callback) {
  const preset = COMPRESSION_PRESETS[targetSize] || COMPRESSION_PRESETS["custom"]

  let gsCommand = `gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/${preset.quality} -dNOPAUSE -dQUIET -dBATCH`

  // Ultra-low resolution for maximum compression
  gsCommand += ` -dColorImageResolution=${preset.dpi} -dGrayImageResolution=${preset.dpi} -dMonoImageResolution=${preset.dpi}`

  if (preset.downsample) {
    gsCommand += ` -dDownsampleColorImages=true -dDownsampleGrayImages=true -dDownsampleMonoImages=true`
    gsCommand += ` -dColorImageDownsampleType=/Subsample -dGrayImageDownsampleType=/Subsample -dMonoImageDownsampleType=/Subsample`
  }

  gsCommand += ` -dJPEGQ=${preset.imageQuality} -dAutoFilterColorImages=false -dAutoFilterGrayImages=false`
  gsCommand += ` -dColorImageFilter=/DCTEncode -dGrayImageFilter=/DCTEncode`

  gsCommand += ` -dDetectDuplicateImages=true -dCompressFonts=true -dSubsetFonts=true`
  gsCommand += ` -dEmbedAllFonts=false -dOptimize=true -dUseFlateCompression=true`
  gsCommand += ` -dColorConversionStrategy=/${preset.colorConversion} -dProcessColorModel=/DeviceRGB`

  if (preset.ultraAggressive) {
    gsCommand += ` -dConvertCMYKImagesToRGB=true -dConvertImagesToIndexed=true`
    gsCommand += ` -dColorImageDownsampleThreshold=1.0 -dGrayImageDownsampleThreshold=1.0`
    gsCommand += ` -dMonoImageDownsampleThreshold=1.0 -dImageMemory=524288`
    gsCommand += ` -dMaxSubsetPct=100 -dSubsetFonts=true -dCompressPages=true`
    gsCommand += ` -dPreserveEPSInfo=false -dPreserveOPIComments=false -dPreserveHalftoneInfo=false`

    // Convert to grayscale for ultra-small targets
    if (targetSize <= 75) {
      gsCommand += ` -sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray`
    }
  }

  gsCommand += ` -dFastWebView=true -dPrinted=false -dCannotEmbedFontPolicy=/Warning`

  gsCommand += ` -sOutputFile="${outputPath}" "${inputPath}"`

  console.log(`[API] Ultra-aggressive compression for ${targetSize}KB target`)
  console.log(`[API] Command: ${gsCommand}`)

  exec(gsCommand, (error, stdout, stderr) => {
    if (error) {
      console.error(`[API] Ghostscript error: ${error.message}`)
      return callback(error)
    }

    if (stderr) {
      console.log(`[API] Ghostscript stderr: ${stderr}`)
    }

    if (!fs.existsSync(outputPath)) {
      return callback(new Error("Compression failed - output file not created"))
    }

    callback(null)
  })
}

// Health check endpoint
app.get("/health", (req, res) => {
  res.json({
    status: "OK",
    message: "PDF Compression API is running",
    timestamp: new Date().toISOString(),
  })
})

app.post("/compress", upload.single("pdf"), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({
        success: false,
        error: "No PDF file uploaded",
      })
    }

    const { targetSize = "150" } = req.body
    const inputPath = req.file.path
    const outputPath = `${inputPath}_compressed.pdf`

    console.log(`[API] Starting ultra-aggressive compression for ${req.file.originalname}, target: ${targetSize}KB`)

    // Get original file size
    const originalStats = fs.statSync(inputPath)
    const originalSize = originalStats.size

    const performIterativeCompression = async (currentInput, target) => {
      const maxIterations = target <= 50 ? 4 : target <= 100 ? 3 : target <= 150 ? 2 : 1
      let currentInputPath = currentInput

      for (let i = 0; i < maxIterations; i++) {
        const iterationOutput = i === maxIterations - 1 ? outputPath : `${inputPath}_temp_${i}.pdf`

        await new Promise((resolve, reject) => {
          compressPDF(currentInputPath, iterationOutput, target, (error) => {
            if (error) return reject(error)

            // Update input for next iteration
            if (i < maxIterations - 1) {
              currentInputPath = iterationOutput
            }
            resolve()
          })
        })

        if (fs.existsSync(iterationOutput)) {
          const iterationStats = fs.statSync(iterationOutput)
          console.log(`[API] Iteration ${i + 1}/${maxIterations}: ${iterationStats.size} bytes`)
        }
      }
    }

    try {
      await performIterativeCompression(inputPath, targetSize)
    } catch (compressionError) {
      // Cleanup
      fs.unlink(inputPath, () => {})
      return res.status(500).json({
        success: false,
        error: "PDF compression failed",
        details: compressionError.message,
      })
    }

    // Get compressed file size
    const compressedStats = fs.statSync(outputPath)
    const compressedSize = compressedStats.size
    const compressionRatio = (((originalSize - compressedSize) / originalSize) * 100).toFixed(2)

    console.log(
      `[API] Ultra-aggressive compression complete: ${originalSize} -> ${compressedSize} bytes (${compressionRatio}%)`,
    )

    // Read compressed file
    const compressedData = fs.readFileSync(outputPath)

    // Cleanup files
    fs.unlink(inputPath, () => {})
    fs.unlink(outputPath, () => {})

    // Cleanup any temporary files
    for (let i = 0; i < 4; i++) {
      const tempFile = `${inputPath}_temp_${i}.pdf`
      if (fs.existsSync(tempFile)) {
        fs.unlink(tempFile, () => {})
      }
    }

    res.json({
      success: true,
      data: {
        original_size: originalSize,
        compressed_size: compressedSize,
        compression_ratio: Number.parseFloat(compressionRatio),
        file_data: compressedData.toString("base64"),
        compression_method:
          compressionRatio > 70
            ? "Ultra-Aggressive Multi-Pass Ghostscript"
            : compressionRatio > 50
              ? "Aggressive Multi-Pass Ghostscript"
              : "Standard Ghostscript Compression",
        iterations_used: targetSize <= 50 ? 4 : targetSize <= 100 ? 3 : targetSize <= 150 ? 2 : 1,
      },
    })
  } catch (error) {
    console.error(`[API] Error: ${error.message}`)

    // Cleanup on error
    if (req.file) {
      fs.unlink(req.file.path, () => {})
    }

    res.status(500).json({
      success: false,
      error: "Internal server error",
      details: error.message,
    })
  }
})

// Error handling middleware
app.use((error, req, res, next) => {
  if (error instanceof multer.MulterError) {
    if (error.code === "LIMIT_FILE_SIZE") {
      return res.status(400).json({
        success: false,
        error: "File too large. Maximum size is 50MB.",
      })
    }
  }

  res.status(500).json({
    success: false,
    error: error.message,
  })
})

// Start server
app.listen(PORT, () => {
  console.log(`[API] PDF Compression API running on port ${PORT}`)
  console.log(`[API] Health check: http://localhost:${PORT}/health`)
})

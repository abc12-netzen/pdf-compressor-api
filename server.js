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
  50: {
    quality: "screen",
    dpi: 24,
    imageQuality: 5,
    colorConversion: "Gray",
    downsample: true,
    ultraAggressive: true,
    extremeMode: true,
  }, // Ultra Aggressive (50KB target)
  75: {
    quality: "screen",
    dpi: 30,
    imageQuality: 8,
    colorConversion: "Gray",
    downsample: true,
    ultraAggressive: true,
    extremeMode: true,
  }, // Super Aggressive (75KB target)
  100: {
    quality: "screen",
    dpi: 36,
    imageQuality: 10,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: true,
    extremeMode: true,
  }, // Aggressive (100KB target)
  150: {
    quality: "screen",
    dpi: 48,
    imageQuality: 15,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: true,
    extremeMode: false,
  }, // 150KB target
  180: {
    quality: "screen",
    dpi: 60,
    imageQuality: 18,
    colorConversion: "RGB",
    downsample: true,
    ultraAggressive: false,
    extremeMode: false,
  }, // 180KB target
  400: {
    quality: "ebook",
    dpi: 72,
    imageQuality: 25,
    colorConversion: "RGB",
    downsample: false,
    ultraAggressive: false,
    extremeMode: false,
  }, // 400KB target
  custom: {
    quality: "default",
    dpi: 150,
    imageQuality: 50,
    colorConversion: "RGB",
    downsample: false,
    ultraAggressive: false,
    extremeMode: false,
  }, // Custom target
}

function compressPDF(inputPath, outputPath, targetSize, callback) {
  const preset = COMPRESSION_PRESETS[targetSize] || COMPRESSION_PRESETS["custom"]

  function performCompression(pass = 1) {
    const tempOutput = pass === 1 ? outputPath : `${outputPath}_temp_${pass}`
    const tempInput = pass === 1 ? inputPath : `${outputPath}_temp_${pass - 1}`

    let gsCommand = `gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/${preset.quality} -dNOPAUSE -dQUIET -dBATCH`

    gsCommand += ` -dColorImageResolution=${preset.dpi} -dGrayImageResolution=${preset.dpi} -dMonoImageResolution=${preset.dpi}`

    if (preset.downsample) {
      gsCommand += ` -dDownsampleColorImages=true -dDownsampleGrayImages=true -dDownsampleMonoImages=true`
      gsCommand += ` -dColorImageDownsampleType=/Bicubic -dGrayImageDownsampleType=/Bicubic -dMonoImageDownsampleType=/Bicubic`
    }

    gsCommand += ` -dJPEGQ=${preset.imageQuality} -dAutoFilterColorImages=false -dAutoFilterGrayImages=false`
    gsCommand += ` -dColorImageFilter=/DCTEncode -dGrayImageFilter=/DCTEncode`
    gsCommand += ` -dEncodeColorImages=true -dEncodeGrayImages=true -dEncodeMonoImages=true`

    gsCommand += ` -dDetectDuplicateImages=true -dCompressFonts=true -dSubsetFonts=true`
    gsCommand += ` -dEmbedAllFonts=false -dOptimize=true -dUseFlateCompression=true`
    gsCommand += ` -dColorConversionStrategy=/${preset.colorConversion} -dProcessColorModel=/DeviceRGB`

    if (preset.ultraAggressive) {
      gsCommand += ` -dConvertCMYKImagesToRGB=true -dConvertImagesToIndexed=true`
      gsCommand += ` -dColorImageDownsampleThreshold=1.0 -dGrayImageDownsampleThreshold=1.0`
      gsCommand += ` -dMonoImageDownsampleThreshold=1.0 -dImageMemory=262144`
      gsCommand += ` -dMaxSubsetPct=100 -dSubsetFonts=true -dCompressPages=true`
      gsCommand += ` -dPreserveEPSInfo=false -dPreserveOPIComments=false -dPreserveHalftoneInfo=false`
      gsCommand += ` -dRemoveUnusedObjects=true -dCompressStreams=true -dASCII85EncodePages=false`

      if (targetSize <= 100) {
        gsCommand += ` -sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray`
        gsCommand += ` -dGrayImageDict="{/QFactor 2.0 /Blend 1 /HSamples [2 1 1 2] /VSamples [2 1 1 2]}"` + "}"
      }
    }

    if (preset.extremeMode) {
      gsCommand += ` -dDoThumbnails=false -dCreateJobTicket=false -dPreserveMarkedContent=false`
      gsCommand += ` -dFastWebView=true -dPrinted=false -dCannotEmbedFontPolicy=/Warning`
    }

    gsCommand += ` -sOutputFile="${tempOutput}" "${tempInput}"`

    exec(gsCommand, (error, stdout, stderr) => {
      if (error) {
        callback(error)
        return
      }

      // Check file size and decide if more passes are needed
      fs.stat(tempOutput, (err, stats) => {
        if (err) {
          callback(err)
          return
        }

        const currentSize = stats.size
        const targetBytes = targetSize * 1024
        const maxPasses = targetSize <= 50 ? 6 : targetSize <= 100 ? 4 : 2

        // Clean up temp files from previous passes
        if (pass > 1) {
          fs.unlink(tempInput, () => {})
        }

        if (currentSize > targetBytes && pass < maxPasses) {
          // Need another pass with more aggressive settings
          const newPreset = { ...preset }
          newPreset.imageQuality = Math.max(5, preset.imageQuality - 2)
          newPreset.dpi = Math.max(24, preset.dpi - 6)

          setTimeout(() => performCompression(pass + 1), 100)
        } else {
          // Final result
          if (pass > 1) {
            fs.rename(tempOutput, outputPath, (renameErr) => {
              if (renameErr) callback(renameErr)
              else callback(null, outputPath)
            })
          } else {
            callback(null, outputPath)
          }
        }
      })
    })
  }

  performCompression()
}

app.post("/compress", upload.single("pdf"), (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: "No PDF file uploaded" })
  }

  const targetSize = Number.parseInt(req.body.targetSize) || 150
  const inputPath = req.file.path
  const outputPath = `${inputPath}_compressed.pdf`
  const originalSize = req.file.size

  console.log(`[API] Compressing PDF: ${originalSize} bytes -> ${targetSize}KB target`)

  compressPDF(inputPath, outputPath, targetSize, (error, compressedPath) => {
    if (error) {
      console.error("[API] Compression failed:", error)
      // Clean up files
      fs.unlink(inputPath, () => {})
      return res.status(500).json({ error: "Compression failed" })
    }

    fs.stat(compressedPath, (err, stats) => {
      if (err) {
        fs.unlink(inputPath, () => {})
        fs.unlink(compressedPath, () => {})
        return res.status(500).json({ error: "Failed to read compressed file" })
      }

      const compressedSize = stats.size
      const compressionRatio = (((originalSize - compressedSize) / originalSize) * 100).toFixed(2)

      console.log(`[API] Compression complete: ${originalSize} -> ${compressedSize} bytes (${compressionRatio}%)`)

      // Send file and clean up
      res.download(compressedPath, "compressed.pdf", (downloadErr) => {
        // Clean up temporary files
        fs.unlink(inputPath, () => {})
        fs.unlink(compressedPath, () => {})

        if (downloadErr) {
          console.error("[API] Download failed:", downloadErr)
        }
      })
    })
  })
})

app.get("/health", (req, res) => {
  res.json({
    status: "OK",
    message: "PDF Compression API is running",
    timestamp: new Date().toISOString(),
  })
})

app.use((error, req, res, next) => {
  if (error instanceof multer.MulterError) {
    if (error.code === "LIMIT_FILE_SIZE") {
      return res.status(400).json({ error: "File too large. Maximum size is 50MB." })
    }
  }

  console.error("[API] Error:", error)
  res.status(500).json({ error: "Internal server error" })
})

// Start the server
app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`)
})

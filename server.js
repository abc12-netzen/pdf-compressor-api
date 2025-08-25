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
  50: { quality: "screen", dpi: 50, imageQuality: 15, colorConversion: "RGB", downsample: true }, // Ultra Aggressive (50KB target)
  75: { quality: "screen", dpi: 60, imageQuality: 20, colorConversion: "RGB", downsample: true }, // Super Aggressive (75KB target)
  100: { quality: "screen", dpi: 72, imageQuality: 25, colorConversion: "RGB", downsample: true }, // Aggressive (100KB target)
  150: { quality: "screen", dpi: 96, imageQuality: 35, colorConversion: "RGB", downsample: true }, // 150KB target
  180: { quality: "ebook", dpi: 120, imageQuality: 45, colorConversion: "RGB", downsample: true }, // 180KB target
  400: { quality: "printer", dpi: 150, imageQuality: 55, colorConversion: "RGB", downsample: false }, // 400KB target
  custom: { quality: "default", dpi: 150, imageQuality: 50, colorConversion: "RGB", downsample: false }, // Custom target
}

function compressPDF(inputPath, outputPath, targetSize, callback) {
  const preset = COMPRESSION_PRESETS[targetSize] || COMPRESSION_PRESETS["custom"]

  // Build advanced Ghostscript command with aggressive compression
  let gsCommand = `gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/${preset.quality} -dNOPAUSE -dQUIET -dBATCH`

  // Image resolution settings
  gsCommand += ` -dColorImageResolution=${preset.dpi} -dGrayImageResolution=${preset.dpi} -dMonoImageResolution=${preset.dpi}`

  // Downsampling settings for aggressive compression
  if (preset.downsample) {
    gsCommand += ` -dDownsampleColorImages=true -dDownsampleGrayImages=true -dDownsampleMonoImages=true`
    gsCommand += ` -dColorImageDownsampleType=/Bicubic -dGrayImageDownsampleType=/Bicubic -dMonoImageDownsampleType=/Bicubic`
  }

  // JPEG quality and compression
  gsCommand += ` -dJPEGQ=${preset.imageQuality} -dAutoFilterColorImages=false -dAutoFilterGrayImages=false`
  gsCommand += ` -dColorImageFilter=/DCTEncode -dGrayImageFilter=/DCTEncode`

  // Ultra-aggressive settings for smallest file sizes
  if (targetSize <= 100) {
    gsCommand += ` -dDetectDuplicateImages=true -dCompressFonts=true -dSubsetFonts=true`
    gsCommand += ` -dEmbedAllFonts=false -dOptimize=true -dUseFlateCompression=true`
    gsCommand += ` -dColorConversionStrategy=/${preset.colorConversion} -dProcessColorModel=/DeviceRGB`
    gsCommand += ` -dConvertCMYKImagesToRGB=true -dConvertImagesToIndexed=true`
  }

  // Font optimization for all compression levels
  gsCommand += ` -dCompressFonts=true -dSubsetFonts=true`

  // Final output
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

    // Check if output file exists
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

    // For ultra-aggressive compression (50KB, 75KB), try multiple passes
    const performIterativeCompression = async (currentInput, target) => {
      const currentOutput = outputPath
      const iteration = 1
      const maxIterations = target <= 75 ? 3 : 1 // Multiple passes for ultra-small targets

      for (let i = 0; i < maxIterations; i++) {
        const iterationOutput = i === maxIterations - 1 ? outputPath : `${inputPath}_temp_${i}.pdf`

        await new Promise((resolve, reject) => {
          compressPDF(currentInput, iterationOutput, target, (error) => {
            if (error) return reject(error)

            // Update input for next iteration
            if (i < maxIterations - 1) {
              currentInput = iterationOutput
            }
            resolve()
          })
        })

        console.log(`[API] Compression iteration ${i + 1}/${maxIterations} complete`)
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
    for (let i = 0; i < 3; i++) {
      const tempFile = `${inputPath}_temp_${i}.pdf`
      if (fs.existsSync(tempFile)) {
        fs.unlink(tempFile, () => {})
      }
    }

    // Send response
    res.json({
      success: true,
      data: {
        original_size: originalSize,
        compressed_size: compressedSize,
        compression_ratio: Number.parseFloat(compressionRatio),
        file_data: compressedData.toString("base64"),
        compression_method: compressionRatio > 60 ? "Ultra-Aggressive Ghostscript API" : "Aggressive Ghostscript API",
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

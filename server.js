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
  100: { quality: "screen", dpi: 72, imageQuality: 30 }, // Aggressive (100KB target)
  150: { quality: "screen", dpi: 96, imageQuality: 40 }, // 150KB target
  180: { quality: "ebook", dpi: 150, imageQuality: 50 }, // 180KB target
  400: { quality: "printer", dpi: 200, imageQuality: 60 }, // 400KB target
  custom: { quality: "default", dpi: 150, imageQuality: 50 }, // Custom target
}

// PDF compression function using Ghostscript
function compressPDF(inputPath, outputPath, targetSize, callback) {
  const preset = COMPRESSION_PRESETS[targetSize] || COMPRESSION_PRESETS["custom"]

  const gsCommand = `gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/${preset.quality} -dNOPAUSE -dQUIET -dBATCH -dColorImageResolution=${preset.dpi} -dGrayImageResolution=${preset.dpi} -dMonoImageResolution=${preset.dpi} -dColorImageDownsampleType=/Bicubic -dGrayImageDownsampleType=/Bicubic -dMonoImageDownsampleType=/Bicubic -dJPEGQ=${preset.imageQuality} -sOutputFile="${outputPath}" "${inputPath}"`

  console.log(`[API] Compressing PDF with command: ${gsCommand}`)

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

// Main compression endpoint
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

    console.log(`[API] Starting compression for ${req.file.originalname}, target: ${targetSize}KB`)

    // Get original file size
    const originalStats = fs.statSync(inputPath)
    const originalSize = originalStats.size

    // Compress PDF
    compressPDF(inputPath, outputPath, targetSize, (error) => {
      if (error) {
        // Cleanup
        fs.unlink(inputPath, () => {})
        return res.status(500).json({
          success: false,
          error: "PDF compression failed",
          details: error.message,
        })
      }

      // Get compressed file size
      const compressedStats = fs.statSync(outputPath)
      const compressedSize = compressedStats.size
      const compressionRatio = (((originalSize - compressedSize) / originalSize) * 100).toFixed(2)

      console.log(`[API] Compression complete: ${originalSize} -> ${compressedSize} bytes (${compressionRatio}%)`)

      // Read compressed file
      const compressedData = fs.readFileSync(outputPath)

      // Cleanup files
      fs.unlink(inputPath, () => {})
      fs.unlink(outputPath, () => {})

      // Send response
      res.json({
        success: true,
        data: {
          original_size: originalSize,
          compressed_size: compressedSize,
          compression_ratio: Number.parseFloat(compressionRatio),
          file_data: compressedData.toString("base64"),
          compression_method: "Ghostscript API",
        },
      })
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

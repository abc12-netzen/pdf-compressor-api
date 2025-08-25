# PDF Compression API

A Node.js API for compressing PDF files using Ghostscript, designed to work with WordPress plugins.

## Features

- Real PDF compression using Ghostscript
- Multiple compression targets (100KB, 150KB, 180KB, 400KB, custom)
- CORS enabled for WordPress integration
- File size validation and error handling
- Base64 response format for easy integration

## Deployment on Render

1. Push this code to GitHub
2. Connect your GitHub repo to Render
3. Deploy as a Web Service
4. Your API will be available at: `https://your-app-name.onrender.com`

## API Endpoints

### POST /compress
Compress a PDF file.

**Parameters:**
- `pdf` (file): PDF file to compress
- `targetSize` (string): Target compression ('100', '150', '180', '400', 'custom')

**Response:**
\`\`\`json
{
  "success": true,
  "data": {
    "original_size": 507028,
    "compressed_size": 153840,
    "compression_ratio": 69.65,
    "file_data": "base64_encoded_pdf_data",
    "compression_method": "Ghostscript API"
  }
}
\`\`\`

### GET /health
Health check endpoint.

## WordPress Integration

Update your WordPress plugin to use this API endpoint instead of external services.

<?php
class PDFCompressorPro_FileHandler {
    
    public function validate_file($file) {
        // Check file size (50MB limit)
        $max_size = 50 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return array(
                'valid' => false,
                'message' => 'File size exceeds 50MB limit'
            );
        }
        
        // Check file type
        $allowed_types = array('application/pdf');
        $file_type = $file['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            return array(
                'valid' => false,
                'message' => 'Only PDF files are allowed'
            );
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'pdf') {
            return array(
                'valid' => false,
                'message' => 'Invalid file extension'
            );
        }
        
        // Additional security check - verify PDF header
        $file_content = file_get_contents($file['tmp_name'], false, null, 0, 10);
        if (strpos($file_content, '%PDF') !== 0) {
            return array(
                'valid' => false,
                'message' => 'Invalid PDF file'
            );
        }
        
        return array('valid' => true);
    }
    
    public function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

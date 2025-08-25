<?php
class PDFCompressorPro_AJAX {
    
    public function __construct() {
        add_action('wp_ajax_compress_pdf', array($this, 'handle_compression'));
        add_action('wp_ajax_nopriv_compress_pdf', array($this, 'handle_compression'));
        add_action('wp_ajax_download_compressed_pdf', array($this, 'handle_download'));
        add_action('wp_ajax_nopriv_download_compressed_pdf', array($this, 'handle_download'));
    }
    
    public function handle_compression() {
        try {
            error_log('[PDF Compressor Pro] Starting compression request');
            
            // Ensure required classes are loaded
            if (!class_exists('PDFCompressorPro_FileHandler')) {
                $file_handler_path = plugin_dir_path(__FILE__) . 'class-file-handler.php';
                if (!file_exists($file_handler_path)) {
                    error_log('[PDF Compressor Pro] FileHandler class file not found: ' . $file_handler_path);
                    wp_send_json_error('FileHandler class file not found');
                    return;
                }
                require_once $file_handler_path;
            }
            
            if (!class_exists('PDFCompressorPro_CompressionEngine')) {
                $compression_engine_path = plugin_dir_path(__FILE__) . 'class-compression-engine.php';
                if (!file_exists($compression_engine_path)) {
                    error_log('[PDF Compressor Pro] CompressionEngine class file not found: ' . $compression_engine_path);
                    wp_send_json_error('CompressionEngine class file not found');
                    return;
                }
                require_once $compression_engine_path;
            }
            
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pdf_compressor_nonce')) {
                error_log('[PDF Compressor Pro] Security check failed');
                wp_send_json_error('Security check failed');
                return;
            }
            
            // Check if file was uploaded
            if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                $error_msg = 'File upload failed';
                if (isset($_FILES['pdf_file']['error'])) {
                    $error_msg .= ' (Error code: ' . $_FILES['pdf_file']['error'] . ')';
                }
                error_log('[PDF Compressor Pro] ' . $error_msg);
                wp_send_json_error($error_msg);
                return;
            }
            
            $file = $_FILES['pdf_file'];
            $target_size = isset($_POST['target_size']) ? intval($_POST['target_size']) : 150;
            
            error_log('[PDF Compressor Pro] File received: ' . $file['name'] . ', Size: ' . $file['size'] . ' bytes, Target: ' . $target_size . 'KB');
            
            // Validate file
            try {
                $file_handler = new PDFCompressorPro_FileHandler();
            } catch (Error $e) {
                error_log('[PDF Compressor Pro] Failed to instantiate FileHandler: ' . $e->getMessage());
                wp_send_json_error('FileHandler initialization failed: ' . $e->getMessage());
                return;
            }
            
            $validation = $file_handler->validate_file($file);
            
            if (!$validation['valid']) {
                error_log('[PDF Compressor Pro] File validation failed: ' . $validation['message']);
                wp_send_json_error($validation['message']);
                return;
            }
            
            // Process compression
            try {
                $compression_engine = new PDFCompressorPro_CompressionEngine();
            } catch (Error $e) {
                error_log('[PDF Compressor Pro] Failed to instantiate CompressionEngine: ' . $e->getMessage());
                wp_send_json_error('CompressionEngine initialization failed: ' . $e->getMessage());
                return;
            }
            
            $result = $compression_engine->compress_pdf($file, $target_size);
            
            if ($result['success']) {
                // Save to database
                $db_result = $this->save_compression_record($result);
                if (!$db_result) {
                    error_log('[PDF Compressor Pro] Database save failed, but compression succeeded');
                }
                error_log('[PDF Compressor Pro] Compression successful');
                wp_send_json_success($result);
            } else {
                error_log('[PDF Compressor Pro] Compression failed: ' . $result['message']);
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] Fatal error in handle_compression: ' . $e->getMessage());
            wp_send_json_error('Internal server error: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('[PDF Compressor Pro] PHP Error in handle_compression: ' . $e->getMessage());
            wp_send_json_error('PHP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        }
    }
    
    public function handle_download() {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'pdf_compressor_nonce')) {
                wp_die('Security check failed');
            }
            
            if (!isset($_GET['file'])) {
                wp_die('No file specified');
            }
            
            $filename = sanitize_file_name($_GET['file']);
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/pdf-compressor-pro/' . $filename;
            
            if (!file_exists($file_path)) {
                error_log('[PDF Compressor Pro] Download file not found: ' . $file_path);
                wp_die('File not found');
            }
            
            // Force download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] Error in handle_download: ' . $e->getMessage());
            wp_die('Download error occurred');
        }
    }
    
    private function save_compression_record($result) {
        try {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'pdf_compressor_files';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('[PDF Compressor Pro] Database table does not exist: ' . $table_name);
                return false;
            }
            
            // Get user IP safely
            $user_ip = 'unknown';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $user_ip = $_SERVER['REMOTE_ADDR'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            
            $insert_result = $wpdb->insert(
                $table_name,
                array(
                    'original_filename' => $result['original_filename'],
                    'compressed_filename' => $result['compressed_filename'],
                    'original_size' => $result['original_size'],
                    'compressed_size' => $result['compressed_size'],
                    'compression_ratio' => $result['compression_ratio'],
                    'target_size' => $result['target_size'] . 'KB',
                    'user_ip' => $user_ip
                ),
                array('%s', '%s', '%d', '%d', '%f', '%s', '%s')
            );
            
            if ($insert_result === false) {
                error_log('[PDF Compressor Pro] Database insert failed: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] Error saving compression record: ' . $e->getMessage());
            return false;
        }
    }
}

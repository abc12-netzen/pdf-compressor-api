<?php
/**
 * Plugin Name: PDF Compressor Pro
 * Plugin URI: https://yourwebsite.com/pdf-compressor-pro
 * Description: Professional PDF compression plugin with multiple size options and concurrent user support
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: pdf-compressor-pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PDF_COMPRESSOR_PRO_VERSION', '1.0.0');
define('PDF_COMPRESSOR_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PDF_COMPRESSOR_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PDF_COMPRESSOR_PRO_PLUGIN_FILE', __FILE__);

// Main plugin class
class PDFCompressorPro {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load plugin classes
        $this->load_dependencies();
        
        // Initialize components
        new PDFCompressorPro_Frontend();
        new PDFCompressorPro_Admin();
        new PDFCompressorPro_AJAX();
        
        // Schedule cleanup
        if (!wp_next_scheduled('pdf_compressor_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pdf_compressor_cleanup');
        }
        add_action('pdf_compressor_cleanup', array($this, 'cleanup_old_files'));
    }
    
    private function load_dependencies() {
        require_once PDF_COMPRESSOR_PRO_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once PDF_COMPRESSOR_PRO_PLUGIN_DIR . 'includes/class-admin.php';
        require_once PDF_COMPRESSOR_PRO_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once PDF_COMPRESSOR_PRO_PLUGIN_DIR . 'includes/class-compression-engine.php';
        require_once PDF_COMPRESSOR_PRO_PLUGIN_DIR . 'includes/class-file-handler.php';
    }
    
    public function activate() {
        $this->create_database_table();
        $this->create_upload_directory();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('pdf_compressor_cleanup');
    }
    
    private function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pdf_compressor_files';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            original_filename varchar(255) NOT NULL,
            compressed_filename varchar(255) NOT NULL,
            original_size bigint(20) NOT NULL,
            compressed_size bigint(20) NOT NULL,
            compression_ratio decimal(5,2) NOT NULL,
            target_size varchar(20) NOT NULL,
            user_ip varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $pdf_compressor_dir = $upload_dir['basedir'] . '/pdf-compressor-pro';
        
        if (!file_exists($pdf_compressor_dir)) {
            wp_mkdir_p($pdf_compressor_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($pdf_compressor_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    public function cleanup_old_files() {
        $upload_dir = wp_upload_dir();
        $pdf_compressor_dir = $upload_dir['basedir'] . '/pdf-compressor-pro';
        
        if (is_dir($pdf_compressor_dir)) {
            $files = glob($pdf_compressor_dir . '/*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > (24 * 60 * 60)) {
                    unlink($file);
                }
            }
        }
        
        // Clean database records older than 7 days
        global $wpdb;
        $table_name = $wpdb->prefix . 'pdf_compressor_files';
        $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
}

// Initialize the plugin
new PDFCompressorPro();

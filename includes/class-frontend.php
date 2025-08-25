<?php
class PDFCompressorPro_Frontend {
    
    public function __construct() {
        add_shortcode('pdf_compressor', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts() {
        global $post;
        
        // Only enqueue when shortcode is used
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'pdf_compressor')) {
            wp_enqueue_style(
                'pdf-compressor-pro-style',
                PDF_COMPRESSOR_PRO_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                PDF_COMPRESSOR_PRO_VERSION
            );
            
            wp_enqueue_script(
                'pdf-compressor-pro-script',
                PDF_COMPRESSOR_PRO_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                PDF_COMPRESSOR_PRO_VERSION,
                true
            );
            
            $ajax_data = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pdf_compressor_nonce'),
                'max_file_size' => 50 * 1024 * 1024, // 50MB
                'allowed_types' => array('application/pdf'),
                'messages' => array(
                    'invalid_file' => __('Please select a valid PDF file.', 'pdf-compressor-pro'),
                    'file_too_large' => __('File size exceeds 50MB limit.', 'pdf-compressor-pro'),
                    'compression_failed' => __('Compression failed. Please try again.', 'pdf-compressor-pro'),
                    'compression_success' => __('PDF compressed successfully!', 'pdf-compressor-pro')
                )
            );
            
            wp_localize_script('pdf-compressor-pro-script', 'pdfCompressorAjax', $ajax_data);
            
            wp_add_inline_script('pdf-compressor-pro-script', 
                'window.pdfCompressorAjax = window.pdfCompressorAjax || ' . wp_json_encode($ajax_data) . ';', 
                'before'
            );
        }
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'max_file_size' => '50MB',
            'allowed_sizes' => '150KB,400KB,180KB,Custom'
        ), $atts);
        
        ob_start();
        ?>
        <div id="pdf-compressor-pro-container" class="pdf-compressor-container">
            <div class="pdf-compressor-header">
                <h3><?php _e('PDF Compressor Pro', 'pdf-compressor-pro'); ?></h3>
                <p><?php _e('Compress your PDF files to specific sizes. Maximum file size: 50MB', 'pdf-compressor-pro'); ?></p>
            </div>
            
            <div class="pdf-compressor-form">
                <!-- Always show compression options before file upload -->
                <div class="compression-options" id="compression-options">
                    <h4><?php _e('Select Target Size:', 'pdf-compressor-pro'); ?></h4>
                    <div class="size-options">
                        <label class="size-option"><input type="radio" name="target_size" value="150" checked> <span class="size-label">150KB</span> <span class="size-desc"><?php _e('Email friendly', 'pdf-compressor-pro'); ?></span></label>
                        <label class="size-option"><input type="radio" name="target_size" value="400"> <span class="size-label">400KB</span> <span class="size-desc"><?php _e('Web optimized', 'pdf-compressor-pro'); ?></span></label>
                        <label class="size-option"><input type="radio" name="target_size" value="180"> <span class="size-label">180KB</span> <span class="size-desc"><?php _e('Mobile friendly', 'pdf-compressor-pro'); ?></span></label>
                        <label class="size-option aggressive"><input type="radio" name="target_size" value="100"> <span class="size-label">100KB</span> <span class="size-desc aggressive-label"><?php _e('Aggressive compression', 'pdf-compressor-pro'); ?></span></label>
                        <label class="size-option"><input type="radio" name="target_size" value="custom"> <span class="size-label"><?php _e('Custom', 'pdf-compressor-pro'); ?></span> <span class="size-desc"><?php _e('Your choice', 'pdf-compressor-pro'); ?></span></label>
                    </div>
                    <div class="custom-size-input" id="custom-size-input" style="display: none;">
                        <input type="number" id="custom-size" placeholder="<?php _e('Enter size in KB (50-5000)', 'pdf-compressor-pro'); ?>" min="50" max="5000">
                        <span class="custom-help"><?php _e('Lower values = more compression', 'pdf-compressor-pro'); ?></span>
                    </div>
                </div>
                
                <div class="file-upload-area" id="file-upload-area">
                    <div class="upload-icon">📄</div>
                    <div class="upload-text">
                        <p><?php _e('Drag and drop your PDF file here', 'pdf-compressor-pro'); ?></p>
                        <p><?php _e('or', 'pdf-compressor-pro'); ?></p>
                        <button type="button" id="browse-button" class="browse-button"><?php _e('Browse Files', 'pdf-compressor-pro'); ?></button>
                    </div>
                    <input type="file" id="pdf-file-input" accept=".pdf" style="display: none;">
                </div>
                
                <div class="file-info" id="file-info" style="display: none;">
                    <div class="file-details">
                        <span class="file-name" id="file-name"></span>
                        <span class="file-size" id="file-size"></span>
                    </div>
                </div>
                
                <div class="progress-container" id="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="progress-text" id="progress-text"><?php _e('Compressing...', 'pdf-compressor-pro'); ?></div>
                </div>
                
                <div class="action-buttons" id="action-buttons" style="display: none;">
                    <button type="button" id="compress-button" class="compress-button"><?php _e('Compress PDF', 'pdf-compressor-pro'); ?></button>
                    <button type="button" id="reset-button" class="reset-button"><?php _e('Reset', 'pdf-compressor-pro'); ?></button>
                </div>
                
                <div class="result-container" id="result-container" style="display: none;">
                    <div class="result-info">
                        <h4><?php _e('Compression Complete!', 'pdf-compressor-pro'); ?></h4>
                        <div class="compression-stats">
                            <div class="stat">
                                <span class="label"><?php _e('Original Size:', 'pdf-compressor-pro'); ?></span>
                                <span class="value" id="original-size"></span>
                            </div>
                            <div class="stat">
                                <span class="label"><?php _e('Compressed Size:', 'pdf-compressor-pro'); ?></span>
                                <span class="value" id="compressed-size"></span>
                            </div>
                            <div class="stat">
                                <span class="label"><?php _e('Compression Ratio:', 'pdf-compressor-pro'); ?></span>
                                <span class="value" id="compression-ratio"></span>
                            </div>
                        </div>
                        <button type="button" id="download-button" class="download-button"><?php _e('Download Compressed PDF', 'pdf-compressor-pro'); ?></button>
                    </div>
                </div>
                
                <div class="error-message" id="error-message" style="display: none;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

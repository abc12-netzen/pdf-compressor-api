<?php
class PDFCompressorPro_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_post_save_pdf_compressor_settings', array($this, 'save_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'PDF Compressor Pro Settings',
            'PDF Compressor Pro',
            'manage_options',
            'pdf-compressor-pro',
            array($this, 'admin_page')
        );
    }
    
    public function init_settings() {
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_convertapi_secret');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_pdfco_key');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_adobe_client_id');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_adobe_client_secret');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_adobe_organization_id');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_smallpdf_key');
        register_setting('pdf_compressor_pro_settings', 'pdf_compressor_pro_selected_api');
    }
    
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        check_admin_referer('pdf_compressor_pro_settings_nonce');
        
        $convertapi_secret = sanitize_text_field($_POST['convertapi_secret']);
        $pdfco_key = sanitize_text_field($_POST['pdfco_key']);
        $adobe_client_id = sanitize_text_field($_POST['adobe_client_id']);
        $adobe_client_secret = sanitize_text_field($_POST['adobe_client_secret']);
        $adobe_organization_id = sanitize_text_field($_POST['adobe_organization_id']);
        $smallpdf_key = sanitize_text_field($_POST['smallpdf_key']);
        $selected_api = sanitize_text_field($_POST['selected_api']);
        
        update_option('pdf_compressor_pro_convertapi_secret', $convertapi_secret);
        update_option('pdf_compressor_pro_pdfco_key', $pdfco_key);
        update_option('pdf_compressor_pro_adobe_client_id', $adobe_client_id);
        update_option('pdf_compressor_pro_adobe_client_secret', $adobe_client_secret);
        update_option('pdf_compressor_pro_adobe_organization_id', $adobe_organization_id);
        update_option('pdf_compressor_pro_smallpdf_key', $smallpdf_key);
        update_option('pdf_compressor_pro_selected_api', $selected_api);
        
        wp_redirect(admin_url('options-general.php?page=pdf-compressor-pro&settings-updated=1'));
        exit;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_pdf-compressor-pro') {
            return;
        }
        
        wp_enqueue_style('pdf-compressor-pro-admin', PDF_COMPRESSOR_PRO_PLUGIN_URL . 'assets/css/admin.css', array(), PDF_COMPRESSOR_PRO_VERSION);
    }
    
    public function admin_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pdf_compressor_files';
        
        // Get statistics
        $total_compressions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_original_size = $wpdb->get_var("SELECT SUM(original_size) FROM $table_name");
        $total_compressed_size = $wpdb->get_var("SELECT SUM(compressed_size) FROM $table_name");
        $avg_compression_ratio = $wpdb->get_var("SELECT AVG(compression_ratio) FROM $table_name");
        
        // Get recent compressions
        $recent_compressions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
        
        $file_handler = new PDFCompressorPro_FileHandler();
        
        $convertapi_secret = get_option('pdf_compressor_pro_convertapi_secret', '');
        $pdfco_key = get_option('pdf_compressor_pro_pdfco_key', '');
        $adobe_client_id = get_option('pdf_compressor_pro_adobe_client_id', '');
        $adobe_client_secret = get_option('pdf_compressor_pro_adobe_client_secret', '');
        $adobe_organization_id = get_option('pdf_compressor_pro_adobe_organization_id', '');
        $smallpdf_key = get_option('pdf_compressor_pro_smallpdf_key', '');
        $selected_api = get_option('pdf_compressor_pro_selected_api', 'convertapi');
        
        $configured_apis = 0;
        if (!empty($convertapi_secret)) $configured_apis++;
        if (!empty($pdfco_key)) $configured_apis++;
        if (!empty($adobe_client_id) && !empty($adobe_client_secret) && !empty($adobe_organization_id)) $configured_apis++;
        if (!empty($smallpdf_key)) $configured_apis++;
        ?>
        <div class="wrap">
            <h1><?php _e('PDF Compressor Pro', 'pdf-compressor-pro'); ?></h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'pdf-compressor-pro'); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Updated API Configuration Section for multiple APIs -->
            <div class="pdf-compressor-api-config">
                <h2><?php _e('API Configuration', 'pdf-compressor-pro'); ?></h2>
                
                <?php if ($configured_apis === 0): ?>
                    <div class="notice notice-error">
                        <p><strong><?php _e('No APIs Configured:', 'pdf-compressor-pro'); ?></strong> 
                        <?php _e('You need to configure at least one API service below to enable real PDF compression.', 'pdf-compressor-pro'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success">
                        <p><strong><?php _e('APIs Configured:', 'pdf-compressor-pro'); ?></strong> 
                        <?php printf(__('%d API service(s) configured and ready for high-quality PDF compression.', 'pdf-compressor-pro'), $configured_apis); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="save_pdf_compressor_settings">
                    <?php wp_nonce_field('pdf_compressor_pro_settings_nonce'); ?>
                    
                    <h3><?php _e('Primary API Selection', 'pdf-compressor-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="selected_api"><?php _e('Preferred API Service', 'pdf-compressor-pro'); ?></label>
                            </th>
                            <td>
                                <select id="selected_api" name="selected_api" class="regular-text">
                                    <option value="convertapi" <?php selected($selected_api, 'convertapi'); ?>>ConvertAPI (Up to 90% compression)</option>
                                    <option value="pdfco" <?php selected($selected_api, 'pdfco'); ?>>PDF.co (Professional compression)</option>
                                    <option value="adobe" <?php selected($selected_api, 'adobe'); ?>>Adobe PDF Services (Premium quality)</option>
                                    <option value="smallpdf" <?php selected($selected_api, 'smallpdf'); ?>>SmallPDF (Fast compression)</option>
                                </select>
                                <p class="description"><?php _e('Choose your primary API. Others will be used as fallbacks if configured.', 'pdf-compressor-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('API Keys Configuration', 'pdf-compressor-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="convertapi_secret"><?php _e('ConvertAPI Secret Key', 'pdf-compressor-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="convertapi_secret" name="convertapi_secret" 
                                       value="<?php echo esc_attr($convertapi_secret); ?>" 
                                       class="regular-text" placeholder="Enter ConvertAPI secret key">
                                <p class="description">
                                    <strong>Cost:</strong> $0.50 per 100 conversions | 
                                    <strong>Free:</strong> 1500/month | 
                                    <a href="https://www.convertapi.com/a/signup" target="_blank">Get Free Key</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pdfco_key"><?php _e('PDF.co API Key', 'pdf-compressor-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="pdfco_key" name="pdfco_key" 
                                       value="<?php echo esc_attr($pdfco_key); ?>" 
                                       class="regular-text" placeholder="Enter PDF.co API key">
                                <p class="description">
                                    <strong>Cost:</strong> $0.99 per 100 conversions | 
                                    <strong>Free:</strong> 100/month | 
                                    <a href="https://pdf.co/pricing" target="_blank">Get Free Key</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="adobe_client_id"><?php _e('Adobe PDF Services', 'pdf-compressor-pro'); ?></label>
                            </th>
                            <td>
                                <!-- Replaced single Adobe key field with three OAuth credential fields -->
                                <input type="text" id="adobe_client_id" name="adobe_client_id" 
                                       value="<?php echo esc_attr($adobe_client_id); ?>" 
                                       class="regular-text" placeholder="Client ID">
                                <br><br>
                                <input type="password" id="adobe_client_secret" name="adobe_client_secret" 
                                       value="<?php echo esc_attr($adobe_client_secret); ?>" 
                                       class="regular-text" placeholder="Client Secret">
                                <br><br>
                                <input type="text" id="adobe_organization_id" name="adobe_organization_id" 
                                       value="<?php echo esc_attr($adobe_organization_id); ?>" 
                                       class="regular-text" placeholder="Organization ID">
                                <p class="description">
                                    <strong>Cost:</strong> $1.50 per 100 conversions | 
                                    <strong>Free:</strong> 1000/month | 
                                    <a href="https://developer.adobe.com/document-services/pricing/" target="_blank">Get Free Credentials</a><br>
                                    <em>Enter all three OAuth credentials from your Adobe Developer Console</em>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smallpdf_key"><?php _e('SmallPDF API Key', 'pdf-compressor-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="smallpdf_key" name="smallpdf_key" 
                                       value="<?php echo esc_attr($smallpdf_key); ?>" 
                                       class="regular-text" placeholder="Enter SmallPDF API key">
                                <p class="description">
                                    <strong>Cost:</strong> $2.00 per 100 conversions | 
                                    <strong>Free:</strong> 50/month | 
                                    <a href="https://rapidapi.com/smallpdf/api/smallpdf-compress-pdf" target="_blank">Get Free Key</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save API Settings', 'pdf-compressor-pro')); ?>
                </form>
                
                <div class="api-comparison">
                    <h3><?php _e('API Comparison & Recommendations:', 'pdf-compressor-pro'); ?></h3>
                    <ul>
                        <li><strong>ConvertAPI:</strong> Best value - Highest free quota, excellent compression up to 90%</li>
                        <li><strong>PDF.co:</strong> Good balance - Reliable service with decent free tier</li>
                        <li><strong>Adobe PDF Services:</strong> Premium quality - Best for professional documents</li>
                        <li><strong>SmallPDF:</strong> Fast processing - Good for quick compressions</li>
                    </ul>
                    <p><em>Tip: Configure multiple APIs for automatic fallback if one service is unavailable.</em></p>
                </div>
            </div>
            
            <div class="pdf-compressor-admin-stats">
                <h2><?php _e('Usage Statistics', 'pdf-compressor-pro'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3><?php echo number_format($total_compressions); ?></h3>
                        <p><?php _e('Total Compressions', 'pdf-compressor-pro'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $file_handler->format_file_size($total_original_size); ?></h3>
                        <p><?php _e('Total Original Size', 'pdf-compressor-pro'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $file_handler->format_file_size($total_compressed_size); ?></h3>
                        <p><?php _e('Total Compressed Size', 'pdf-compressor-pro'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo round($avg_compression_ratio, 2); ?>%</h3>
                        <p><?php _e('Average Compression', 'pdf-compressor-pro'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="pdf-compressor-recent">
                <h2><?php _e('Recent Compressions', 'pdf-compressor-pro'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Filename', 'pdf-compressor-pro'); ?></th>
                            <th><?php _e('Original Size', 'pdf-compressor-pro'); ?></th>
                            <th><?php _e('Compressed Size', 'pdf-compressor-pro'); ?></th>
                            <th><?php _e('Compression Ratio', 'pdf-compressor-pro'); ?></th>
                            <th><?php _e('Target Size', 'pdf-compressor-pro'); ?></th>
                            <th><?php _e('Date', 'pdf-compressor-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_compressions): ?>
                            <?php foreach ($recent_compressions as $compression): ?>
                                <tr>
                                    <td><?php echo esc_html($compression->original_filename); ?></td>
                                    <td><?php echo $file_handler->format_file_size($compression->original_size); ?></td>
                                    <td><?php echo $file_handler->format_file_size($compression->compressed_size); ?></td>
                                    <td><?php echo $compression->compression_ratio; ?>%</td>
                                    <td><?php echo esc_html($compression->target_size); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($compression->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"><?php _e('No compressions yet.', 'pdf-compressor-pro'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pdf-compressor-shortcode">
                <h2><?php _e('Usage Instructions', 'pdf-compressor-pro'); ?></h2>
                <p><?php _e('Use the following shortcode to display the PDF compressor on any page or post:', 'pdf-compressor-pro'); ?></p>
                <code>[pdf_compressor]</code>
            </div>
        </div>
        <?php
    }
}

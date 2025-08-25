<?php
class PDFCompressorPro_CompressionEngine {
    
    public function compress_pdf($file, $target_size_kb) {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/pdf-compressor-pro/';
        
        if (!file_exists($pdf_dir)) {
            if (!wp_mkdir_p($pdf_dir)) {
                error_log('[PDF Compressor Pro] Failed to create directory: ' . $pdf_dir);
                return array('success' => false, 'message' => 'Failed to create upload directory');
            }
        }
        
        // Generate unique filenames
        $original_name = sanitize_file_name($file['name']);
        $unique_id = uniqid();
        $temp_original = $pdf_dir . $unique_id . '_original.pdf';
        $temp_compressed = $pdf_dir . $unique_id . '_compressed.pdf';
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $temp_original)) {
            error_log('[PDF Compressor Pro] Failed to move uploaded file');
            return array('success' => false, 'message' => 'Failed to save uploaded file');
        }
        
        $original_size = filesize($temp_original);
        error_log('[PDF Compressor Pro] Starting compression. Original size: ' . round($original_size/1024, 2) . 'KB, Target: ' . $target_size_kb . 'KB');
        
        $compression_result = $this->try_compression_methods($temp_original, $temp_compressed, $target_size_kb);
        
        if (!$compression_result['success']) {
            unlink($temp_original);
            return $compression_result;
        }
        
        $compressed_size = filesize($temp_compressed);
        $compression_ratio = round((($original_size - $compressed_size) / $original_size) * 100, 2);
        
        error_log('[PDF Compressor Pro] Compression successful using ' . $compression_result['method'] . '. Final size: ' . round($compressed_size/1024, 2) . 'KB');
        
        // Clean up original
        unlink($temp_original);
        
        return array(
            'success' => true,
            'original_filename' => $original_name,
            'compressed_filename' => basename($temp_compressed),
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'compression_ratio' => $compression_ratio,
            'target_size' => $target_size_kb,
            'compression_method' => $compression_result['method'],
            'download_url' => admin_url('admin-ajax.php?action=download_compressed_pdf&file=' . basename($temp_compressed) . '&nonce=' . wp_create_nonce('pdf_compressor_nonce'))
        );
    }
    
    private function try_compression_methods($input_file, $output_file, $target_size_kb) {
        $selected_api = get_option('pdf_compressor_pro_selected_api', 'convertapi');
        
        // Try selected API first
        switch ($selected_api) {
            case 'convertapi':
                $result = $this->compress_with_convertapi($input_file, $output_file, $target_size_kb);
                if ($result['success']) return array('success' => true, 'method' => 'ConvertAPI');
                break;
                
            case 'pdfco':
                $result = $this->compress_with_pdfco($input_file, $output_file, $target_size_kb);
                if ($result['success']) return array('success' => true, 'method' => 'PDF.co');
                break;
                
            case 'adobe':
                $result = $this->compress_with_adobe($input_file, $output_file, $target_size_kb);
                if ($result['success']) return array('success' => true, 'method' => 'Adobe PDF Services');
                break;
                
            case 'smallpdf':
                $result = $this->compress_with_smallpdf($input_file, $output_file, $target_size_kb);
                if ($result['success']) return array('success' => true, 'method' => 'SmallPDF');
                break;
        }
        
        // Try other APIs as fallback (only if configured)
        $apis = array('convertapi', 'pdfco', 'adobe', 'smallpdf');
        foreach ($apis as $api) {
            if ($api === $selected_api) continue; // Skip already tried
            
            switch ($api) {
                case 'convertapi':
                    if (!empty(get_option('pdf_compressor_pro_convertapi_secret'))) {
                        $result = $this->compress_with_convertapi($input_file, $output_file, $target_size_kb);
                        if ($result['success']) return array('success' => true, 'method' => 'ConvertAPI (Fallback)');
                    }
                    break;
                    
                case 'pdfco':
                    if (!empty(get_option('pdf_compressor_pro_pdfco_key'))) {
                        $result = $this->compress_with_pdfco($input_file, $output_file, $target_size_kb);
                        if ($result['success']) return array('success' => true, 'method' => 'PDF.co (Fallback)');
                    }
                    break;
                    
                case 'adobe':
                    if (!empty(get_option('pdf_compressor_pro_adobe_key'))) {
                        $result = $this->compress_with_adobe($input_file, $output_file, $target_size_kb);
                        if ($result['success']) return array('success' => true, 'method' => 'Adobe PDF Services (Fallback)');
                    }
                    break;
                    
                case 'smallpdf':
                    if (!empty(get_option('pdf_compressor_pro_smallpdf_key'))) {
                        $result = $this->compress_with_smallpdf($input_file, $output_file, $target_size_kb);
                        if ($result['success']) return array('success' => true, 'method' => 'SmallPDF (Fallback)');
                    }
                    break;
            }
        }
        
        return array('success' => false, 'message' => 'All compression APIs failed. Please check API configuration.');
    }
    
    private function compress_with_convertapi($input_file, $output_file, $target_size_kb) {
        try {
            $api_secret = get_option('pdf_compressor_pro_convertapi_secret', '');
            if (empty($api_secret)) {
                error_log('[PDF Compressor Pro] ConvertAPI: No API secret configured');
                return array('success' => false, 'message' => 'ConvertAPI key not configured');
            }
            
            error_log('[PDF Compressor Pro] ConvertAPI: Starting compression with target ' . $target_size_kb . 'KB');
            
            $preset = $this->get_compression_preset($target_size_kb);
            $image_quality = $this->get_image_quality($target_size_kb);
            $image_resolution = $this->get_image_resolution($target_size_kb);
            
            error_log('[PDF Compressor Pro] ConvertAPI: Using preset=' . $preset . ', quality=' . $image_quality . ', resolution=' . $image_resolution);
            
            $file_data = new CURLFile($input_file, 'application/pdf', 'document.pdf');
            $post_data = array(
                'File' => $file_data,
                'Preset' => $preset,
                'ImageQuality' => $image_quality,
                'ImageResolution' => $image_resolution,
                'RemoveForms' => 'true',
                'RemoveMetadata' => 'true',
                'RemoveAnnotations' => 'true',
                'OptimizeFonts' => 'true',
                'CompressImages' => 'true'
            );
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'https://v2.convertapi.com/convert/pdf/to/compress?Secret=' . $api_secret,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => false
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            error_log('[PDF Compressor Pro] ConvertAPI: HTTP Code=' . $http_code . ', Response=' . substr($response, 0, 500));
            
            if (!empty($curl_error)) {
                error_log('[PDF Compressor Pro] ConvertAPI: CURL Error=' . $curl_error);
                return array('success' => false, 'message' => 'ConvertAPI connection failed: ' . $curl_error);
            }
            
            if ($http_code === 200) {
                $result = json_decode($response, true);
                
                if (isset($result['Files'][0]['FileData'])) {
                    // ConvertAPI returns base64 encoded file data
                    $compressed_content = base64_decode($result['Files'][0]['FileData']);
                    if ($compressed_content && file_put_contents($output_file, $compressed_content)) {
                        $file_size = $result['Files'][0]['FileSize'] ?? filesize($output_file);
                        error_log('[PDF Compressor Pro] ConvertAPI: Success! File saved to ' . $output_file . ', Size: ' . $file_size . ' bytes');
                        return array('success' => true);
                    } else {
                        error_log('[PDF Compressor Pro] ConvertAPI: Failed to decode or save file data');
                        return array('success' => false, 'message' => 'Failed to save compressed file');
                    }
                } elseif (isset($result['Files'][0]['Url'])) {
                    // Fallback for URL-based response (if ConvertAPI changes format)
                    $compressed_content = file_get_contents($result['Files'][0]['Url']);
                    if ($compressed_content && file_put_contents($output_file, $compressed_content)) {
                        error_log('[PDF Compressor Pro] ConvertAPI: Success! File saved to ' . $output_file);
                        return array('success' => true);
                    }
                } else {
                    error_log('[PDF Compressor Pro] ConvertAPI: Invalid response structure - missing FileData or Url: ' . print_r($result, true));
                    return array('success' => false, 'message' => 'Invalid ConvertAPI response format');
                }
            }
            
            return array('success' => false, 'message' => 'ConvertAPI failed with HTTP ' . $http_code);
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] ConvertAPI: Exception=' . $e->getMessage());
            return array('success' => false, 'message' => 'ConvertAPI error: ' . $e->getMessage());
        }
    }
    
    private function compress_with_pdfco($input_file, $output_file, $target_size_kb) {
        try {
            $api_key = get_option('pdf_compressor_pro_pdfco_key', '');
            if (empty($api_key)) {
                error_log('[PDF Compressor Pro] PDF.co: No API key configured');
                return array('success' => false, 'message' => 'PDF.co key not configured');
            }
            
            error_log('[PDF Compressor Pro] PDF.co: Starting compression with target ' . $target_size_kb . 'KB');
            
            // Step 1: Upload file
            $upload_response = $this->pdfco_upload_file($input_file, $api_key);
            if (!$upload_response['success']) {
                error_log('[PDF Compressor Pro] PDF.co: Upload failed');
                return $upload_response;
            }
            
            error_log('[PDF Compressor Pro] PDF.co: File uploaded to ' . $upload_response['url']);
            
            $compress_data = array(
                'url' => $upload_response['url'],
                'profiles' => json_encode(array(
                    'CompressImages' => true,
                    'ImageQuality' => $this->get_image_quality($target_size_kb),
                    'RemoveAnnotations' => true,
                    'OptimizeFonts' => true
                )),
                'async' => false
            );
            
            error_log('[PDF Compressor Pro] PDF.co: Compression params=' . json_encode($compress_data));
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'https://api.pdf.co/v1/pdf/compress',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($compress_data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => array(
                    'x-api-key: ' . $api_key,
                    'Content-Type: application/json'
                )
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            error_log('[PDF Compressor Pro] PDF.co: HTTP Code=' . $http_code . ', Response=' . substr($response, 0, 500));
            
            if (!empty($curl_error)) {
                error_log('[PDF Compressor Pro] PDF.co: CURL Error=' . $curl_error);
                return array('success' => false, 'message' => 'PDF.co connection failed: ' . $curl_error);
            }
            
            if ($http_code === 200) {
                $result = json_decode($response, true);
                if (isset($result['url']) && !$result['error']) {
                    $compressed_content = file_get_contents($result['url']);
                    if ($compressed_content && file_put_contents($output_file, $compressed_content)) {
                        error_log('[PDF Compressor Pro] PDF.co: Success! File saved to ' . $output_file);
                        return array('success' => true);
                    }
                }
                error_log('[PDF Compressor Pro] PDF.co: API Error=' . ($result['message'] ?? 'Unknown error'));
            }
            
            return array('success' => false, 'message' => 'PDF.co failed with HTTP ' . $http_code);
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] PDF.co: Exception=' . $e->getMessage());
            return array('success' => false, 'message' => 'PDF.co error: ' . $e->getMessage());
        }
    }
    
    private function compress_with_adobe($input_file, $output_file, $target_size_kb) {
        try {
            $client_id = get_option('pdf_compressor_pro_adobe_client_id', '');
            $client_secret = get_option('pdf_compressor_pro_adobe_client_secret', '');
            $organization_id = get_option('pdf_compressor_pro_adobe_organization_id', '');
            
            if (empty($client_id) || empty($client_secret) || empty($organization_id)) {
                error_log('[PDF Compressor Pro] Adobe: Missing credentials (client_id, client_secret, or organization_id)');
                return array('success' => false, 'message' => 'Adobe credentials not configured');
            }
            
            error_log('[PDF Compressor Pro] Adobe: Starting compression with target ' . $target_size_kb . 'KB');
            
            $access_token = $this->get_adobe_access_token($client_id, $client_secret);
            if (!$access_token) {
                error_log('[PDF Compressor Pro] Adobe: Failed to get access token');
                return array('success' => false, 'message' => 'Adobe authentication failed');
            }
            
            error_log('[PDF Compressor Pro] Adobe: Got access token successfully');
            
            $asset_id = $this->adobe_upload_file($input_file, $access_token, $client_id);
            if (!$asset_id) {
                error_log('[PDF Compressor Pro] Adobe: File upload failed');
                return array('success' => false, 'message' => 'Adobe file upload failed');
            }
            
            error_log('[PDF Compressor Pro] Adobe: File uploaded, assetID=' . $asset_id);
            
            $compression_level = $this->get_adobe_compression_level($target_size_kb);
            $compressed_asset_id = $this->adobe_compress_pdf($asset_id, $compression_level, $access_token, $client_id);
            if (!$compressed_asset_id) {
                error_log('[PDF Compressor Pro] Adobe: Compression failed');
                return array('success' => false, 'message' => 'Adobe compression failed');
            }
            
            error_log('[PDF Compressor Pro] Adobe: Compression successful, compressed assetID=' . $compressed_asset_id);
            
            $download_success = $this->adobe_download_file($compressed_asset_id, $output_file, $access_token, $client_id);
            if ($download_success) {
                error_log('[PDF Compressor Pro] Adobe: Success! File saved to ' . $output_file);
                return array('success' => true);
            }
            
            return array('success' => false, 'message' => 'Adobe download failed');
            
        } catch (Exception $e) {
            error_log('[PDF Compressor Pro] Adobe: Exception=' . $e->getMessage());
            return array('success' => false, 'message' => 'Adobe error: ' . $e->getMessage());
        }
    }
    
    private function compress_with_smallpdf($input_file, $output_file, $target_size_kb) {
        return array('success' => false, 'message' => 'SmallPDF API not available - no public API found');
    }
    
    private function pdfco_upload_file($file_path, $api_key) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://api.pdf.co/v1/file/upload/get-presigned-url?name=' . basename($file_path),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array('x-api-key: ' . $api_key)
        ));
        
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        
        if (isset($result['presignedUrl'])) {
            // Upload file to presigned URL
            $file_data = file_get_contents($file_path);
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $result['presignedUrl'],
                CURLOPT_PUT => true,
                CURLOPT_INFILE => fopen($file_path, 'r'),
                CURLOPT_INFILESIZE => filesize($file_path),
                CURLOPT_RETURNTRANSFER => true
            ));
            
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return array('success' => true, 'url' => $result['url']);
            }
        }
        
        return array('success' => false, 'message' => 'Upload failed');
    }
    
    private function get_compression_preset($target_size_kb) {
        if ($target_size_kb <= 150) return 'Web';      // More aggressive than Archive
        if ($target_size_kb <= 400) return 'Ebook';    // Better for medium compression
        return 'Printer';                               // Least aggressive for larger targets
    }
    
    private function get_image_quality($target_size_kb) {
        if ($target_size_kb <= 150) return 20;         // Very low quality for maximum compression
        if ($target_size_kb <= 400) return 40;         // Low quality for good compression
        return 70;                                      // Medium quality for larger targets
    }
    
    private function get_image_resolution($target_size_kb) {
        if ($target_size_kb <= 150) return 72;         // Low resolution for maximum compression
        if ($target_size_kb <= 400) return 150;        // Medium resolution
        return 300;                                     // High resolution for larger targets
    }
    
    private function get_adobe_compression_level($target_size_kb) {
        if ($target_size_kb <= 150) return 'HIGH';
        if ($target_size_kb <= 400) return 'MEDIUM';
        return 'LOW';
    }
    
    private function get_smallpdf_compression($target_size_kb) {
        if ($target_size_kb <= 150) return 'extreme';
        if ($target_size_kb <= 400) return 'recommended';
        return 'less';
    }
    
    private function get_pdfco_compression_level($target_size_kb) {
        if ($target_size_kb <= 150) return 'high';
        if ($target_size_kb <= 400) return 'medium';
        return 'low';
    }
    
    private function adobe_upload_file($file_path, $access_token, $client_id) {
        $file_data = new CURLFile($file_path, 'application/pdf', 'document.pdf');
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://pdf-services.adobe.io/assets',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array('contentAnalyzerRequests' => $file_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $access_token,
                'X-API-Key: ' . $client_id
            )
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log('[PDF Compressor Pro] Adobe Upload: HTTP Code=' . $http_code . ', Response=' . substr($response, 0, 500));
        
        if (!empty($curl_error)) {
            error_log('[PDF Compressor Pro] Adobe Upload: CURL Error=' . $curl_error);
            return false;
        }
        
        if ($http_code === 201) {
            $result = json_decode($response, true);
            if (isset($result['assetID'])) {
                return $result['assetID'];
            }
        }
        
        return false;
    }
    
    private function adobe_compress_pdf($asset_id, $compression_level, $access_token, $client_id) {
        $payload = json_encode(array(
            'assetID' => $asset_id,
            'compressionLevel' => $compression_level
        ));
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://pdf-services.adobe.io/operation/compresspdf',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $access_token,
                'X-API-Key: ' . $client_id,
                'Content-Type: application/json'
            )
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log('[PDF Compressor Pro] Adobe Compress: HTTP Code=' . $http_code . ', Response=' . substr($response, 0, 500));
        
        if (!empty($curl_error)) {
            error_log('[PDF Compressor Pro] Adobe Compress: CURL Error=' . $curl_error);
            return false;
        }
        
        if ($http_code === 201) {
            $result = json_decode($response, true);
            if (isset($result['asset']['assetID'])) {
                return $result['asset']['assetID'];
            }
        }
        
        return false;
    }
    
    private function adobe_download_file($asset_id, $output_file, $access_token, $client_id) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://pdf-services.adobe.io/assets/' . $asset_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $access_token,
                'X-API-Key: ' . $client_id
            )
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log('[PDF Compressor Pro] Adobe Download: HTTP Code=' . $http_code);
        
        if (!empty($curl_error)) {
            error_log('[PDF Compressor Pro] Adobe Download: CURL Error=' . $curl_error);
            return false;
        }
        
        if ($http_code === 200 && !empty($response)) {
            return file_put_contents($output_file, $response) !== false;
        }
        
        return false;
    }
    
    // Deprecated method
    private function adobe_compress_pdf_direct($input_file, $output_file, $compression_level, $access_token, $client_id) {
        return array('success' => false, 'message' => 'Deprecated method');
    }
    
    private function get_adobe_access_token($client_id, $client_secret) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://ims-na1.adobelogin.com/ims/token/v3',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array(
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'scope' => 'openid,AdobeID,read_organizations,additional_info.projectedProductContext,additional_info.roles'
            )),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            )
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log('[PDF Compressor Pro] Adobe OAuth: HTTP Code=' . $http_code . ', Response=' . substr($response, 0, 300));
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                return $result['access_token'];
            }
        }
        
        return false;
    }
}

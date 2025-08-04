<?php
/**
 * Helper functions for WP Licensing Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a unique license key
 *
 * @return string
 */
function wp_licensing_manager_generate_license_key() {
    return strtoupper(wp_generate_password(32, false, false));
}

/**
 * Sanitize license key
 *
 * @param string $license_key
 * @return string
 */
function wp_licensing_manager_sanitize_license_key($license_key) {
    return sanitize_text_field(strtoupper($license_key));
}

/**
 * Validate email address
 *
 * @param string $email
 * @return bool
 */
function wp_licensing_manager_is_valid_email($email) {
    return is_email($email) !== false;
}

/**
 * Sanitize domain
 *
 * @param string $domain
 * @return string
 */
function wp_licensing_manager_sanitize_domain($domain) {
    $domain = sanitize_text_field($domain);
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    $domain = preg_replace('/\/.*$/', '', $domain);
    return strtolower($domain);
}

/**
 * Get client IP address
 *
 * @return string
 */
function wp_licensing_manager_get_client_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
}

/**
 * Format license status for display
 *
 * @param string $status
 * @return string
 */
function wp_licensing_manager_format_status($status) {
    switch ($status) {
        case 'active':
            return '<span class="status-active">' . esc_html__('Active', 'wp-licensing-manager') . '</span>';
        case 'inactive':
            return '<span class="status-inactive">' . esc_html__('Inactive', 'wp-licensing-manager') . '</span>';
        case 'expired':
            return '<span class="status-expired">' . esc_html__('Expired', 'wp-licensing-manager') . '</span>';
        default:
            return '<span class="status-unknown">' . esc_html__('Unknown', 'wp-licensing-manager') . '</span>';
    }
}

/**
 * Check if license is expired
 *
 * @param string $expires_at
 * @return bool
 */
function wp_licensing_manager_is_license_expired($expires_at) {
    if (empty($expires_at) || $expires_at === '0000-00-00') {
        return false; // Lifetime license
    }
    
    return strtotime($expires_at) < time();
}

/**
 * Get upload directory for updates
 *
 * @return string
 */
function wp_licensing_manager_get_updates_dir() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/wp-licensing-manager/updates';
}

/**
 * Get upload URL for updates
 *
 * @return string
 */
function wp_licensing_manager_get_updates_url() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'] . '/wp-licensing-manager/updates';
}

// Note: wp_licensing_manager_verify_ajax_nonce function is defined in main plugin file

/**
 * Log debug information
 *
 * @param mixed $data
 * @param string $title
 */
function wp_licensing_manager_log($data, $title = 'WP Licensing Manager') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($title . ': ' . print_r($data, true));
    }
}

/**
 * Check if request is HTTPS or localhost (for testing)
 *
 * @return bool
 */
function wp_licensing_manager_is_https() {
    // Allow localhost for testing
    if (wp_licensing_manager_is_localhost()) {
        return true;
    }
    
    return is_ssl() || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Check if request is from localhost
 *
 * @return bool
 */
function wp_licensing_manager_is_localhost() {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    
    // Common localhost indicators
    $localhost_hosts = array(
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0'
    );
    
    // Check if host starts with localhost
    foreach ($localhost_hosts as $localhost) {
        if (strpos($host, $localhost) === 0) {
            return true;
        }
    }
    
    // Check for local IP ranges
    if (strpos($host, '192.168.') === 0 || 
        strpos($host, '10.') === 0 || 
        strpos($host, '172.') === 0) {
        return true;
    }
    
    // Check remote address
    if (in_array($remote_addr, $localhost_hosts)) {
        return true;
    }
    
    return false;
}

/**
 * Get license expiry date based on settings
 *
 * @param int $days
 * @return string
 */
function wp_licensing_manager_get_expiry_date($days = null) {
    if ($days === null) {
        $days = get_option('wp_licensing_manager_default_expiry_days', 365);
    }
    
    if ($days <= 0) {
        return null; // Lifetime license
    }
    
    return date('Y-m-d', strtotime('+' . $days . ' days'));
}

/**
 * Get effective license duration for a WooCommerce product
 * Uses preset if set, otherwise falls back to manual expiry days
 *
 * @param int $product_id WooCommerce product ID
 * @return int Days until expiry (0 for lifetime)
 */
function wp_licensing_manager_get_product_license_duration($product_id) {
    // Check for duration preset first
    $duration_preset = get_post_meta($product_id, '_license_duration_preset', true);
    
    if (!empty($duration_preset)) {
        return absint($duration_preset);
    }
    
    // Fall back to manual expiry days
    $manual_expiry = get_post_meta($product_id, '_license_expiry_days', true);
    if (!empty($manual_expiry)) {
        return absint($manual_expiry);
    }
    
    // Default fallback
    return get_option('wp_licensing_manager_default_expiry_days', 365);
}

/**
 * Get grace period for a product
 *
 * @param int $product_id WooCommerce product ID
 * @return int Grace period in days
 */
function wp_licensing_manager_get_product_grace_period($product_id) {
    $grace_period = get_post_meta($product_id, '_license_grace_period', true);
    return absint($grace_period);
}

/**
 * Check if product has auto-renewal enabled
 *
 * @param int $product_id WooCommerce product ID
 * @return bool
 */
function wp_licensing_manager_is_product_auto_renewal($product_id) {
    $auto_renewal = get_post_meta($product_id, '_license_auto_renewal', true);
    return $auto_renewal === 'yes';
}

/**
 * Get trial period for a product
 *
 * @param int $product_id WooCommerce product ID
 * @return int Trial period in days
 */
function wp_licensing_manager_get_product_trial_period($product_id) {
    $trial_period = get_post_meta($product_id, '_license_trial_period', true);
    return absint($trial_period);
}

/**
 * Get usage limit for a product
 *
 * @param int $product_id WooCommerce product ID
 * @return int Usage limit (0 for unlimited)
 */
function wp_licensing_manager_get_product_usage_limit($product_id) {
    $usage_limit = get_post_meta($product_id, '_license_usage_limit', true);
    return absint($usage_limit);
}

/**
 * Check if license is in grace period
 *
 * @param object $license License object
 * @param int $product_id WooCommerce product ID
 * @return bool
 */
function wp_licensing_manager_is_license_in_grace_period($license, $product_id) {
    if (empty($license->expires_at) || $license->expires_at === '0000-00-00') {
        return false; // Lifetime license
    }
    
    $grace_period = wp_licensing_manager_get_product_grace_period($product_id);
    if ($grace_period <= 0) {
        return false; // No grace period
    }
    
    $expiry_time = strtotime($license->expires_at);
    $grace_end_time = strtotime('+' . $grace_period . ' days', $expiry_time);
    $current_time = time();
    
    // In grace period if expired but within grace period
    return ($current_time > $expiry_time && $current_time <= $grace_end_time);
}

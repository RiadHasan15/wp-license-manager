<?php
/**
 * WordPress Test Environment for WP Licensing Manager
 */

// Define WordPress constants for plugin testing
define('ABSPATH', dirname(__FILE__) . '/');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Mock WordPress functions for basic plugin testing
if (!function_exists('wp_die')) {
    function wp_die($message) {
        die($message);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $include_standard_special_chars = true, $exclude_ambiguous_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Simple mock implementation
        static $options = array(
            'wp_licensing_manager_default_expiry_days' => 365,
            'wp_licensing_manager_default_max_activations' => 1
        );
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value) {
        return true;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost:5000/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return array(
            'path' => ABSPATH . 'uploads',
            'url' => 'http://localhost:5000/uploads',
            'subdir' => '',
            'basedir' => ABSPATH . 'uploads',
            'baseurl' => 'http://localhost:5000/uploads',
            'error' => false
        );
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true; // Mock verification
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl() {
        return false; // Mock SSL check
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('delete_metadata')) {
    function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $function) {
        // Mock registration
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $function) {
        // Mock registration
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        // Mock action registration
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        // Mock filter registration
        return true;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock_nonce_' . md5($action . time());
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true; // Mock admin capability
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        return true; // Mock email sending
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        return array('body' => '{"success": true}', 'response' => array('code' => 200));
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return true; // Mock admin area
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://localhost:5000/wp-admin/' . $path;
    }
}

if (!function_exists('wp_ajax_url')) {
    function wp_ajax_url() {
        return admin_url('admin-ajax.php');
    }
}

if (!function_exists('wp_rest_url')) {
    function wp_rest_url($path = '', $scheme = 'rest') {
        return 'http://localhost:5000/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest') {
        return wp_rest_url($path, $scheme);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array(), $override = false) {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        wp_send_json(array('success' => true, 'data' => $data), $status_code);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        wp_send_json(array('success' => false, 'data' => $data), $status_code);
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null) {
        header('Content-Type: application/json; charset=utf-8');
        if ($status_code) {
            http_response_code($status_code);
        }
        echo wp_json_encode($response);
        exit;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            wp_parse_str($args, $parsed_args);
        }
        
        if (is_array($defaults)) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) {
        return parse_str($string, $array);
    }
}

// Mock global $wpdb methods
class MockWPDB {
    public $prefix = 'wp_';
    
    public function prepare($query, ...$args) {
        return vsprintf(str_replace('?', '%s', $query), $args);
    }
    
    public function get_results($query, $output = OBJECT) {
        return array();
    }
    
    public function get_row($query, $output = OBJECT, $y = 0) {
        return null;
    }
    
    public function get_var($query, $x = 0, $y = 0) {
        return null;
    }
    
    public function query($query) {
        return 0;
    }
    
    public function insert($table, $data, $format = null) {
        return 1;
    }
    
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return 1;
    }
    
    public function delete($table, $where, $where_format = null) {
        return 1;
    }
}

// Initialize mock database
global $wpdb;
$wpdb = new MockWPDB();

// Mock WordPress post/meta functions
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        return $single ? '' : array();
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

// Mock WooCommerce functions for testing
if (!function_exists('woocommerce_wp_checkbox')) {
    function woocommerce_wp_checkbox($field) {
        return true;
    }
}

if (!function_exists('woocommerce_wp_select')) {
    function woocommerce_wp_select($field) {
        return true;
    }
}

if (!function_exists('woocommerce_wp_text_field')) {
    function woocommerce_wp_text_field($field) {
        return true;
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        return null; // Mock order object would go here
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id) {
        return null; // Mock product object would go here
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        return $checked == $current ? 'checked="checked"' : '';
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        return $selected == $current ? 'selected="selected"' : '';
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true; // Mock verification for testing
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock_nonce_' . md5($action . time());
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true; // Mock admin capability for testing
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        die($message);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '-', $title));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return strip_tags($data, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6>');
    }
}

// Mock database object
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';

// Basic plugin test
echo "<!DOCTYPE html>\n";
echo "<html>\n<head>\n";
echo "<title>WP Licensing Manager - Plugin Test</title>\n";
echo "<style>\n";
echo "body { font-family: Arial, sans-serif; margin: 40px; }\n";
echo ".plugin-info { background: #f0f0f1; padding: 20px; border-radius: 5px; margin-bottom: 20px; }\n";
echo ".success { color: #00a32a; }\n";
echo ".error { color: #d63638; }\n";
echo ".test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

echo "<h1>WP Licensing Manager - Plugin Test Environment</h1>\n";

echo "<div class='plugin-info'>\n";
echo "<h2>Plugin Information</h2>\n";
echo "<p><strong>Name:</strong> WP Licensing Manager</p>\n";
echo "<p><strong>Version:</strong> 1.0.0</p>\n";
echo "<p><strong>Description:</strong> A comprehensive licensing system for WordPress plugins and themes</p>\n";
echo "</div>\n";

// Test plugin structure
echo "<div class='test-section'>\n";
echo "<h3>Plugin Structure Test</h3>\n";

$required_files = array(
    'wp-licensing-manager.php' => 'Main Plugin File',
    'includes/helpers.php' => 'Helper Functions',
    'includes/class-license-manager.php' => 'License Manager Class',
    'includes/class-product-manager.php' => 'Product Manager Class',
    'includes/class-activation-manager.php' => 'Activation Manager Class',
    'includes/class-api.php' => 'REST API Class',
    'includes/class-woocommerce.php' => 'WooCommerce Integration',
    'includes/class-updates.php' => 'Updates Handler',
    'admin/class-admin-menu.php' => 'Admin Menu Handler',
    'admin/css/admin.css' => 'Admin Styles',
    'assets/css/style.css' => 'Frontend Styles',
    'assets/js/admin.js' => 'Admin JavaScript',
    'uninstall.php' => 'Uninstall Script'
);

$all_files_exist = true;
foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ {$description} ({$file})</p>\n";
    } else {
        echo "<p class='error'>✗ Missing: {$description} ({$file})</p>\n";
        $all_files_exist = false;
    }
}

if ($all_files_exist) {
    echo "<p class='success'><strong>All required files are present!</strong></p>\n";
} else {
    echo "<p class='error'><strong>Some required files are missing!</strong></p>\n";
}
echo "</div>\n";

// Test plugin loading
echo "<div class='test-section'>\n";
echo "<h3>Plugin Loading Test</h3>\n";

try {
    if (file_exists('wp-licensing-manager.php')) {
        // Include the main plugin file
        ob_start();
        include_once 'wp-licensing-manager.php';
        $output = ob_get_clean();
        
        if (class_exists('WP_Licensing_Manager')) {
            echo "<p class='success'>✓ Main plugin class loaded successfully</p>\n";
        } else {
            echo "<p class='error'>✗ Main plugin class not found</p>\n";
        }
        
        // Test helper functions
        if (function_exists('wp_licensing_manager_generate_license_key')) {
            $test_key = wp_licensing_manager_generate_license_key();
            echo "<p class='success'>✓ Helper functions loaded - Sample license key: <code>{$test_key}</code></p>\n";
        } else {
            echo "<p class='error'>✗ Helper functions not loaded</p>\n";
        }
        
    } else {
        echo "<p class='error'>✗ Main plugin file not found</p>\n";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading plugin: " . esc_html($e->getMessage()) . "</p>\n";
}
echo "</div>\n";

// Test API endpoints
echo "<div class='test-section'>\n";
echo "<h3>Available API Endpoints</h3>\n";
echo "<p>The plugin provides the following REST API endpoints:</p>\n";
echo "<ul>\n";
echo "<li><code>POST /wp-json/licensing/v1/validate</code> - Validate license keys</li>\n";
echo "<li><code>POST /wp-json/licensing/v1/activate</code> - Activate licenses on domains</li>\n";
echo "<li><code>POST /wp-json/licensing/v1/deactivate</code> - Deactivate licenses from domains</li>\n";
echo "<li><code>POST /wp-json/licensing/v1/update-check</code> - Check for product updates</li>\n";
echo "<li><code>GET /wp-json/licensing/v1/update-download</code> - Download updates</li>\n";
echo "<li><code>GET /wp-json/licensing/v1/stats</code> - Get licensing statistics</li>\n";
echo "</ul>\n";
echo "<p><strong>Note:</strong> Full WordPress environment required for API testing</p>\n";
echo "</div>\n";

// Feature overview
echo "<div class='test-section'>\n";
echo "<h3>Plugin Features</h3>\n";
echo "<ul>\n";
echo "<li>✓ Multi-product licensing system</li>\n";
echo "<li>✓ WooCommerce integration for automatic license generation</li>\n";
echo "<li>✓ Domain-based activation tracking</li>\n";
echo "<li>✓ REST API for license validation and management</li>\n";
echo "<li>✓ Automatic plugin update system</li>\n";
echo "<li>✓ Comprehensive admin dashboard</li>\n";
echo "<li>✓ Analytics and reporting</li>\n";
echo "<li>✓ Email notifications</li>\n";
echo "<li>✓ Security features (HTTPS enforcement, nonce verification)</li>\n";
echo "<li>✓ WordPress coding standards compliance</li>\n";
echo "</ul>\n";
echo "</div>\n";

// Installation instructions
echo "<div class='test-section'>\n";
echo "<h3>Installation Instructions</h3>\n";
echo "<ol>\n";
echo "<li>Upload the entire <code>wp-licensing-manager</code> folder to <code>/wp-content/plugins/</code></li>\n";
echo "<li>Activate the plugin through the WordPress admin panel</li>\n";
echo "<li>Ensure WooCommerce is installed and activated</li>\n";
echo "<li>Navigate to <strong>Licensing Manager</strong> in the admin menu</li>\n";
echo "<li>Configure settings and create your first product</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<div class='test-section'>\n";
echo "<h3>Next Steps</h3>\n";
echo "<p>To fully test this plugin:</p>\n";
echo "<ol>\n";
echo "<li>Install on a WordPress site with WooCommerce</li>\n";
echo "<li>Create license products in the admin panel</li>\n";
echo "<li>Configure WooCommerce products to generate licenses</li>\n";
echo "<li>Test the complete purchase → license generation → API validation flow</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "</body>\n</html>\n";
?>
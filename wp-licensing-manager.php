<?php
/**
 * Plugin Name: WP Licensing Manager
 * Plugin URI: https://stackcastle.com/wp-licensing-manager
 * Description: A comprehensive licensing system for WordPress plugins and themes with WooCommerce integration.
 * Version: 1.1.0
 * Author: StackCastle
 * Author URI: https://stackcastle.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-licensing-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_LICENSING_MANAGER_VERSION', '1.1.0');
define('WP_LICENSING_MANAGER_PLUGIN_FILE', __FILE__);
define('WP_LICENSING_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_LICENSING_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_LICENSING_MANAGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WP Licensing Manager class
 */
class WP_Licensing_Manager {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * License Manager instance
     */
    public $license_manager;

    /**
     * Product Manager instance
     */
    public $product_manager;

    /**
     * Activation Manager instance
     */
    public $activation_manager;

    /**
     * API instance
     */
    public $api;

    /**
     * WooCommerce integration instance
     */
    public $woocommerce;

    /**
     * Updates instance
     */
    public $updates;

    /**
     * Admin Menu instance
     */
    public $admin_menu;

    /**
     * Email Manager instance
     */
    public $email_manager;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_classes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'), 10);
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/helpers.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-license-manager.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-product-manager.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-activation-manager.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-api.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-woocommerce.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-updates.php';
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'includes/class-email-manager.php';
        
        if (is_admin()) {
            require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/class-admin-menu.php';
        }
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        $this->license_manager = new WP_Licensing_Manager_License_Manager();
        $this->product_manager = new WP_Licensing_Manager_Product_Manager();
        $this->activation_manager = new WP_Licensing_Manager_Activation_Manager();
        
        // Initialize API with error handling
        try {
            $this->api = new WP_Licensing_Manager_API();
            
            // Debug log for API initialization
            if (function_exists('error_log')) {
                error_log('WP Licensing Manager: API class initialized successfully');
            }
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('WP Licensing Manager: API initialization failed - ' . $e->getMessage());
            }
        }
        
        $this->woocommerce = new WP_Licensing_Manager_WooCommerce();
        $this->updates = new WP_Licensing_Manager_Updates();
        $this->email_manager = new WP_Licensing_Manager_Email_Manager();
        
        if (is_admin()) {
            $this->admin_menu = new WP_Licensing_Manager_Admin_Menu();
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize plugin components
        do_action('wp_licensing_manager_init');
    }

    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-licensing-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_tables();
        $this->create_directories();
        
        // Set default options
        $default_options = array(
            'default_expiry_days' => 365,
            'default_max_activations' => 1,
            'email_template_subject' => 'Your {product_name} License Key',
            'email_template_body' => 'Hi {customer_name},

Thank you for your purchase of {product_name}!

Your license key is: {license_key}
Order ID: {order_id}

Please keep this information safe as you will need it to activate your product.

Best regards,
StackCastle Team',
            'api_version' => '1.0'
        );
        
        foreach ($default_options as $key => $value) {
            if (!get_option('wp_licensing_manager_' . $key)) {
                add_option('wp_licensing_manager_' . $key, $value);
            }
        }
        
        // Reset rewrite flush flag to ensure endpoints work
        delete_option('wp_licensing_manager_rewrite_flushed');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Licenses table
        $licenses_table = $wpdb->prefix . 'licenses';
        $licenses_sql = "CREATE TABLE $licenses_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            license_key varchar(64) NOT NULL,
            status enum('active','inactive','expired') DEFAULT 'active',
            expires_at date NULL,
            max_activations int(11) DEFAULT 1,
            activations int(11) DEFAULT 0,
            domains text,
            customer_email varchar(255) NOT NULL,
            order_id bigint(20) NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY product_id (product_id),
            KEY customer_email (customer_email),
            KEY status (status)
        ) $charset_collate;";

        // Activations table
        $activations_table = $wpdb->prefix . 'license_activations';
        $activations_sql = "CREATE TABLE $activations_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            domain varchar(255) NOT NULL,
            ip_address varchar(45) NULL,
            activated_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY license_id (license_id),
            KEY product_id (product_id),
            KEY domain (domain)
        ) $charset_collate;";

        // Products table
        $products_table = $wpdb->prefix . 'license_products';
        $products_sql = "CREATE TABLE $products_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            latest_version varchar(20) DEFAULT '1.0.0',
            changelog text,
            update_file_path varchar(255),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($licenses_sql);
        dbDelta($activations_sql);
        dbDelta($products_sql);
        
        // Migration: Add updated_at column to existing products table if it doesn't exist
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $products_table LIKE 'updated_at'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $products_table ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }

    /**
     * Create necessary directories
     */
    private function create_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/wp-licensing-manager';
        $updates_dir = $base_dir . '/updates';

        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }

        if (!file_exists($updates_dir)) {
            wp_mkdir_p($updates_dir);
        }

        // Create .htaccess to protect files
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($base_dir . '/.htaccess', $htaccess_content);
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP Licensing Manager requires WooCommerce to be installed and activated.', 'wp-licensing-manager');
        echo '</p></div>';
    }
}

/**
 * Verify AJAX nonce
 */
function wp_licensing_manager_verify_ajax_nonce($nonce_name = 'wp_licensing_manager_nonce') {
    if (!function_exists('wp_verify_nonce')) {
        return true; // Skip verification if WordPress functions not available
    }
    
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST[$nonce_name]) ? $_POST[$nonce_name] : '');
    
    if (empty($nonce)) {
        return false;
    }
    
    return wp_verify_nonce($nonce, $nonce_name);
}

/**
 * Get main instance
 */
function wp_licensing_manager() {
    return WP_Licensing_Manager::get_instance();
}

// Initialize the plugin
wp_licensing_manager();

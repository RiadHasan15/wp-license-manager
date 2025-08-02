<?php
/**
 * Admin Menu class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_Admin_Menu {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_wp_licensing_manager_save_license', array($this, 'ajax_save_license'));
        add_action('wp_ajax_wp_licensing_manager_delete_license', array($this, 'ajax_delete_license'));
        add_action('wp_ajax_wp_licensing_manager_save_product', array($this, 'ajax_save_product'));
        add_action('wp_ajax_wp_licensing_manager_delete_product', array($this, 'ajax_delete_product'));
        add_action('wp_ajax_wp_licensing_manager_get_integration_code', array($this, 'ajax_get_integration_code'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Licensing Manager', 'wp-licensing-manager'),
            __('Licensing Manager', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager',
            array($this, 'licenses_page'),
            'dashicons-admin-network',
            30
        );

        add_submenu_page(
            'wp-licensing-manager',
            __('Licenses', 'wp-licensing-manager'),
            __('Licenses', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager',
            array($this, 'licenses_page')
        );

        add_submenu_page(
            'wp-licensing-manager',
            __('Products', 'wp-licensing-manager'),
            __('Products', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager-products',
            array($this, 'products_page')
        );

        add_submenu_page(
            'wp-licensing-manager',
            __('Analytics', 'wp-licensing-manager'),
            __('Analytics', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager-analytics',
            array($this, 'analytics_page')
        );

        add_submenu_page(
            'wp-licensing-manager',
            __('Settings', 'wp-licensing-manager'),
            __('Settings', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wp-licensing-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'wp-licensing-manager-admin',
            WP_LICENSING_MANAGER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WP_LICENSING_MANAGER_VERSION
        );

        wp_enqueue_script(
            'wp-licensing-manager-admin',
            WP_LICENSING_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_LICENSING_MANAGER_VERSION,
            true
        );

        wp_localize_script('wp-licensing-manager-admin', 'wpLicensingManager', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_licensing_manager_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'wp-licensing-manager')
            )
        ));
    }

    /**
     * Licenses page
     */
    public function licenses_page() {
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/licenses.php';
    }

    /**
     * Products page
     */
    public function products_page() {
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/products.php';
    }

    /**
     * Analytics page
     */
    public function analytics_page() {
        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/analytics.php';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_licensing_manager_settings')) {
            $settings = array(
                'default_expiry_days',
                'default_max_activations',
                'email_template_subject',
                'email_template_body'
            );

            foreach ($settings as $setting) {
                if (isset($_POST[$setting])) {
                    update_option('wp_licensing_manager_' . $setting, sanitize_textarea_field($_POST[$setting]));
                }
            }

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'wp-licensing-manager') . '</p></div>';
        }

        require_once WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * AJAX: Save license
     */
    public function ajax_save_license() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();

        $license_id = isset($_POST['license_id']) ? absint($_POST['license_id']) : 0;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        $expires_at = isset($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : '';
        $max_activations = isset($_POST['max_activations']) ? absint($_POST['max_activations']) : 1;

        if (empty($product_id) || empty($customer_email)) {
            wp_send_json_error('Missing required fields');
        }

        if ($license_id > 0) {
            // Update existing license
            $result = $license_manager->update_license($license_id, array(
                'status' => $status,
                'expires_at' => $expires_at,
                'max_activations' => $max_activations
            ));
        } else {
            // Create new license
            $result = $license_manager->create_license(array(
                'product_id' => $product_id,
                'customer_email' => $customer_email,
                'status' => $status,
                'expires_at' => $expires_at,
                'max_activations' => $max_activations
            ));
        }

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save license');
        }
    }

    /**
     * AJAX: Delete license
     */
    public function ajax_delete_license() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $license_id = isset($_POST['license_id']) ? absint($_POST['license_id']) : 0;

        if (empty($license_id)) {
            wp_send_json_error('Missing license ID');
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();
        $result = $license_manager->delete_license($license_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete license');
        }
    }

    /**
     * AJAX: Save product
     */
    public function ajax_save_product() {
        // Debug logging
        error_log('AJAX Save Product Called');
        error_log('POST Data: ' . print_r($_POST, true));
        
        // More permissive nonce checking for testing
        $nonce_valid = wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce');
        error_log('Nonce Valid: ' . ($nonce_valid ? 'Yes' : 'No'));
        
        if (!$nonce_valid) {
            error_log('Security check failed - nonce invalid');
            wp_send_json_error('Security check failed - please refresh the page');
            return;
        }

        if (!current_user_can('manage_options')) {
            error_log('Insufficient permissions');
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $product_manager = new WP_Licensing_Manager_Product_Manager();

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $latest_version = isset($_POST['latest_version']) ? sanitize_text_field($_POST['latest_version']) : '1.0.0';
        $changelog = isset($_POST['changelog']) ? wp_kses_post($_POST['changelog']) : '';

        if (empty($slug) || empty($name)) {
            error_log('Missing required fields - slug: ' . $slug . ', name: ' . $name);
            wp_send_json_error('Missing required fields: slug and name are required');
        }

        if ($product_id > 0) {
            // Update existing product
            error_log('Updating product ID: ' . $product_id);
            $result = $product_manager->update_product($product_id, array(
                'name' => $name,
                'latest_version' => $latest_version,
                'changelog' => $changelog
            ));
        } else {
            // Create new product
            error_log('Creating new product: ' . $name . ' (slug: ' . $slug . ')');
            $result = $product_manager->create_product(array(
                'slug' => $slug,
                'name' => $name,
                'latest_version' => $latest_version,
                'changelog' => $changelog
            ));
        }

        error_log('Product operation result: ' . ($result ? 'SUCCESS' : 'FAILED'));

        if ($result) {
            wp_send_json_success(array('product_id' => $result, 'message' => 'Product saved successfully'));
        } else {
            error_log('Product save failed - checking database and slug conflicts');
            wp_send_json_error('Failed to save product. This might be due to a database error or duplicate slug.');
        }
    }

    /**
     * AJAX: Delete product
     */
    public function ajax_delete_product() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (empty($product_id)) {
            wp_send_json_error('Missing product ID');
        }

        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $result = $product_manager->delete_product($product_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete product - it may have existing licenses');
        }
    }

    /**
     * AJAX: Get integration code
     */
    public function ajax_get_integration_code() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $product_slug = isset($_POST['product_slug']) ? sanitize_text_field($_POST['product_slug']) : '';

        if (empty($product_slug)) {
            wp_send_json_error('Missing product slug');
        }

        // Generate the complete integration code using the fixed Updates class
        $updates = new WP_Licensing_Manager_Updates();
        $integration_code = $updates->generate_integration_code($product_slug);

        wp_send_json_success($integration_code);
    }
}

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
        add_action('wp_ajax_wp_licensing_manager_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_wp_licensing_manager_import_data', array($this, 'ajax_import_data'));
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

        add_submenu_page(
            'wp-licensing-manager',
            __('Import/Export', 'wp-licensing-manager'),
            __('Import/Export', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-manager-import-export',
            array($this, 'import_export_page')
        );

        // Add database repair submenu (only if tables are missing)
        global $wpdb;
        $tables_missing = false;
        $tables_to_check = array(
            $wpdb->prefix . 'licenses',
            $wpdb->prefix . 'license_products', 
            $wpdb->prefix . 'license_activations'
        );
        
        foreach ($tables_to_check as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                $tables_missing = true;
                break;
            }
        }
        
        if ($tables_missing) {
            add_submenu_page(
                'wp-licensing-manager',
                __('🔧 Database Repair', 'wp-licensing-manager'),
                __('🔧 Database Repair', 'wp-licensing-manager'),
                'manage_options',
                'wp-licensing-manager-repair',
                array($this, 'database_repair_page')
            );
        }
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

    /**
     * Database repair page
     */
    public function database_repair_page() {
        include WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/database-repair-admin.php';
    }

    /**
     * Import/Export page
     */
    public function import_export_page() {
        include WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/import-export.php';
    }

    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : '';
        
        if (empty($export_type)) {
            wp_send_json_error('Missing export type');
        }

        global $wpdb;
        $data = array();
        $filename = '';

        switch ($export_type) {
            case 'licenses':
                $licenses = $wpdb->get_results("
                    SELECT l.*, p.name as product_name, p.slug as product_slug 
                    FROM {$wpdb->prefix}licenses l 
                    LEFT JOIN {$wpdb->prefix}license_products p ON l.product_id = p.id 
                    ORDER BY l.created_at DESC
                ");
                
                // Get activations for each license
                foreach ($licenses as &$license) {
                    $activations = $wpdb->get_results($wpdb->prepare("
                        SELECT domain, ip_address, activated_at 
                        FROM {$wpdb->prefix}license_activations 
                        WHERE license_id = %d
                    ", $license->id));
                    $license->activations_data = $activations;
                }
                
                $data = array(
                    'type' => 'licenses',
                    'version' => WP_LICENSING_MANAGER_VERSION,
                    'exported_at' => current_time('mysql'),
                    'count' => count($licenses),
                    'data' => $licenses
                );
                $filename = 'wp-licensing-licenses-' . date('Y-m-d-H-i-s') . '.json';
                break;

            case 'products':
                $products = $wpdb->get_results("
                    SELECT * FROM {$wpdb->prefix}license_products 
                    ORDER BY created_at DESC
                ");
                
                // Get statistics for each product
                foreach ($products as &$product) {
                    $stats = $wpdb->get_row($wpdb->prepare("
                        SELECT 
                            COUNT(*) as total_licenses,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_licenses,
                            SUM(activations) as total_activations
                        FROM {$wpdb->prefix}licenses 
                        WHERE product_id = %d
                    ", $product->id));
                    $product->statistics = $stats;
                }
                
                $data = array(
                    'type' => 'products',
                    'version' => WP_LICENSING_MANAGER_VERSION,
                    'exported_at' => current_time('mysql'),
                    'count' => count($products),
                    'data' => $products
                );
                $filename = 'wp-licensing-products-' . date('Y-m-d-H-i-s') . '.json';
                break;

            case 'full':
                // Export everything
                $licenses = $wpdb->get_results("
                    SELECT l.*, p.name as product_name, p.slug as product_slug 
                    FROM {$wpdb->prefix}licenses l 
                    LEFT JOIN {$wpdb->prefix}license_products p ON l.product_id = p.id 
                    ORDER BY l.created_at DESC
                ");
                
                foreach ($licenses as &$license) {
                    $activations = $wpdb->get_results($wpdb->prepare("
                        SELECT domain, ip_address, activated_at 
                        FROM {$wpdb->prefix}license_activations 
                        WHERE license_id = %d
                    ", $license->id));
                    $license->activations_data = $activations;
                }
                
                $products = $wpdb->get_results("
                    SELECT * FROM {$wpdb->prefix}license_products 
                    ORDER BY created_at DESC
                ");
                
                $activations = $wpdb->get_results("
                    SELECT * FROM {$wpdb->prefix}license_activations 
                    ORDER BY activated_at DESC
                ");
                
                $data = array(
                    'type' => 'full',
                    'version' => WP_LICENSING_MANAGER_VERSION,
                    'exported_at' => current_time('mysql'),
                    'licenses' => array('count' => count($licenses), 'data' => $licenses),
                    'products' => array('count' => count($products), 'data' => $products),
                    'activations' => array('count' => count($activations), 'data' => $activations)
                );
                $filename = 'wp-licensing-full-backup-' . date('Y-m-d-H-i-s') . '.json';
                break;

            default:
                wp_send_json_error('Invalid export type');
        }

        wp_send_json_success(array(
            'data' => $data,
            'filename' => $filename
        ));
    }

    /**
     * AJAX: Import data
     */
    public function ajax_import_data() {
        if (!wp_licensing_manager_verify_ajax_nonce('wp_licensing_manager_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No file uploaded or upload error');
        }

        $uploaded_file = $_FILES['import_file'];
        
        // Validate file type
        $file_info = pathinfo($uploaded_file['name']);
        if (strtolower($file_info['extension']) !== 'json') {
            wp_send_json_error('Only JSON files are allowed');
        }

        // Read file content
        $file_content = file_get_contents($uploaded_file['tmp_name']);
        $data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON file: ' . json_last_error_msg());
        }

        // Validate data structure
        if (!isset($data['type']) || !isset($data['version'])) {
            wp_send_json_error('Invalid export file format');
        }

        global $wpdb;
        $import_mode = isset($_POST['import_mode']) ? sanitize_text_field($_POST['import_mode']) : 'skip';
        $results = array();

        try {
            $wpdb->query('START TRANSACTION');

            switch ($data['type']) {
                case 'licenses':
                    $results = $this->import_licenses($data['data'], $import_mode);
                    break;

                case 'products':
                    $results = $this->import_products($data['data'], $import_mode);
                    break;

                case 'full':
                    // Import products first, then licenses
                    $product_results = $this->import_products($data['products']['data'], $import_mode);
                    $license_results = $this->import_licenses($data['licenses']['data'], $import_mode);
                    
                    $results = array(
                        'products' => $product_results,
                        'licenses' => $license_results,
                        'total_imported' => $product_results['imported'] + $license_results['imported'],
                        'total_skipped' => $product_results['skipped'] + $license_results['skipped'],
                        'total_errors' => $product_results['errors'] + $license_results['errors']
                    );
                    break;

                default:
                    throw new Exception('Unsupported import type: ' . $data['type']);
            }

            $wpdb->query('COMMIT');
            wp_send_json_success($results);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import licenses from data array
     */
    private function import_licenses($licenses_data, $import_mode) {
        global $wpdb;
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $messages = array();

        foreach ($licenses_data as $license_data) {
            try {
                // Check if license exists
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}licenses WHERE license_key = %s",
                    $license_data->license_key
                ));

                if ($existing) {
                    if ($import_mode === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($import_mode === 'update') {
                        // Update existing license
                        $result = $wpdb->update(
                            $wpdb->prefix . 'licenses',
                            array(
                                'product_id' => $license_data->product_id,
                                'status' => $license_data->status,
                                'expires_at' => $license_data->expires_at,
                                'max_activations' => $license_data->max_activations,
                                'activations' => $license_data->activations,
                                'domains' => $license_data->domains,
                                'customer_email' => $license_data->customer_email,
                                'order_id' => $license_data->order_id
                            ),
                            array('id' => $existing->id),
                            array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d'),
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            $imported++;
                            
                            // Import activations data if available
                            if (isset($license_data->activations_data) && is_array($license_data->activations_data)) {
                                $this->import_license_activations($existing->id, $license_data->product_id, $license_data->activations_data);
                            }
                        } else {
                            $errors++;
                        }
                    }
                } else {
                    // Insert new license
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'licenses',
                        array(
                            'product_id' => $license_data->product_id,
                            'license_key' => $license_data->license_key,
                            'status' => $license_data->status,
                            'expires_at' => $license_data->expires_at,
                            'max_activations' => $license_data->max_activations,
                            'activations' => $license_data->activations,
                            'domains' => $license_data->domains,
                            'customer_email' => $license_data->customer_email,
                            'order_id' => $license_data->order_id,
                            'created_at' => isset($license_data->created_at) ? $license_data->created_at : current_time('mysql')
                        ),
                        array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s')
                    );
                    
                    if ($result) {
                        $imported++;
                        $license_id = $wpdb->insert_id;
                        
                        // Import activations data if available
                        if (isset($license_data->activations_data) && is_array($license_data->activations_data)) {
                            $this->import_license_activations($license_id, $license_data->product_id, $license_data->activations_data);
                        }
                    } else {
                        $errors++;
                    }
                }
            } catch (Exception $e) {
                $errors++;
                $messages[] = 'Error importing license ' . $license_data->license_key . ': ' . $e->getMessage();
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'messages' => $messages
        );
    }

    /**
     * Import products from data array
     */
    private function import_products($products_data, $import_mode) {
        global $wpdb;
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $messages = array();

        foreach ($products_data as $product_data) {
            try {
                // Check if product exists
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}license_products WHERE slug = %s",
                    $product_data->slug
                ));

                if ($existing) {
                    if ($import_mode === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($import_mode === 'update') {
                        // Update existing product
                        $result = $wpdb->update(
                            $wpdb->prefix . 'license_products',
                            array(
                                'name' => $product_data->name,
                                'latest_version' => $product_data->latest_version,
                                'changelog' => $product_data->changelog,
                                'update_file_path' => $product_data->update_file_path,
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $existing->id),
                            array('%s', '%s', '%s', '%s', '%s'),
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            $imported++;
                        } else {
                            $errors++;
                        }
                    }
                } else {
                    // Insert new product
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'license_products',
                        array(
                            'slug' => $product_data->slug,
                            'name' => $product_data->name,
                            'latest_version' => $product_data->latest_version,
                            'changelog' => $product_data->changelog ?: '',
                            'update_file_path' => $product_data->update_file_path ?: '',
                            'created_at' => isset($product_data->created_at) ? $product_data->created_at : current_time('mysql')
                        ),
                        array('%s', '%s', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result) {
                        $imported++;
                    } else {
                        $errors++;
                    }
                }
            } catch (Exception $e) {
                $errors++;
                $messages[] = 'Error importing product ' . $product_data->slug . ': ' . $e->getMessage();
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'messages' => $messages
        );
    }

    /**
     * Import license activations
     */
    private function import_license_activations($license_id, $product_id, $activations_data) {
        global $wpdb;
        
        foreach ($activations_data as $activation) {
            // Check if activation already exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}license_activations WHERE license_id = %d AND domain = %s",
                $license_id,
                $activation->domain
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    $wpdb->prefix . 'license_activations',
                    array(
                        'license_id' => $license_id,
                        'product_id' => $product_id,
                        'domain' => $activation->domain,
                        'ip_address' => $activation->ip_address ?: '',
                        'activated_at' => $activation->activated_at
                    ),
                    array('%d', '%d', '%s', '%s', '%s')
                );
            }
        }
    }
}

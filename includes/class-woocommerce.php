<?php
/**
 * WooCommerce Integration class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_WooCommerce {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Add product meta hooks - these should work even if WooCommerce isn't fully loaded yet
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_meta_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta_fields'));

        // Also try the licensing tab approach
        add_filter('woocommerce_product_data_tabs', array($this, 'add_licensing_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_licensing_tab_content'));

        // Order completion hooks
        add_action('woocommerce_order_status_completed', array($this, 'generate_license_on_order_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'generate_license_on_order_complete'));
        
        // Order refund hook
        add_action('woocommerce_order_status_refunded', array($this, 'disable_licenses_on_refund'));
        add_action('woocommerce_order_status_cancelled', array($this, 'disable_licenses_on_refund'));

        // Downloads table integration - add license keys to downloads
        add_filter('woocommerce_account_downloads_columns', array($this, 'add_license_column_to_downloads'));
        add_action('woocommerce_account_downloads_column_license-key', array($this, 'display_license_in_downloads'));
        
        // Modify downloads data to include license information
        add_filter('woocommerce_customer_get_downloadable_products', array($this, 'add_license_data_to_downloads'));
        
        // Customize downloads table columns and data
        add_filter('woocommerce_account_downloads_columns', array($this, 'customize_downloads_columns'));
        add_action('woocommerce_account_downloads_column_download-expires', array($this, 'display_license_expiry'));
        
        // Add professional dashboard styling
        add_action('wp_head', array($this, 'add_dashboard_styles'));
        add_action('woocommerce_account_navigation', array($this, 'add_dashboard_enhancements'));
    }

    /**
     * Add product meta fields for licensing
     */
    public function add_product_meta_fields() {
        // Check if WooCommerce functions are available
        if (!function_exists('woocommerce_wp_checkbox') || 
            !function_exists('woocommerce_wp_select') || 
            !function_exists('woocommerce_wp_text_field')) {
            return;
        }
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_is_licensed',
            'label' => __('Licensed Product', 'wp-licensing-manager'),
            'description' => __('Generate license key when order is completed', 'wp-licensing-manager')
        ));

        // Get products for dropdown
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $products = $product_manager->get_products_for_select();

        $options = array('' => __('Select a product...', 'wp-licensing-manager')) + $products;

        woocommerce_wp_select(array(
            'id' => '_license_product_id',
            'label' => __('License Product', 'wp-licensing-manager'),
            'options' => $options,
            'description' => __('Select the product for license generation', 'wp-licensing-manager')
        ));

        woocommerce_wp_text_field(array(
            'id' => '_license_expiry_days',
            'label' => __('License Expiry (Days)', 'wp-licensing-manager'),
            'placeholder' => get_option('wp_licensing_manager_default_expiry_days', 365),
            'description' => __('Number of days until license expires (0 for lifetime)', 'wp-licensing-manager'),
            'type' => 'number'
        ));

        woocommerce_wp_text_field(array(
            'id' => '_license_max_activations',
            'label' => __('Max Activations', 'wp-licensing-manager'),
            'placeholder' => get_option('wp_licensing_manager_default_max_activations', 1),
            'description' => __('Maximum number of activations allowed', 'wp-licensing-manager'),
            'type' => 'number'
        ));

        echo '</div>';
    }

    /**
     * Add licensing tab to product data tabs
     */
    public function add_licensing_tab($tabs) {
        $tabs['licensing'] = array(
            'label' => __('Licensing', 'wp-licensing-manager'),
            'target' => 'licensing_product_data',
            'class' => array('show_if_simple', 'show_if_variable'),
            'priority' => 80
        );
        return $tabs;
    }

    /**
     * Add licensing tab content
     */
    public function add_licensing_tab_content() {
        global $post;
        
        echo '<div id="licensing_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        
        // Check if WooCommerce functions are available
        if (function_exists('woocommerce_wp_checkbox')) {
            woocommerce_wp_checkbox(array(
                'id' => '_is_licensed',
                'label' => __('Licensed Product', 'wp-licensing-manager'),
                'description' => __('Generate license key when order is completed', 'wp-licensing-manager')
            ));
        } else {
            // Fallback HTML if WooCommerce functions not available
            $is_licensed = get_post_meta($post->ID, '_is_licensed', true);
            echo '<p class="form-field _is_licensed_field">';
            echo '<label for="_is_licensed">' . __('Licensed Product', 'wp-licensing-manager') . '</label>';
            echo '<input type="checkbox" class="checkbox" name="_is_licensed" id="_is_licensed" value="yes" ' . checked($is_licensed, 'yes', false) . ' />';
            echo '<span class="description">' . __('Generate license key when order is completed', 'wp-licensing-manager') . '</span>';
            echo '</p>';
        }

        // Get products for dropdown
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $products = $product_manager->get_products_for_select();
        $options = array('' => __('Select a product...', 'wp-licensing-manager')) + $products;

        if (function_exists('woocommerce_wp_select')) {
            woocommerce_wp_select(array(
                'id' => '_license_product_id',
                'label' => __('License Product', 'wp-licensing-manager'),
                'options' => $options,
                'description' => __('Select the product for license generation', 'wp-licensing-manager')
            ));
        } else {
            // Fallback HTML
            $selected_product = get_post_meta($post->ID, '_license_product_id', true);
            echo '<p class="form-field _license_product_id_field">';
            echo '<label for="_license_product_id">' . __('License Product', 'wp-licensing-manager') . '</label>';
            echo '<select name="_license_product_id" id="_license_product_id" class="select short">';
            foreach ($options as $key => $value) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($selected_product, $key, false) . '>' . esc_html($value) . '</option>';
            }
            echo '</select>';
            echo '<span class="description">' . __('Select the product for license generation', 'wp-licensing-manager') . '</span>';
            echo '</p>';
        }

        if (function_exists('woocommerce_wp_text_field')) {
            woocommerce_wp_text_field(array(
                'id' => '_license_expiry_days',
                'label' => __('License Expiry (Days)', 'wp-licensing-manager'),
                'placeholder' => get_option('wp_licensing_manager_default_expiry_days', 365),
                'description' => __('Number of days until license expires (0 for lifetime)', 'wp-licensing-manager'),
                'type' => 'number'
            ));

            woocommerce_wp_text_field(array(
                'id' => '_license_max_activations',
                'label' => __('Max Activations', 'wp-licensing-manager'),
                'placeholder' => get_option('wp_licensing_manager_default_max_activations', 1),
                'description' => __('Maximum number of activations allowed', 'wp-licensing-manager'),
                'type' => 'number'
            ));
        } else {
            // Fallback HTML for text fields
            $expiry_days = get_post_meta($post->ID, '_license_expiry_days', true);
            $max_activations = get_post_meta($post->ID, '_license_max_activations', true);
            
            echo '<p class="form-field _license_expiry_days_field">';
            echo '<label for="_license_expiry_days">' . __('License Expiry (Days)', 'wp-licensing-manager') . '</label>';
            echo '<input type="number" class="short" name="_license_expiry_days" id="_license_expiry_days" value="' . esc_attr($expiry_days) . '" placeholder="365" />';
            echo '<span class="description">' . __('Number of days until license expires (0 for lifetime)', 'wp-licensing-manager') . '</span>';
            echo '</p>';
            
            echo '<p class="form-field _license_max_activations_field">';
            echo '<label for="_license_max_activations">' . __('Max Activations', 'wp-licensing-manager') . '</label>';
            echo '<input type="number" class="short" name="_license_max_activations" id="_license_max_activations" value="' . esc_attr($max_activations) . '" placeholder="1" />';
            echo '<span class="description">' . __('Maximum number of activations allowed', 'wp-licensing-manager') . '</span>';
            echo '</p>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Save product meta fields
     *
     * @param int $post_id
     */
    public function save_product_meta_fields($post_id) {
        $is_licensed = isset($_POST['_is_licensed']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_licensed', $is_licensed);

        if (isset($_POST['_license_product_id'])) {
            update_post_meta($post_id, '_license_product_id', absint($_POST['_license_product_id']));
        }

        if (isset($_POST['_license_expiry_days'])) {
            update_post_meta($post_id, '_license_expiry_days', absint($_POST['_license_expiry_days']));
        }

        if (isset($_POST['_license_max_activations'])) {
            update_post_meta($post_id, '_license_max_activations', absint($_POST['_license_max_activations']));
        }
    }

    /**
     * Generate license when order is completed
     *
     * @param int $order_id
     */
    public function generate_license_on_order_complete($order_id) {
        // Check if WooCommerce functions are available
        if (!function_exists('wc_get_order') || !function_exists('wc_get_product')) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if licenses already generated for this order
        $existing_licenses = $this->get_order_licenses($order_id);
        if (!empty($existing_licenses)) {
            return;
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            // Check if product is licensed
            $is_licensed = get_post_meta($product_id, '_is_licensed', true);
            if ($is_licensed !== 'yes') {
                continue;
            }

            // Get license product ID
            $license_product_id = get_post_meta($product_id, '_license_product_id', true);
            if (empty($license_product_id)) {
                continue;
            }

            // Get license settings
            $expiry_days = get_post_meta($product_id, '_license_expiry_days', true);
            $max_activations = get_post_meta($product_id, '_license_max_activations', true);

            if (empty($expiry_days)) {
                $expiry_days = get_option('wp_licensing_manager_default_expiry_days', 365);
            }

            if (empty($max_activations)) {
                $max_activations = get_option('wp_licensing_manager_default_max_activations', 1);
            }

            // Generate license for each quantity
            $quantity = $item->get_quantity();
            for ($i = 0; $i < $quantity; $i++) {
                $license_args = array(
                    'product_id' => $license_product_id,
                    'customer_email' => $order->get_billing_email(),
                    'order_id' => $order_id,
                    'expires_at' => wp_licensing_manager_get_expiry_date($expiry_days),
                    'max_activations' => $max_activations
                );

                $license_id = $license_manager->create_license($license_args);

                if ($license_id) {
                    // Send email to customer
                    $this->send_license_email($order, $license_manager->get_license($license_id));
                }
            }
        }
    }

    /**
     * Send license email to customer
     *
     * @param WC_Order $order
     * @param object $license
     */
    private function send_license_email($order, $license) {
        $subject = get_option('wp_licensing_manager_email_template_subject', 'Your License Key');
        $body = get_option('wp_licensing_manager_email_template_body', 'Thank you for your purchase. Your license key is: {license_key}');

        // Get product name
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product($license->product_id);
        $product_name = $product ? $product->name : 'Unknown Product';

        // Replace placeholders
        $subject = str_replace('{license_key}', $license->license_key, $subject);
        $subject = str_replace('{customer_name}', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $subject);
        $subject = str_replace('{order_id}', $order->get_id(), $subject);
        $subject = str_replace('{product_name}', $product_name, $subject);

        $body = str_replace('{license_key}', $license->license_key, $body);
        $body = str_replace('{customer_name}', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), $body);
        $body = str_replace('{order_id}', $order->get_id(), $body);
        $body = str_replace('{product_name}', $product_name, $body);

        wp_mail(
            $order->get_billing_email(),
            $subject,
            $body,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }

    /**
     * Disable licenses when order is refunded or cancelled
     *
     * @param int $order_id
     */
    public function disable_licenses_on_refund($order_id) {
        $licenses = $this->get_order_licenses($order_id);
        
        if (empty($licenses)) {
            return;
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();
        
        foreach ($licenses as $license) {
            // Only disable active licenses
            if ($license->status === 'active') {
                $license_manager->update_license($license->id, array(
                    'status' => 'disabled'
                ));
                
                // Log the action
                if (function_exists('error_log')) {
                    error_log("WP Licensing Manager: Disabled license {$license->license_key} due to order {$order_id} refund/cancellation");
                }
            }
        }
    }

    /**
     * Get licenses for an order
     *
     * @param int $order_id
     * @return array
     */
    private function get_order_licenses($order_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}licenses WHERE order_id = %d",
                $order_id
            )
        );
    }

    /**
     * Add License Key column to Downloads table
     *
     * @param array $columns
     * @return array
     */
    public function add_license_column_to_downloads($columns) {
        // Insert License Key column before the Download column
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            if ($key === 'download-actions') {
                $new_columns['license-key'] = __('License Key', 'wp-licensing-manager');
            }
            $new_columns[$key] = $column;
        }
        
        return $new_columns;
    }

    /**
     * Customize Downloads table columns
     *
     * @param array $columns
     * @return array
     */
    public function customize_downloads_columns($columns) {
        // Remove Downloads remaining column and customize expires column
        $customized_columns = array();
        
        foreach ($columns as $key => $column) {
            // Skip downloads remaining column
            if ($key === 'download-remaining') {
                continue;
            }
            
            // Rename expires column to be more specific about license expiry
            if ($key === 'download-expires') {
                $customized_columns[$key] = __('License Expires', 'wp-licensing-manager');
            } else {
                $customized_columns[$key] = $column;
            }
        }
        
        return $customized_columns;
    }

    /**
     * Display license key in Downloads table
     *
     * @param array $download
     */
    public function display_license_in_downloads($download) {
        // Get license for this order/product combination
        $license = $this->get_license_for_download($download);
        
        if ($license) {
            echo '<div class="wc-license-key-cell" data-license-id="' . esc_attr($license->id) . '">';
            
            // License key with responsive formatting
            echo '<code class="wc-license-key" data-full-key="' . esc_attr($license->license_key) . '">';
            echo esc_html($license->license_key);
            echo '</code>';
            
            // Copy button with responsive behavior
            echo '<button type="button" class="button wc-copy-license" ';
            echo 'data-license="' . esc_attr($license->license_key) . '" ';
            echo 'title="' . esc_attr__('Copy License Key', 'wp-licensing-manager') . '" ';
            echo 'aria-label="' . esc_attr__('Copy license key to clipboard', 'wp-licensing-manager') . '">';
            echo '<span class="copy-text">' . esc_html__('Copy', 'wp-licensing-manager') . '</span>';
            echo '<span class="copy-icon" aria-hidden="true">📋</span>';
            echo '</button>';
            
            // Status and expiry information
            echo '<div class="wc-license-status">';
            echo '<small class="license-status-badge" data-status="' . esc_attr($license->status) . '">';
            echo wp_licensing_manager_format_status($license->status);
            echo '</small>';
            
            if (!empty($license->expires_at) && $license->expires_at !== '0000-00-00') {
                echo '<small class="license-expiry">';
                echo sprintf(__('Expires: %s', 'wp-licensing-manager'), 
                    '<time datetime="' . esc_attr($license->expires_at) . '">' . 
                    date_i18n(get_option('date_format'), strtotime($license->expires_at)) . 
                    '</time>');
                echo '</small>';
            } else {
                echo '<small class="license-expiry license-lifetime">';
                echo esc_html__('Lifetime License', 'wp-licensing-manager');
                echo '</small>';
            }
            
            // Activation count for mobile view
            echo '<small class="license-activations" data-activations="' . esc_attr($license->activations) . '" data-max="' . esc_attr($license->max_activations) . '">';
            echo sprintf(__('Activations: %d/%d', 'wp-licensing-manager'), 
                (int)$license->activations, 
                (int)$license->max_activations);
            echo '</small>';
            
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="wc-no-license-cell">';
            echo '<span class="wc-no-license">' . esc_html__('No license required', 'wp-licensing-manager') . '</span>';
            echo '</div>';
        }
    }

    /**
     * Add license data to downloads
     *
     * @param array $downloads
     * @return array
     */
    public function add_license_data_to_downloads($downloads) {
        foreach ($downloads as &$download) {
            $license = $this->get_license_for_download($download);
            if ($license) {
                $download['license_data'] = $license;
            }
        }
        return $downloads;
    }

    /**
     * Get license for a download
     *
     * @param array $download
     * @return object|null
     */
    private function get_license_for_download($download) {
        global $wpdb;
        
        // Get the product ID and order ID from the download
        $order_id = $download['order_id'] ?? null;
        
        if (!$order_id) {
            return null;
        }
        
        // Get the WooCommerce product ID
        $wc_product_id = $download['product_id'] ?? null;
        if (!$wc_product_id) {
            return null;
        }
        
        // Check if this WooCommerce product has licensing enabled
        $is_licensed = get_post_meta($wc_product_id, '_is_licensed', true);
        if ($is_licensed !== 'yes') {
            return null;
        }
        
        // Get the license product ID
        $license_product_id = get_post_meta($wc_product_id, '_license_product_id', true);
        if (!$license_product_id) {
            return null;
        }
        
        // Find the license for this order and license product
        $license = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*, p.name as product_name 
                 FROM {$wpdb->prefix}licenses l 
                 LEFT JOIN {$wpdb->prefix}license_products p ON l.product_id = p.id 
                 WHERE l.order_id = %d AND l.product_id = %d 
                 ORDER BY l.created_at DESC 
                 LIMIT 1",
                $order_id,
                $license_product_id
            )
        );
        
        return $license;
    }

    /**
     * Display license expiry in Downloads table expires column
     *
     * @param array $download
     */
    public function display_license_expiry($download) {
        // Get license for this download
        $license = $this->get_license_for_download($download);
        
        if ($license) {
            if (!empty($license->expires_at) && $license->expires_at !== '0000-00-00') {
                $expiry_date = date_i18n(get_option('date_format'), strtotime($license->expires_at));
                $days_remaining = ceil((strtotime($license->expires_at) - time()) / (60 * 60 * 24));
                
                echo '<div class="license-expiry-cell">';
                echo '<time datetime="' . esc_attr($license->expires_at) . '" class="license-expiry-date">' . esc_html($expiry_date) . '</time>';
                
                if ($days_remaining > 0) {
                    if ($days_remaining <= 30) {
                        echo '<small class="expiry-warning">(' . sprintf(__('%d days left', 'wp-licensing-manager'), $days_remaining) . ')</small>';
                    } else {
                        echo '<small class="expiry-info">(' . sprintf(__('%d days left', 'wp-licensing-manager'), $days_remaining) . ')</small>';
                    }
                } else {
                    echo '<small class="expiry-expired">(' . __('Expired', 'wp-licensing-manager') . ')</small>';
                }
                echo '</div>';
            } else {
                echo '<div class="license-expiry-cell lifetime">';
                echo '<span class="lifetime-license">' . esc_html__('Lifetime', 'wp-licensing-manager') . '</span>';
                echo '<small class="lifetime-info">(' . __('Never expires', 'wp-licensing-manager') . ')</small>';
                echo '</div>';
            }
        } else {
            // For non-licensed products, show the original download expiry or "N/A"
            if (isset($download['access_expires']) && !empty($download['access_expires'])) {
                echo esc_html(date_i18n(get_option('date_format'), strtotime($download['access_expires'])));
            } else {
                echo '<span class="no-expiry">' . esc_html__('N/A', 'wp-licensing-manager') . '</span>';
            }
        }
    }

    /**
     * My Licenses tab content (legacy - keeping for backward compatibility)
     */
    public function my_licenses_content() {
        $customer_email = wp_get_current_user()->user_email;
        
        if (empty($customer_email)) {
            return;
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();
        $licenses = $license_manager->get_customer_licenses($customer_email);

        echo '<h3>' . esc_html__('My Licenses', 'wp-licensing-manager') . '</h3>';

        if (empty($licenses)) {
            echo '<p>' . esc_html__('You don\'t have any licenses yet.', 'wp-licensing-manager') . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Product', 'wp-licensing-manager') . '</th>';
        echo '<th>' . esc_html__('License Key', 'wp-licensing-manager') . '</th>';
        echo '<th>' . esc_html__('Status', 'wp-licensing-manager') . '</th>';
        echo '<th>' . esc_html__('Expires', 'wp-licensing-manager') . '</th>';
        echo '<th>' . esc_html__('Activations', 'wp-licensing-manager') . '</th>';
        echo '<th>' . esc_html__('Actions', 'wp-licensing-manager') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($licenses as $license) {
            echo '<tr>';
            echo '<td data-title="' . esc_attr__('Product', 'wp-licensing-manager') . '">';
            echo esc_html($license->product_name ? $license->product_name : 'Unknown Product');
            echo '</td>';
            echo '<td data-title="' . esc_attr__('License Key', 'wp-licensing-manager') . '">';
            echo '<code>' . esc_html($license->license_key) . '</code>';
            echo '</td>';
            echo '<td data-title="' . esc_attr__('Status', 'wp-licensing-manager') . '">';
            echo wp_licensing_manager_format_status($license->status);
            echo '</td>';
            echo '<td data-title="' . esc_attr__('Expires', 'wp-licensing-manager') . '">';
            if (empty($license->expires_at) || $license->expires_at === '0000-00-00') {
                echo esc_html__('Never', 'wp-licensing-manager');
            } else {
                echo esc_html(date_i18n(get_option('date_format'), strtotime($license->expires_at)));
            }
            echo '</td>';
            echo '<td data-title="' . esc_attr__('Activations', 'wp-licensing-manager') . '">';
            echo esc_html($license->activations . ' / ' . $license->max_activations);
            echo '</td>';
            echo '<td data-title="' . esc_attr__('Actions', 'wp-licensing-manager') . '">';
            echo '<button type="button" class="button wc-copy-license" data-license="' . esc_attr($license->license_key) . '">' . esc_html__('Copy', 'wp-licensing-manager') . '</button>';
            if ($license->status === 'active') {
                echo ' <small style="color: #666;">' . esc_html__('Active', 'wp-licensing-manager') . '</small>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Add professional dashboard styles
     */
    public function add_dashboard_styles() {
        if (!is_account_page()) {
            return;
        }
        
        ?>
        <style id="wp-licensing-manager-dashboard-styles">
        /* THEME-AGNOSTIC PROFESSIONAL WOOCOMMERCE DASHBOARD STYLING */
        /* Using highly specific selectors and !important to override theme conflicts */
        /* This CSS is designed to work with ALL WordPress themes */
        
        /* CSS Reset for WooCommerce account area to prevent theme conflicts */
        body.woocommerce-account .woocommerce-MyAccount-navigation *,
        .woocommerce-account .woocommerce-MyAccount-navigation * {
            box-sizing: border-box !important;
        }
        
        /* Dashboard Navigation - Override theme styles safely */
        body.woocommerce-account .woocommerce-MyAccount-navigation,
        .woocommerce-account .woocommerce-MyAccount-navigation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation ul,
        .woocommerce-account .woocommerce-MyAccount-navigation ul {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation li,
        .woocommerce-account .woocommerce-MyAccount-navigation li {
            margin: 0 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            list-style: none !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation li:last-child,
        .woocommerce-account .woocommerce-MyAccount-navigation li:last-child {
            border-bottom: none !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a,
        .woocommerce-account .woocommerce-MyAccount-navigation a {
            display: flex !important;
            align-items: center !important;
            padding: 18px 24px !important;
            color: rgba(255, 255, 255, 0.9) !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            transition: all 0.3s ease !important;
            position: relative !important;
            overflow: hidden !important;
            border: none !important;
            background: transparent !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a:before,
        .woocommerce-account .woocommerce-MyAccount-navigation a:before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent) !important;
            transition: left 0.5s !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a:hover:before,
        .woocommerce-account .woocommerce-MyAccount-navigation a:hover:before {
            left: 100% !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a:hover,
        .woocommerce-account .woocommerce-MyAccount-navigation a:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            transform: translateX(5px) !important;
            text-decoration: none !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation .is-active a,
        .woocommerce-account .woocommerce-MyAccount-navigation .is-active a {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
            font-weight: 600 !important;
            box-shadow: inset 4px 0 0 #fff !important;
        }
        
        /* Add icons to navigation items - Safe icon insertion */
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="dashboard"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="dashboard"]:after {
            content: "🏠" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="orders"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="orders"]:after {
            content: "📦" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="downloads"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="downloads"]:after {
            content: "💾" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="edit-address"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="edit-address"]:after {
            content: "📍" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="edit-account"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="edit-account"]:after {
            content: "👤" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation a[href*="customer-logout"]:after,
        .woocommerce-account .woocommerce-MyAccount-navigation a[href*="customer-logout"]:after {
            content: "🚪" !important;
            margin-left: auto !important;
            font-size: 16px !important;
            opacity: 0.8 !important;
        }
        
        /* Dashboard Content Area - Theme-safe styling */
        body.woocommerce-account .woocommerce-MyAccount-content,
        .woocommerce-account .woocommerce-MyAccount-content {
            background: white !important;
            border-radius: 12px !important;
            padding: 30px !important;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #f0f2f5 !important;
        }
        
        /* Page Headers - Theme-safe */
        body.woocommerce-account .woocommerce-MyAccount-content h2,
        .woocommerce-account .woocommerce-MyAccount-content h2,
        body.woocommerce-account .woocommerce-MyAccount-content h3,
        .woocommerce-account .woocommerce-MyAccount-content h3 {
            color: #2c3e50 !important;
            font-weight: 600 !important;
            margin-bottom: 24px !important;
            padding-bottom: 12px !important;
            border-bottom: 2px solid #e74c3c !important;
            display: inline-block !important;
        }
        
        /* Downloads Table Enhancements - Highly specific selectors */
        body.woocommerce-account .woocommerce table.shop_table_responsive,
        .woocommerce-account .woocommerce table.shop_table_responsive,
        body.woocommerce-account .shop_table_responsive,
        .woocommerce-account .shop_table_responsive {
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e8ecef !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive thead,
        .woocommerce-account .woocommerce table.shop_table_responsive thead,
        body.woocommerce-account .shop_table_responsive thead,
        .woocommerce-account .shop_table_responsive thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive thead th,
        .woocommerce-account .woocommerce table.shop_table_responsive thead th,
        body.woocommerce-account .shop_table_responsive thead th,
        .woocommerce-account .shop_table_responsive thead th {
            color: #495057 !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 20px 16px !important;
            border-bottom: 2px solid #dee2e6 !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive tbody tr,
        .woocommerce-account .woocommerce table.shop_table_responsive tbody tr,
        body.woocommerce-account .shop_table_responsive tbody tr,
        .woocommerce-account .shop_table_responsive tbody tr {
            transition: all 0.3s ease !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive tbody tr:hover,
        .woocommerce-account .woocommerce table.shop_table_responsive tbody tr:hover,
        body.woocommerce-account .shop_table_responsive tbody tr:hover,
        .woocommerce-account .shop_table_responsive tbody tr:hover {
            background: #f8f9fa !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive tbody td,
        .woocommerce-account .woocommerce table.shop_table_responsive tbody td,
        body.woocommerce-account .shop_table_responsive tbody td,
        .woocommerce-account .shop_table_responsive tbody td {
            padding: 18px 16px !important;
            border-bottom: 1px solid #f1f3f4 !important;
            vertical-align: middle !important;
        }
        
        /* License Expiry Cell Styling */
        .license-expiry-cell {
            text-align: left;
        }
        
        .license-expiry-date {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .expiry-warning {
            color: #e74c3c;
            font-weight: 500;
            background: #ffeaa7;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
        }
        
        .expiry-info {
            color: #27ae60;
            font-weight: 500;
            background: #d5f4e6;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
        }
        
        .expiry-expired {
            color: #e74c3c;
            font-weight: 600;
            background: #ffeaa7;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
        }
        
        .lifetime-license {
            color: #27ae60;
            font-weight: 600;
            font-size: 14px;
        }
        
        .lifetime-info {
            color: #27ae60;
            background: #d5f4e6;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin-top: 4px;
        }
        
        .no-expiry {
            color: #6c757d;
            font-style: italic;
        }
        
        /* Download Actions */
        .download-actions .button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .download-actions .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Responsive Dashboard */
        @media (max-width: 768px) {
            .woocommerce-MyAccount-navigation {
                margin-bottom: 20px;
                border-radius: 8px;
            }
            
            .woocommerce-MyAccount-navigation a {
                padding: 15px 20px;
                font-size: 14px;
            }
            
            .woocommerce-MyAccount-content {
                padding: 20px;
                border-radius: 8px;
            }
            
            .shop_table_responsive thead th {
                padding: 15px 12px;
                font-size: 12px;
            }
            
            .shop_table_responsive tbody td {
                padding: 15px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .woocommerce-MyAccount-navigation a {
                padding: 12px 16px;
                font-size: 13px;
            }
            
            .woocommerce-MyAccount-content {
                padding: 16px;
            }
            
            .shop_table_responsive thead th {
                padding: 12px 8px;
                font-size: 11px;
            }
            
            .shop_table_responsive tbody td {
                padding: 12px 8px;
            }
        }
        </style>
        <?php
    }

    /**
     * Add dashboard enhancements
     */
    public function add_dashboard_enhancements() {
        // Add theme compatibility enhancements
        echo '<script>
        jQuery(document).ready(function($) {
            // Add theme-safe CSS classes for maximum compatibility
            $("body").addClass("wp-licensing-dashboard-override");
            $(".woocommerce-MyAccount-navigation").addClass("wp-licensing-nav-override");
            $(".woocommerce-MyAccount-content").addClass("wp-licensing-content-override");
            $(".shop_table_responsive").addClass("wp-licensing-table-override");
            
            // Force CSS reset on navigation if theme is too aggressive
            var $nav = $(".woocommerce-MyAccount-navigation");
            if ($nav.length) {
                // Reset any theme interference
                $nav.css({
                    "background": "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                    "border-radius": "12px",
                    "box-shadow": "0 10px 30px rgba(102, 126, 234, 0.3)",
                    "margin-bottom": "30px",
                    "overflow": "hidden"
                });
                
                // Ensure navigation links display properly
                $nav.find("a").css({
                    "color": "rgba(255, 255, 255, 0.9)",
                    "text-decoration": "none",
                    "padding": "18px 24px",
                    "display": "flex",
                    "align-items": "center"
                });
            }
            
            // Force table styling if theme overrides it
            var $table = $(".shop_table_responsive");
            if ($table.length) {
                $table.css({
                    "background": "white",
                    "border-radius": "8px",
                    "box-shadow": "0 2px 10px rgba(0, 0, 0, 0.05)",
                    "border": "1px solid #e8ecef"
                });
            }
            
            // Theme compatibility check
            setTimeout(function() {
                // Check if our styles are being overridden
                var navBg = $nav.css("background-image");
                if (!navBg || navBg === "none") {
                    // Fallback: inject inline styles as backup
                    $nav.attr("style", $nav.attr("style") + "; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;");
                }
            }, 100);
            
            // Add smooth scrolling to dashboard
            $(".woocommerce-MyAccount-navigation a").on("click", function(e) {
                var href = $(this).attr("href");
                // Only handle internal links
                if (href && href.indexOf("#") === 0) {
                    var target = $(href);
                    if (target.length) {
                        e.preventDefault();
                        $("html, body").stop().animate({
                            scrollTop: target.offset().top - 100
                        }, 1000);
                    }
                }
            });
            
            // Add loading states for download buttons
            $(document).on("click", ".download-actions .button, .woocommerce-MyAccount-downloads .button", function() {
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text("Downloading...").prop("disabled", true);
                
                setTimeout(function() {
                    $btn.text(originalText).prop("disabled", false);
                }, 3000);
            });
            
            // Mobile menu toggle for very small screens
            if ($(window).width() <= 480) {
                $(".woocommerce-MyAccount-navigation").addClass("mobile-compact");
            }
        });
        </script>';
        
        // Add copy functionality
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wc-copy-license').on('click', function(e) {
                e.preventDefault();
                var licenseKey = $(this).data('license');
                
                // Create temporary input element
                var temp = $('<input>');
                $('body').append(temp);
                temp.val(licenseKey).select();
                document.execCommand('copy');
                temp.remove();
                
                // Update button text temporarily
                var button = $(this);
                var originalText = button.text();
                button.text('<?php echo esc_js(__('Copied!', 'wp-licensing-manager')); ?>');
                setTimeout(function() {
                    button.text(originalText);
                }, 2000);
            });
        });
        </script>
        <style>
        .wc-copy-license {
            font-size: 12px;
            padding: 4px 8px;
            line-height: 1.2;
        }
        
        /* Downloads table license integration - FULLY RESPONSIVE */
        .wc-license-key-cell {
            text-align: left;
            min-width: 200px;
        }
        
        .wc-license-key {
            display: block;
            background: #f8f9fa;
            padding: 8px 10px;
            border-radius: 4px;
            font-family: Monaco, Consolas, 'Courier New', monospace;
            font-size: 13px;
            margin-bottom: 8px;
            word-break: break-all;
            border: 1px solid #e1e5e9;
            line-height: 1.4;
            color: #2c3e50;
            position: relative;
        }
        
        .wc-license-key-cell .wc-copy-license {
            font-size: 11px;
            padding: 4px 8px;
            margin-bottom: 6px;
            border-radius: 3px;
            background: #0073aa;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wc-license-key-cell .wc-copy-license:hover {
            background: #005a87;
            transform: translateY(-1px);
        }
        
        .wc-license-status {
            margin-top: 4px;
        }
        
        .wc-license-status small {
            color: #666;
            font-size: 11px;
            line-height: 1.4;
            display: block;
        }
        
        .wc-no-license {
            color: #999;
            font-style: italic;
            font-size: 12px;
            padding: 8px 0;
        }
        
        .wc-no-license-cell {
            text-align: center;
            padding: 12px 8px;
        }
        
        /* Enhanced license status styling */
        .license-status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .license-status-badge[data-status="active"] {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .license-status-badge[data-status="expired"] {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .license-status-badge[data-status="disabled"] {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .license-expiry {
            display: block;
            margin-top: 3px;
            color: #666;
        }
        
        .license-lifetime {
            color: #28a745 !important;
            font-weight: 500;
        }
        
        .license-activations {
            display: block;
            margin-top: 3px;
            color: #6c757d;
            font-size: 10px;
        }
        
        /* Copy button enhancements */
        .wc-copy-license .copy-icon {
            display: none;
            margin-left: 3px;
        }
        
        .wc-copy-license:hover .copy-icon {
            display: inline;
        }
        
        .wc-copy-license:focus {
            outline: 2px solid #0073aa;
            outline-offset: 1px;
        }
        
        .wc-copy-license.copy-success {
            background: #28a745 !important;
            color: white !important;
        }
        
        .wc-copy-license.copy-error {
            background: #dc3545 !important;
            color: white !important;
        }
        
        /* Touch device optimizations */
        .touch-device .wc-license-key {
            user-select: all;
            -webkit-user-select: all;
            -moz-user-select: all;
            -ms-user-select: all;
        }
        
        .touch-device .wc-license-key.expanded {
            white-space: normal;
            word-break: break-all;
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        /* Mobile labels */
        .mobile-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* RESPONSIVE BREAKPOINTS */
        
        /* Large Desktop (1200px+) */
        @media (min-width: 1200px) {
            .wc-license-key {
                font-size: 14px;
                padding: 10px 12px;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 12px;
                padding: 5px 10px;
            }
            
            .wc-license-key-cell {
                min-width: 250px;
            }
        }
        
        /* Desktop (992px - 1199px) */
        @media (min-width: 992px) and (max-width: 1199px) {
            .wc-license-key {
                font-size: 13px;
                padding: 8px 10px;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .wc-license-key-cell {
                min-width: 220px;
            }
        }
        
        /* Tablet (768px - 991px) */
        @media (min-width: 768px) and (max-width: 991px) {
            .wc-license-key {
                font-size: 12px;
                padding: 7px 9px;
                margin-bottom: 6px;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 10px;
                padding: 3px 6px;
                margin-bottom: 4px;
            }
            
            .wc-license-key-cell {
                min-width: 180px;
            }
            
            .wc-license-status small {
                font-size: 10px;
            }
        }
        
        /* Mobile Large (576px - 767px) */
        @media (min-width: 576px) and (max-width: 767px) {
            .wc-license-key-cell {
                min-width: auto;
                width: 100%;
            }
            
            .wc-license-key {
                font-size: 11px;
                padding: 6px 8px;
                margin-bottom: 5px;
                word-break: break-all;
                white-space: pre-wrap;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 9px;
                padding: 3px 5px;
                margin-bottom: 3px;
                width: 60px;
                text-align: center;
            }
            
            .wc-license-status small {
                font-size: 9px;
                line-height: 1.3;
            }
            
            /* Stack license info vertically on mobile */
            .wc-license-key-cell {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        /* Mobile Small (480px - 575px) */
        @media (min-width: 480px) and (max-width: 575px) {
            .wc-license-key {
                font-size: 10px;
                padding: 5px 7px;
                margin-bottom: 4px;
                letter-spacing: -0.5px;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 8px;
                padding: 2px 4px;
                width: 50px;
                height: 24px;
                line-height: 20px;
            }
            
            .wc-license-status small {
                font-size: 8px;
            }
            
            .wc-no-license {
                font-size: 10px;
            }
        }
        
        /* Mobile Extra Small (up to 479px) */
        @media (max-width: 479px) {
            .wc-license-key-cell {
                padding: 4px !important;
            }
            
            .wc-license-key {
                font-size: 9px;
                padding: 4px 6px;
                margin-bottom: 3px;
                line-height: 1.3;
                letter-spacing: -0.3px;
                max-width: 100%;
                overflow-wrap: break-word;
            }
            
            .wc-license-key-cell .wc-copy-license {
                font-size: 7px;
                padding: 2px 3px;
                width: 45px;
                height: 20px;
                line-height: 16px;
                border-radius: 2px;
            }
            
            .wc-license-status small {
                font-size: 7px;
                line-height: 1.2;
            }
            
            .wc-no-license {
                font-size: 9px;
                padding: 4px 0;
            }
            
            /* Compact layout for very small screens */
            .wc-license-key-cell {
                display: block;
            }
            
            .wc-license-key-cell .wc-copy-license {
                margin-top: 2px;
                margin-bottom: 2px;
            }
        }
        
        /* Theme Compatibility Fallbacks */
        
        /* Ensure our styles work even with aggressive theme CSS */
        body.woocommerce-account .woocommerce-MyAccount-navigation,
        .woocommerce-account .woocommerce-MyAccount-navigation {
            all: unset !important;
            display: block !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            padding: 0 !important;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3) !important;
            margin-bottom: 30px !important;
            overflow: hidden !important;
        }
        
        /* Force table styling regardless of theme */
        body.woocommerce-account .woocommerce table.shop_table_responsive,
        .woocommerce-account .woocommerce table.shop_table_responsive,
        body.woocommerce-account .shop_table_responsive,
        .woocommerce-account .shop_table_responsive {
            all: unset !important;
            display: table !important;
            width: 100% !important;
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e8ecef !important;
            border-collapse: collapse !important;
        }
        
        /* Fallback for very aggressive themes */
        .wp-licensing-dashboard-override {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
            line-height: 1.5 !important;
        }
        
        /* Additional theme override classes */
        .wp-licensing-nav-override {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            padding: 0 !important;
            margin: 0 0 30px 0 !important;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3) !important;
            overflow: hidden !important;
            list-style: none !important;
        }
        
        .wp-licensing-content-override {
            background: white !important;
            border-radius: 12px !important;
            padding: 30px !important;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #f0f2f5 !important;
        }
        
        .wp-licensing-table-override {
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e8ecef !important;
            width: 100% !important;
        }
        
        /* Mobile compatibility enhancements */
        .mobile-compact .woocommerce-MyAccount-navigation a {
            padding: 12px 16px !important;
            font-size: 13px !important;
        }
        
        /* Ultimate fallback - highest specificity possible */
        html body.woocommerce-account div.woocommerce-MyAccount-navigation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        html body.woocommerce-account div.woocommerce-MyAccount-content {
            background: white !important;
            border-radius: 12px !important;
        }
        
        /* Enhanced responsive table for professional dashboard */
        @media (max-width: 768px) {
            /* Professional downloads table mobile styling */
            body.woocommerce-account .woocommerce table.shop_table_responsive,
            .woocommerce-account .woocommerce table.shop_table_responsive,
            body.woocommerce-account .shop_table_responsive,
            .woocommerce-account .shop_table_responsive {
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive thead,
            .woocommerce-account .woocommerce table.shop_table_responsive thead,
            body.woocommerce-account .shop_table_responsive thead,
            .woocommerce-account .shop_table_responsive thead {
                border: none !important;
                clip: rect(0 0 0 0) !important;
                height: 1px !important;
                margin: -1px !important;
                overflow: hidden !important;
                padding: 0 !important;
                position: absolute !important;
                width: 1px !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive tr,
            .woocommerce-account .woocommerce table.shop_table_responsive tr,
            body.woocommerce-account .shop_table_responsive tr,
            .woocommerce-account .shop_table_responsive tr {
                background: white !important;
                border-radius: 8px !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
                display: block !important;
                margin-bottom: 15px !important;
                padding: 15px !important;
                border: 1px solid #e8ecef !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive tr:hover,
            .woocommerce-account .woocommerce table.shop_table_responsive tr:hover,
            body.woocommerce-account .shop_table_responsive tr:hover,
            .woocommerce-account .shop_table_responsive tr:hover {
                transform: none !important;
                box-shadow: 0 3px 15px rgba(0, 0, 0, 0.15) !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive td,
            .woocommerce-account .woocommerce table.shop_table_responsive td,
            body.woocommerce-account .shop_table_responsive td,
            .woocommerce-account .shop_table_responsive td {
                border: none !important;
                border-bottom: 1px solid #f1f3f4 !important;
                display: block !important;
                font-size: 14px !important;
                text-align: left !important;
                padding: 10px 0 !important;
                position: relative !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive td:last-child,
            .woocommerce-account .woocommerce table.shop_table_responsive td:last-child,
            body.woocommerce-account .shop_table_responsive td:last-child,
            .woocommerce-account .shop_table_responsive td:last-child {
                border-bottom: none !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive td:before,
            .woocommerce-account .woocommerce table.shop_table_responsive td:before,
            body.woocommerce-account .shop_table_responsive td:before,
            .woocommerce-account .shop_table_responsive td:before {
                content: attr(data-title) !important;
                font-weight: 600 !important;
                color: #495057 !important;
                display: block !important;
                margin-bottom: 5px !important;
                font-size: 12px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }
            
            /* License cell mobile enhancements */
            .shop_table_responsive td[data-title*="License"] .wc-license-key-cell {
                margin-top: 5px;
            }
            
            .shop_table_responsive td[data-title*="License"] .wc-license-key {
                font-size: 11px;
                padding: 8px 10px;
                margin-bottom: 8px;
            }
            
            .shop_table_responsive td[data-title*="License"] .wc-copy-license {
                font-size: 10px;
                padding: 4px 8px;
            }
            
            /* License expiry mobile styling */
            .shop_table_responsive td[data-title*="Expires"] .license-expiry-cell {
                margin-top: 5px;
            }
            
            .shop_table_responsive td[data-title*="Expires"] .license-expiry-date {
                font-size: 14px;
                margin-bottom: 6px;
            }
            
            .shop_table_responsive td[data-title*="Expires"] .expiry-warning,
            .shop_table_responsive td[data-title*="Expires"] .expiry-info,
            .shop_table_responsive td[data-title*="Expires"] .expiry-expired {
                font-size: 10px;
                padding: 3px 8px;
            }
            
            /* Download actions mobile */
            .shop_table_responsive td[data-title*="Download"] .button {
                padding: 12px 20px;
                font-size: 14px;
                border-radius: 6px;
                margin-top: 5px;
            }
        }
        
        /* Print styles */
        @media print {
            .wc-license-key-cell .wc-copy-license {
                display: none;
            }
            
            .wc-license-key {
                border: 1px solid #000;
                background: none;
                font-weight: bold;
            }
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Enhanced copy functionality for downloads table
            $(document).on('click', '.wc-copy-license', function(e) {
                e.preventDefault();
                var button = $(this);
                var licenseKey = button.data('license');
                var copyText = button.find('.copy-text');
                var originalText = copyText.text();
                
                // Modern clipboard API with fallback
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(licenseKey).then(function() {
                        showCopyFeedback(button, copyText, originalText);
                    }).catch(function() {
                        fallbackCopyToClipboard(licenseKey, button, copyText, originalText);
                    });
                } else {
                    fallbackCopyToClipboard(licenseKey, button, copyText, originalText);
                }
            });
            
            // Modern copy method
            function showCopyFeedback(button, copyText, originalText) {
                button.addClass('copy-success');
                copyText.text('<?php echo esc_js(__('Copied!', 'wp-licensing-manager')); ?>');
                
                setTimeout(function() {
                    button.removeClass('copy-success');
                    copyText.text(originalText);
                }, 2000);
            }
            
            // Fallback copy method
            function fallbackCopyToClipboard(text, button, copyText, originalText) {
                var temp = $('<input>');
                $('body').append(temp);
                temp.val(text).select();
                
                try {
                    var successful = document.execCommand('copy');
                    if (successful) {
                        showCopyFeedback(button, copyText, originalText);
                    } else {
                        showCopyError(button, copyText, originalText);
                    }
                } catch (err) {
                    showCopyError(button, copyText, originalText);
                }
                
                temp.remove();
            }
            
            // Error handling
            function showCopyError(button, copyText, originalText) {
                button.addClass('copy-error');
                copyText.text('<?php echo esc_js(__('Failed', 'wp-licensing-manager')); ?>');
                
                setTimeout(function() {
                    button.removeClass('copy-error');
                    copyText.text(originalText);
                }, 2000);
            }
            
            // Touch device optimizations
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                $('.wc-license-key-cell').addClass('touch-device');
                
                // Show license key on touch for very long keys
                $(document).on('touchstart', '.wc-license-key', function() {
                    var $this = $(this);
                    if (!$this.hasClass('expanded')) {
                        $this.addClass('expanded');
                        setTimeout(function() {
                            $this.removeClass('expanded');
                        }, 3000);
                    }
                });
            }
            
            // Responsive table handling
            function handleResponsiveTable() {
                var windowWidth = $(window).width();
                
                if (windowWidth <= 768) {
                    // Mobile specific enhancements
                    $('.wc-license-key-cell').each(function() {
                        var $cell = $(this);
                        if (!$cell.find('.mobile-label').length) {
                            $cell.prepend('<div class="mobile-label"><?php echo esc_js(__('License Key:', 'wp-licensing-manager')); ?></div>');
                        }
                    });
                } else {
                    // Remove mobile labels on larger screens
                    $('.mobile-label').remove();
                }
            }
            
            // Initial call and resize handler
            handleResponsiveTable();
            $(window).on('resize', debounce(handleResponsiveTable, 250));
            
            // Debounce function for performance
            function debounce(func, wait) {
                var timeout;
                return function executedFunction() {
                    var context = this;
                    var args = arguments;
                    var later = function() {
                        timeout = null;
                        func.apply(context, args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        });
        </script>
        <?php
    }
}

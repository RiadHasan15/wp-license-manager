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
        
        // Enhanced dashboard AJAX handlers
        add_action('wp_ajax_wc_deactivate_license_domain', array($this, 'ajax_deactivate_license_domain'));
        add_action('wp_ajax_wc_regenerate_license_key', array($this, 'ajax_regenerate_license_key'));
        add_action('wp_ajax_wc_get_license_details', array($this, 'ajax_get_license_details'));
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

        // Enhanced License Configuration Section
        echo '<h4>' . __('Advanced License Settings', 'wp-licensing-manager') . '</h4>';
        
        // License Duration Dropdown
        $duration_options = array(
            '' => __('Use default expiry days (above)', 'wp-licensing-manager'),
            '30' => __('30 days', 'wp-licensing-manager'),
            '90' => __('90 days', 'wp-licensing-manager'),
            '180' => __('180 days', 'wp-licensing-manager'),
            '365' => __('1 year', 'wp-licensing-manager'),
            '730' => __('2 years', 'wp-licensing-manager'),
            '0' => __('Lifetime', 'wp-licensing-manager')
        );

        woocommerce_wp_select(array(
            'id' => '_license_duration_preset',
            'label' => __('License Duration Preset', 'wp-licensing-manager'),
            'options' => $duration_options,
            'description' => __('Quick duration presets (overrides expiry days above if selected)', 'wp-licensing-manager')
        ));

        // Grace Period Settings
        woocommerce_wp_text_field(array(
            'id' => '_license_grace_period',
            'label' => __('Grace Period (Days)', 'wp-licensing-manager'),
            'placeholder' => '0',
            'description' => __('Additional days after expiry before license is deactivated (0 = immediate)', 'wp-licensing-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '365'
            )
        ));

        // Auto-renewal Checkbox
        woocommerce_wp_checkbox(array(
            'id' => '_license_auto_renewal',
            'label' => __('Auto-Renewal Eligible', 'wp-licensing-manager'),
            'description' => __('License can be automatically renewed if customer has active subscription', 'wp-licensing-manager')
        ));

        // Trial Period Configuration
        woocommerce_wp_text_field(array(
            'id' => '_license_trial_period',
            'label' => __('Trial Period (Days)', 'wp-licensing-manager'),
            'placeholder' => '0',
            'description' => __('Free trial period before license activation (0 = no trial)', 'wp-licensing-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '365'
            )
        ));

        // Usage Limit Settings
        woocommerce_wp_text_field(array(
            'id' => '_license_usage_limit',
            'label' => __('Usage Limit (API Calls)', 'wp-licensing-manager'),
            'placeholder' => '0',
            'description' => __('Maximum API validation calls per month (0 = unlimited)', 'wp-licensing-manager'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0'
            )
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
            
            // Enhanced License Fields - Fallback HTML
            $duration_preset = get_post_meta($post->ID, '_license_duration_preset', true);
            $grace_period = get_post_meta($post->ID, '_license_grace_period', true);
            $auto_renewal = get_post_meta($post->ID, '_license_auto_renewal', true);
            $trial_period = get_post_meta($post->ID, '_license_trial_period', true);
            $usage_limit = get_post_meta($post->ID, '_license_usage_limit', true);
            
            echo '<h4>' . __('Advanced License Settings', 'wp-licensing-manager') . '</h4>';
            
            // Duration Preset Dropdown
            echo '<p class="form-field _license_duration_preset_field">';
            echo '<label for="_license_duration_preset">' . __('License Duration Preset', 'wp-licensing-manager') . '</label>';
            echo '<select name="_license_duration_preset" id="_license_duration_preset" class="select short">';
            $duration_options = array(
                '' => __('Use default expiry days (above)', 'wp-licensing-manager'),
                '30' => __('30 days', 'wp-licensing-manager'),
                '90' => __('90 days', 'wp-licensing-manager'),
                '180' => __('180 days', 'wp-licensing-manager'),
                '365' => __('1 year', 'wp-licensing-manager'),
                '730' => __('2 years', 'wp-licensing-manager'),
                '0' => __('Lifetime', 'wp-licensing-manager')
            );
            foreach ($duration_options as $key => $value) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($duration_preset, $key, false) . '>' . esc_html($value) . '</option>';
            }
            echo '</select>';
            echo '<span class="description">' . __('Quick duration presets (overrides expiry days above if selected)', 'wp-licensing-manager') . '</span>';
            echo '</p>';
            
            // Grace Period
            echo '<p class="form-field _license_grace_period_field">';
            echo '<label for="_license_grace_period">' . __('Grace Period (Days)', 'wp-licensing-manager') . '</label>';
            echo '<input type="number" class="short" name="_license_grace_period" id="_license_grace_period" value="' . esc_attr($grace_period) . '" placeholder="0" min="0" max="365" />';
            echo '<span class="description">' . __('Additional days after expiry before license is deactivated (0 = immediate)', 'wp-licensing-manager') . '</span>';
            echo '</p>';
            
            // Auto-renewal
            echo '<p class="form-field _license_auto_renewal_field">';
            echo '<label for="_license_auto_renewal">' . __('Auto-Renewal Eligible', 'wp-licensing-manager') . '</label>';
            echo '<input type="checkbox" class="checkbox" name="_license_auto_renewal" id="_license_auto_renewal" value="yes" ' . checked($auto_renewal, 'yes', false) . ' />';
            echo '<span class="description">' . __('License can be automatically renewed if customer has active subscription', 'wp-licensing-manager') . '</span>';
            echo '</p>';
            
            // Trial Period
            echo '<p class="form-field _license_trial_period_field">';
            echo '<label for="_license_trial_period">' . __('Trial Period (Days)', 'wp-licensing-manager') . '</label>';
            echo '<input type="number" class="short" name="_license_trial_period" id="_license_trial_period" value="' . esc_attr($trial_period) . '" placeholder="0" min="0" max="365" />';
            echo '<span class="description">' . __('Free trial period before license activation (0 = no trial)', 'wp-licensing-manager') . '</span>';
            echo '</p>';
            
            // Usage Limit
            echo '<p class="form-field _license_usage_limit_field">';
            echo '<label for="_license_usage_limit">' . __('Usage Limit (API Calls)', 'wp-licensing-manager') . '</label>';
            echo '<input type="number" class="short" name="_license_usage_limit" id="_license_usage_limit" value="' . esc_attr($usage_limit) . '" placeholder="0" min="0" />';
            echo '<span class="description">' . __('Maximum API validation calls per month (0 = unlimited)', 'wp-licensing-manager') . '</span>';
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

        // Save Enhanced License Fields
        if (isset($_POST['_license_duration_preset'])) {
            $duration_preset = sanitize_text_field($_POST['_license_duration_preset']);
            // Validate the preset value
            $valid_presets = array('', '30', '90', '180', '365', '730', '0');
            if (in_array($duration_preset, $valid_presets)) {
                update_post_meta($post_id, '_license_duration_preset', $duration_preset);
            }
        }

        if (isset($_POST['_license_grace_period'])) {
            $grace_period = absint($_POST['_license_grace_period']);
            // Validate range (0-365 days)
            if ($grace_period >= 0 && $grace_period <= 365) {
                update_post_meta($post_id, '_license_grace_period', $grace_period);
            }
        }

        $auto_renewal = isset($_POST['_license_auto_renewal']) ? 'yes' : 'no';
        update_post_meta($post_id, '_license_auto_renewal', $auto_renewal);

        if (isset($_POST['_license_trial_period'])) {
            $trial_period = absint($_POST['_license_trial_period']);
            // Validate range (0-365 days)
            if ($trial_period >= 0 && $trial_period <= 365) {
                update_post_meta($post_id, '_license_trial_period', $trial_period);
            }
        }

        if (isset($_POST['_license_usage_limit'])) {
            $usage_limit = absint($_POST['_license_usage_limit']);
            update_post_meta($post_id, '_license_usage_limit', $usage_limit);
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

            // Get license settings using enhanced fields
            $expiry_days = wp_licensing_manager_get_product_license_duration($product_id);
            $max_activations = get_post_meta($product_id, '_license_max_activations', true);

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
            echo '<div class="wc-license-key-cell enhanced-license-dashboard" data-license-id="' . esc_attr($license->id) . '">';
            
            // Enhanced License Header with Status
            echo '<div class="license-header">';
            echo '<div class="license-key-section">';
            echo '<code class="wc-license-key" data-full-key="' . esc_attr($license->license_key) . '">';
            echo esc_html($license->license_key);
            echo '</code>';
            
            // Enhanced Status Indicator with Color Coding
            $status_class = 'status-' . $license->status;
            $is_expiring = false;
            $days_until_expiry = 0;
            
            // Check if license is expiring soon (within 30 days)
            if (!empty($license->expires_at) && $license->expires_at !== '0000-00-00') {
                $expiry_time = strtotime($license->expires_at);
                $current_time = time();
                $days_until_expiry = floor(($expiry_time - $current_time) / (24 * 60 * 60));
                
                if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
                    $is_expiring = true;
                    $status_class .= ' status-expiring';
                }
            }
            
            echo '<span class="enhanced-status-badge ' . esc_attr($status_class) . '" data-status="' . esc_attr($license->status) . '">';
            if ($license->status === 'active' && $is_expiring) {
                echo '<span class="status-icon">⚠️</span>' . esc_html__('Expiring Soon', 'wp-licensing-manager');
            } else {
                echo '<span class="status-icon">' . $this->get_status_icon($license->status) . '</span>';
                echo wp_licensing_manager_format_status($license->status);
            }
            echo '</span>';
            echo '</div>';
            echo '</div>';
            
            // Enhanced License Information Panel
            echo '<div class="license-info-panel">';
            
            // Expiry Information with Countdown
            echo '<div class="license-expiry-section">';
            if (!empty($license->expires_at) && $license->expires_at !== '0000-00-00') {
                echo '<div class="expiry-info">';
                echo '<span class="expiry-label">' . esc_html__('Expires:', 'wp-licensing-manager') . '</span>';
                echo '<time class="expiry-date" datetime="' . esc_attr($license->expires_at) . '">' . 
                    date_i18n(get_option('date_format'), strtotime($license->expires_at)) . 
                    '</time>';
                
                // Countdown Timer
                if ($days_until_expiry > 0) {
                    echo '<div class="countdown-timer" data-expiry="' . esc_attr($license->expires_at) . '">';
                    if ($days_until_expiry <= 30) {
                        echo '<span class="countdown-warning">';
                        echo sprintf(_n('%d day remaining', '%d days remaining', $days_until_expiry, 'wp-licensing-manager'), $days_until_expiry);
                        echo '</span>';
                    } else {
                        echo '<span class="countdown-normal">';
                        echo sprintf(_n('%d day remaining', '%d days remaining', $days_until_expiry, 'wp-licensing-manager'), $days_until_expiry);
                        echo '</span>';
                    }
                    echo '</div>';
                } elseif ($days_until_expiry <= 0) {
                    echo '<div class="countdown-expired">';
                    echo esc_html__('Expired', 'wp-licensing-manager');
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="lifetime-license">';
                echo '<span class="lifetime-icon">♾️</span>';
                echo esc_html__('Lifetime License', 'wp-licensing-manager');
                echo '</div>';
            }
            echo '</div>';
            
            // Domain Usage Visualization
            echo '<div class="domain-usage-section">';
            echo '<div class="usage-header">';
            echo '<span class="usage-label">' . esc_html__('Activations:', 'wp-licensing-manager') . '</span>';
            echo '<span class="usage-count">' . sprintf('%d/%d', (int)$license->activations, (int)$license->max_activations) . '</span>';
            echo '</div>';
            
            // Usage Progress Bar
            $usage_percentage = $license->max_activations > 0 ? ($license->activations / $license->max_activations) * 100 : 0;
            $progress_class = $usage_percentage >= 100 ? 'usage-full' : ($usage_percentage >= 80 ? 'usage-high' : 'usage-normal');
            
            echo '<div class="usage-progress-bar">';
            echo '<div class="usage-progress ' . esc_attr($progress_class) . '" style="width: ' . min(100, $usage_percentage) . '%"></div>';
            echo '</div>';
            
            // Domain List (if activated)
            if ($license->activations > 0 && !empty($license->domains)) {
                $domains = explode(',', $license->domains);
                $domains = array_filter(array_map('trim', $domains));
                
                if (!empty($domains)) {
                    echo '<div class="active-domains">';
                    echo '<span class="domains-label">' . esc_html__('Active Domains:', 'wp-licensing-manager') . '</span>';
                    echo '<ul class="domains-list">';
                    foreach ($domains as $domain) {
                        echo '<li class="domain-item">';
                        echo '<span class="domain-name">' . esc_html($domain) . '</span>';
                        echo '<button type="button" class="domain-deactivate-btn" data-license-id="' . esc_attr($license->id) . '" data-domain="' . esc_attr($domain) . '" title="' . esc_attr__('Deactivate from this domain', 'wp-licensing-manager') . '">';
                        echo '<span class="deactivate-icon">🚫</span>';
                        echo '</button>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            // Quick Action Buttons
            echo '<div class="license-actions">';
            
            // Copy License Key Button
            echo '<button type="button" class="license-action-btn copy-license-btn" ';
            echo 'data-license="' . esc_attr($license->license_key) . '" ';
            echo 'title="' . esc_attr__('Copy License Key', 'wp-licensing-manager') . '">';
            echo '<span class="action-icon">📋</span>';
            echo '<span class="action-text">' . esc_html__('Copy Key', 'wp-licensing-manager') . '</span>';
            echo '</button>';
            
            // Regenerate License Key Button (if allowed)
            echo '<button type="button" class="license-action-btn regenerate-license-btn" ';
            echo 'data-license-id="' . esc_attr($license->id) . '" ';
            echo 'title="' . esc_attr__('Generate New License Key', 'wp-licensing-manager') . '">';
            echo '<span class="action-icon">🔄</span>';
            echo '<span class="action-text">' . esc_html__('Regenerate', 'wp-licensing-manager') . '</span>';
            echo '</button>';
            
            // View Details Button
            echo '<button type="button" class="license-action-btn view-details-btn" ';
            echo 'data-license-id="' . esc_attr($license->id) . '" ';
            echo 'title="' . esc_attr__('View License Details', 'wp-licensing-manager') . '">';
            echo '<span class="action-icon">👁️</span>';
            echo '<span class="action-text">' . esc_html__('Details', 'wp-licensing-manager') . '</span>';
            echo '</button>';
            
            echo '</div>';
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
     * Get status icon for license status
     *
     * @param string $status
     * @return string
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'active':
                return '✅';
            case 'inactive':
                return '⏸️';
            case 'expired':
                return '❌';
            default:
                return '❓';
        }
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
        /* ================================================================================================
           WP LICENSING MANAGER - UNIVERSAL RESPONSIVE DASHBOARD STYLES 
           Optimized for ALL devices, themes, and browsers
           ================================================================================================ */
        
        /* UNIVERSAL CSS RESET & BASE STYLES */
        body.woocommerce-account *,
        .woocommerce-account *,
        .woocommerce-MyAccount-navigation *,
        .woocommerce-MyAccount-content * {
            box-sizing: border-box !important;
        }
        
        /* RESPONSIVE FOUNDATION */
        @media (max-width: 768px) {
            body.woocommerce-account,
            .woocommerce-account {
                font-size: 14px !important;
                line-height: 1.5 !important;
            }
        }
        
        /* ================================================================================================
           UNIVERSAL RESPONSIVE LAYOUT FRAMEWORK
           ================================================================================================ */
        
        /* DESKTOP LAYOUT (1025px and up) */
        @media (min-width: 1025px) {
            body.woocommerce-account .woocommerce,
            .woocommerce-account .woocommerce {
                display: grid !important;
                grid-template-columns: 280px 1fr !important;
                gap: 40px !important;
                max-width: 1400px !important;
                margin: 0 auto !important;
                padding: 40px 20px !important;
            }
        }
        
        /* LARGE TABLET LAYOUT (769px - 1024px) */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce,
            .woocommerce-account .woocommerce {
                display: grid !important;
                grid-template-columns: 250px 1fr !important;
                gap: 30px !important;
                max-width: 1200px !important;
                margin: 0 auto !important;
                padding: 30px 20px !important;
            }
        }
        
        /* SMALL TABLET LAYOUT (481px - 768px) */
        @media (max-width: 768px) and (min-width: 481px) {
            body.woocommerce-account .woocommerce,
            .woocommerce-account .woocommerce {
                display: block !important;
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 20px 16px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation,
            .woocommerce-account .woocommerce-MyAccount-navigation {
                margin-bottom: 20px !important;
                position: sticky !important;
                top: 20px !important;
                z-index: 100 !important;
            }
        }
        
        /* MOBILE LAYOUT (320px - 480px) */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce,
            .woocommerce-account .woocommerce {
                display: block !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 15px 10px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation,
            .woocommerce-account .woocommerce-MyAccount-navigation {
                margin-bottom: 15px !important;
                position: relative !important;
                top: auto !important;
            }
        }
        
        /* EXTRA SMALL MOBILE (under 320px) */
        @media (max-width: 319px) {
            body.woocommerce-account .woocommerce,
            .woocommerce-account .woocommerce {
                padding: 10px 5px !important;
            }
        }
        
        /* THEME COMPATIBILITY OVERRIDES */
        body.woocommerce-account .woocommerce *,
        .woocommerce-account .woocommerce * {
            box-sizing: border-box !important;
        }
        
        /* PREVENT LAYOUT BREAKS */
        body.woocommerce-account .woocommerce-MyAccount-navigation,
        body.woocommerce-account .woocommerce-MyAccount-content,
        .woocommerce-account .woocommerce-MyAccount-navigation,
        .woocommerce-account .woocommerce-MyAccount-content {
            min-width: 0 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }
        
        /* ACCESSIBILITY IMPROVEMENTS */
        @media (prefers-reduced-motion: reduce) {
            body.woocommerce-account *,
            .woocommerce-account * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* HIGH CONTRAST MODE SUPPORT */
        @media (prefers-contrast: high) {
            body.woocommerce-account .woocommerce-MyAccount-navigation,
            .woocommerce-account .woocommerce-MyAccount-navigation {
                background: #000 !important;
                border: 2px solid #fff !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation a,
            .woocommerce-account .woocommerce-MyAccount-navigation a {
                color: #fff !important;
                border-bottom: 1px solid #fff !important;
            }
        }
        
        /* ================================================================================================
           RESPONSIVE DASHBOARD NAVIGATION
           ================================================================================================ */
        
        /* DESKTOP NAVIGATION STYLES */
        body.woocommerce-account .woocommerce-MyAccount-navigation,
        .woocommerce-account .woocommerce-MyAccount-navigation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 16px !important;
            padding: 0 !important;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.25) !important;
            margin-bottom: 30px !important;
            overflow: hidden !important;
            position: relative !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        /* HOVER ENHANCEMENT */
        body.woocommerce-account .woocommerce-MyAccount-navigation:hover,
        .woocommerce-account .woocommerce-MyAccount-navigation:hover {
            box-shadow: 0 25px 80px rgba(102, 126, 234, 0.35) !important;
            transform: translateY(-2px) !important;
        }
        
        /* MOBILE NAVIGATION OPTIMIZATION */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation,
            .woocommerce-account .woocommerce-MyAccount-navigation {
                border-radius: 12px !important;
                margin-bottom: 20px !important;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2) !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation:hover,
            .woocommerce-account .woocommerce-MyAccount-navigation:hover {
                transform: none !important;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2) !important;
            }
        }
        
        /* TABLET NAVIGATION */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation,
            .woocommerce-account .woocommerce-MyAccount-navigation {
                border-radius: 14px !important;
                margin-bottom: 25px !important;
            }
        }
        
        /* NAVIGATION LIST STYLES */
        body.woocommerce-account .woocommerce-MyAccount-navigation ul,
        .woocommerce-account .woocommerce-MyAccount-navigation ul {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation li,
        .woocommerce-account .woocommerce-MyAccount-navigation li {
            margin: 0 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            list-style: none !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation li:last-child,
        .woocommerce-account .woocommerce-MyAccount-navigation li:last-child {
            border-bottom: none !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-navigation li:first-child,
        .woocommerce-account .woocommerce-MyAccount-navigation li:first-child {
            border-top-left-radius: inherit !important;
            border-top-right-radius: inherit !important;
        }
        
        /* MOBILE NAVIGATION LIST */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation li,
            .woocommerce-account .woocommerce-MyAccount-navigation li {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            }
        }
        
        /* ================================================================================================
           RESPONSIVE NAVIGATION LINKS
           ================================================================================================ */
        
        /* DESKTOP NAVIGATION LINKS */
        body.woocommerce-account .woocommerce-MyAccount-navigation a,
        .woocommerce-account .woocommerce-MyAccount-navigation a {
            display: flex !important;
            align-items: center !important;
            padding: 20px 28px !important;
            color: rgba(255, 255, 255, 0.95) !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            letter-spacing: 0.5px !important;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative !important;
            overflow: hidden !important;
            border: none !important;
            background: transparent !important;
            min-height: 60px !important;
            z-index: 1 !important;
            backdrop-filter: blur(10px) !important;
        }
        
        /* TABLET NAVIGATION LINKS */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation a,
            .woocommerce-account .woocommerce-MyAccount-navigation a {
                padding: 18px 24px !important;
                font-size: 14px !important;
                min-height: 56px !important;
                letter-spacing: 0.3px !important;
            }
        }
        
        /* MOBILE NAVIGATION LINKS */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation a,
            .woocommerce-account .woocommerce-MyAccount-navigation a {
                padding: 16px 20px !important;
                font-size: 14px !important;
                min-height: 52px !important;
                justify-content: center !important;
                text-align: center !important;
                font-weight: 600 !important;
            }
        }
        
        /* SMALL MOBILE OPTIMIZATION */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation a,
            .woocommerce-account .woocommerce-MyAccount-navigation a {
                padding: 14px 16px !important;
                font-size: 13px !important;
                min-height: 48px !important;
                letter-spacing: 0.2px !important;
            }
        }
        
        /* ================================================================================================
           INTERACTIVE NAVIGATION EFFECTS
           ================================================================================================ */
        
        /* SHIMMER EFFECT */
        body.woocommerce-account .woocommerce-MyAccount-navigation a:before,
        .woocommerce-account .woocommerce-MyAccount-navigation a:before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent) !important;
            transition: left 0.6s cubic-bezier(0.4, 0, 0.2, 1) !important;
            z-index: -1 !important;
        }
        
        /* DESKTOP HOVER EFFECTS */
        @media (min-width: 769px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation a:hover:before,
            .woocommerce-account .woocommerce-MyAccount-navigation a:hover:before {
                left: 100% !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation a:hover,
            .woocommerce-account .woocommerce-MyAccount-navigation a:hover {
                background: rgba(255, 255, 255, 0.12) !important;
                color: white !important;
                transform: translateX(8px) !important;
                text-decoration: none !important;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2) !important;
            }
        }
        
        /* MOBILE TOUCH EFFECTS */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation a:hover,
            body.woocommerce-account .woocommerce-MyAccount-navigation a:focus,
            body.woocommerce-account .woocommerce-MyAccount-navigation a:active,
            .woocommerce-account .woocommerce-MyAccount-navigation a:hover,
            .woocommerce-account .woocommerce-MyAccount-navigation a:focus,
            .woocommerce-account .woocommerce-MyAccount-navigation a:active {
                background: rgba(255, 255, 255, 0.15) !important;
                color: white !important;
                transform: none !important;
                text-decoration: none !important;
                outline: none !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-navigation a:before,
            .woocommerce-account .woocommerce-MyAccount-navigation a:before {
                display: none !important;
            }
        }
        
        /* ================================================================================================
           ACTIVE NAVIGATION STATE
           ================================================================================================ */
        
        /* DESKTOP ACTIVE STATE */
        body.woocommerce-account .woocommerce-MyAccount-navigation .is-active a,
        .woocommerce-account .woocommerce-MyAccount-navigation .is-active a {
            background: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
            font-weight: 600 !important;
            box-shadow: inset 4px 0 0 rgba(255, 255, 255, 0.9) !important;
            transform: translateX(6px) !important;
            backdrop-filter: blur(20px) !important;
        }
        
        /* MOBILE ACTIVE STATE */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation .is-active a,
            .woocommerce-account .woocommerce-MyAccount-navigation .is-active a {
                background: rgba(255, 255, 255, 0.3) !important;
                transform: none !important;
                box-shadow: inset 0 4px 0 rgba(255, 255, 255, 0.9) !important;
                border-radius: 0 !important;
            }
        }
        
        /* Navigation items - Clean design without icons */
        
        /* ================================================================================================
           RESPONSIVE CONTENT AREA
           ================================================================================================ */
        
        /* DESKTOP CONTENT AREA */
        body.woocommerce-account .woocommerce-MyAccount-content,
        .woocommerce-account .woocommerce-MyAccount-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border-radius: 20px !important;
            padding: 40px !important;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.12) !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            position: relative !important;
            overflow: visible !important;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            backdrop-filter: blur(20px) !important;
        }
        
        /* CONTENT AREA SUBTLE ANIMATION */
        body.woocommerce-account .woocommerce-MyAccount-content:hover,
        .woocommerce-account .woocommerce-MyAccount-content:hover {
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.12),
                0 2px 6px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-3px) !important;
        }
        
        /* TABLET CONTENT AREA */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce-MyAccount-content,
            .woocommerce-account .woocommerce-MyAccount-content {
                padding: 32px !important;
                border-radius: 16px !important;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08) !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content:hover,
            .woocommerce-account .woocommerce-MyAccount-content:hover {
                transform: translateY(-2px) !important;
            }
        }
        
        /* MOBILE CONTENT AREA */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-content,
            .woocommerce-account .woocommerce-MyAccount-content {
                padding: 24px !important;
                border-radius: 14px !important;
                margin: 0 !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
                background: #ffffff !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content:hover,
            .woocommerce-account .woocommerce-MyAccount-content:hover {
                transform: none !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
            }
        }
        
        /* SMALL MOBILE CONTENT AREA */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce-MyAccount-content,
            .woocommerce-account .woocommerce-MyAccount-content {
                padding: 20px !important;
                border-radius: 12px !important;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06) !important;
                margin: 0 8px !important;
            }
        }
        
        /* ================================================================================================
           RESPONSIVE TYPOGRAPHY
           ================================================================================================ */
        
        /* DESKTOP HEADERS */
        body.woocommerce-account .woocommerce-MyAccount-content h1,
        body.woocommerce-account .woocommerce-MyAccount-content h2,
        body.woocommerce-account .woocommerce-MyAccount-content h3,
        .woocommerce-account .woocommerce-MyAccount-content h1,
        .woocommerce-account .woocommerce-MyAccount-content h2,
        .woocommerce-account .woocommerce-MyAccount-content h3 {
            color: #1a202c !important;
            font-weight: 700 !important;
            margin-bottom: 32px !important;
            padding-bottom: 16px !important;
            position: relative !important;
            display: block !important;
            line-height: 1.3 !important;
            letter-spacing: -0.025em !important;
        }
        
        /* GRADIENT UNDERLINE EFFECT */
        body.woocommerce-account .woocommerce-MyAccount-content h1:after,
        body.woocommerce-account .woocommerce-MyAccount-content h2:after,
        body.woocommerce-account .woocommerce-MyAccount-content h3:after,
        .woocommerce-account .woocommerce-MyAccount-content h1:after,
        .woocommerce-account .woocommerce-MyAccount-content h2:after,
        .woocommerce-account .woocommerce-MyAccount-content h3:after {
            content: '' !important;
            position: absolute !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 60px !important;
            height: 3px !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 2px !important;
        }
        
        /* HEADER SIZES */
        body.woocommerce-account .woocommerce-MyAccount-content h1,
        .woocommerce-account .woocommerce-MyAccount-content h1 {
            font-size: 32px !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-content h2,
        .woocommerce-account .woocommerce-MyAccount-content h2 {
            font-size: 28px !important;
        }
        
        body.woocommerce-account .woocommerce-MyAccount-content h3,
        .woocommerce-account .woocommerce-MyAccount-content h3 {
            font-size: 24px !important;
        }
        
        /* TABLET HEADERS */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce-MyAccount-content h1,
            .woocommerce-account .woocommerce-MyAccount-content h1 {
                font-size: 28px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h2,
            .woocommerce-account .woocommerce-MyAccount-content h2 {
                font-size: 24px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h3,
            .woocommerce-account .woocommerce-MyAccount-content h3 {
                font-size: 20px !important;
            }
        }
        
        /* MOBILE HEADERS */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce-MyAccount-content h1,
            body.woocommerce-account .woocommerce-MyAccount-content h2,
            body.woocommerce-account .woocommerce-MyAccount-content h3,
            .woocommerce-account .woocommerce-MyAccount-content h1,
            .woocommerce-account .woocommerce-MyAccount-content h2,
            .woocommerce-account .woocommerce-MyAccount-content h3 {
                margin-bottom: 24px !important;
                padding-bottom: 12px !important;
                text-align: center !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h1:after,
            body.woocommerce-account .woocommerce-MyAccount-content h2:after,
            body.woocommerce-account .woocommerce-MyAccount-content h3:after,
            .woocommerce-account .woocommerce-MyAccount-content h1:after,
            .woocommerce-account .woocommerce-MyAccount-content h2:after,
            .woocommerce-account .woocommerce-MyAccount-content h3:after {
                left: 50% !important;
                transform: translateX(-50%) !important;
                width: 40px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h1,
            .woocommerce-account .woocommerce-MyAccount-content h1 {
                font-size: 24px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h2,
            .woocommerce-account .woocommerce-MyAccount-content h2 {
                font-size: 20px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h3,
            .woocommerce-account .woocommerce-MyAccount-content h3 {
                font-size: 18px !important;
            }
        }
        
        /* SMALL MOBILE HEADERS */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce-MyAccount-content h1,
            .woocommerce-account .woocommerce-MyAccount-content h1 {
                font-size: 22px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h2,
            .woocommerce-account .woocommerce-MyAccount-content h2 {
                font-size: 18px !important;
            }
            
            body.woocommerce-account .woocommerce-MyAccount-content h3,
            .woocommerce-account .woocommerce-MyAccount-content h3 {
                font-size: 16px !important;
            }
        }
        
        /* ================================================================================================
           RESPONSIVE TABLE DESIGN
           ================================================================================================ */
        
        /* DESKTOP TABLE STYLES */
        body.woocommerce-account .woocommerce table.shop_table_responsive,
        .woocommerce-account .woocommerce table.shop_table_responsive,
        body.woocommerce-account .shop_table_responsive,
        .woocommerce-account .shop_table_responsive {
            background: #ffffff !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 
                0 10px 40px rgba(0, 0, 0, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.12) !important;
            border: 1px solid rgba(0, 0, 0, 0.06) !important;
            margin-bottom: 30px !important;
            transition: all 0.3s ease !important;
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }
        
        /* TABLE HOVER EFFECT */
        body.woocommerce-account .woocommerce table.shop_table_responsive:hover,
        .woocommerce-account .woocommerce table.shop_table_responsive:hover,
        body.woocommerce-account .shop_table_responsive:hover,
        .woocommerce-account .shop_table_responsive:hover {
            box-shadow: 
                0 15px 60px rgba(0, 0, 0, 0.12),
                0 2px 6px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-2px) !important;
        }
        
        /* TABLET TABLE STYLES */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive,
            .woocommerce-account .woocommerce table.shop_table_responsive,
            body.woocommerce-account .shop_table_responsive,
            .woocommerce-account .shop_table_responsive {
                border-radius: 12px !important;
                margin-bottom: 25px !important;
            }
        }
        
        /* MOBILE TABLE STYLES */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive,
            .woocommerce-account .woocommerce table.shop_table_responsive,
            body.woocommerce-account .shop_table_responsive,
            .woocommerce-account .shop_table_responsive {
                border-radius: 12px !important;
                margin-bottom: 20px !important;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06) !important;
            }
            
            body.woocommerce-account .woocommerce table.shop_table_responsive:hover,
            .woocommerce-account .woocommerce table.shop_table_responsive:hover,
            body.woocommerce-account .shop_table_responsive:hover,
            .woocommerce-account .shop_table_responsive:hover {
                transform: none !important;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06) !important;
            }
        }
        
        /* SMALL MOBILE TABLE STYLES */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive,
            .woocommerce-account .woocommerce table.shop_table_responsive,
            body.woocommerce-account .shop_table_responsive,
            .woocommerce-account .shop_table_responsive {
                border-radius: 10px !important;
                margin: 0 4px 15px 4px !important;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06) !important;
            }
        }
        
        /* ================================================================================================
           RESPONSIVE TABLE HEADERS
           ================================================================================================ */
        
        /* DESKTOP TABLE HEADERS */
        body.woocommerce-account .woocommerce table.shop_table_responsive thead,
        .woocommerce-account .woocommerce table.shop_table_responsive thead,
        body.woocommerce-account .shop_table_responsive thead,
        .woocommerce-account .shop_table_responsive thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            position: relative !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive thead th,
        .woocommerce-account .woocommerce table.shop_table_responsive thead th,
        body.woocommerce-account .shop_table_responsive thead th,
        .woocommerce-account .shop_table_responsive thead th {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.8px !important;
            padding: 24px 20px !important;
            border-bottom: none !important;
            border-right: 1px solid rgba(255, 255, 255, 0.1) !important;
            text-align: left !important;
            backdrop-filter: blur(10px) !important;
        }
        
        body.woocommerce-account .woocommerce table.shop_table_responsive thead th:last-child,
        .woocommerce-account .woocommerce table.shop_table_responsive thead th:last-child,
        body.woocommerce-account .shop_table_responsive thead th:last-child,
        .woocommerce-account .shop_table_responsive thead th:last-child {
            border-right: none !important;
        }
        
        /* TABLET TABLE HEADERS */
        @media (max-width: 1024px) and (min-width: 769px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive thead th,
            .woocommerce-account .woocommerce table.shop_table_responsive thead th,
            body.woocommerce-account .shop_table_responsive thead th,
            .woocommerce-account .shop_table_responsive thead th {
                padding: 20px 16px !important;
                font-size: 13px !important;
                letter-spacing: 0.6px !important;
            }
        }
        
        /* MOBILE TABLE HEADERS */
        @media (max-width: 768px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive thead th,
            .woocommerce-account .woocommerce table.shop_table_responsive thead th,
            body.woocommerce-account .shop_table_responsive thead th,
            .woocommerce-account .shop_table_responsive thead th {
                padding: 16px 12px !important;
                font-size: 12px !important;
                letter-spacing: 0.4px !important;
                text-align: center !important;
            }
        }
        
        /* SMALL MOBILE TABLE HEADERS */
        @media (max-width: 480px) {
            body.woocommerce-account .woocommerce table.shop_table_responsive thead th,
            .woocommerce-account .woocommerce table.shop_table_responsive thead th,
            body.woocommerce-account .shop_table_responsive thead th,
            .woocommerce-account .shop_table_responsive thead th {
                padding: 12px 8px !important;
                font-size: 11px !important;
                letter-spacing: 0.3px !important;
            }
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
        
        /* ================================================================================================
           ENHANCED LICENSE DASHBOARD - FULLY RESPONSIVE
           ================================================================================================ */
        
        /* DESKTOP ENHANCED LICENSE CARDS */
        .enhanced-license-dashboard {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border-radius: 20px !important;
            padding: 32px !important;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.12) !important;
            margin: 20px 0 !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative !important;
            overflow: hidden !important;
            backdrop-filter: blur(20px) !important;
        }
        
        .enhanced-license-dashboard:hover {
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.15),
                0 2px 6px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-4px) !important;
        }
        
        /* TABLET ENHANCED LICENSE CARDS */
        @media (max-width: 1024px) and (min-width: 769px) {
            .enhanced-license-dashboard {
                padding: 28px !important;
                border-radius: 16px !important;
                margin: 16px 0 !important;
            }
            
            .enhanced-license-dashboard:hover {
                transform: translateY(-3px) !important;
            }
        }
        
        /* MOBILE ENHANCED LICENSE CARDS */
        @media (max-width: 768px) {
            .enhanced-license-dashboard {
                padding: 24px !important;
                border-radius: 16px !important;
                margin: 15px 0 !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
                background: #ffffff !important;
            }
            
            .enhanced-license-dashboard:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12) !important;
            }
        }
        
        /* SMALL MOBILE ENHANCED LICENSE CARDS */
        @media (max-width: 480px) {
            .enhanced-license-dashboard {
                padding: 20px !important;
                border-radius: 14px !important;
                margin: 12px 0 !important;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06) !important;
            }
            
            .enhanced-license-dashboard:hover {
                transform: translateY(-1px) !important;
            }
        }
        
        /* License Header */
        .license-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 15px !important;
            padding-bottom: 15px !important;
            border-bottom: 1px solid #e8ecef !important;
        }
        
        .license-key-section {
            flex: 1 !important;
        }
        
        .wc-license-key {
            background: #f8f9fa !important;
            color: #495057 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            font-family: 'Monaco', 'Consolas', monospace !important;
            font-size: 13px !important;
            display: inline-block !important;
            margin-right: 10px !important;
            border: 1px solid #e8ecef !important;
        }
        
        /* Enhanced Status Badges */
        .enhanced-status-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        
        .enhanced-status-badge .status-icon {
            margin-right: 5px !important;
            font-size: 14px !important;
        }
        
        .status-active {
            background: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #c3e6cb !important;
        }
        
        .status-expiring {
            background: #fff3cd !important;
            color: #856404 !important;
            border: 1px solid #ffeaa7 !important;
        }
        
        .status-expired {
            background: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #f5c6cb !important;
        }
        
        .status-inactive {
            background: #e2e3e5 !important;
            color: #383d41 !important;
            border: 1px solid #d6d8db !important;
        }
        
        /* License Information Panel */
        .license-info-panel {
            display: grid !important;
            gap: 15px !important;
        }
        
        /* Expiry Section */
        .license-expiry-section {
            background: #f8f9fa !important;
            padding: 15px !important;
            border-radius: 8px !important;
            border-left: 4px solid #007cba !important;
        }
        
        .expiry-info {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .expiry-label {
            font-weight: 600 !important;
            color: #495057 !important;
        }
        
        .expiry-date {
            color: #007cba !important;
            font-weight: 500 !important;
        }
        
        .countdown-timer {
            margin-top: 8px !important;
        }
        
        .countdown-warning {
            color: #e74c3c !important;
            font-weight: 600 !important;
            animation: pulse 2s infinite !important;
        }
        
        .countdown-normal {
            color: #27ae60 !important;
            font-weight: 500 !important;
        }
        
        .countdown-expired {
            color: #e74c3c !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
        }
        
        .lifetime-license {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            color: #27ae60 !important;
            font-weight: 600 !important;
        }
        
        .lifetime-icon {
            font-size: 18px !important;
        }
        
        /* Domain Usage Section */
        .domain-usage-section {
            background: #fff !important;
            padding: 15px !important;
            border-radius: 8px !important;
            border: 1px solid #e8ecef !important;
        }
        
        .usage-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 10px !important;
        }
        
        .usage-label {
            font-weight: 600 !important;
            color: #495057 !important;
        }
        
        .usage-count {
            color: #007cba !important;
            font-weight: 600 !important;
            font-size: 14px !important;
        }
        
        /* Usage Progress Bar */
        .usage-progress-bar {
            width: 100% !important;
            height: 8px !important;
            background: #e9ecef !important;
            border-radius: 4px !important;
            overflow: hidden !important;
            margin-bottom: 15px !important;
        }
        
        .usage-progress {
            height: 100% !important;
            transition: width 0.3s ease !important;
            border-radius: 4px !important;
        }
        
        .usage-normal {
            background: linear-gradient(90deg, #28a745, #20c997) !important;
        }
        
        .usage-high {
            background: linear-gradient(90deg, #ffc107, #fd7e14) !important;
        }
        
        .usage-full {
            background: linear-gradient(90deg, #dc3545, #e74c3c) !important;
        }
        
        /* Active Domains */
        .active-domains {
            margin-top: 15px !important;
        }
        
        .domains-label {
            font-weight: 600 !important;
            color: #495057 !important;
            display: block !important;
            margin-bottom: 8px !important;
        }
        
        .domains-list {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .domain-item {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 12px !important;
            margin: 4px 0 !important;
            background: #f8f9fa !important;
            border-radius: 6px !important;
            border: 1px solid #e8ecef !important;
        }
        
        .domain-name {
            font-family: 'Monaco', 'Consolas', monospace !important;
            font-size: 13px !important;
            color: #495057 !important;
        }
        
        .domain-deactivate-btn {
            background: #dc3545 !important;
            color: white !important;
            border: none !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 12px !important;
            transition: all 0.2s ease !important;
        }
        
        .domain-deactivate-btn:hover {
            background: #c82333 !important;
            transform: scale(1.05) !important;
        }
        
        /* License Actions */
        .license-actions {
            display: flex !important;
            gap: 10px !important;
            margin-top: 15px !important;
            padding-top: 15px !important;
            border-top: 1px solid #e8ecef !important;
            flex-wrap: wrap !important;
        }
        
        .license-action-btn {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 8px 16px !important;
            border-radius: 6px !important;
            border: 1px solid #007cba !important;
            background: #fff !important;
            color: #007cba !important;
            text-decoration: none !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        
        .license-action-btn:hover {
            background: #007cba !important;
            color: white !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 8px rgba(0, 124, 186, 0.3) !important;
        }
        
        .copy-license-btn:hover {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }
        
        .regenerate-license-btn:hover {
            background: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #212529 !important;
        }
        
        .action-icon {
            font-size: 14px !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .license-header {
                flex-direction: column !important;
                gap: 10px !important;
                align-items: flex-start !important;
            }
            
            .license-actions {
                justify-content: center !important;
            }
            
            .license-action-btn {
                flex: 1 !important;
                justify-content: center !important;
                min-width: 0 !important;
            }
            
            .action-text {
                display: none !important;
            }
            
            .domain-item {
                flex-direction: column !important;
                gap: 8px !important;
                text-align: center !important;
            }
        }
        
        /* Animations */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .loading {
            opacity: 0.6 !important;
            pointer-events: none !important;
        }
        
        /* Success/Error Messages */
        .license-message {
            padding: 10px 15px !important;
            border-radius: 6px !important;
            margin: 10px 0 !important;
            font-size: 14px !important;
        }
        
        .license-message.success {
            background: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #c3e6cb !important;
        }
        
        .license-message.error {
            background: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #f5c6cb !important;
        }
        
        /* License Details Modal */
        .license-details-modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.7) !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .license-details-modal {
            background: #fff !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
            max-width: 600px !important;
            width: 90% !important;
            max-height: 80vh !important;
            overflow-y: auto !important;
        }
        
        .modal-header {
            padding: 20px !important;
            border-bottom: 1px solid #e8ecef !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }
        
        .modal-header h3 {
            margin: 0 !important;
            color: #495057 !important;
            font-size: 20px !important;
        }
        
        .modal-close {
            background: none !important;
            border: none !important;
            font-size: 24px !important;
            cursor: pointer !important;
            color: #6c757d !important;
            padding: 0 !important;
            width: 30px !important;
            height: 30px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 50% !important;
            transition: all 0.2s ease !important;
        }
        
        .modal-close:hover {
            background: #f8f9fa !important;
            color: #495057 !important;
        }
        
        .modal-body {
            padding: 20px !important;
        }
        
        .modal-body p {
            margin: 15px 0 !important;
            line-height: 1.6 !important;
        }
        
        .modal-body strong {
            color: #495057 !important;
            font-weight: 600 !important;
        }
        
        .modal-body code {
            background: #f8f9fa !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            font-family: 'Monaco', 'Consolas', monospace !important;
            font-size: 13px !important;
            color: #495057 !important;
            border: 1px solid #e8ecef !important;
        }
        
        /* Copy Success State */
        .copy-success {
            background: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
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
            
            // Enhance styling without breaking layout
            var $nav = $(".woocommerce-MyAccount-navigation");
            if ($nav.length) {
                // Only enhance visual styling, preserve layout
                // DON\'T override: width, display, float, position
                $nav.css({
                    "background": "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                    "border-radius": "12px",
                    "box-shadow": "0 10px 30px rgba(102, 126, 234, 0.3)",
                    "overflow": "hidden"
                });
                
                // Enhance navigation links styling only
                // DON\'T override: display, align-items that break layout
                $nav.find("a").css({
                    "color": "rgba(255, 255, 255, 0.9)",
                    "text-decoration": "none",
                    "padding": "18px 24px"
                });
            }
            
            // Enhance table styling without breaking layout
            // DON\'T override: width, display, table-layout
            var $table = $(".shop_table_responsive");
            if ($table.length) {
                $table.css({
                    "background": "white",
                    "border-radius": "8px",
                    "box-shadow": "0 2px 10px rgba(0, 0, 0, 0.05)",
                    "border": "1px solid #e8ecef"
                });
            }
            
            // Light theme compatibility check (non-intrusive)
            setTimeout(function() {
                // Only check and enhance, don\'t force override
                var navBg = $nav.css("background-image");
                if (!navBg || navBg === "none") {
                    // Gentle enhancement - preserve layout
                    $nav.css("background", "linear-gradient(135deg, #667eea 0%, #764ba2 100%)");
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
            // Enhanced License Dashboard Functionality
            
            // Copy License Key functionality
            $(document).on('click', '.copy-license-btn', function(e) {
                e.preventDefault();
                var licenseKey = $(this).data('license');
                var button = $(this);
                
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(licenseKey).then(function() {
                        showCopySuccess(button);
                    }).catch(function() {
                        fallbackCopy(licenseKey, button);
                    });
                } else {
                    fallbackCopy(licenseKey, button);
                }
            });
            
            // Fallback copy method
            function fallbackCopy(text, button) {
                var temp = $('<input>');
                $('body').append(temp);
                temp.val(text).select();
                document.execCommand('copy');
                temp.remove();
                showCopySuccess(button);
            }
            
            // Show copy success feedback
            function showCopySuccess(button) {
                var originalHtml = button.html();
                button.html('<span class="action-icon">✅</span><span class="action-text"><?php echo esc_js(__('Copied!', 'wp-licensing-manager')); ?></span>');
                button.addClass('copy-success');
                setTimeout(function() {
                    button.html(originalHtml);
                    button.removeClass('copy-success');
                }, 2000);
            }
            
            // Domain Deactivation
            $(document).on('click', '.domain-deactivate-btn', function(e) {
                e.preventDefault();
                var button = $(this);
                var licenseId = button.data('license-id');
                var domain = button.data('domain');
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to deactivate this license from', 'wp-licensing-manager')); ?> ' + domain + '?')) {
                    return;
                }
                
                button.prop('disabled', true).html('<span class="deactivate-icon">⏳</span>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wc_deactivate_license_domain',
                        license_id: licenseId,
                        domain: domain,
                        nonce: '<?php echo wp_create_nonce('wp_licensing_manager_customer_actions'); ?>'
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            button.closest('.domain-item').fadeOut(300, function() {
                                $(this).remove();
                                showMessage('Domain deactivated successfully', 'success');
                                // Refresh the page to update activation counts
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            });
                        } else {
                            showMessage(data.error || 'Failed to deactivate domain', 'error');
                            button.prop('disabled', false).html('<span class="deactivate-icon">🚫</span>');
                        }
                    },
                    error: function() {
                        showMessage('Network error occurred', 'error');
                        button.prop('disabled', false).html('<span class="deactivate-icon">🚫</span>');
                    }
                });
            });
            
            // License Regeneration
            $(document).on('click', '.regenerate-license-btn', function(e) {
                e.preventDefault();
                var button = $(this);
                var licenseId = button.data('license-id');
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to regenerate this license key? This will invalidate the current key.', 'wp-licensing-manager')); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).html('<span class="action-icon">⏳</span><span class="action-text"><?php echo esc_js(__('Generating...', 'wp-licensing-manager')); ?></span>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wc_regenerate_license_key',
                        license_id: licenseId,
                        nonce: '<?php echo wp_create_nonce('wp_licensing_manager_customer_actions'); ?>'
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            // Update the license key display
                            var licenseCell = button.closest('.enhanced-license-dashboard');
                            var licenseKeyElement = licenseCell.find('.wc-license-key');
                            licenseKeyElement.text(data.new_license_key);
                            licenseKeyElement.attr('data-full-key', data.new_license_key);
                            
                            // Update copy button data
                            licenseCell.find('.copy-license-btn').attr('data-license', data.new_license_key);
                            
                            showMessage(data.message, 'success');
                            button.prop('disabled', false).html('<span class="action-icon">🔄</span><span class="action-text"><?php echo esc_js(__('Regenerate', 'wp-licensing-manager')); ?></span>');
                        } else {
                            showMessage(data.error || 'Failed to regenerate license key', 'error');
                            button.prop('disabled', false).html('<span class="action-icon">🔄</span><span class="action-text"><?php echo esc_js(__('Regenerate', 'wp-licensing-manager')); ?></span>');
                        }
                    },
                    error: function() {
                        showMessage('Network error occurred', 'error');
                        button.prop('disabled', false).html('<span class="action-icon">🔄</span><span class="action-text"><?php echo esc_js(__('Regenerate', 'wp-licensing-manager')); ?></span>');
                    }
                });
            });
            
            // View License Details
            $(document).on('click', '.view-details-btn', function(e) {
                e.preventDefault();
                var button = $(this);
                var licenseId = button.data('license-id');
                
                button.prop('disabled', true).html('<span class="action-icon">⏳</span><span class="action-text"><?php echo esc_js(__('Loading...', 'wp-licensing-manager')); ?></span>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wc_get_license_details',
                        license_id: licenseId,
                        nonce: '<?php echo wp_create_nonce('wp_licensing_manager_customer_actions'); ?>'
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            showLicenseDetailsModal(data.license, data.product, data.activations);
                        } else {
                            showMessage(data.error || 'Failed to load license details', 'error');
                        }
                        button.prop('disabled', false).html('<span class="action-icon">👁️</span><span class="action-text"><?php echo esc_js(__('Details', 'wp-licensing-manager')); ?></span>');
                    },
                    error: function() {
                        showMessage('Network error occurred', 'error');
                        button.prop('disabled', false).html('<span class="action-icon">👁️</span><span class="action-text"><?php echo esc_js(__('Details', 'wp-licensing-manager')); ?></span>');
                    }
                });
            });
            
            // Show message function
            function showMessage(message, type) {
                var messageDiv = $('<div class="license-message ' + type + '">' + message + '</div>');
                $('.woocommerce-MyAccount-content').prepend(messageDiv);
                setTimeout(function() {
                    messageDiv.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Show license details modal
            function showLicenseDetailsModal(license, product, activations) {
                var modal = $('<div class="license-details-modal-overlay">');
                var modalContent = $('<div class="license-details-modal">');
                
                modalContent.html(
                    '<div class="modal-header">' +
                    '<h3><?php echo esc_js(__('License Details', 'wp-licensing-manager')); ?></h3>' +
                    '<button class="modal-close">×</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                    '<p><strong><?php echo esc_js(__('Product:', 'wp-licensing-manager')); ?></strong> ' + (product ? product.name : 'Unknown') + '</p>' +
                    '<p><strong><?php echo esc_js(__('License Key:', 'wp-licensing-manager')); ?></strong> <code>' + license.license_key + '</code></p>' +
                    '<p><strong><?php echo esc_js(__('Status:', 'wp-licensing-manager')); ?></strong> ' + license.status + '</p>' +
                    '<p><strong><?php echo esc_js(__('Created:', 'wp-licensing-manager')); ?></strong> ' + license.created_at + '</p>' +
                    '<p><strong><?php echo esc_js(__('Expires:', 'wp-licensing-manager')); ?></strong> ' + (license.expires_at && license.expires_at !== '0000-00-00' ? license.expires_at : '<?php echo esc_js(__('Never', 'wp-licensing-manager')); ?>') + '</p>' +
                    '<p><strong><?php echo esc_js(__('Activations:', 'wp-licensing-manager')); ?></strong> ' + license.activations + '/' + license.max_activations + '</p>' +
                    '</div>'
                );
                
                modal.append(modalContent);
                $('body').append(modal);
                
                // Close modal functionality
                modal.on('click', function(e) {
                    if (e.target === modal[0] || $(e.target).hasClass('modal-close')) {
                        modal.fadeOut(300, function() {
                            modal.remove();
                        });
                    }
                });
            }
            
            // Real-time countdown updates
            function updateCountdowns() {
                $('.countdown-timer').each(function() {
                    var element = $(this);
                    var expiryDate = element.data('expiry');
                    if (!expiryDate) return;
                    
                    var now = new Date().getTime();
                    var expiry = new Date(expiryDate).getTime();
                    var timeLeft = expiry - now;
                    
                    if (timeLeft > 0) {
                        var days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                        var countdownElement = element.find('.countdown-warning, .countdown-normal');
                        
                        if (days <= 30) {
                            countdownElement.removeClass('countdown-normal').addClass('countdown-warning');
                        }
                        
                        var text = days > 1 ? days + ' <?php echo esc_js(__('days remaining', 'wp-licensing-manager')); ?>' : days + ' <?php echo esc_js(__('day remaining', 'wp-licensing-manager')); ?>';
                        countdownElement.text(text);
                    } else {
                        element.html('<span class="countdown-expired"><?php echo esc_js(__('Expired', 'wp-licensing-manager')); ?></span>');
                    }
                });
            }
            
            // Update countdowns every minute
            updateCountdowns();
            setInterval(updateCountdowns, 60000);
            
            // Legacy copy button support
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
        
        /* Theme Compatibility - Gentle Enhancement Only */
        
        /* Style navigation without breaking layout */
        body.woocommerce-account .woocommerce-MyAccount-navigation,
        .woocommerce-account .woocommerce-MyAccount-navigation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            padding: 0 !important;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3) !important;
            margin-bottom: 30px !important;
            overflow: hidden !important;
            /* Keep original WooCommerce layout properties */
        }
        
        /* Style table without breaking layout */
        body.woocommerce-account .woocommerce table.shop_table_responsive,
        .woocommerce-account .woocommerce table.shop_table_responsive,
        body.woocommerce-account .shop_table_responsive,
        .woocommerce-account .shop_table_responsive {
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e8ecef !important;
            /* Keep original table display and width properties */
        }
        
        /* Fallback for very aggressive themes */
        .wp-licensing-dashboard-override {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
            line-height: 1.5 !important;
        }
        
        /* Additional theme enhancement classes - Layout safe */
        .wp-licensing-nav-override {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            padding: 0 !important;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3) !important;
            overflow: hidden !important;
            /* Preserve original layout properties */
        }
        
        .wp-licensing-content-override {
            background: white !important;
            border-radius: 12px !important;
            padding: 30px !important;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #f0f2f5 !important;
            /* Preserve original layout properties */
        }
        
        .wp-licensing-table-override {
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e8ecef !important;
            /* Preserve original width and display properties */
        }
        
        /* Mobile compatibility enhancements */
        .mobile-compact .woocommerce-MyAccount-navigation a {
            padding: 12px 16px !important;
            font-size: 13px !important;
        }
        
        /* Gentle fallback - preserve WooCommerce layout */
        html body.woocommerce-account div.woocommerce-MyAccount-navigation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            /* Only style, don't change layout */
        }
        
        html body.woocommerce-account div.woocommerce-MyAccount-content {
            background: white !important;
            border-radius: 12px !important;
            /* Only style, don't change layout */
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

    /**
     * AJAX handler for deactivating license from a domain
     */
    public function ajax_deactivate_license_domain() {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'wp_licensing_manager_customer_actions') || !is_user_logged_in()) {
            wp_die(json_encode(array('success' => false, 'error' => 'Unauthorized')));
        }

        $license_id = absint($_POST['license_id']);
        $domain = sanitize_text_field($_POST['domain']);
        $current_user = wp_get_current_user();

        // Verify the license belongs to the current user
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $license = $license_manager->get_license($license_id);

        if (!$license || $license->customer_email !== $current_user->user_email) {
            wp_die(json_encode(array('success' => false, 'error' => 'License not found or access denied')));
        }

        // Use activation manager to deactivate
        $activation_manager = new WP_Licensing_Manager_Activation_Manager();
        $result = $activation_manager->deactivate_license($license->license_key, $domain);

        wp_die(json_encode($result));
    }

    /**
     * AJAX handler for regenerating license key
     */
    public function ajax_regenerate_license_key() {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'wp_licensing_manager_customer_actions') || !is_user_logged_in()) {
            wp_die(json_encode(array('success' => false, 'error' => 'Unauthorized')));
        }

        $license_id = absint($_POST['license_id']);
        $current_user = wp_get_current_user();

        // Verify the license belongs to the current user
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $license = $license_manager->get_license($license_id);

        if (!$license || $license->customer_email !== $current_user->user_email) {
            wp_die(json_encode(array('success' => false, 'error' => 'License not found or access denied')));
        }

        // Generate new license key
        $new_license_key = wp_licensing_manager_generate_license_key();
        $result = $license_manager->update_license($license_id, array('license_key' => $new_license_key));

        if ($result) {
            wp_die(json_encode(array(
                'success' => true,
                'new_license_key' => $new_license_key,
                'message' => 'License key regenerated successfully'
            )));
        } else {
            wp_die(json_encode(array('success' => false, 'error' => 'Failed to regenerate license key')));
        }
    }

    /**
     * AJAX handler for getting license details
     */
    public function ajax_get_license_details() {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'wp_licensing_manager_customer_actions') || !is_user_logged_in()) {
            wp_die(json_encode(array('success' => false, 'error' => 'Unauthorized')));
        }

        $license_id = absint($_POST['license_id']);
        $current_user = wp_get_current_user();

        // Verify the license belongs to the current user
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $license = $license_manager->get_license($license_id);

        if (!$license || $license->customer_email !== $current_user->user_email) {
            wp_die(json_encode(array('success' => false, 'error' => 'License not found or access denied')));
        }

        // Get detailed license information
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product($license->product_id);

        // Get activation details
        $activation_manager = new WP_Licensing_Manager_Activation_Manager();
        $activations = $activation_manager->get_activations_by_license($license_id);

        wp_die(json_encode(array(
            'success' => true,
            'license' => $license,
            'product' => $product,
            'activations' => $activations
        )));
    }
}

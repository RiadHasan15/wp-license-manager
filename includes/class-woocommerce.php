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

        // Customer account hooks
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_licenses_tab'));
        add_action('woocommerce_account_my-licenses_endpoint', array($this, 'my_licenses_content'));
        add_action('init', array($this, 'add_my_licenses_endpoint'));
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
     * Add My Licenses tab to customer account
     *
     * @param array $items
     * @return array
     */
    public function add_my_licenses_tab($items) {
        $items['my-licenses'] = __('My Licenses', 'wp-licensing-manager');
        return $items;
    }

    /**
     * Add My Licenses endpoint
     */
    public function add_my_licenses_endpoint() {
        add_rewrite_endpoint('my-licenses', EP_ROOT | EP_PAGES);
    }

    /**
     * My Licenses tab content
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
        </style>
        <?php
    }
}

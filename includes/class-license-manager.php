<?php
/**
 * License Manager class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_License_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        // No hooks needed in constructor
    }

    /**
     * Create a new license
     *
     * @param array $args
     * @return int|false License ID on success, false on failure
     */
    public function create_license($args) {
        global $wpdb;

        $defaults = array(
            'product_id' => 0,
            'license_key' => wp_licensing_manager_generate_license_key(),
            'status' => 'active',
            'expires_at' => wp_licensing_manager_get_expiry_date(),
            'max_activations' => get_option('wp_licensing_manager_default_max_activations', 1),
            'activations' => 0,
            'domains' => '',
            'customer_email' => '',
            'order_id' => null
        );

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($args['product_id']) || empty($args['customer_email'])) {
            return false;
        }

        // Sanitize inputs
        $args['product_id'] = absint($args['product_id']);
        $args['license_key'] = wp_licensing_manager_sanitize_license_key($args['license_key']);
        $args['status'] = sanitize_text_field($args['status']);
        $args['expires_at'] = sanitize_text_field($args['expires_at']);
        $args['max_activations'] = absint($args['max_activations']);
        $args['activations'] = absint($args['activations']);
        $args['domains'] = sanitize_textarea_field($args['domains']);
        $args['customer_email'] = sanitize_email($args['customer_email']);
        $args['order_id'] = !empty($args['order_id']) ? absint($args['order_id']) : null;

        // Insert license
        $result = $wpdb->insert(
            $wpdb->prefix . 'licenses',
            array(
                'product_id' => $args['product_id'],
                'license_key' => $args['license_key'],
                'status' => $args['status'],
                'expires_at' => $args['expires_at'],
                'max_activations' => $args['max_activations'],
                'activations' => $args['activations'],
                'domains' => $args['domains'],
                'customer_email' => $args['customer_email'],
                'order_id' => $args['order_id']
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get license by ID
     *
     * @param int $license_id
     * @return object|null
     */
    public function get_license($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}licenses WHERE id = %d",
                $license_id
            )
        );
    }

    /**
     * Get license by key
     *
     * @param string $license_key
     * @return object|null
     */
    public function get_license_by_key($license_key) {
        global $wpdb;

        $license_key = wp_licensing_manager_sanitize_license_key($license_key);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}licenses WHERE license_key = %s",
                $license_key
            )
        );
    }

    /**
     * Update license
     *
     * @param int $license_id
     * @param array $args
     * @return bool
     */
    public function update_license($license_id, $args) {
        global $wpdb;

        $license_id = absint($license_id);

        // Sanitize update data
        $update_data = array();
        $update_format = array();

        if (isset($args['status'])) {
            $update_data['status'] = sanitize_text_field($args['status']);
            $update_format[] = '%s';
        }

        if (isset($args['expires_at'])) {
            $update_data['expires_at'] = sanitize_text_field($args['expires_at']);
            $update_format[] = '%s';
        }

        if (isset($args['max_activations'])) {
            $update_data['max_activations'] = absint($args['max_activations']);
            $update_format[] = '%d';
        }

        if (isset($args['activations'])) {
            $update_data['activations'] = absint($args['activations']);
            $update_format[] = '%d';
        }

        if (isset($args['domains'])) {
            $update_data['domains'] = sanitize_textarea_field($args['domains']);
            $update_format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'licenses',
            $update_data,
            array('id' => $license_id),
            $update_format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete license
     *
     * @param int $license_id
     * @return bool
     */
    public function delete_license($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        // Delete related activations first
        $wpdb->delete(
            $wpdb->prefix . 'license_activations',
            array('license_id' => $license_id),
            array('%d')
        );

        // Delete license
        $result = $wpdb->delete(
            $wpdb->prefix . 'licenses',
            array('id' => $license_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Validate license
     *
     * @param string $license_key
     * @param int $product_id
     * @return array
     */
    public function validate_license($license_key, $product_id = null) {
        $license = $this->get_license_by_key($license_key);

        if (!$license) {
            return array(
                'valid' => false,
                'error' => 'License key not found'
            );
        }

        // Check product match if specified
        if ($product_id && $license->product_id != $product_id) {
            return array(
                'valid' => false,
                'error' => 'License key not valid for this product'
            );
        }

        // Check if license is active
        if ($license->status !== 'active') {
            return array(
                'valid' => false,
                'error' => 'License is not active'
            );
        }

        // Check expiration
        if (wp_licensing_manager_is_license_expired($license->expires_at)) {
            // Update status to expired
            $this->update_license($license->id, array('status' => 'expired'));
            
            return array(
                'valid' => false,
                'error' => 'License has expired'
            );
        }

        return array(
            'valid' => true,
            'license' => $license
        );
    }

    /**
     * Get licenses with pagination and search
     *
     * @param array $args
     * @return array
     */
    public function get_licenses($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => '',
            'product_id' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $where_values = array();

        // Search
        if (!empty($args['search'])) {
            $where[] = "(license_key LIKE %s OR customer_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        // Status filter
        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $where_values[] = $args['status'];
        }

        // Product filter
        if (!empty($args['product_id'])) {
            $where[] = "product_id = %d";
            $where_values[] = $args['product_id'];
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE $where_clause";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);

        // Get licenses
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = $args['per_page'];

        $query = "SELECT * FROM {$wpdb->prefix}licenses WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($limit, $offset));
        
        $licenses = $wpdb->get_results($wpdb->prepare($query, $query_values));

        return array(
            'licenses' => $licenses,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Get licenses by customer email
     *
     * @param string $email
     * @return array
     */
    public function get_customer_licenses($email) {
        global $wpdb;

        $email = sanitize_email($email);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, p.name as product_name, p.slug as product_slug 
                 FROM {$wpdb->prefix}licenses l 
                 LEFT JOIN {$wpdb->prefix}license_products p ON l.product_id = p.id 
                 WHERE l.customer_email = %s 
                 ORDER BY l.created_at DESC",
                $email
            )
        );
    }

    /**
     * Increment activation count
     *
     * @param int $license_id
     * @return bool
     */
    public function increment_activations($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}licenses SET activations = activations + 1 WHERE id = %d",
                $license_id
            )
        );

        return $result !== false;
    }

    /**
     * Decrement activation count
     *
     * @param int $license_id
     * @return bool
     */
    public function decrement_activations($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}licenses SET activations = GREATEST(0, activations - 1) WHERE id = %d",
                $license_id
            )
        );

        return $result !== false;
    }
}

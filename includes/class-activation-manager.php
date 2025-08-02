<?php
/**
 * Activation Manager class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_Activation_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        // No hooks needed in constructor
    }

    /**
     * Activate license for a domain
     *
     * @param string $license_key
     * @param string $domain
     * @param int $product_id
     * @return array
     */
    public function activate_license($license_key, $domain, $product_id = null) {
        global $wpdb;

        // Validate and get license
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $validation = $license_manager->validate_license($license_key, $product_id);

        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => $validation['error']
            );
        }

        $license = $validation['license'];

        // Sanitize domain
        $domain = wp_licensing_manager_sanitize_domain($domain);

        // Check if already activated on this domain
        $existing_activation = $this->get_activation($license->id, $domain);
        if ($existing_activation) {
            return array(
                'success' => true,
                'message' => 'License already activated on this domain'
            );
        }

        // Check activation limit
        if ($license->activations >= $license->max_activations) {
            return array(
                'success' => false,
                'error' => 'Maximum activations reached'
            );
        }

        // Create activation record
        $activation_id = $this->create_activation(array(
            'license_id' => $license->id,
            'product_id' => $license->product_id,
            'domain' => $domain,
            'ip_address' => wp_licensing_manager_get_client_ip()
        ));

        if (!$activation_id) {
            return array(
                'success' => false,
                'error' => 'Failed to create activation record'
            );
        }

        // Update license activation count and domains
        $license_manager->increment_activations($license->id);
        $this->update_license_domains($license->id);

        return array(
            'success' => true,
            'message' => 'License activated successfully',
            'activation_id' => $activation_id
        );
    }

    /**
     * Deactivate license for a domain
     *
     * @param string $license_key
     * @param string $domain
     * @param int $product_id
     * @return array
     */
    public function deactivate_license($license_key, $domain, $product_id = null) {
        global $wpdb;

        // Get license
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $license = $license_manager->get_license_by_key($license_key);

        if (!$license) {
            return array(
                'success' => false,
                'error' => 'License key not found'
            );
        }

        // Check product match if specified
        if ($product_id && $license->product_id != $product_id) {
            return array(
                'success' => false,
                'error' => 'License key not valid for this product'
            );
        }

        // Sanitize domain
        $domain = wp_licensing_manager_sanitize_domain($domain);

        // Find activation
        $activation = $this->get_activation($license->id, $domain);
        if (!$activation) {
            return array(
                'success' => false,
                'error' => 'License not activated on this domain'
            );
        }

        // Delete activation record
        $result = $wpdb->delete(
            $wpdb->prefix . 'license_activations',
            array('id' => $activation->id),
            array('%d')
        );

        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Failed to remove activation'
            );
        }

        // Update license activation count and domains
        $license_manager->decrement_activations($license->id);
        $this->update_license_domains($license->id);

        return array(
            'success' => true,
            'message' => 'License deactivated successfully'
        );
    }

    /**
     * Create activation record
     *
     * @param array $args
     * @return int|false
     */
    public function create_activation($args) {
        global $wpdb;

        $defaults = array(
            'license_id' => 0,
            'product_id' => 0,
            'domain' => '',
            'ip_address' => ''
        );

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($args['license_id']) || empty($args['product_id']) || empty($args['domain'])) {
            return false;
        }

        // Sanitize inputs
        $args['license_id'] = absint($args['license_id']);
        $args['product_id'] = absint($args['product_id']);
        $args['domain'] = wp_licensing_manager_sanitize_domain($args['domain']);
        $args['ip_address'] = sanitize_text_field($args['ip_address']);

        // Insert activation
        $result = $wpdb->insert(
            $wpdb->prefix . 'license_activations',
            array(
                'license_id' => $args['license_id'],
                'product_id' => $args['product_id'],
                'domain' => $args['domain'],
                'ip_address' => $args['ip_address']
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get activation by license and domain
     *
     * @param int $license_id
     * @param string $domain
     * @return object|null
     */
    public function get_activation($license_id, $domain) {
        global $wpdb;

        $license_id = absint($license_id);
        $domain = wp_licensing_manager_sanitize_domain($domain);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}license_activations WHERE license_id = %d AND domain = %s",
                $license_id,
                $domain
            )
        );
    }

    /**
     * Get all activations for a license
     *
     * @param int $license_id
     * @return array
     */
    public function get_license_activations($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}license_activations WHERE license_id = %d ORDER BY activated_at DESC",
                $license_id
            )
        );
    }

    /**
     * Update license domains field
     *
     * @param int $license_id
     * @return bool
     */
    private function update_license_domains($license_id) {
        global $wpdb;

        $license_id = absint($license_id);

        // Get all domains for this license
        $domains = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT domain FROM {$wpdb->prefix}license_activations WHERE license_id = %d",
                $license_id
            )
        );

        $domains_string = implode(',', $domains);

        // Update license
        $result = $wpdb->update(
            $wpdb->prefix . 'licenses',
            array('domains' => $domains_string),
            array('id' => $license_id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get activations with pagination
     *
     * @param array $args
     * @return array
     */
    public function get_activations($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'license_id' => 0,
            'product_id' => 0,
            'domain' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $where_values = array();

        // License filter
        if (!empty($args['license_id'])) {
            $where[] = "license_id = %d";
            $where_values[] = $args['license_id'];
        }

        // Product filter
        if (!empty($args['product_id'])) {
            $where[] = "product_id = %d";
            $where_values[] = $args['product_id'];
        }

        // Domain filter
        if (!empty($args['domain'])) {
            $where[] = "domain LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($args['domain']) . '%';
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}license_activations WHERE $where_clause";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);

        // Get activations
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = $args['per_page'];

        $query = "SELECT a.*, l.license_key, p.name as product_name 
                  FROM {$wpdb->prefix}license_activations a 
                  LEFT JOIN {$wpdb->prefix}licenses l ON a.license_id = l.id 
                  LEFT JOIN {$wpdb->prefix}license_products p ON a.product_id = p.id 
                  WHERE $where_clause 
                  ORDER BY a.activated_at DESC 
                  LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($limit, $offset));
        
        $activations = $wpdb->get_results($wpdb->prepare($query, $query_values));

        return array(
            'activations' => $activations,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Get activation statistics
     *
     * @return array
     */
    public function get_activation_stats() {
        global $wpdb;

        $stats = array();

        // Total activations
        $stats['total_activations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}license_activations"
        );

        // Activations today
        $stats['activations_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}license_activations WHERE DATE(activated_at) = CURDATE()"
        );

        // Most active domains
        $stats['top_domains'] = $wpdb->get_results(
            "SELECT domain, COUNT(*) as activation_count 
             FROM {$wpdb->prefix}license_activations 
             GROUP BY domain 
             ORDER BY activation_count DESC 
             LIMIT 10"
        );

        return $stats;
    }
}

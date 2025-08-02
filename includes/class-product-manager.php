<?php
/**
 * Product Manager class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_Product_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        // No hooks needed in constructor
    }

    /**
     * Create a new product
     *
     * @param array $args
     * @return int|false Product ID on success, false on failure
     */
    public function create_product($args) {
        global $wpdb;

        $defaults = array(
            'slug' => '',
            'name' => '',
            'latest_version' => '1.0.0',
            'changelog' => '',
            'update_file_path' => ''
        );

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($args['slug']) || empty($args['name'])) {
            return false;
        }

        // Sanitize inputs
        $args['slug'] = sanitize_title($args['slug']);
        $args['name'] = sanitize_text_field($args['name']);
        $args['latest_version'] = sanitize_text_field($args['latest_version']);
        $args['changelog'] = wp_kses_post($args['changelog']);
        $args['update_file_path'] = sanitize_text_field($args['update_file_path']);

        // Ensure unique slug
        $args['slug'] = $this->generate_unique_slug($args['slug']);

        // Insert product
        $result = $wpdb->insert(
            $wpdb->prefix . 'license_products',
            array(
                'slug' => $args['slug'],
                'name' => $args['name'],
                'latest_version' => $args['latest_version'],
                'changelog' => $args['changelog'],
                'update_file_path' => $args['update_file_path']
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Generate unique slug
     *
     * @param string $slug
     * @return string
     */
    private function generate_unique_slug($slug) {
        $original_slug = $slug;
        $counter = 1;
        
        // Keep checking until we find a unique slug
        while ($this->get_product_by_slug($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 100) {
                $slug = $original_slug . '-' . time();
                break;
            }
        }
        
        return $slug;
    }

    /**
     * Get product by ID
     *
     * @param int $product_id
     * @return object|null
     */
    public function get_product($product_id) {
        global $wpdb;

        $product_id = absint($product_id);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}license_products WHERE id = %d",
                $product_id
            )
        );
    }

    /**
     * Get product by slug
     *
     * @param string $slug
     * @return object|null
     */
    public function get_product_by_slug($slug) {
        global $wpdb;

        $slug = sanitize_title($slug);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}license_products WHERE slug = %s",
                $slug
            )
        );
    }

    /**
     * Update product
     *
     * @param int $product_id
     * @param array $args
     * @return bool
     */
    public function update_product($product_id, $args) {
        global $wpdb;

        $product_id = absint($product_id);

        // Sanitize update data
        $update_data = array();
        $update_format = array();

        if (isset($args['name'])) {
            $update_data['name'] = sanitize_text_field($args['name']);
            $update_format[] = '%s';
        }

        if (isset($args['latest_version'])) {
            $update_data['latest_version'] = sanitize_text_field($args['latest_version']);
            $update_format[] = '%s';
        }

        if (isset($args['changelog'])) {
            $update_data['changelog'] = wp_kses_post($args['changelog']);
            $update_format[] = '%s';
        }

        if (isset($args['update_file_path'])) {
            $update_data['update_file_path'] = sanitize_text_field($args['update_file_path']);
            $update_format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        // Always update the updated_at timestamp
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';

        $result = $wpdb->update(
            $wpdb->prefix . 'license_products',
            $update_data,
            array('id' => $product_id),
            $update_format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete product
     *
     * @param int $product_id
     * @return bool
     */
    public function delete_product($product_id) {
        global $wpdb;

        $product_id = absint($product_id);

        // Check if product has licenses
        $license_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE product_id = %d",
                $product_id
            )
        );

        if ($license_count > 0) {
            return false; // Cannot delete product with existing licenses
        }

        // Delete product
        $result = $wpdb->delete(
            $wpdb->prefix . 'license_products',
            array('id' => $product_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get all products
     *
     * @param array $args
     * @return array
     */
    public function get_products($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $where_values = array();

        // Search
        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR slug LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}license_products WHERE $where_clause";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);

        // Get products
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = $args['per_page'];

        $query = "SELECT * FROM {$wpdb->prefix}license_products WHERE $where_clause ORDER BY name ASC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($limit, $offset));
        
        $products = $wpdb->get_results($wpdb->prepare($query, $query_values));

        return array(
            'products' => $products,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Get products for select dropdown
     *
     * @return array
     */
    public function get_products_for_select() {
        global $wpdb;

        $products = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}license_products ORDER BY name ASC"
        );

        $options = array();
        foreach ($products as $product) {
            $options[$product->id] = $product->name;
        }

        return $options;
    }

    /**
     * Handle file upload for product updates
     *
     * @param array $file $_FILES array element
     * @param string $product_slug
     * @return string|false File path on success, false on failure
     */
    public function handle_update_file_upload($file, $product_slug) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return false;
        }

        // Validate file type
        $allowed_types = array('application/zip', 'application/x-zip-compressed');
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }

        // Validate file extension
        $file_info = pathinfo($file['name']);
        if (strtolower($file_info['extension']) !== 'zip') {
            return false;
        }

        // Create upload directory
        $upload_dir = wp_licensing_manager_get_updates_dir();
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        // Generate file name
        $file_name = sanitize_file_name($product_slug . '.zip');
        $file_path = $upload_dir . '/' . $file_name;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return $file_name;
        }

        return false;
    }

    /**
     * Get product statistics
     *
     * @param int $product_id
     * @return array
     */
    public function get_product_stats($product_id) {
        global $wpdb;

        $product_id = absint($product_id);

        $stats = array();

        // Total licenses
        $stats['total_licenses'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE product_id = %d",
                $product_id
            )
        );

        // Active licenses
        $stats['active_licenses'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE product_id = %d AND status = 'active'",
                $product_id
            )
        );

        // Total activations
        $stats['total_activations'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}license_activations WHERE product_id = %d",
                $product_id
            )
        );

        return $stats;
    }

    /**
     * Clear update caches for a product
     * Call this when a product version is updated to ensure clients get the new version
     *
     * @param string $product_slug
     */
    public function clear_update_caches($product_slug) {
        // Clear WordPress update caches
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');
        
        // Clear any custom caches for this product
        $cache_patterns = array(
            $product_slug . '_remote_version',
            $product_slug . '_changelog',
            $product_slug . '_update_info'
        );
        
        foreach ($cache_patterns as $pattern) {
            delete_transient($pattern);
            delete_site_transient($pattern);
        }
        
        // Fire action hook for other plugins to clear their caches
        do_action('wp_licensing_manager_product_updated', $product_slug);
    }
}

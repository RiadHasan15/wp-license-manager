<?php
/**
 * REST API class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Debug: Log that route registration is being called
        if (function_exists('error_log')) {
            error_log('WP Licensing Manager: Registering REST API routes');
        }
        
        $namespace = 'licensing/v1';

        // Validate endpoint
        register_rest_route($namespace, '/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_license'),
            'permission_callback' => '__return_true',
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_license_key'
                ),
                'product_slug' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Activate endpoint
        register_rest_route($namespace, '/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_license'),
            'permission_callback' => '__return_true',
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_license_key'
                ),
                'domain' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_domain'
                ),
                'product_slug' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Deactivate endpoint
        register_rest_route($namespace, '/deactivate', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_license'),
            'permission_callback' => '__return_true',
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_license_key'
                ),
                'domain' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_domain'
                ),
                'product_slug' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Update check endpoint
        register_rest_route($namespace, '/update-check', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_for_updates'),
            'permission_callback' => '__return_true',
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_license_key'
                ),
                'product_slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'current_version' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Update download endpoint
        register_rest_route($namespace, '/update-download', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_update'),
            'permission_callback' => '__return_true',
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_licensing_manager_sanitize_license_key'
                ),
                'product_slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Stats endpoint (admin only)
        register_rest_route($namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // API status endpoint (for testing)
        register_rest_route($namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_status'),
            'permission_callback' => '__return_true'
        ));
        
        // Debug: Log successful route registration
        if (function_exists('error_log')) {
            error_log('WP Licensing Manager: All REST API routes registered successfully');
        }
    }

    /**
     * Validate license endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function validate_license($request) {
        // Enforce HTTPS (localhost allowed for testing)
        if (!wp_licensing_manager_is_https()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'HTTPS required for security',
                'note' => 'Localhost and local IP addresses (127.0.0.1, 192.168.x.x, etc.) are allowed for testing'
            ), 400);
        }

        $license_key = $request->get_param('license_key');
        $product_slug = $request->get_param('product_slug');

        $product_id = null;
        if ($product_slug) {
            $product_manager = new WP_Licensing_Manager_Product_Manager();
            $product = $product_manager->get_product_by_slug($product_slug);
            if (!$product) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Product not found'
                ), 404);
            }
            $product_id = $product->id;
        }

        $license_manager = new WP_Licensing_Manager_License_Manager();
        $validation = $license_manager->validate_license($license_key, $product_id);

        if ($validation['valid']) {
            return new WP_REST_Response(array(
                'success' => true,
                'license' => array(
                    'status' => $validation['license']->status,
                    'expires_at' => $validation['license']->expires_at,
                    'max_activations' => $validation['license']->max_activations,
                    'activations' => $validation['license']->activations
                )
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $validation['error']
            ), 400);
        }
    }

    /**
     * Activate license endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function activate_license($request) {
        // Enforce HTTPS (localhost allowed for testing)
        if (!wp_licensing_manager_is_https()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'HTTPS required for security',
                'note' => 'Localhost and local IP addresses are allowed for testing'
            ), 400);
        }

        $license_key = $request->get_param('license_key');
        $domain = $request->get_param('domain');
        $product_slug = $request->get_param('product_slug');

        $product_id = null;
        if ($product_slug) {
            $product_manager = new WP_Licensing_Manager_Product_Manager();
            $product = $product_manager->get_product_by_slug($product_slug);
            if (!$product) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Product not found'
                ), 404);
            }
            $product_id = $product->id;
        }

        $activation_manager = new WP_Licensing_Manager_Activation_Manager();
        $result = $activation_manager->activate_license($license_key, $domain, $product_id);

        $status_code = $result['success'] ? 200 : 400;
        return new WP_REST_Response($result, $status_code);
    }

    /**
     * Deactivate license endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deactivate_license($request) {
        // Enforce HTTPS (localhost allowed for testing)
        if (!wp_licensing_manager_is_https()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'HTTPS required for security',
                'note' => 'Localhost and local IP addresses are allowed for testing'
            ), 400);
        }

        $license_key = $request->get_param('license_key');
        $domain = $request->get_param('domain');
        $product_slug = $request->get_param('product_slug');

        $product_id = null;
        if ($product_slug) {
            $product_manager = new WP_Licensing_Manager_Product_Manager();
            $product = $product_manager->get_product_by_slug($product_slug);
            if (!$product) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Product not found'
                ), 404);
            }
            $product_id = $product->id;
        }

        $activation_manager = new WP_Licensing_Manager_Activation_Manager();
        $result = $activation_manager->deactivate_license($license_key, $domain, $product_id);

        $status_code = $result['success'] ? 200 : 400;
        return new WP_REST_Response($result, $status_code);
    }

    /**
     * Check for updates endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function check_for_updates($request) {
        // Enforce HTTPS (localhost allowed for testing)
        if (!wp_licensing_manager_is_https()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'HTTPS required for security',
                'note' => 'Localhost and local IP addresses are allowed for testing'
            ), 400);
        }

        $license_key = $request->get_param('license_key');
        $product_slug = $request->get_param('product_slug');
        $current_version = $request->get_param('current_version');

        // Get product
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product_by_slug($product_slug);
        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Product not found'
            ), 404);
        }

        // Validate license
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $validation = $license_manager->validate_license($license_key, $product->id);

        if (!$validation['valid']) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $validation['error']
            ), 400);
        }

        // Check if update is available
        $has_update = version_compare($product->latest_version, $current_version, '>');

        $response = array(
            'success' => true,
            'has_update' => $has_update,
            'latest_version' => $product->latest_version
        );

        if ($has_update) {
            $response['changelog'] = $product->changelog;
            $response['download_url'] = rest_url('licensing/v1/update-download') . 
                '?license_key=' . urlencode($license_key) . 
                '&product_slug=' . urlencode($product_slug);
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Download update endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function download_update($request) {
        // Enforce HTTPS (localhost allowed for testing)
        if (!wp_licensing_manager_is_https()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'HTTPS required for security',
                'note' => 'Localhost and local IP addresses are allowed for testing'
            ), 400);
        }

        $license_key = $request->get_param('license_key');
        $product_slug = $request->get_param('product_slug');

        // Get product
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product_by_slug($product_slug);
        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Product not found'
            ), 404);
        }

        // Validate license
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $validation = $license_manager->validate_license($license_key, $product->id);

        if (!$validation['valid']) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $validation['error']
            ), 400);
        }

        // Check if update file exists
        $updates_dir = wp_licensing_manager_get_updates_dir();
        $file_path = $updates_dir . '/' . $product->update_file_path;

        if (!file_exists($file_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Update file not found'
            ), 404);
        }

        // Serve file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        
        readfile($file_path);
        exit;
    }

    /**
     * Get stats endpoint (admin only)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_stats($request) {
        $activation_manager = new WP_Licensing_Manager_Activation_Manager();
        $stats = $activation_manager->get_activation_stats();

        return new WP_REST_Response(array(
            'success' => true,
            'stats' => $stats
        ), 200);
    }

    /**
     * Check admin permissions
     *
     * @return bool
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * API status endpoint for testing
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_status($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'WP Licensing Manager API is working correctly',
            'version' => '1.0.0',
            'endpoints' => array(
                'POST /licensing/v1/validate' => 'Validate a license key',
                'POST /licensing/v1/activate' => 'Activate a license on a domain',
                'POST /licensing/v1/deactivate' => 'Deactivate a license from a domain',
                'POST /licensing/v1/update-check' => 'Check for product updates',
                'GET /licensing/v1/update-download' => 'Download product updates',
                'GET /licensing/v1/stats' => 'Get licensing statistics (admin only)',
                'GET /licensing/v1/status' => 'API status (this endpoint)'
            ),
            'timestamp' => current_time('c')
        ), 200);
    }
}

<?php
/**
 * Updates handling class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_Updates {

    /**
     * Constructor
     */
    public function __construct() {
        // No hooks needed in constructor
    }

    /**
     * Get update information for a product
     *
     * @param string $product_slug
     * @param string $license_key
     * @param string $current_version
     * @return array
     */
    public function get_update_info($product_slug, $license_key, $current_version) {
        // Get product
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product_by_slug($product_slug);
        
        if (!$product) {
            return array(
                'has_update' => false,
                'error' => 'Product not found'
            );
        }

        // Validate license
        $license_manager = new WP_Licensing_Manager_License_Manager();
        $validation = $license_manager->validate_license($license_key, $product->id);

        if (!$validation['valid']) {
            return array(
                'has_update' => false,
                'error' => $validation['error']
            );
        }

        // Check if update is available
        $has_update = version_compare($product->latest_version, $current_version, '>');

        $update_info = array(
            'has_update' => $has_update,
            'latest_version' => $product->latest_version,
            'current_version' => $current_version
        );

        if ($has_update) {
            $update_info['changelog'] = $product->changelog;
            $update_info['download_url'] = $this->get_download_url($product_slug, $license_key);
        }

        return $update_info;
    }

    /**
     * Get download URL for a product update
     *
     * @param string $product_slug
     * @param string $license_key
     * @return string
     */
    public function get_download_url($product_slug, $license_key) {
        return rest_url('licensing/v1/update-download') . 
            '?license_key=' . urlencode($license_key) . 
            '&product_slug=' . urlencode($product_slug);
    }

    /**
     * Generate integration code snippet for premium plugins
     *
     * @param string $product_slug
     * @return string
     */
    public function generate_integration_code($product_slug) {
        $base_url = home_url();
        
        $code = '<?php
/**
 * License and Update Integration for ' . esc_html($product_slug) . '
 * 
 * Add this code to your premium plugin to enable licensing and automatic updates.
 */

// Prevent direct access
if (!defined(\'ABSPATH\')) {
    exit;
}

class ' . ucwords(str_replace('-', '_', $product_slug)) . '_License_Manager {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $license_server_url;
    private $license_key;
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = \'' . esc_js($product_slug) . '\';
        $this->version = $version;
        $this->license_server_url = \'' . esc_url($base_url) . '\';
        $this->license_key = get_option($this->plugin_slug . \'_license_key\');
        
        add_action(\'admin_init\', array($this, \'init\'));
        add_action(\'admin_menu\', array($this, \'license_menu\'));
        add_filter(\'pre_set_site_transient_update_plugins\', array($this, \'check_for_update\'));
        add_filter(\'plugins_api\', array($this, \'plugins_api_filter\'), 10, 3);
    }
    
    public function init() {
        if (isset($_POST[\'activate_license\'])) {
            $this->activate_license();
        }
        
        if (isset($_POST[\'deactivate_license\'])) {
            $this->deactivate_license();
        }
    }
    
    public function license_menu() {
        add_options_page(
            \'' . esc_html($product_slug) . ' License\',
            \'' . esc_html($product_slug) . ' License\',
            \'manage_options\',
            $this->plugin_slug . \'-license\',
            array($this, \'license_page\')
        );
    }
    
    public function license_page() {
        $license_key = get_option($this->plugin_slug . \'_license_key\');
        $status = get_option($this->plugin_slug . \'_license_status\');
        
        ?>
        <div class="wrap">
            <h1>' . esc_html($product_slug) . ' License Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field(\'license_nonce\', \'license_nonce\'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">License Key</th>
                        <td>
                            <input type="text" name="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <?php if ($status == \'valid\'): ?>
                                <span style="color: green;">Active</span>
                                <input type="submit" name="deactivate_license" value="Deactivate License" class="button" />
                            <?php else: ?>
                                <span style="color: red;">Inactive</span>
                                <input type="submit" name="activate_license" value="Activate License" class="button-primary" />
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }
    
    public function activate_license() {
        if (!wp_verify_nonce($_POST[\'license_nonce\'], \'license_nonce\')) {
            return;
        }
        
        $license_key = sanitize_text_field($_POST[\'license_key\']);
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->license_server_url . \'/wp-json/licensing/v1/activate\', array(
            \'body\' => array(
                \'license_key\' => $license_key,
                \'domain\' => $domain,
                \'product_slug\' => $this->plugin_slug
            ),
            \'sslverify\' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body[\'success\']) {
                update_option($this->plugin_slug . \'_license_key\', $license_key);
                update_option($this->plugin_slug . \'_license_status\', \'valid\');
                add_action(\'admin_notices\', function() {
                    echo \'<div class="notice notice-success"><p>License activated successfully!</p></div>\';
                });
            } else {
                add_action(\'admin_notices\', function() use ($body) {
                    echo \'<div class="notice notice-error"><p>Error: \' . esc_html($body[\'error\']) . \'</p></div>\';
                });
            }
        }
    }
    
    public function deactivate_license() {
        if (!wp_verify_nonce($_POST[\'license_nonce\'], \'license_nonce\')) {
            return;
        }
        
        $license_key = get_option($this->plugin_slug . \'_license_key\');
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->license_server_url . \'/wp-json/licensing/v1/deactivate\', array(
            \'body\' => array(
                \'license_key\' => $license_key,
                \'domain\' => $domain,
                \'product_slug\' => $this->plugin_slug
            ),
            \'sslverify\' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body[\'success\']) {
                update_option($this->plugin_slug . \'_license_status\', \'invalid\');
                add_action(\'admin_notices\', function() {
                    echo \'<div class="notice notice-success"><p>License deactivated successfully!</p></div>\';
                });
            }
        }
    }
    
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_basename = plugin_basename($this->plugin_file);
        
        if (!isset($transient->checked[$plugin_basename])) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, \'<\')) {
            $transient->response[$plugin_basename] = (object) array(
                \'slug\' => dirname($plugin_basename),
                \'new_version\' => $remote_version,
                \'url\' => \'\',
                \'package\' => $this->get_download_url()
            );
        }
        
        return $transient;
    }
    
    public function plugins_api_filter($res, $action, $args) {
        if ($action != \'plugin_information\') {
            return $res;
        }
        
        if ($args->slug != dirname(plugin_basename($this->plugin_file))) {
            return $res;
        }
        
        $remote_version = $this->get_remote_version();
        
        return (object) array(
            \'name\' => \'' . esc_js($product_slug) . '\',
            \'slug\' => dirname(plugin_basename($this->plugin_file)),
            \'version\' => $remote_version,
            \'author\' => \'Your Name\',
            \'homepage\' => \'\',
            \'requires\' => \'5.0\',
            \'tested\' => \'6.3\',
            \'downloaded\' => 0,
            \'last_updated\' => date(\'Y-m-d\'),
            \'sections\' => array(
                \'description\' => \'Premium plugin description\',
                \'changelog\' => $this->get_changelog()
            ),
            \'download_link\' => $this->get_download_url()
        );
    }
    
    private function get_remote_version() {
        $license_key = get_option($this->plugin_slug . \'_license_key\');
        
        if (empty($license_key)) {
            return $this->version;
        }
        
        $response = wp_remote_post($this->license_server_url . \'/wp-json/licensing/v1/update-check\', array(
            \'body\' => array(
                \'license_key\' => $license_key,
                \'product_slug\' => $this->plugin_slug,
                \'current_version\' => $this->version
            ),
            \'sslverify\' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body[\'success\'] && $body[\'has_update\']) {
                return $body[\'latest_version\'];
            }
        }
        
        return $this->version;
    }
    
    private function get_changelog() {
        $license_key = get_option($this->plugin_slug . \'_license_key\');
        
        if (empty($license_key)) {
            return \'\';
        }
        
        $response = wp_remote_post($this->license_server_url . \'/wp-json/licensing/v1/update-check\', array(
            \'body\' => array(
                \'license_key\' => $license_key,
                \'product_slug\' => $this->plugin_slug,
                \'current_version\' => $this->version
            ),
            \'sslverify\' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body[\'success\'] && isset($body[\'changelog\'])) {
                return $body[\'changelog\'];
            }
        }
        
        return \'\';
    }
    
    private function get_download_url() {
        $license_key = get_option($this->plugin_slug . \'_license_key\');
        
        if (empty($license_key)) {
            return \'\';
        }
        
        return $this->license_server_url . \'/wp-json/licensing/v1/update-download?license_key=\' . urlencode($license_key) . \'&product_slug=\' . urlencode($this->plugin_slug);
    }
}

// Initialize the license manager
// Replace \'__FILE__\' with your main plugin file and \'1.0.0\' with your plugin version
// new ' . ucwords(str_replace('-', '_', $product_slug)) . '_License_Manager(__FILE__, \'1.0.0\');
';

        return $code;
    }
}

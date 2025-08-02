<?php
/**
 * Complete License and Update Integration for bottom-navigation-pro
 * Replace your existing basic integration code with this complete version
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BOTTOM_NAVIGATION_PRO_License_Manager {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $license_server_url;
    private $license_key;
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = 'bottom-navigation-pro';
        $this->version = $version;
        $this->license_server_url = 'https://stackcastle.com';
        $this->license_key = get_option($this->plugin_slug . '_license_key');
        
        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'license_menu'));
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugins_api_filter'), 10, 3);
    }
    
    public function init() {
        if (isset($_POST['activate_license'])) {
            $this->activate_license();
        }
        
        if (isset($_POST['deactivate_license'])) {
            $this->deactivate_license();
        }
    }
    
    public function license_menu() {
        add_options_page(
            'bottom-navigation-pro License',
            'bottom-navigation-pro License',
            'manage_options',
            $this->plugin_slug . '-license',
            array($this, 'license_page')
        );
    }
    
    public function license_page() {
        $license_key = get_option($this->plugin_slug . '_license_key');
        $status = get_option($this->plugin_slug . '_license_status');
        
        ?>
        <div class="wrap">
            <h1>bottom-navigation-pro License Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('license_nonce', 'license_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">License Key</th>
                        <td>
                            <input type="text" name="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                            <p class="description">Enter your license key to enable automatic updates</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <?php if ($status == 'valid'): ?>
                                <span style="color: green; font-weight: bold;">✓ Active</span>
                                <p class="description">Your license is active and updates are enabled</p>
                                <input type="submit" name="deactivate_license" value="Deactivate License" class="button" />
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">✗ Inactive</span>
                                <p class="description">Please activate your license to receive updates</p>
                                <input type="submit" name="activate_license" value="Activate License" class="button-primary" />
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Current Version</th>
                        <td><?php echo esc_html($this->version); ?></td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }
    
    public function activate_license() {
        if (!wp_verify_nonce($_POST['license_nonce'], 'license_nonce')) {
            return;
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->license_server_url . '/wp-json/licensing/v1/activate', array(
            'body' => array(
                'license_key' => $license_key,
                'domain' => $domain,
                'product_slug' => $this->plugin_slug
            ),
            'sslverify' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body['success']) {
                update_option($this->plugin_slug . '_license_key', $license_key);
                update_option($this->plugin_slug . '_license_status', 'valid');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>License activated successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() use ($body) {
                    echo '<div class="notice notice-error"><p>Error: ' . esc_html($body['error']) . '</p></div>';
                });
            }
        }
    }
    
    public function deactivate_license() {
        if (!wp_verify_nonce($_POST['license_nonce'], 'license_nonce')) {
            return;
        }
        
        $license_key = get_option($this->plugin_slug . '_license_key');
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->license_server_url . '/wp-json/licensing/v1/deactivate', array(
            'body' => array(
                'license_key' => $license_key,
                'domain' => $domain,
                'product_slug' => $this->plugin_slug
            ),
            'sslverify' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body['success']) {
                update_option($this->plugin_slug . '_license_status', 'invalid');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>License deactivated successfully!</p></div>';
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
        
        if ($remote_version && version_compare($this->version, $remote_version, '<')) {
            $transient->response[$plugin_basename] = (object) array(
                'slug' => dirname($plugin_basename),
                'new_version' => $remote_version,
                'url' => '',
                'package' => $this->get_download_url()
            );
        }
        
        return $transient;
    }
    
    public function plugins_api_filter($res, $action, $args) {
        if ($action != 'plugin_information') {
            return $res;
        }
        
        if ($args->slug != dirname(plugin_basename($this->plugin_file))) {
            return $res;
        }
        
        $remote_version = $this->get_remote_version();
        
        return (object) array(
            'name' => 'bottom-navigation-pro',
            'slug' => dirname(plugin_basename($this->plugin_file)),
            'version' => $remote_version,
            'author' => 'Your Name',
            'homepage' => '',
            'requires' => '5.0',
            'tested' => '6.3',
            'downloaded' => 0,
            'last_updated' => date('Y-m-d'),
            'sections' => array(
                'description' => 'Premium plugin description',
                'changelog' => $this->get_changelog()
            ),
            'download_link' => $this->get_download_url()
        );
    }
    
    private function get_remote_version() {
        $license_key = get_option($this->plugin_slug . '_license_key');
        
        if (empty($license_key)) {
            return $this->version;
        }
        
        $response = wp_remote_post($this->license_server_url . '/wp-json/licensing/v1/update-check', array(
            'body' => array(
                'license_key' => $license_key,
                'product_slug' => $this->plugin_slug,
                'current_version' => $this->version
            ),
            'sslverify' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body['success'] && $body['has_update']) {
                return $body['latest_version'];
            }
        }
        
        return $this->version;
    }
    
    private function get_changelog() {
        $license_key = get_option($this->plugin_slug . '_license_key');
        
        if (empty($license_key)) {
            return '';
        }
        
        $response = wp_remote_post($this->license_server_url . '/wp-json/licensing/v1/update-check', array(
            'body' => array(
                'license_key' => $license_key,
                'product_slug' => $this->plugin_slug,
                'current_version' => $this->version
            ),
            'sslverify' => true
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body['success'] && isset($body['changelog'])) {
                return $body['changelog'];
            }
        }
        
        return '';
    }
    
    private function get_download_url() {
        $license_key = get_option($this->plugin_slug . '_license_key');
        
        if (empty($license_key)) {
            return '';
        }
        
        return $this->license_server_url . '/wp-json/licensing/v1/update-download?license_key=' . urlencode($license_key) . '&product_slug=' . urlencode($this->plugin_slug);
    }
}

// Initialize the license manager
// IMPORTANT: Uncomment the line below and replace with your actual plugin file and version
// new BOTTOM_NAVIGATION_PRO_License_Manager(__FILE__, '1.0.0');
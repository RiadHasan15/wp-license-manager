<?php
/**
 * Plugin Name: Bottom Navigation Pro
 * Plugin URI: https://stackcastle.com/bottom-navigation-pro
 * Description: A premium navigation plugin with advanced features
 * Version: 1.0.0
 * Author: StackCastle
 * License: Commercial
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BOTTOM_NAVIGATION_PRO_VERSION', '1.0.0');
define('BOTTOM_NAVIGATION_PRO_PLUGIN_FILE', __FILE__);
define('BOTTOM_NAVIGATION_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOTTOM_NAVIGATION_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Bottom Navigation Pro class
 */
class Bottom_Navigation_Pro {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * License Manager instance
     */
    public $license_manager;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_license_manager();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
    }
    
    /**
     * Initialize license manager
     */
    private function init_license_manager() {
        // Include the license manager file
        require_once BOTTOM_NAVIGATION_PRO_PLUGIN_DIR . 'bottom-navigation-pro-license.php';
        
        // Initialize the license manager
        $this->license_manager = new BOTTOM_NAVIGATION_PRO_License_Manager(
            BOTTOM_NAVIGATION_PRO_PLUGIN_FILE, 
            BOTTOM_NAVIGATION_PRO_VERSION
        );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('bottom-navigation-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize plugin features
        $this->init_features();
    }
    
    /**
     * Initialize plugin features
     */
    private function init_features() {
        // Only enable features if license is valid
        $license_status = get_option('bottom-navigation-pro_license_status');
        
        if ($license_status === 'valid') {
            // Initialize premium features
            add_action('wp_footer', array($this, 'render_navigation'));
            add_action('customize_register', array($this, 'customizer_settings'));
            
            // Enable all premium features
            add_filter('bottom_navigation_pro_features', array($this, 'enable_premium_features'));
        } else {
            // Show license activation notice
            add_action('admin_notices', array($this, 'license_activation_notice'));
            
            // Limited functionality for unlicensed version
            add_filter('bottom_navigation_pro_features', array($this, 'limit_features'));
        }
    }
    
    /**
     * Admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'Bottom Navigation Pro',
            'Navigation Pro',
            'manage_options',
            'bottom-navigation-pro',
            array($this, 'admin_page'),
            'dashicons-menu-alt3',
            30
        );
        
        add_submenu_page(
            'bottom-navigation-pro',
            'Settings',
            'Settings',
            'manage_options',
            'bottom-navigation-pro',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'bottom-navigation-pro',
            'License',
            'License',
            'manage_options',
            'bottom-navigation-pro-license',
            array($this->license_manager, 'license_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $license_status = get_option('bottom-navigation-pro_license_status');
        ?>
        <div class="wrap">
            <h1>Bottom Navigation Pro Settings</h1>
            
            <?php if ($license_status !== 'valid'): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>License Required:</strong> 
                        Please <a href="<?php echo admin_url('admin.php?page=bottom-navigation-pro-license'); ?>">activate your license</a> 
                        to unlock all premium features and receive automatic updates.
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Navigation Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('bottom_navigation_pro_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Navigation</th>
                            <td>
                                <input type="checkbox" name="bnp_enabled" value="1" 
                                       <?php checked(get_option('bnp_enabled', 1)); ?> />
                                <p class="description">Enable the bottom navigation bar</p>
                            </td>
                        </tr>
                        
                        <?php if ($license_status === 'valid'): ?>
                        <tr>
                            <th scope="row">Custom Colors</th>
                            <td>
                                <input type="color" name="bnp_primary_color" 
                                       value="<?php echo esc_attr(get_option('bnp_primary_color', '#007cba')); ?>" />
                                <p class="description">Primary navigation color (Premium Feature)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Advanced Animations</th>
                            <td>
                                <select name="bnp_animation">
                                    <option value="slide" <?php selected(get_option('bnp_animation'), 'slide'); ?>>Slide</option>
                                    <option value="fade" <?php selected(get_option('bnp_animation'), 'fade'); ?>>Fade</option>
                                    <option value="bounce" <?php selected(get_option('bnp_animation'), 'bounce'); ?>>Bounce</option>
                                </select>
                                <p class="description">Navigation animation effect (Premium Feature)</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2">
                                <div style="padding: 20px; background: #f0f0f1; border-radius: 4px;">
                                    <h3>Premium Features (License Required)</h3>
                                    <ul>
                                        <li>Custom color schemes</li>
                                        <li>Advanced animations</li>
                                        <li>Mobile-specific settings</li>
                                        <li>Analytics integration</li>
                                        <li>Priority support</li>
                                        <li>Automatic updates</li>
                                    </ul>
                                    <p>
                                        <a href="<?php echo admin_url('admin.php?page=bottom-navigation-pro-license'); ?>" 
                                           class="button-primary">Activate License</a>
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>License Information</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <?php if ($license_status === 'valid'): ?>
                                <span style="color: green; font-weight: bold;">✓ Active</span>
                            <?php elseif ($license_status === 'expired'): ?>
                                <span style="color: orange; font-weight: bold;">⚠ Expired</span>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">✗ Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Version</th>
                        <td><?php echo BOTTOM_NAVIGATION_PRO_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Manage License</th>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=bottom-navigation-pro-license'); ?>" 
                               class="button">License Settings</a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin assets
     */
    public function admin_assets($hook) {
        if (strpos($hook, 'bottom-navigation-pro') !== false) {
            wp_enqueue_style(
                'bottom-navigation-pro-admin',
                BOTTOM_NAVIGATION_PRO_PLUGIN_URL . 'assets/admin.css',
                array(),
                BOTTOM_NAVIGATION_PRO_VERSION
            );
        }
    }
    
    /**
     * Frontend assets
     */
    public function frontend_assets() {
        if (get_option('bnp_enabled', 1)) {
            wp_enqueue_style(
                'bottom-navigation-pro',
                BOTTOM_NAVIGATION_PRO_PLUGIN_URL . 'assets/style.css',
                array(),
                BOTTOM_NAVIGATION_PRO_VERSION
            );
            
            wp_enqueue_script(
                'bottom-navigation-pro',
                BOTTOM_NAVIGATION_PRO_PLUGIN_URL . 'assets/script.js',
                array('jquery'),
                BOTTOM_NAVIGATION_PRO_VERSION,
                true
            );
        }
    }
    
    /**
     * Render navigation
     */
    public function render_navigation() {
        $license_status = get_option('bottom-navigation-pro_license_status');
        
        if ($license_status !== 'valid') {
            // Show basic navigation only
            echo '<div id="bottom-navigation-basic">Basic Navigation (License Required for Premium Features)</div>';
            return;
        }
        
        // Render premium navigation
        $primary_color = get_option('bnp_primary_color', '#007cba');
        $animation = get_option('bnp_animation', 'slide');
        
        ?>
        <div id="bottom-navigation-pro" class="bnp-<?php echo esc_attr($animation); ?>" 
             style="--primary-color: <?php echo esc_attr($primary_color); ?>">
            <nav class="bnp-nav">
                <a href="<?php echo home_url(); ?>" class="bnp-item">
                    <span class="bnp-icon">🏠</span>
                    <span class="bnp-label">Home</span>
                </a>
                <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="bnp-item">
                    <span class="bnp-icon">📰</span>
                    <span class="bnp-label">Blog</span>
                </a>
                <a href="#search" class="bnp-item bnp-search-trigger">
                    <span class="bnp-icon">🔍</span>
                    <span class="bnp-label">Search</span>
                </a>
                <a href="#menu" class="bnp-item bnp-menu-trigger">
                    <span class="bnp-icon">☰</span>
                    <span class="bnp-label">Menu</span>
                </a>
            </nav>
        </div>
        <?php
    }
    
    /**
     * Enable premium features
     */
    public function enable_premium_features($features) {
        return array_merge($features, array(
            'custom_colors' => true,
            'animations' => true,
            'analytics' => true,
            'mobile_settings' => true,
            'priority_support' => true
        ));
    }
    
    /**
     * Limit features for unlicensed version
     */
    public function limit_features($features) {
        return array(
            'basic_navigation' => true,
            'limited_customization' => true
        );
    }
    
    /**
     * License activation notice
     */
    public function license_activation_notice() {
        $current_screen = get_current_screen();
        
        if ($current_screen && strpos($current_screen->id, 'bottom-navigation-pro') !== false) {
            return; // Don't show on plugin pages
        }
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Bottom Navigation Pro:</strong> 
                Please <a href="<?php echo admin_url('admin.php?page=bottom-navigation-pro-license'); ?>">activate your license</a> 
                to unlock all premium features and receive automatic updates.
            </p>
        </div>
        <?php
    }
    
    /**
     * Customizer settings (premium feature)
     */
    public function customizer_settings($wp_customize) {
        $license_status = get_option('bottom-navigation-pro_license_status');
        
        if ($license_status !== 'valid') {
            return; // Only available with valid license
        }
        
        $wp_customize->add_section('bottom_navigation_pro', array(
            'title' => 'Bottom Navigation Pro',
            'priority' => 30,
        ));
        
        $wp_customize->add_setting('bnp_primary_color', array(
            'default' => '#007cba',
            'sanitize_callback' => 'sanitize_hex_color',
        ));
        
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'bnp_primary_color', array(
            'label' => 'Primary Color',
            'section' => 'bottom_navigation_pro',
            'settings' => 'bnp_primary_color',
        )));
    }
}

/**
 * Get main instance
 */
function bottom_navigation_pro() {
    return Bottom_Navigation_Pro::get_instance();
}

// Initialize the plugin
bottom_navigation_pro();

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('bnp_enabled', 1);
    add_option('bnp_primary_color', '#007cba');
    add_option('bnp_animation', 'slide');
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
    delete_transient('bottom-navigation-pro_remote_version');
});
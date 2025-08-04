<?php
/**
 * WP Licensing Manager Email Manager
 * 
 * Handles all email automation including welcome emails, renewal reminders,
 * usage tips, and grace period notifications with full admin customization.
 * 
 * @package WP_Licensing_Manager
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Licensing_Manager_Email_Manager {

    /**
     * Email templates
     */
    private $email_templates = array();

    /**
     * Default email settings
     */
    private $default_settings = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_default_settings();
        // Initialize templates on init action to avoid translation loading issues
        add_action('init', array($this, 'init_email_templates'), 20);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // License lifecycle hooks (SAFE - only adds new functionality)
        add_action('wp_licensing_manager_license_created', array($this, 'send_welcome_email'), 10, 2);
        add_action('wp_licensing_manager_license_expiring', array($this, 'send_expiry_reminder'), 10, 2);
        add_action('wp_licensing_manager_license_expired', array($this, 'send_grace_period_email'), 10, 2);
        add_action('wp_licensing_manager_send_usage_tips_email', array($this, 'send_usage_tips_email'), 10, 1);
        
        // WooCommerce integration hooks (SAFE - only extends existing functionality)
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completion'), 20, 1);
        
        // Daily cron for automated emails (SAFE - new functionality)
        add_action('wp_licensing_manager_daily_email_check', array($this, 'process_automated_emails'));
        
        // Admin hooks (SAFE - only adds admin functionality)
        if (is_admin()) {
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_menu', array($this, 'add_admin_menu'), 20);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('wp_ajax_wp_licensing_test_email', array($this, 'ajax_test_email'));
            add_action('wp_ajax_wp_licensing_preview_email', array($this, 'ajax_preview_email'));
        }

        // Schedule daily cron if not already scheduled (SAFE - only adds new functionality)
        if (!wp_next_scheduled('wp_licensing_manager_daily_email_check')) {
            wp_schedule_event(time(), 'daily', 'wp_licensing_manager_daily_email_check');
        }
    }

    /**
     * Initialize default email settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'email_enabled' => true,
            'welcome_email_enabled' => true,
            'renewal_reminders_enabled' => true,
            'grace_period_emails_enabled' => true,
            'usage_tips_enabled' => true,
            'reminder_days' => array(30, 14, 7, 1),
            'usage_tips_schedule' => 7, // Days after purchase
        );
    }

    /**
     * Initialize default email templates
     */
    public function init_email_templates() {
        $this->email_templates = array(
            'welcome' => array(
                'subject' => __('Welcome! Your License is Ready', 'wp-licensing-manager'),
                'heading' => __('Welcome to {site_name}!', 'wp-licensing-manager'),
                'content' => $this->get_default_welcome_template(),
                'enabled' => true,
            ),
            'renewal_reminder' => array(
                'subject' => __('License Renewal Reminder - {days_until_expiry} days remaining', 'wp-licensing-manager'),
                'heading' => __('Your License Expires Soon', 'wp-licensing-manager'),
                'content' => $this->get_default_renewal_template(),
                'enabled' => true,
            ),
            'grace_period' => array(
                'subject' => __('License Expired - Grace Period Active', 'wp-licensing-manager'),
                'heading' => __('Grace Period Activated', 'wp-licensing-manager'),
                'content' => $this->get_default_grace_period_template(),
                'enabled' => true,
            ),
            'usage_tips' => array(
                'subject' => __('Getting the Most from Your License', 'wp-licensing-manager'),
                'heading' => __('Tips to Maximize Your Experience', 'wp-licensing-manager'),
                'content' => $this->get_default_usage_tips_template(),
                'enabled' => true,
            ),
        );
    }

    /**
     * Send welcome email on license purchase
     */
    public function send_welcome_email($license_id, $order_id = null) {
        if (!$this->is_email_enabled('welcome_email_enabled')) {
            return;
        }

        $license = $this->get_license($license_id);
        if (!$license) {
            return;
        }

        $template = $this->get_email_template('welcome');
        if (!$template || !$template['enabled']) {
            return;
        }

        $variables = $this->get_email_variables($license, $order_id);
        
        $subject = $this->replace_variables($template['subject'], $variables);
        $content = $this->replace_variables($template['content'], $variables);
        
        $this->send_email($license->customer_email, $subject, $content, $template['heading'], $variables);
        
        // Log email sent
        $this->log_email_sent($license_id, 'welcome', $license->customer_email);
    }

    /**
     * Send renewal reminder emails
     */
    public function send_expiry_reminder($license_id, $days_until_expiry) {
        if (!$this->is_email_enabled('renewal_reminders_enabled')) {
            return;
        }

        $reminder_days = $this->get_setting('reminder_days', array(30, 14, 7, 1));
        if (!in_array($days_until_expiry, $reminder_days)) {
            return;
        }

        $license = $this->get_license($license_id);
        if (!$license) {
            return;
        }

        $template = $this->get_email_template('renewal_reminder');
        if (!$template || !$template['enabled']) {
            return;
        }

        $variables = $this->get_email_variables($license);
        $variables['days_until_expiry'] = $days_until_expiry;
        
        $subject = $this->replace_variables($template['subject'], $variables);
        $content = $this->replace_variables($template['content'], $variables);
        
        $this->send_email($license->customer_email, $subject, $content, $template['heading'], $variables);
        
        // Log email sent
        $this->log_email_sent($license_id, 'renewal_reminder', $license->customer_email, array('days' => $days_until_expiry));
    }

    /**
     * Send grace period email
     */
    public function send_grace_period_email($license_id, $grace_period_days = null) {
        if (!$this->is_email_enabled('grace_period_emails_enabled')) {
            return;
        }

        $license = $this->get_license($license_id);
        if (!$license) {
            return;
        }

        $template = $this->get_email_template('grace_period');
        if (!$template || !$template['enabled']) {
            return;
        }

        $variables = $this->get_email_variables($license);
        $variables['grace_period_days'] = $grace_period_days ?: 30;
        
        $subject = $this->replace_variables($template['subject'], $variables);
        $content = $this->replace_variables($template['content'], $variables);
        
        $this->send_email($license->customer_email, $subject, $content, $template['heading'], $variables);
        
        // Log email sent
        $this->log_email_sent($license_id, 'grace_period', $license->customer_email);
    }

    /**
     * Send usage tips email
     */
    public function send_usage_tips_email($license_id) {
        if (!$this->is_email_enabled('usage_tips_enabled')) {
            return;
        }

        $license = $this->get_license($license_id);
        if (!$license) {
            return;
        }

        $template = $this->get_email_template('usage_tips');
        if (!$template || !$template['enabled']) {
            return;
        }

        $variables = $this->get_email_variables($license);
        
        $subject = $this->replace_variables($template['subject'], $variables);
        $content = $this->replace_variables($template['content'], $variables);
        
        $this->send_email($license->customer_email, $subject, $content, $template['heading'], $variables);
        
        // Log email sent
        $this->log_email_sent($license_id, 'usage_tips', $license->customer_email);
    }

    /**
     * Handle WooCommerce order completion
     */
    public function handle_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Schedule usage tips email
        $usage_tips_delay = $this->get_setting('usage_tips_schedule', 7);
        if ($usage_tips_delay > 0) {
            wp_schedule_single_event(
                time() + ($usage_tips_delay * DAY_IN_SECONDS),
                'wp_licensing_manager_send_usage_tips',
                array($order_id)
            );
        }
    }

    /**
     * Process automated emails (daily cron)
     */
    public function process_automated_emails() {
        if (!$this->is_email_enabled('email_enabled')) {
            return;
        }

        $this->process_renewal_reminders();
        $this->process_grace_period_emails();
    }

    /**
     * Process renewal reminder emails
     */
    private function process_renewal_reminders() {
        $reminder_days = $this->get_setting('reminder_days', array(30, 14, 7, 1));
        
        foreach ($reminder_days as $days) {
            $expiry_date = date('Y-m-d', strtotime("+{$days} days"));
            $licenses = $this->get_expiring_licenses($expiry_date);
            
            foreach ($licenses as $license) {
                // Check if reminder already sent for this day
                if (!$this->reminder_already_sent($license->id, 'renewal_reminder', $days)) {
                    $this->send_expiry_reminder($license->id, $days);
                }
            }
        }
    }

    /**
     * Process grace period emails
     */
    private function process_grace_period_emails() {
        $expired_licenses = $this->get_expired_licenses_in_grace_period();
        
        foreach ($expired_licenses as $license) {
            // Send grace period email only once
            if (!$this->reminder_already_sent($license->id, 'grace_period')) {
                $this->send_grace_period_email($license->id);
            }
        }
    }

    /**
     * Send email using WordPress mail system
     */
    private function send_email($to, $subject, $content, $heading = '', $variables = array()) {
        $from_name = $this->get_setting('from_name', get_bloginfo('name'));
        $from_email = $this->get_setting('from_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        // Create HTML email with proper styling
        $html_content = $this->wrap_email_content($content, $heading, $variables);
        
        // Send email
        $sent = wp_mail($to, $subject, $html_content, $headers);
        
        // Log result
        if ($sent) {
            error_log("WP Licensing Manager: Email sent successfully to {$to}");
        } else {
            error_log("WP Licensing Manager: Failed to send email to {$to}");
        }

        return $sent;
    }

    /**
     * Wrap email content in HTML template
     */
    private function wrap_email_content($content, $heading = '', $variables = array()) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($heading ?: $site_name); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .email-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .email-content { padding: 30px 20px; }
                .email-content h2 { color: #333; margin-top: 0; }
                .email-content p { margin-bottom: 16px; }
                .license-info { background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #667eea; }
                .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; margin: 10px 0; }
                .email-footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                .email-footer a { color: #667eea; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <?php if ($heading): ?>
                <div class="email-header">
                    <h1><?php echo esc_html($this->replace_variables($heading, $variables)); ?></h1>
                </div>
                <?php endif; ?>
                
                <div class="email-content">
                    <?php echo wpautop($content); ?>
                </div>
                
                <div class="email-footer">
                    <p>This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get email variables for template replacement
     */
    private function get_email_variables($license, $order_id = null) {
        $variables = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'customer_name' => $this->get_customer_name($license->customer_email),
            'customer_email' => $license->customer_email,
            'license_key' => $license->license_key,
            'product_name' => $this->get_product_name($license->product_id),
            'expires_at' => $license->expires_at,
            'status' => ucfirst($license->status),
            'activations' => $license->activations,
            'max_activations' => $license->max_activations,
            'my_account_url' => wc_get_page_permalink('myaccount'),
            'downloads_url' => wc_get_endpoint_url('downloads', '', wc_get_page_permalink('myaccount')),
        );

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $variables['order_id'] = $order_id;
                $variables['order_total'] = $order->get_formatted_order_total();
            }
        }

        return apply_filters('wp_licensing_manager_email_variables', $variables, $license, $order_id);
    }

    /**
     * Replace variables in email content
     */
    private function replace_variables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    /**
     * Get default welcome email template
     */
    private function get_default_welcome_template() {
        return __('Hi {customer_name},

Welcome to {site_name}! Thank you for your purchase.

<div class="license-info">
<strong>Your License Details:</strong><br>
Product: {product_name}<br>
License Key: <code>{license_key}</code><br>
Status: {status}<br>
Expires: {expires_at}<br>
Activations: {activations}/{max_activations}
</div>

You can manage your license and download your files from your account dashboard:

<a href="{downloads_url}" class="button">View My Downloads</a>

If you have any questions, don\'t hesitate to contact our support team.

Best regards,<br>
The {site_name} Team', 'wp-licensing-manager');
    }

    /**
     * Get default renewal reminder template
     */
    private function get_default_renewal_template() {
        return __('Hi {customer_name},

Your license for {product_name} expires in {days_until_expiry} days.

<div class="license-info">
<strong>License Details:</strong><br>
Product: {product_name}<br>
License Key: <code>{license_key}</code><br>
Expires: {expires_at}<br>
Status: {status}
</div>

To ensure uninterrupted access, renew your license before it expires:

<a href="{my_account_url}" class="button">Renew License</a>

Don\'t let your license expire! Renew now to continue receiving updates and support.

Best regards,<br>
The {site_name} Team', 'wp-licensing-manager');
    }

    /**
     * Get default grace period template
     */
    private function get_default_grace_period_template() {
        return __('Hi {customer_name},

Your license for {product_name} has expired, but we\'ve activated a {grace_period_days}-day grace period.

<div class="license-info">
<strong>License Details:</strong><br>
Product: {product_name}<br>
License Key: <code>{license_key}</code><br>
Status: Grace Period Active<br>
Grace Period: {grace_period_days} days remaining
</div>

During this grace period, you can still use your product, but you won\'t receive updates or support.

Renew now to restore full access:

<a href="{my_account_url}" class="button">Renew License</a>

Don\'t wait - renew your license today!

Best regards,<br>
The {site_name} Team', 'wp-licensing-manager');
    }

    /**
     * Get default usage tips template
     */
    private function get_default_usage_tips_template() {
        return __('Hi {customer_name},

Hope you\'re enjoying {product_name}! Here are some tips to get the most out of your license:

<strong>💡 Getting Started:</strong>
• Download the latest version from your account
• Keep your license key safe and accessible
• Register your license for automatic updates

<strong>🔧 Best Practices:</strong>
• Use your license on authorized domains only
• Keep your software updated for security
• Check our documentation for advanced features

<strong>📞 Need Help?</strong>
Our support team is here to help you succeed:

<a href="{my_account_url}" class="button">Access My Account</a>

Thanks for choosing {site_name}!

Best regards,<br>
The {site_name} Team', 'wp-licensing-manager');
    }

    /**
     * Helper methods (SAFE - only read data, don't modify)
     */
    private function get_license($license_id) {
        $license_manager = new WP_Licensing_Manager_License_Manager();
        return $license_manager->get_license($license_id);
    }

    private function get_customer_name($email) {
        $user = get_user_by('email', $email);
        if ($user) {
            return $user->display_name ?: $user->user_login;
        }
        return ucfirst(explode('@', $email)[0]);
    }

    private function get_product_name($product_id) {
        $product_manager = new WP_Licensing_Manager_Product_Manager();
        $product = $product_manager->get_product($product_id);
        return $product ? $product->name : __('Unknown Product', 'wp-licensing-manager');
    }

    private function get_expiring_licenses($expiry_date) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}licenses WHERE expires_at = %s AND status = 'active'",
            $expiry_date
        ));
    }

    private function get_expired_licenses_in_grace_period() {
        global $wpdb;
        $grace_period_start = date('Y-m-d', strtotime('-30 days'));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}licenses WHERE expires_at BETWEEN %s AND %s AND status = 'expired'",
            $grace_period_start,
            date('Y-m-d', strtotime('-1 day'))
        ));
    }

    private function reminder_already_sent($license_id, $type, $days = null) {
        $log_key = $type . ($days ? "_day_{$days}" : '');
        $sent_reminders = get_option('wp_licensing_email_log_' . $license_id, array());
        return isset($sent_reminders[$log_key]) && $sent_reminders[$log_key] > strtotime('-1 day');
    }

    private function log_email_sent($license_id, $type, $email, $meta = array()) {
        $log_key = $type . (isset($meta['days']) ? "_day_{$meta['days']}" : '');
        $sent_reminders = get_option('wp_licensing_email_log_' . $license_id, array());
        $sent_reminders[$log_key] = time();
        update_option('wp_licensing_email_log_' . $license_id, $sent_reminders);
        
        // Also log in general email log for admin review
        $email_logs = get_option('wp_licensing_email_general_log', array());
        $email_logs[] = array(
            'license_id' => $license_id,
            'type' => $type,
            'email' => $email,
            'sent_at' => current_time('mysql'),
            'meta' => $meta,
        );
        
        // Keep only last 1000 entries
        if (count($email_logs) > 1000) {
            $email_logs = array_slice($email_logs, -1000);
        }
        
        update_option('wp_licensing_email_general_log', $email_logs);
    }

    private function get_setting($key, $default = null) {
        $settings = get_option('wp_licensing_email_settings', $this->default_settings);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    private function get_email_template($type) {
        // Ensure templates are initialized
        if (empty($this->email_templates)) {
            $this->init_email_templates();
        }
        
        $templates = get_option('wp_licensing_email_templates', $this->email_templates);
        return isset($templates[$type]) ? $templates[$type] : null;
    }

    private function is_email_enabled($setting_key) {
        return $this->get_setting('email_enabled', true) && $this->get_setting($setting_key, true);
    }

    /**
     * Admin functionality (SAFE - only adds admin interface)
     */
    public function register_settings() {
        register_setting('wp_licensing_email_settings', 'wp_licensing_email_settings');
        register_setting('wp_licensing_email_templates', 'wp_licensing_email_templates');
    }

    public function add_admin_menu() {
        add_submenu_page(
            'wp-licensing-manager',
            __('Email Automation', 'wp-licensing-manager'),
            __('Email Automation', 'wp-licensing-manager'),
            'manage_options',
            'wp-licensing-emails',
            array($this, 'admin_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-licensing-emails') === false) {
            return;
        }

        wp_enqueue_script('wp-licensing-email-admin', WP_LICENSING_MANAGER_PLUGIN_URL . 'admin/js/email-admin.js', array('jquery'), WP_LICENSING_MANAGER_VERSION . '-' . time(), true);
        wp_enqueue_style('wp-licensing-email-admin', WP_LICENSING_MANAGER_PLUGIN_URL . 'admin/css/email-admin.css', array(), WP_LICENSING_MANAGER_VERSION . '-' . time());
        
        wp_localize_script('wp-licensing-email-admin', 'wpLicensingEmailAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_licensing_email_admin'),
            'strings' => array(
                'test_email_sent' => __('Test email sent successfully!', 'wp-licensing-manager'),
                'test_email_failed' => __('Failed to send test email.', 'wp-licensing-manager'),
                'confirm_test' => __('Send test email to your admin email?', 'wp-licensing-manager'),
            ),
        ));
    }

    public function admin_page() {
        // Ensure templates are initialized before loading the admin page
        if (empty($this->email_templates)) {
            $this->init_email_templates();
        }
        
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['wp_licensing_email_nonce'], 'wp_licensing_email_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'wp-licensing-manager') . '</p></div>';
        }

        include WP_LICENSING_MANAGER_PLUGIN_DIR . 'admin/views/email-settings.php';
    }

    public function ajax_test_email() {
        check_ajax_referer('wp_licensing_email_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'wp-licensing-manager'));
        }

        $template_type = sanitize_text_field($_POST['template_type']);
        $admin_email = get_option('admin_email');
        
        // Create dummy license for testing
        $dummy_license = (object) array(
            'id' => 999,
            'license_key' => 'TEST-LICENSE-KEY-123',
            'customer_email' => $admin_email,
            'product_id' => 1,
            'status' => 'active',
            'expires_at' => date('Y-m-d', strtotime('+30 days')),
            'activations' => 1,
            'max_activations' => 5,
        );

        $variables = $this->get_email_variables($dummy_license);
        $variables['days_until_expiry'] = 7;
        $variables['grace_period_days'] = 30;

        $template = $this->get_email_template($template_type);
        if (!$template) {
            wp_die(__('Template not found', 'wp-licensing-manager'));
        }

        $subject = '[TEST] ' . $this->replace_variables($template['subject'], $variables);
        $content = $this->replace_variables($template['content'], $variables);
        
        $sent = $this->send_email($admin_email, $subject, $content, $template['heading'], $variables);
        
        wp_die(json_encode(array(
            'success' => $sent,
            'message' => $sent ? __('Test email sent successfully!', 'wp-licensing-manager') : __('Failed to send test email.', 'wp-licensing-manager')
        )));
    }

    public function ajax_preview_email() {
        check_ajax_referer('wp_licensing_email_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'wp-licensing-manager'));
        }

        $template_type = sanitize_text_field($_POST['template_type']);
        $content = wp_kses_post($_POST['content']);
        $heading = sanitize_text_field($_POST['heading']);
        
        // Create dummy variables for preview
        $variables = array(
            'site_name' => get_bloginfo('name'),
            'customer_name' => 'John Doe',
            'product_name' => 'Sample Product',
            'license_key' => 'SAMPLE-LICENSE-KEY-123',
            'expires_at' => date('Y-m-d', strtotime('+30 days')),
            'status' => 'Active',
            'days_until_expiry' => 7,
            'grace_period_days' => 30,
        );

        $preview_content = $this->replace_variables($content, $variables);
        $html_content = $this->wrap_email_content($preview_content, $heading, $variables);
        
        wp_die($html_content);
    }

    private function save_settings() {
        // Save email settings
        $settings = array();
        $settings['email_enabled'] = isset($_POST['email_enabled']);
        $settings['from_name'] = sanitize_text_field($_POST['from_name']);
        $settings['from_email'] = sanitize_email($_POST['from_email']);
        $settings['welcome_email_enabled'] = isset($_POST['welcome_email_enabled']);
        $settings['renewal_reminders_enabled'] = isset($_POST['renewal_reminders_enabled']);
        $settings['grace_period_emails_enabled'] = isset($_POST['grace_period_emails_enabled']);
        $settings['usage_tips_enabled'] = isset($_POST['usage_tips_enabled']);
        $settings['usage_tips_schedule'] = absint($_POST['usage_tips_schedule']);
        
        // Handle reminder days
        $reminder_days = array();
        if (isset($_POST['reminder_days']) && is_array($_POST['reminder_days'])) {
            foreach ($_POST['reminder_days'] as $day) {
                $day = absint($day);
                if ($day > 0) {
                    $reminder_days[] = $day;
                }
            }
        }
        $settings['reminder_days'] = $reminder_days;
        
        update_option('wp_licensing_email_settings', $settings);

        // Save email templates
        $templates = array();
        foreach ($this->email_templates as $type => $default_template) {
            // Always save the template, regardless of enabled status
            $templates[$type] = array(
                'enabled' => isset($_POST['template_' . $type . '_enabled']),
                'subject' => isset($_POST['template_' . $type . '_subject']) ? sanitize_text_field($_POST['template_' . $type . '_subject']) : $default_template['subject'],
                'heading' => isset($_POST['template_' . $type . '_heading']) ? sanitize_text_field($_POST['template_' . $type . '_heading']) : $default_template['heading'],
                'content' => isset($_POST['template_' . $type . '_content']) ? wp_kses_post($_POST['template_' . $type . '_content']) : $default_template['content'],
            );
        }
        
        update_option('wp_licensing_email_templates', $templates);
    }
}
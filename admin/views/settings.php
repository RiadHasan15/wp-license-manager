<?php
/**
 * Settings admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$default_expiry_days = get_option('wp_licensing_manager_default_expiry_days', 365);
$default_max_activations = get_option('wp_licensing_manager_default_max_activations', 1);
$email_template_subject = get_option('wp_licensing_manager_email_template_subject', 'Your {product_name} License Key');
$email_template_body = get_option('wp_licensing_manager_email_template_body', 'Hi {customer_name},

Thank you for your purchase of {product_name}!

Your license key is: {license_key}
Order ID: {order_id}

Please keep this information safe as you will need it to activate your product.

Best regards,
StackCastle Team');
?>

<div class="wrap">
    <h1><?php esc_html_e('Licensing Manager Settings', 'wp-licensing-manager'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('wp_licensing_manager_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="default_expiry_days"><?php esc_html_e('Default License Expiry (Days)', 'wp-licensing-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="default_expiry_days" name="default_expiry_days" value="<?php echo esc_attr($default_expiry_days); ?>" min="0" class="regular-text" />
                    <p class="description"><?php esc_html_e('Default number of days until a license expires. Set to 0 for lifetime licenses.', 'wp-licensing-manager'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="default_max_activations"><?php esc_html_e('Default Max Activations', 'wp-licensing-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="default_max_activations" name="default_max_activations" value="<?php echo esc_attr($default_max_activations); ?>" min="1" class="regular-text" />
                    <p class="description"><?php esc_html_e('Default maximum number of activations allowed per license.', 'wp-licensing-manager'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Email Templates', 'wp-licensing-manager'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="email_template_subject"><?php esc_html_e('License Email Subject', 'wp-licensing-manager'); ?></label>
                </th>
                <td>
                    <input type="text" id="email_template_subject" name="email_template_subject" value="<?php echo esc_attr($email_template_subject); ?>" class="large-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="email_template_body"><?php esc_html_e('License Email Body', 'wp-licensing-manager'); ?></label>
                </th>
                <td>
                    <textarea id="email_template_body" name="email_template_body" rows="10" class="large-text"><?php echo esc_textarea($email_template_body); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Available placeholders:', 'wp-licensing-manager'); ?>
                        <code>{license_key}</code>, <code>{customer_name}</code>, <code>{order_id}</code>, <code>{product_name}</code>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('API Information', 'wp-licensing-manager'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('API Base URL', 'wp-licensing-manager'); ?></th>
                <td>
                    <code><?php echo esc_html(rest_url('licensing/v1/')); ?></code>
                    <p class="description"><?php esc_html_e('Use this URL for API calls from your premium plugins.', 'wp-licensing-manager'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Available Endpoints', 'wp-licensing-manager'); ?></h2>
        
        <div class="wp-licensing-endpoints">
            <div class="wp-licensing-endpoint">
                <h4><code>POST /validate</code></h4>
                <p><?php esc_html_e('Validate a license key for a specific product.', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Parameters:', 'wp-licensing-manager'); ?></strong>
                <ul>
                    <li><code>license_key</code> (required) - The license key to validate</li>
                    <li><code>product_slug</code> (optional) - Product slug to validate against</li>
                </ul>
            </div>

            <div class="wp-licensing-endpoint">
                <h4><code>POST /activate</code></h4>
                <p><?php esc_html_e('Activate a license for a specific domain.', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Parameters:', 'wp-licensing-manager'); ?></strong>
                <ul>
                    <li><code>license_key</code> (required) - The license key to activate</li>
                    <li><code>domain</code> (required) - Domain to activate the license on</li>
                    <li><code>product_slug</code> (optional) - Product slug</li>
                </ul>
            </div>

            <div class="wp-licensing-endpoint">
                <h4><code>POST /deactivate</code></h4>
                <p><?php esc_html_e('Deactivate a license for a specific domain.', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Parameters:', 'wp-licensing-manager'); ?></strong>
                <ul>
                    <li><code>license_key</code> (required) - The license key to deactivate</li>
                    <li><code>domain</code> (required) - Domain to deactivate the license from</li>
                    <li><code>product_slug</code> (optional) - Product slug</li>
                </ul>
            </div>

            <div class="wp-licensing-endpoint">
                <h4><code>POST /update-check</code></h4>
                <p><?php esc_html_e('Check for available updates for a licensed product.', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Parameters:', 'wp-licensing-manager'); ?></strong>
                <ul>
                    <li><code>license_key</code> (required) - Valid license key</li>
                    <li><code>product_slug</code> (required) - Product slug</li>
                    <li><code>current_version</code> (optional) - Current version installed</li>
                </ul>
            </div>

            <div class="wp-licensing-endpoint">
                <h4><code>GET /update-download</code></h4>
                <p><?php esc_html_e('Download the latest version of a licensed product.', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Parameters:', 'wp-licensing-manager'); ?></strong>
                <ul>
                    <li><code>license_key</code> (required) - Valid license key</li>
                    <li><code>product_slug</code> (required) - Product slug</li>
                </ul>
            </div>

            <div class="wp-licensing-endpoint">
                <h4><code>GET /stats</code></h4>
                <p><?php esc_html_e('Get licensing statistics (admin only).', 'wp-licensing-manager'); ?></p>
                <strong><?php esc_html_e('Authentication:', 'wp-licensing-manager'); ?></strong>
                <p><?php esc_html_e('Requires admin privileges.', 'wp-licensing-manager'); ?></p>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

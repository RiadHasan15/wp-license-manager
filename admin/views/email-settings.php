<?php
/**
 * Email Automation Settings Page
 * 
 * @package WP_Licensing_Manager
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$email_settings = get_option('wp_licensing_email_settings', array());
$email_templates = get_option('wp_licensing_email_templates', array());

// Merge with defaults
$default_settings = array(
    'from_name' => get_bloginfo('name'),
    'from_email' => get_option('admin_email'),
    'email_enabled' => true,
    'welcome_email_enabled' => true,
    'renewal_reminders_enabled' => true,
    'grace_period_emails_enabled' => true,
    'usage_tips_enabled' => true,
    'reminder_days' => array(30, 14, 7, 1),
    'usage_tips_schedule' => 7,
);

$settings = wp_parse_args($email_settings, $default_settings);

// Default templates
$default_templates = array(
    'welcome' => array(
        'subject' => __('Welcome! Your License is Ready', 'wp-licensing-manager'),
        'heading' => __('Welcome to {site_name}!', 'wp-licensing-manager'),
        'content' => __('Hi {customer_name},

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
The {site_name} Team', 'wp-licensing-manager'),
        'enabled' => true,
    ),
    'renewal_reminder' => array(
        'subject' => __('License Renewal Reminder - {days_until_expiry} days remaining', 'wp-licensing-manager'),
        'heading' => __('Your License Expires Soon', 'wp-licensing-manager'),
        'content' => __('Hi {customer_name},

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
The {site_name} Team', 'wp-licensing-manager'),
        'enabled' => true,
    ),
    'grace_period' => array(
        'subject' => __('License Expired - Grace Period Active', 'wp-licensing-manager'),
        'heading' => __('Grace Period Activated', 'wp-licensing-manager'),
        'content' => __('Hi {customer_name},

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
The {site_name} Team', 'wp-licensing-manager'),
        'enabled' => true,
    ),
    'usage_tips' => array(
        'subject' => __('Getting the Most from Your License', 'wp-licensing-manager'),
        'heading' => __('Tips to Maximize Your Experience', 'wp-licensing-manager'),
        'content' => __('Hi {customer_name},

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
The {site_name} Team', 'wp-licensing-manager'),
        'enabled' => true,
    ),
);

foreach ($default_templates as $type => $default_template) {
    if (!isset($email_templates[$type])) {
        $email_templates[$type] = $default_template;
    } else {
        $email_templates[$type] = wp_parse_args($email_templates[$type], $default_template);
    }
}
?>

<div class="wrap">
    <h1><?php _e('Email Automation Settings', 'wp-licensing-manager'); ?></h1>
    <p class="description"><?php _e('Configure automated email sequences for license lifecycle management. All emails are fully customizable and can be enabled/disabled individually.', 'wp-licensing-manager'); ?></p>

    <form method="post" action="" id="email-settings-form">
        <?php wp_nonce_field('wp_licensing_email_settings', 'wp_licensing_email_nonce'); ?>
        
        <nav class="nav-tab-wrapper">
            <a href="#general-settings" class="nav-tab nav-tab-active"><?php _e('General Settings', 'wp-licensing-manager'); ?></a>
            <a href="#welcome-email" class="nav-tab"><?php _e('Welcome Email', 'wp-licensing-manager'); ?></a>
            <a href="#renewal-reminders" class="nav-tab"><?php _e('Renewal Reminders', 'wp-licensing-manager'); ?></a>
            <a href="#grace-period" class="nav-tab"><?php _e('Grace Period', 'wp-licensing-manager'); ?></a>
            <a href="#usage-tips" class="nav-tab"><?php _e('Usage Tips', 'wp-licensing-manager'); ?></a>
            <a href="#email-logs" class="nav-tab"><?php _e('Email Logs', 'wp-licensing-manager'); ?></a>
        </nav>

        <!-- General Settings Tab -->
        <div id="general-settings" class="tab-content active">
            <h2><?php _e('General Email Settings', 'wp-licensing-manager'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_enabled"><?php _e('Enable Email Automation', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="email_enabled" name="email_enabled" value="1" <?php checked($settings['email_enabled']); ?>>
                        <p class="description"><?php _e('Master switch for all email automation. Uncheck to disable all automated emails.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="from_name"><?php _e('From Name', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($settings['from_name']); ?>" class="regular-text">
                        <p class="description"><?php _e('The name that appears in the "From" field of emails.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="from_email"><?php _e('From Email', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>" class="regular-text">
                        <p class="description"><?php _e('The email address that appears in the "From" field of emails.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Email Type Settings', 'wp-licensing-manager'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Welcome Emails', 'wp-licensing-manager'); ?></th>
                    <td>
                        <input type="checkbox" id="welcome_email_enabled" name="welcome_email_enabled" value="1" <?php checked($settings['welcome_email_enabled']); ?>>
                        <label for="welcome_email_enabled"><?php _e('Send welcome email when license is purchased', 'wp-licensing-manager'); ?></label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Renewal Reminders', 'wp-licensing-manager'); ?></th>
                    <td>
                        <input type="checkbox" id="renewal_reminders_enabled" name="renewal_reminders_enabled" value="1" <?php checked($settings['renewal_reminders_enabled']); ?>>
                        <label for="renewal_reminders_enabled"><?php _e('Send renewal reminder emails before expiry', 'wp-licensing-manager'); ?></label>
                        
                        <div class="renewal-settings" style="margin-top: 10px; <?php echo $settings['renewal_reminders_enabled'] ? '' : 'display: none;'; ?>">
                            <label><?php _e('Send reminders (days before expiry):', 'wp-licensing-manager'); ?></label><br>
                            <?php 
                            $reminder_days = $settings['reminder_days'];
                            $common_days = array(30, 14, 7, 3, 1);
                            foreach ($common_days as $day): 
                            ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" name="reminder_days[]" value="<?php echo $day; ?>" <?php checked(in_array($day, $reminder_days)); ?>>
                                <?php printf(_n('%d day', '%d days', $day, 'wp-licensing-manager'), $day); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Grace Period Emails', 'wp-licensing-manager'); ?></th>
                    <td>
                        <input type="checkbox" id="grace_period_emails_enabled" name="grace_period_emails_enabled" value="1" <?php checked($settings['grace_period_emails_enabled']); ?>>
                        <label for="grace_period_emails_enabled"><?php _e('Send grace period notification when license expires', 'wp-licensing-manager'); ?></label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Usage Tips', 'wp-licensing-manager'); ?></th>
                    <td>
                        <input type="checkbox" id="usage_tips_enabled" name="usage_tips_enabled" value="1" <?php checked($settings['usage_tips_enabled']); ?>>
                        <label for="usage_tips_enabled"><?php _e('Send usage tips email after purchase', 'wp-licensing-manager'); ?></label>
                        
                        <div class="usage-tips-settings" style="margin-top: 10px; <?php echo $settings['usage_tips_enabled'] ? '' : 'display: none;'; ?>">
                            <label for="usage_tips_schedule"><?php _e('Send tips email after:', 'wp-licensing-manager'); ?></label>
                            <input type="number" id="usage_tips_schedule" name="usage_tips_schedule" value="<?php echo esc_attr($settings['usage_tips_schedule']); ?>" min="0" max="30" style="width: 60px;">
                            <?php _e('days', 'wp-licensing-manager'); ?>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="email-variables-help">
                <h3><?php _e('Available Variables', 'wp-licensing-manager'); ?></h3>
                <p class="description"><?php _e('You can use these variables in your email templates. They will be automatically replaced with actual values:', 'wp-licensing-manager'); ?></p>
                <div class="variables-grid">
                    <div class="variable-item">
                        <code>{site_name}</code> - <?php _e('Your site name', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{customer_name}</code> - <?php _e('Customer\'s name', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{customer_email}</code> - <?php _e('Customer\'s email', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{product_name}</code> - <?php _e('Product name', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{license_key}</code> - <?php _e('License key', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{expires_at}</code> - <?php _e('Expiration date', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{status}</code> - <?php _e('License status', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{activations}</code> - <?php _e('Current activations', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{max_activations}</code> - <?php _e('Maximum activations', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{my_account_url}</code> - <?php _e('Customer account URL', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{downloads_url}</code> - <?php _e('Downloads page URL', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{days_until_expiry}</code> - <?php _e('Days until expiry (renewal reminders)', 'wp-licensing-manager'); ?>
                    </div>
                    <div class="variable-item">
                        <code>{grace_period_days}</code> - <?php _e('Grace period days (grace period emails)', 'wp-licensing-manager'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Template Tabs -->
        <?php foreach ($email_templates as $type => $template): ?>
        <div id="<?php echo str_replace('_', '-', $type); ?>-email" class="tab-content">
            <h2><?php echo esc_html($template['subject']); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="template_<?php echo $type; ?>_enabled"><?php _e('Enable This Email', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="template_<?php echo $type; ?>_enabled" name="template_<?php echo $type; ?>_enabled" value="1" <?php checked($template['enabled']); ?>>
                        <p class="description"><?php _e('Check to enable this email template.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="template_<?php echo $type; ?>_subject"><?php _e('Email Subject', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="template_<?php echo $type; ?>_subject" name="template_<?php echo $type; ?>_subject" value="<?php echo esc_attr($template['subject']); ?>" class="large-text">
                        <p class="description"><?php _e('The subject line for this email. You can use variables like {customer_name} and {product_name}.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="template_<?php echo $type; ?>_heading"><?php _e('Email Heading', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="template_<?php echo $type; ?>_heading" name="template_<?php echo $type; ?>_heading" value="<?php echo esc_attr($template['heading']); ?>" class="large-text">
                        <p class="description"><?php _e('The main heading that appears in the email header.', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="template_<?php echo $type; ?>_content"><?php _e('Email Content', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor($template['content'], 'template_' . $type . '_content', array(
                            'textarea_name' => 'template_' . $type . '_content',
                            'textarea_rows' => 15,
                            'teeny' => false,
                            'media_buttons' => false,
                            'tinymce' => array(
                                'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                                'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,|,code',
                            ),
                        ));
                        ?>
                        <p class="description"><?php _e('The main content of your email. You can use HTML and all the available variables.', 'wp-licensing-manager'); ?></p>
                        
                        <div class="email-actions" style="margin-top: 15px;">
                            <button type="button" class="button button-secondary preview-email" data-template="<?php echo $type; ?>">
                                <?php _e('Preview Email', 'wp-licensing-manager'); ?>
                            </button>
                            <button type="button" class="button button-secondary test-email" data-template="<?php echo $type; ?>">
                                <?php _e('Send Test Email', 'wp-licensing-manager'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- Email Logs Tab -->
        <div id="email-logs" class="tab-content">
            <h2><?php _e('Email Logs', 'wp-licensing-manager'); ?></h2>
            <p class="description"><?php _e('Recent email activities and delivery status.', 'wp-licensing-manager'); ?></p>
            
            <?php
            $email_logs = get_option('wp_licensing_email_general_log', array());
            $email_logs = array_reverse(array_slice($email_logs, -50)); // Show last 50 emails
            ?>
            
            <?php if (empty($email_logs)): ?>
                <p><?php _e('No email logs found. Emails will appear here once the automation system starts sending them.', 'wp-licensing-manager'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date/Time', 'wp-licensing-manager'); ?></th>
                            <th><?php _e('Email Type', 'wp-licensing-manager'); ?></th>
                            <th><?php _e('Recipient', 'wp-licensing-manager'); ?></th>
                            <th><?php _e('License ID', 'wp-licensing-manager'); ?></th>
                            <th><?php _e('Details', 'wp-licensing-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($email_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['sent_at']))); ?></td>
                            <td>
                                <span class="email-type-badge email-type-<?php echo esc_attr($log['type']); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $log['type']))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['email']); ?></td>
                            <td>#<?php echo esc_html($log['license_id']); ?></td>
                            <td>
                                <?php if (!empty($log['meta'])): ?>
                                    <?php foreach ($log['meta'] as $key => $value): ?>
                                        <small><?php echo esc_html($key); ?>: <?php echo esc_html($value); ?></small><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <small class="description"><?php _e('No additional details', 'wp-licensing-manager'); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="description">
                    <?php printf(__('Showing last %d email activities. Logs are automatically cleaned up to maintain performance.', 'wp-licensing-manager'), count($email_logs)); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php submit_button(__('Save Email Settings', 'wp-licensing-manager')); ?>
    </form>
</div>

<!-- Email Preview Modal -->
<div id="email-preview-modal" class="email-modal" style="display: none;">
    <div class="email-modal-content">
        <div class="email-modal-header">
            <h3><?php _e('Email Preview', 'wp-licensing-manager'); ?></h3>
            <button type="button" class="email-modal-close">&times;</button>
        </div>
        <div class="email-modal-body">
            <iframe id="email-preview-iframe" style="width: 100%; height: 500px; border: none;"></iframe>
        </div>
    </div>
</div>

<style>
/* Email Settings Styling */
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.variables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
    margin-top: 10px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}

.variable-item {
    padding: 8px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #667eea;
}

.variable-item code {
    background: #667eea;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
}

.email-actions {
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid #667eea;
}

.email-actions .button {
    margin-right: 10px;
}

.renewal-settings, .usage-tips-settings {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
    margin-top: 10px;
}

.email-type-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.email-type-welcome {
    background: #d4edda;
    color: #155724;
}

.email-type-renewal-reminder {
    background: #fff3cd;
    color: #856404;
}

.email-type-grace-period {
    background: #f8d7da;
    color: #721c24;
}

.email-type-usage-tips {
    background: #d1ecf1;
    color: #0c5460;
}

/* Email Modal Styles */
.email-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.email-modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 90%;
    display: flex;
    flex-direction: column;
}

.email-modal-header {
    padding: 20px;
    border-bottom: 1px solid #e8ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.email-modal-header h3 {
    margin: 0;
}

.email-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.email-modal-close:hover {
    background: #f8f9fa;
}

.email-modal-body {
    padding: 20px;
    flex: 1;
    overflow: auto;
}

@media (max-width: 768px) {
    .variables-grid {
        grid-template-columns: 1fr;
    }
    
    .email-modal-content {
        width: 95%;
        max-height: 95%;
    }
    
    .email-actions .button {
        display: block;
        width: 100%;
        margin-bottom: 10px;
        margin-right: 0;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update tab states
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        $('.tab-content').removeClass('active');
        var target = $(this).attr('href');
        $(target).addClass('active');
    });
    
    // Toggle renewal settings visibility
    $('#renewal_reminders_enabled').on('change', function() {
        $('.renewal-settings').toggle(this.checked);
    });
    
    // Toggle usage tips settings visibility
    $('#usage_tips_enabled').on('change', function() {
        $('.usage-tips-settings').toggle(this.checked);
    });
    
    // Preview email functionality
    $('.preview-email').on('click', function() {
        var templateType = $(this).data('template');
        var content = '';
        var heading = '';
        
        // Get content from editor
        if (typeof tinyMCE !== 'undefined') {
            var editor = tinyMCE.get('template_' + templateType + '_content');
            if (editor) {
                content = editor.getContent();
            } else {
                content = $('#template_' + templateType + '_content').val();
            }
        } else {
            content = $('#template_' + templateType + '_content').val();
        }
        
        heading = $('#template_' + templateType + '_heading').val();
        
        // Send AJAX request for preview
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_licensing_preview_email',
                template_type: templateType,
                content: content,
                heading: heading,
                nonce: wpLicensingEmailAdmin.nonce
            },
            success: function(response) {
                // Show modal with preview
                $('#email-preview-iframe').attr('srcdoc', response);
                $('#email-preview-modal').show();
            },
            error: function() {
                alert('<?php _e('Failed to generate preview.', 'wp-licensing-manager'); ?>');
            }
        });
    });
    
    // Test email functionality
    $('.test-email').on('click', function() {
        if (!confirm(wpLicensingEmailAdmin.strings.confirm_test)) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        var templateType = button.data('template');
        
        button.text('<?php _e('Sending...', 'wp-licensing-manager'); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_licensing_test_email',
                template_type: templateType,
                nonce: wpLicensingEmailAdmin.nonce
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    alert(wpLicensingEmailAdmin.strings.test_email_sent);
                } else {
                    alert(wpLicensingEmailAdmin.strings.test_email_failed + ' ' + (data.message || ''));
                }
            },
            error: function() {
                alert(wpLicensingEmailAdmin.strings.test_email_failed);
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Close modal functionality
    $('.email-modal-close, .email-modal').on('click', function(e) {
        if (e.target === this) {
            $('#email-preview-modal').hide();
        }
    });
    
    // Prevent modal close when clicking inside modal content
    $('.email-modal-content').on('click', function(e) {
        e.stopPropagation();
    });
});
</script>
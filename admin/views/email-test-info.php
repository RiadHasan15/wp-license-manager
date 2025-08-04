<?php
/**
 * Email System Test Information
 * 
 * Simple instructions for testing the email automation system.
 * 
 * @package WP_Licensing_Manager
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Email Automation System - Test Instructions', 'wp-licensing-manager'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('Email Automation System Successfully Installed!', 'wp-licensing-manager'); ?></strong></p>
        <p><?php _e('The email automation system has been safely integrated without breaking any existing functionality.', 'wp-licensing-manager'); ?></p>
    </div>

    <div class="card">
        <h2><?php _e('How to Test the Email System', 'wp-licensing-manager'); ?></h2>
        
        <h3><?php _e('1. Configure Email Settings', 'wp-licensing-manager'); ?></h3>
        <ul>
            <li><?php _e('Go to WP Licensing Manager → Email Automation', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Configure your email settings (From Name, From Email)', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Enable the email types you want to test', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Customize email templates if desired', 'wp-licensing-manager'); ?></li>
        </ul>

        <h3><?php _e('2. Test Individual Templates', 'wp-licensing-manager'); ?></h3>
        <ul>
            <li><?php _e('In each email template tab, click "Send Test Email"', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Check your admin email for the test message', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Use "Preview Email" to see how it will look', 'wp-licensing-manager'); ?></li>
        </ul>

        <h3><?php _e('3. Test Live Email Triggers', 'wp-licensing-manager'); ?></h3>
        <ol>
            <li><strong><?php _e('Welcome Email:', 'wp-licensing-manager'); ?></strong> <?php _e('Complete a test order with a licensed product', 'wp-licensing-manager'); ?></li>
            <li><strong><?php _e('Usage Tips:', 'wp-licensing-manager'); ?></strong> <?php _e('Will be sent automatically based on your schedule setting', 'wp-licensing-manager'); ?></li>
            <li><strong><?php _e('Renewal Reminders:', 'wp-licensing-manager'); ?></strong> <?php _e('Will be sent daily for licenses expiring in your configured days', 'wp-licensing-manager'); ?></li>
            <li><strong><?php _e('Grace Period:', 'wp-licensing-manager'); ?></strong> <?php _e('Will be sent when licenses expire', 'wp-licensing-manager'); ?></li>
        </ol>

        <h3><?php _e('4. Monitor Email Activity', 'wp-licensing-manager'); ?></h3>
        <ul>
            <li><?php _e('Go to Email Automation → Email Logs tab', 'wp-licensing-manager'); ?></li>
            <li><?php _e('View sent emails and delivery status', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Check your server error logs for any email-related errors', 'wp-licensing-manager'); ?></li>
        </ul>
    </div>

    <div class="card">
        <h2><?php _e('Available Email Variables', 'wp-licensing-manager'); ?></h2>
        <p><?php _e('Use these variables in your email templates:', 'wp-licensing-manager'); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{site_name}</strong> - <?php _e('Your website name', 'wp-licensing-manager'); ?>
            </div>
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{customer_name}</strong> - <?php _e('Customer\'s name', 'wp-licensing-manager'); ?>
            </div>
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{product_name}</strong> - <?php _e('Licensed product name', 'wp-licensing-manager'); ?>
            </div>
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{license_key}</strong> - <?php _e('The license key', 'wp-licensing-manager'); ?>
            </div>
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{expires_at}</strong> - <?php _e('License expiration date', 'wp-licensing-manager'); ?>
            </div>
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>{my_account_url}</strong> - <?php _e('Customer account URL', 'wp-licensing-manager'); ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h2><?php _e('Production Safety Features', 'wp-licensing-manager'); ?></h2>
        <ul>
            <li>✅ <?php _e('Backward compatible - existing functionality unchanged', 'wp-licensing-manager'); ?></li>
            <li>✅ <?php _e('Master enable/disable switch for all emails', 'wp-licensing-manager'); ?></li>
            <li>✅ <?php _e('Individual email type enable/disable controls', 'wp-licensing-manager'); ?></li>
            <li>✅ <?php _e('Built-in email logging and monitoring', 'wp-licensing-manager'); ?></li>
            <li>✅ <?php _e('Graceful fallbacks for failed emails', 'wp-licensing-manager'); ?></li>
            <li>✅ <?php _e('No database schema changes required', 'wp-licensing-manager'); ?></li>
        </ul>
    </div>

    <div class="card">
        <h2><?php _e('Troubleshooting', 'wp-licensing-manager'); ?></h2>
        
        <h3><?php _e('Emails Not Sending?', 'wp-licensing-manager'); ?></h3>
        <ol>
            <li><?php _e('Check Email Automation → General Settings → "Enable Email Automation" is checked', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Verify individual email types are enabled', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Test your WordPress email functionality with a simple test email', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Check your server error logs for email-related errors', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Verify your From Email address is valid and configured', 'wp-licensing-manager'); ?></li>
        </ol>

        <h3><?php _e('Variables Not Replacing?', 'wp-licensing-manager'); ?></h3>
        <ol>
            <li><?php _e('Ensure you\'re using curly braces: {variable_name}', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Check variable names match exactly (case-sensitive)', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Test with "Preview Email" to see variable replacement', 'wp-licensing-manager'); ?></li>
        </ol>

        <h3><?php _e('Need Support?', 'wp-licensing-manager'); ?></h3>
        <p><?php _e('Check the Email Logs tab for delivery status and error messages. All email activities are logged for troubleshooting.', 'wp-licensing-manager'); ?></p>
    </div>
</div>

<style>
.card {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.card h2 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #667eea;
    padding-bottom: 10px;
}

.card h3 {
    color: #555;
    margin-top: 25px;
}

.card ul, .card ol {
    line-height: 1.6;
}

.card li {
    margin-bottom: 8px;
}
</style>
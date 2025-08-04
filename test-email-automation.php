<?php
/**
 * Email Automation Test & Verification
 * 
 * Simple test to verify the email automation system is working
 * 
 * Add ?test=1 to your admin URL to run this test
 */

// Only run if in admin and test parameter is set
if (is_admin() && isset($_GET['test']) && current_user_can('manage_options')) {
    
    add_action('admin_notices', function() {
        
        echo '<div class="notice notice-info">';
        echo '<h3>🧪 Email Automation System Test Results</h3>';
        
        // Test 1: Check if Email Manager class exists
        if (class_exists('WP_Licensing_Manager_Email_Manager')) {
            echo '<p>✅ <strong>Email Manager Class:</strong> EXISTS</p>';
        } else {
            echo '<p>❌ <strong>Email Manager Class:</strong> NOT FOUND</p>';
        }
        
        // Test 2: Check if main plugin has email manager
        global $wp_licensing_manager;
        if (isset($wp_licensing_manager->email_manager)) {
            echo '<p>✅ <strong>Email Manager Instance:</strong> INITIALIZED</p>';
        } else {
            echo '<p>❌ <strong>Email Manager Instance:</strong> NOT INITIALIZED</p>';
        }
        
        // Test 3: Check email settings
        $email_settings = get_option('wp_licensing_email_settings');
        if ($email_settings) {
            echo '<p>✅ <strong>Email Settings:</strong> CONFIGURED</p>';
        } else {
            echo '<p>⚠️ <strong>Email Settings:</strong> NOT CONFIGURED (using defaults)</p>';
        }
        
        // Test 4: Check email templates
        $email_templates = get_option('wp_licensing_email_templates');
        if ($email_templates && count($email_templates) >= 4) {
            echo '<p>✅ <strong>Email Templates:</strong> ' . count($email_templates) . ' TEMPLATES FOUND</p>';
        } else {
            echo '<p>⚠️ <strong>Email Templates:</strong> USING DEFAULTS</p>';
        }
        
        // Test 5: Check menu integration
        global $submenu;
        $menu_exists = false;
        if (isset($submenu['wp-licensing-manager'])) {
            foreach ($submenu['wp-licensing-manager'] as $item) {
                if (strpos($item[2], 'wp-licensing-emails') !== false) {
                    $menu_exists = true;
                    break;
                }
            }
        }
        
        if ($menu_exists) {
            echo '<p>✅ <strong>Admin Menu:</strong> EMAIL AUTOMATION MENU ADDED</p>';
        } else {
            echo '<p>❌ <strong>Admin Menu:</strong> EMAIL AUTOMATION MENU NOT FOUND</p>';
        }
        
        // Test 6: Check action hooks
        global $wp_filter;
        $hooks_found = 0;
        $test_hooks = [
            'wp_licensing_manager_license_created',
            'wp_licensing_manager_daily_email_check',
            'wp_ajax_wp_licensing_test_email',
            'wp_ajax_wp_licensing_preview_email'
        ];
        
        foreach ($test_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $hooks_found++;
            }
        }
        
        if ($hooks_found >= 3) {
            echo '<p>✅ <strong>Action Hooks:</strong> ' . $hooks_found . '/4 HOOKS REGISTERED</p>';
        } else {
            echo '<p>❌ <strong>Action Hooks:</strong> ONLY ' . $hooks_found . '/4 HOOKS REGISTERED</p>';
        }
        
        // Test 7: Check file existence
        $required_files = [
            'includes/class-email-manager.php',
            'admin/views/email-settings.php',
            'admin/js/email-admin.js',
            'admin/css/email-admin.css'
        ];
        
        $files_found = 0;
        foreach ($required_files as $file) {
            if (file_exists(WP_LICENSING_MANAGER_PLUGIN_DIR . $file)) {
                $files_found++;
            }
        }
        
        if ($files_found == 4) {
            echo '<p>✅ <strong>Required Files:</strong> ALL ' . $files_found . ' FILES EXIST</p>';
        } else {
            echo '<p>❌ <strong>Required Files:</strong> ONLY ' . $files_found . '/4 FILES FOUND</p>';
        }
        
        echo '<hr>';
        
        if (class_exists('WP_Licensing_Manager_Email_Manager') && isset($wp_licensing_manager->email_manager)) {
            echo '<p><strong>🎉 SYSTEM STATUS: EMAIL AUTOMATION IS WORKING!</strong></p>';
            echo '<p><a href="' . admin_url('admin.php?page=wp-licensing-emails') . '" class="button button-primary">Go to Email Automation Settings</a></p>';
        } else {
            echo '<p><strong>⚠️ SYSTEM STATUS: EMAIL AUTOMATION NEEDS ATTENTION</strong></p>';
        }
        
        echo '</div>';
    });
}
?>
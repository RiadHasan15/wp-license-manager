<?php
/**
 * Temporary Email Automation Verification
 * This file helps debug the tab structure issue
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo '<div class="notice notice-info">';
echo '<h3>Email Automation Tab Verification</h3>';

// Check if email templates are loaded
$email_templates = get_option('wp_licensing_email_templates', array());
echo '<p><strong>Email Templates in Database:</strong> ' . count($email_templates) . '</p>';

if (!empty($email_templates)) {
    echo '<ul>';
    foreach ($email_templates as $type => $template) {
        $tab_id = str_replace('_', '-', $type) . '-email';
        echo '<li><strong>' . $type . '</strong> → Tab ID: <code>' . $tab_id . '</code> (Subject: ' . esc_html($template['subject']) . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;">❌ No email templates found in database!</p>';
}

// Check if email manager is loaded
global $wp_licensing_manager;
if (isset($wp_licensing_manager) && $wp_licensing_manager->email_manager) {
    echo '<p>✅ Email Manager is loaded</p>';
    
    // Try to get templates from manager
    $reflection = new ReflectionClass($wp_licensing_manager->email_manager);
    $property = $reflection->getProperty('email_templates');
    $property->setAccessible(true);
    $manager_templates = $property->getValue($wp_licensing_manager->email_manager);
    
    echo '<p><strong>Templates in Manager:</strong> ' . count($manager_templates) . '</p>';
    if (!empty($manager_templates)) {
        echo '<ul>';
        foreach ($manager_templates as $type => $template) {
            echo '<li><strong>' . $type . '</strong> (Subject: ' . esc_html($template['subject']) . ')</li>';
        }
        echo '</ul>';
    }
} else {
    echo '<p style="color: red;">❌ Email Manager not loaded</p>';
}

echo '</div>';
?>
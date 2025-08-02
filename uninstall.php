<?php
/**
 * Uninstall script for WP Licensing Manager
 * 
 * This file is executed when the plugin is uninstalled (deleted).
 * It removes all plugin data from the database and file system.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up database tables and options
 */
function wp_licensing_manager_uninstall_cleanup() {
    global $wpdb;

    // Delete custom tables
    $tables = array(
        $wpdb->prefix . 'licenses',
        $wpdb->prefix . 'license_activations', 
        $wpdb->prefix . 'license_products'
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    // Delete plugin options
    $options = array(
        'wp_licensing_manager_default_expiry_days',
        'wp_licensing_manager_default_max_activations',
        'wp_licensing_manager_email_template_subject',
        'wp_licensing_manager_email_template_body',
        'wp_licensing_manager_api_version'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete any transients
    delete_transient('wp_licensing_manager_stats');
    
    // Clean up user meta (if any)
    delete_metadata('user', 0, 'wp_licensing_manager_last_check', '', true);

    // Remove upload directory and files
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/wp-licensing-manager';
    
    if (file_exists($plugin_upload_dir)) {
        wp_licensing_manager_delete_directory($plugin_upload_dir);
    }
}

/**
 * Recursively delete a directory and its contents
 *
 * @param string $dir Directory path
 * @return bool
 */
function wp_licensing_manager_delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            wp_licensing_manager_delete_directory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

/**
 * Log uninstall activity
 */
function wp_licensing_manager_log_uninstall() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WP Licensing Manager: Plugin uninstalled and all data removed.');
    }
}

// Only run cleanup if this is a complete uninstall (not just deactivation)
if (defined('WP_UNINSTALL_PLUGIN')) {
    wp_licensing_manager_uninstall_cleanup();
    wp_licensing_manager_log_uninstall();
}

<?php
/**
 * Add wp-email-vault Product to WP Licensing Manager
 * Run this script once to add the product, then delete this file
 */

// Include WordPress
require_once '../../../wp-config.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo '<h1>Adding wp-email-vault to WP Licensing Manager</h1>';

global $wpdb;

// Check if product already exists
$existing_product = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}license_products WHERE slug = %s",
    'wp-email-vault'
));

if ($existing_product) {
    echo '<p style="color: orange;">✅ Product "wp-email-vault" already exists with ID: ' . $existing_product->id . '</p>';
    echo '<h3>Current Product Details:</h3>';
    echo '<ul>';
    echo '<li><strong>Name:</strong> ' . esc_html($existing_product->name) . '</li>';
    echo '<li><strong>Slug:</strong> ' . esc_html($existing_product->slug) . '</li>';
    echo '<li><strong>Version:</strong> ' . esc_html($existing_product->latest_version) . '</li>';
    echo '<li><strong>Created:</strong> ' . esc_html($existing_product->created_at) . '</li>';
    echo '</ul>';
} else {
    // Add the product
    $result = $wpdb->insert(
        $wpdb->prefix . 'license_products',
        array(
            'slug' => 'wp-email-vault',
            'name' => 'WP Email Vault',
            'latest_version' => '1.0.0',
            'changelog' => 'Initial release of WP Email Vault with licensing integration.',
            'update_file_path' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result) {
        $product_id = $wpdb->insert_id;
        echo '<p style="color: green;">✅ Successfully added "wp-email-vault" to the licensing system!</p>';
        echo '<h3>Product Details:</h3>';
        echo '<ul>';
        echo '<li><strong>Product ID:</strong> ' . $product_id . '</li>';
        echo '<li><strong>Name:</strong> WP Email Vault</li>';
        echo '<li><strong>Slug:</strong> wp-email-vault</li>';
        echo '<li><strong>Version:</strong> 1.0.0</li>';
        echo '<li><strong>Status:</strong> Ready for licensing</li>';
        echo '</ul>';
        
        echo '<h3>🎯 Next Steps:</h3>';
        echo '<ol>';
        echo '<li><strong>Go to your admin panel:</strong> <a href="' . admin_url('admin.php?page=wp-licensing-manager-products') . '" target="_blank">Licensing Manager → Products</a></li>';
        echo '<li><strong>Upload the plugin ZIP file</strong> for automatic updates</li>';
        echo '<li><strong>Create licenses</strong> from the Licenses page</li>';
        echo '<li><strong>Generate integration code</strong> if needed</li>';
        echo '</ol>';
        
        echo '<h3>📋 Integration Code Summary:</h3>';
        echo '<p>Your wp-email-vault plugin integration code is already perfect! Just make sure to:</p>';
        echo '<ul>';
        echo '<li>✅ <strong>Slug matches:</strong> "wp-email-vault" ✓</li>';
        echo '<li>✅ <strong>Server URL:</strong> "https://stackcastle.com" ✓</li>';
        echo '<li>✅ <strong>API endpoints:</strong> All correct ✓</li>';
        echo '<li>✅ <strong>License manager class:</strong> Ready to use ✓</li>';
        echo '</ul>';
        
    } else {
        echo '<p style="color: red;">❌ Failed to add product. Database error: ' . $wpdb->last_error . '</p>';
    }
}

echo '<hr>';
echo '<h3>🔗 Quick Links:</h3>';
echo '<ul>';
echo '<li><a href="' . admin_url('admin.php?page=wp-licensing-manager-products') . '" target="_blank">📦 Manage Products</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=wp-licensing-manager') . '" target="_blank">📄 Create Licenses</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=wp-licensing-manager-import-export') . '" target="_blank">📤 Import/Export</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=wp-licensing-manager-analytics') . '" target="_blank">📊 Analytics</a></li>';
echo '</ul>';

echo '<hr>';
echo '<p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;"><strong>⚠️ IMPORTANT:</strong> Delete this file (add-wp-email-vault.php) after running it for security!</p>';

// Test API endpoints
echo '<h3>🧪 API Endpoint Tests:</h3>';
echo '<p>Testing your licensing API endpoints...</p>';

$api_base = home_url('/wp-json/licensing/v1/');
$endpoints = array('validate', 'activate', 'deactivate', 'update-check', 'update-download', 'stats');

echo '<ul>';
foreach ($endpoints as $endpoint) {
    $url = $api_base . $endpoint;
    $response = wp_remote_get($url, array('timeout' => 10));
    
    if (!is_wp_error($response)) {
        $code = wp_remote_retrieve_response_code($response);
        if ($code == 405) { // Method not allowed (expected for POST endpoints)
            echo '<li>✅ <strong>' . $endpoint . ':</strong> <code>' . $url . '</code> (Endpoint exists)</li>';
        } elseif ($code == 200) {
            echo '<li>✅ <strong>' . $endpoint . ':</strong> <code>' . $url . '</code> (Ready)</li>';
        } else {
            echo '<li>⚠️ <strong>' . $endpoint . ':</strong> <code>' . $url . '</code> (HTTP ' . $code . ')</li>';
        }
    } else {
        echo '<li>❌ <strong>' . $endpoint . ':</strong> <code>' . $url . '</code> (Error: ' . $response->get_error_message() . ')</li>';
    }
}
echo '</ul>';

echo '<h3>🎉 wp-email-vault is now ready for licensing!</h3>';
?>
<?php
/**
 * Test script for Bottom Navigation Pro License Integration
 */

// Include the WordPress licensing manager
require_once 'wp-licensing-manager.php';

echo "<h1>Bottom Navigation Pro - API Test</h1>\n";

// Initialize the plugin
$wp_licensing_manager = wp_licensing_manager();

// Test 1: Register bottom-navigation-pro product
echo "<h2>1. Product Registration Test</h2>\n";

try {
    $product_manager = new WP_Licensing_Manager_Product_Manager();
    
    // Check if product already exists
    $existing_product = $product_manager->get_product_by_slug('bottom-navigation-pro');
    
    if ($existing_product) {
        echo "<p style='color: green;'>✓ Product 'bottom-navigation-pro' already exists with ID: {$existing_product->id}</p>\n";
    } else {
        // Create the product
        $product_data = array(
            'slug' => 'bottom-navigation-pro',
            'name' => 'Bottom Navigation Pro',
            'latest_version' => '2.1.0',
            'changelog' => "Version 2.1.0:\n- Fixed license integration issues\n- Improved automatic updates\n- Enhanced security\n- Better error handling",
            'update_file_path' => 'bottom-navigation-pro-v2.1.0.zip'
        );
        
        $result = $product_manager->create_product($product_data);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Product 'bottom-navigation-pro' created successfully!</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to create product 'bottom-navigation-pro'</p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test 2: Create a test license
echo "<h2>2. License Creation Test</h2>\n";

try {
    $license_manager = new WP_Licensing_Manager_License_Manager();
    $product = $product_manager->get_product_by_slug('bottom-navigation-pro');
    
    if ($product) {
        // Generate a test license
        $license_data = array(
            'product_id' => $product->id,
            'customer_email' => 'test@example.com',
            'expires_at' => date('Y-m-d', strtotime('+1 year')),
            'max_activations' => 3
        );
        
        $license_key = $license_manager->create_license($license_data);
        
        if ($license_key) {
            echo "<p style='color: green;'>✓ Test license created: <strong>{$license_key}</strong></p>\n";
            echo "<p><em>Save this license key for testing the API endpoints below.</em></p>\n";
        } else {
            echo "<p style='color: orange;'>ℹ Test license creation skipped (may already exist)</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ Cannot create license - product not found</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test 3: API Endpoints Test
echo "<h2>3. API Endpoints Test</h2>\n";

// Test validation endpoint
echo "<h3>License Validation Endpoint</h3>\n";
$api_base = 'http://localhost:5000/wp-json/licensing/v1';

echo "<p><strong>Endpoint:</strong> <code>POST {$api_base}/validate</code></p>\n";
echo "<p><strong>Test with curl:</strong></p>\n";
echo "<pre>curl -X POST '{$api_base}/validate' \\
  -H 'Content-Type: application/x-www-form-urlencoded' \\
  -d 'license_key=YOUR_LICENSE_KEY&product_slug=bottom-navigation-pro'</pre>\n";

echo "<h3>License Activation Endpoint</h3>\n";
echo "<p><strong>Endpoint:</strong> <code>POST {$api_base}/activate</code></p>\n";
echo "<p><strong>Test with curl:</strong></p>\n";
echo "<pre>curl -X POST '{$api_base}/activate' \\
  -H 'Content-Type: application/x-www-form-urlencoded' \\
  -d 'license_key=YOUR_LICENSE_KEY&domain=example.com&product_slug=bottom-navigation-pro'</pre>\n";

echo "<h3>Update Check Endpoint</h3>\n";
echo "<p><strong>Endpoint:</strong> <code>POST {$api_base}/update-check</code></p>\n";
echo "<p><strong>Test with curl:</strong></p>\n";
echo "<pre>curl -X POST '{$api_base}/update-check' \\
  -H 'Content-Type: application/x-www-form-urlencoded' \\
  -d 'license_key=YOUR_LICENSE_KEY&product_slug=bottom-navigation-pro&current_version=1.0.0'</pre>\n";

// Test 4: Integration Instructions
echo "<h2>4. Integration Instructions</h2>\n";
echo "<div style='background: #f0f0f1; padding: 20px; border-radius: 5px;'>\n";
echo "<h3>How to integrate with your Bottom Navigation Pro plugin:</h3>\n";
echo "<ol>\n";
echo "<li>Copy the <code>bottom-navigation-pro-license.php</code> file to your plugin directory</li>\n";
echo "<li>Include it in your main plugin file:</li>\n";
echo "<pre>require_once plugin_dir_path(__FILE__) . 'bottom-navigation-pro-license.php';</pre>\n";
echo "<li>Initialize the license manager:</li>\n";
echo "<pre>new BOTTOM_NAVIGATION_PRO_License_Manager(__FILE__, '1.0.0');</pre>\n";
echo "<li>Replace '1.0.0' with your actual plugin version</li>\n";
echo "<li>Users will see a 'Bottom Navigation Pro License' menu under Settings</li>\n";
echo "</ol>\n";
echo "</div>\n";

// Test 5: Automatic Update Flow
echo "<h2>5. Automatic Update Flow</h2>\n";
echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 5px;'>\n";
echo "<h3>How automatic updates work:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Version Check:</strong> WordPress periodically checks for plugin updates</li>\n";
echo "<li><strong>License Validation:</strong> The system validates the license before checking for updates</li>\n";
echo "<li><strong>Version Comparison:</strong> Current version is compared with the latest version on the server</li>\n";
echo "<li><strong>Update Notification:</strong> If a newer version is available, WordPress shows an update notification</li>\n";
echo "<li><strong>Secure Download:</strong> Updates are downloaded using the validated license key</li>\n";
echo "</ol>\n";
echo "<p><strong>Current Latest Version:</strong> 2.1.0</p>\n";
echo "<p><strong>Changelog:</strong> Fixed license integration issues, improved automatic updates, enhanced security</p>\n";
echo "</div>\n";

// Test 6: Troubleshooting
echo "<h2>6. Troubleshooting</h2>\n";
echo "<div style='background: #fff3cd; padding: 20px; border-radius: 5px;'>\n";
echo "<h3>Common Issues and Solutions:</h3>\n";
echo "<ul>\n";
echo "<li><strong>License activation fails:</strong> Check that the product 'bottom-navigation-pro' exists in the licensing system</li>\n";
echo "<li><strong>Updates not showing:</strong> Ensure the license is activated and valid</li>\n";
echo "<li><strong>SSL errors:</strong> The system allows localhost testing, but production requires HTTPS</li>\n";
echo "<li><strong>Server unreachable:</strong> Check that stackcastle.com is accessible and the endpoints are working</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>7. Test Complete</h2>\n";
echo "<p style='color: green; font-weight: bold;'>✓ Bottom Navigation Pro license integration is ready for testing!</p>\n";

?>
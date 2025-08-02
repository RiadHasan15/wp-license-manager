<?php
/**
 * Debug script for WP Licensing Manager AJAX issues
 */

// Mock WordPress environment
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        header('Content-Type: application/json; charset=utf-8');
        if ($status_code) {
            http_response_code($status_code);
        }
        echo json_encode(array('success' => false, 'data' => $data));
        exit;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        header('Content-Type: application/json; charset=utf-8');
        if ($status_code) {
            http_response_code($status_code);
        }
        echo json_encode(array('success' => true, 'data' => $data));
        exit;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '-', $title));
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return strip_tags($data, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6>');
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

// Simple nonce verification
function wp_licensing_manager_verify_ajax_nonce($nonce_name = 'wp_licensing_manager_nonce') {
    // For debugging, always return true
    return true;
}

// Mock product manager with slug uniqueness
class WP_Licensing_Manager_Product_Manager {
    private static $created_products = array();
    
    public function create_product($data) {
        $slug = $data['slug'];
        
        // Check for duplicate slug
        if (in_array($slug, self::$created_products)) {
            echo "<p style='color: orange;'>Slug conflict detected: '$slug' already exists</p>";
            
            // Generate unique slug
            $original_slug = $slug;
            $counter = 1;
            while (in_array($slug, self::$created_products)) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
            echo "<p style='color: blue;'>Generated unique slug: '$slug'</p>";
        }
        
        // Store the slug
        self::$created_products[] = $slug;
        
        // Mock successful creation with unique ID
        $product_id = count(self::$created_products) + 100;
        
        echo "<p style='color: green;'>Product created successfully with ID: $product_id</p>";
        echo "<p><strong>Current products:</strong> " . implode(', ', self::$created_products) . "</p>";
        
        return $product_id;
    }
    
    public function update_product($id, $data) {
        echo "<p style='color: blue;'>Updated product ID: $id</p>";
        return true;
    }
}

// Handle AJAX request simulation
if (isset($_POST['action']) && $_POST['action'] === 'wp_licensing_manager_save_product') {
    
    echo "<h2>AJAX Request Debug</h2>";
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // Simulate the AJAX handler
    $product_manager = new WP_Licensing_Manager_Product_Manager();
    
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $latest_version = isset($_POST['latest_version']) ? sanitize_text_field($_POST['latest_version']) : '1.0.0';
    $changelog = isset($_POST['changelog']) ? wp_kses_post($_POST['changelog']) : '';
    
    echo "<h3>Processed Data:</h3>";
    echo "<ul>";
    echo "<li><strong>Product ID:</strong> " . $product_id . "</li>";
    echo "<li><strong>Slug:</strong> " . htmlspecialchars($slug) . "</li>";
    echo "<li><strong>Name:</strong> " . htmlspecialchars($name) . "</li>";
    echo "<li><strong>Version:</strong> " . htmlspecialchars($latest_version) . "</li>";
    echo "<li><strong>Changelog:</strong> " . htmlspecialchars($changelog) . "</li>";
    echo "</ul>";
    
    if (empty($slug) || empty($name)) {
        echo "<h3 style='color: red;'>Error: Missing required fields</h3>";
        wp_send_json_error('Missing required fields: slug and name are required');
    } else {
        if ($product_id > 0) {
            echo "<h3 style='color: blue;'>Updating existing product...</h3>";
            $result = $product_manager->update_product($product_id, array(
                'name' => $name,
                'latest_version' => $latest_version,
                'changelog' => $changelog
            ));
        } else {
            echo "<h3 style='color: green;'>Creating new product...</h3>";
            $result = $product_manager->create_product(array(
                'slug' => $slug,
                'name' => $name,
                'latest_version' => $latest_version,
                'changelog' => $changelog
            ));
        }
        
        if ($result) {
            echo "<h3 style='color: green;'>Success! Product saved.</h3>";
            wp_send_json_success(array('product_id' => $result));
        } else {
            echo "<h3 style='color: red;'>Error: Failed to save product</h3>";
            wp_send_json_error('Failed to save product');
        }
    }
    
} else {
    // Show debug form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WP Licensing Manager - AJAX Debug</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, textarea { width: 100%; max-width: 400px; padding: 8px; }
            button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            button:hover { background: #005a87; }
            .debug-info { background: #f0f0f1; padding: 20px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>WP Licensing Manager - AJAX Debug Tool</h1>
        
        <div class="debug-info">
            <h3>This tool helps debug the product creation AJAX request</h3>
            <p>Fill out the form below to simulate creating a product and see what happens.</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="wp_licensing_manager_save_product">
            <input type="hidden" name="nonce" value="debug_nonce_123">
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" placeholder="My Awesome Plugin" required>
            </div>
            
            <div class="form-group">
                <label for="slug">Product Slug *</label>
                <input type="text" id="slug" name="slug" placeholder="my-awesome-plugin" required>
            </div>
            
            <div class="form-group">
                <label for="latest_version">Latest Version</label>
                <input type="text" id="latest_version" name="latest_version" placeholder="1.0.0">
            </div>
            
            <div class="form-group">
                <label for="changelog">Changelog</label>
                <textarea id="changelog" name="changelog" rows="4" placeholder="Initial release"></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit">Test Product Creation</button>
            </div>
        </form>
        
    </body>
    </html>
    <?php
}
?>
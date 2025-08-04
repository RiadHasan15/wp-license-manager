<?php
/**
 * Database Repair Admin Page
 * Safe admin interface for repairing missing database tables
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$repair_message = '';
$repair_status = '';

// Handle repair request
if (isset($_POST['repair_database']) && wp_verify_nonce($_POST['wp_licensing_repair_nonce'], 'wp_licensing_repair_database')) {
    
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $repair_results = array();
    
    // 1. CREATE LICENSES TABLE
    $licenses_table = $wpdb->prefix . 'licenses';
    $licenses_sql = "CREATE TABLE $licenses_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        license_key varchar(64) NOT NULL,
        status enum('active','inactive','expired') DEFAULT 'active',
        expires_at date NULL,
        max_activations int(11) DEFAULT 1,
        activations int(11) DEFAULT 0,
        domains text,
        customer_email varchar(255) NOT NULL,
        order_id bigint(20) NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY license_key (license_key),
        KEY product_id (product_id),
        KEY customer_email (customer_email),
        KEY status (status)
    ) $charset_collate;";

    // 2. CREATE ACTIVATIONS TABLE
    $activations_table = $wpdb->prefix . 'license_activations';
    $activations_sql = "CREATE TABLE $activations_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        domain varchar(255) NOT NULL,
        ip_address varchar(45) NULL,
        activated_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY license_id (license_id),
        KEY product_id (product_id),
        KEY domain (domain)
    ) $charset_collate;";

    // 3. CREATE PRODUCTS TABLE
    $products_table = $wpdb->prefix . 'license_products';
    $products_sql = "CREATE TABLE $products_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        slug varchar(100) NOT NULL,
        name varchar(255) NOT NULL,
        latest_version varchar(20) DEFAULT '1.0.0',
        changelog text,
        update_file_path varchar(255),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Execute table creation
    dbDelta($licenses_sql);
    dbDelta($activations_sql);
    dbDelta($products_sql);
    
    // Verify creation
    $tables_created = 0;
    $tables_failed = array();
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$licenses_table'") == $licenses_table) {
        $repair_results[] = '✅ wp_licenses table created successfully';
        $tables_created++;
    } else {
        $tables_failed[] = 'wp_licenses';
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$activations_table'") == $activations_table) {
        $repair_results[] = '✅ wp_license_activations table created successfully';
        $tables_created++;
    } else {
        $tables_failed[] = 'wp_license_activations';
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$products_table'") == $products_table) {
        $repair_results[] = '✅ wp_license_products table created successfully';
        $tables_created++;
    } else {
        $tables_failed[] = 'wp_license_products';
    }
    
    // Set repair message
    if ($tables_created == 3) {
        $repair_status = 'success';
        $repair_message = sprintf(__('🎉 All %d database tables have been created successfully! Your WP Licensing Manager should now work correctly.', 'wp-licensing-manager'), $tables_created);
    } elseif ($tables_created > 0) {
        $repair_status = 'warning';
        $repair_message = sprintf(__('⚠️ %d out of 3 tables were created. Failed tables: %s', 'wp-licensing-manager'), $tables_created, implode(', ', $tables_failed));
    } else {
        $repair_status = 'error';
        $repair_message = __('❌ Failed to create any database tables. Please check your database permissions.', 'wp-licensing-manager');
    }
    
    // Add repair results to message
    if (!empty($repair_results)) {
        $repair_message .= '<br><br><strong>Details:</strong><br>' . implode('<br>', $repair_results);
    }
}

// Check current table status
global $wpdb;
$table_status = array();
$tables_to_check = array(
    'wp_licenses' => $wpdb->prefix . 'licenses',
    'wp_license_products' => $wpdb->prefix . 'license_products', 
    'wp_license_activations' => $wpdb->prefix . 'license_activations'
);

foreach ($tables_to_check as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    $table_status[$name] = $exists;
}

$all_tables_exist = !in_array(false, $table_status);
?>

<div class="wrap">
    <h1><?php _e('Database Repair', 'wp-licensing-manager'); ?></h1>
    
    <?php if ($repair_message): ?>
        <div class="notice notice-<?php echo esc_attr($repair_status); ?> is-dismissible">
            <p><?php echo wp_kses_post($repair_message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2><?php _e('Database Table Status', 'wp-licensing-manager'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Table Name', 'wp-licensing-manager'); ?></th>
                    <th><?php _e('Status', 'wp-licensing-manager'); ?></th>
                    <th><?php _e('Description', 'wp-licensing-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>wp_licenses</code></td>
                    <td>
                        <?php if ($table_status['wp_licenses']): ?>
                            <span style="color: green;">✅ <?php _e('Exists', 'wp-licensing-manager'); ?></span>
                        <?php else: ?>
                            <span style="color: red;">❌ <?php _e('Missing', 'wp-licensing-manager'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php _e('Stores license keys and their metadata', 'wp-licensing-manager'); ?></td>
                </tr>
                <tr>
                    <td><code>wp_license_products</code></td>
                    <td>
                        <?php if ($table_status['wp_license_products']): ?>
                            <span style="color: green;">✅ <?php _e('Exists', 'wp-licensing-manager'); ?></span>
                        <?php else: ?>
                            <span style="color: red;">❌ <?php _e('Missing', 'wp-licensing-manager'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php _e('Stores licensed products information', 'wp-licensing-manager'); ?></td>
                </tr>
                <tr>
                    <td><code>wp_license_activations</code></td>
                    <td>
                        <?php if ($table_status['wp_license_activations']): ?>
                            <span style="color: green;">✅ <?php _e('Exists', 'wp-licensing-manager'); ?></span>
                        <?php else: ?>
                            <span style="color: red;">❌ <?php _e('Missing', 'wp-licensing-manager'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php _e('Tracks license activations on domains', 'wp-licensing-manager'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <?php if (!$all_tables_exist): ?>
        <div class="card">
            <h2><?php _e('Repair Database Tables', 'wp-licensing-manager'); ?></h2>
            <p><?php _e('Some database tables are missing. Click the button below to recreate them.', 'wp-licensing-manager'); ?></p>
            <p><strong><?php _e('Note:', 'wp-licensing-manager'); ?></strong> <?php _e('This operation is safe and will not affect existing data.', 'wp-licensing-manager'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_licensing_repair_database', 'wp_licensing_repair_nonce'); ?>
                <p>
                    <input type="submit" name="repair_database" class="button button-primary" value="<?php esc_attr_e('Repair Database Tables', 'wp-licensing-manager'); ?>">
                </p>
            </form>
        </div>
    <?php else: ?>
        <div class="notice notice-success">
            <p><?php _e('🎉 All database tables exist and are ready to use!', 'wp-licensing-manager'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2><?php _e('Manual Activation', 'wp-licensing-manager'); ?></h2>
        <p><?php _e('If the repair doesn\'t work, you can manually reactivate the plugin:', 'wp-licensing-manager'); ?></p>
        <ol>
            <li><?php _e('Go to Plugins page', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Deactivate "WP Licensing Manager"', 'wp-licensing-manager'); ?></li>
            <li><?php _e('Activate "WP Licensing Manager" again', 'wp-licensing-manager'); ?></li>
        </ol>
    </div>
</div>
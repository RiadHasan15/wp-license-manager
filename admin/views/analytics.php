<?php
/**
 * Analytics admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$activation_manager = new WP_Licensing_Manager_Activation_Manager();
$license_manager = new WP_Licensing_Manager_License_Manager();
$product_manager = new WP_Licensing_Manager_Product_Manager();

// Get analytics data
$activation_stats = $activation_manager->get_activation_stats();

// Get license statistics
global $wpdb;

// Total licenses by status
$license_stats = $wpdb->get_results(
    "SELECT status, COUNT(*) as count 
     FROM {$wpdb->prefix}licenses 
     GROUP BY status"
);

// Licenses created in last 30 days
$recent_licenses = $wpdb->get_results(
    "SELECT DATE(created_at) as date, COUNT(*) as count 
     FROM {$wpdb->prefix}licenses 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
     GROUP BY DATE(created_at) 
     ORDER BY date DESC"
);

// Activations in last 30 days
$recent_activations = $wpdb->get_results(
    "SELECT DATE(activated_at) as date, COUNT(*) as count 
     FROM {$wpdb->prefix}license_activations 
     WHERE activated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
     GROUP BY DATE(activated_at) 
     ORDER BY date DESC"
);

// Product statistics
$product_stats = $wpdb->get_results(
    "SELECT p.name, p.slug,
            COUNT(l.id) as total_licenses,
            SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_licenses,
            COUNT(a.id) as total_activations
     FROM {$wpdb->prefix}license_products p
     LEFT JOIN {$wpdb->prefix}licenses l ON p.id = l.product_id
     LEFT JOIN {$wpdb->prefix}license_activations a ON p.id = a.product_id
     GROUP BY p.id, p.name, p.slug
     ORDER BY total_licenses DESC"
);
?>

<div class="wrap">
    <h1><?php esc_html_e('Analytics', 'wp-licensing-manager'); ?></h1>
    
    <?php if (empty($activation_stats['top_domains']) && $activation_stats['total_activations'] == 0): ?>
    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('Getting Started with Analytics', 'wp-licensing-manager'); ?></strong><br>
            <?php esc_html_e('Domain analytics will populate automatically as users activate licenses. To see data:', 'wp-licensing-manager'); ?>
        </p>
        <ol style="margin-left: 20px;">
            <li><?php esc_html_e('Create licenses for your products', 'wp-licensing-manager'); ?></li>
            <li><?php esc_html_e('Distribute integration code to your premium plugins/themes', 'wp-licensing-manager'); ?></li>
            <li><?php esc_html_e('Users activate licenses on their domains', 'wp-licensing-manager'); ?></li>
            <li><?php esc_html_e('Domain analytics will appear here automatically', 'wp-licensing-manager'); ?></li>
        </ol>
        <p>
            <strong><?php esc_html_e('For testing:', 'wp-licensing-manager'); ?></strong>
            <?php esc_html_e('Use the activation API endpoint to test the system:', 'wp-licensing-manager'); ?>
            <code>POST /wp-json/licensing/v1/activate</code>
        </p>
    </div>
    <?php endif; ?>

    <!-- Overview Stats -->
    <div class="wp-licensing-stats-grid">
        <div class="wp-licensing-stat-card">
            <h3><?php esc_html_e('Total Licenses', 'wp-licensing-manager'); ?></h3>
            <div class="wp-licensing-stat-number">
                <?php
                $total_licenses = 0;
                foreach ($license_stats as $stat) {
                    $total_licenses += $stat->count;
                }
                echo esc_html(number_format($total_licenses));
                ?>
            </div>
        </div>

        <div class="wp-licensing-stat-card">
            <h3><?php esc_html_e('Active Licenses', 'wp-licensing-manager'); ?></h3>
            <div class="wp-licensing-stat-number">
                <?php
                $active_licenses = 0;
                foreach ($license_stats as $stat) {
                    if ($stat->status === 'active') {
                        $active_licenses = $stat->count;
                        break;
                    }
                }
                echo esc_html(number_format($active_licenses));
                ?>
            </div>
        </div>

        <div class="wp-licensing-stat-card">
            <h3><?php esc_html_e('Total Activations', 'wp-licensing-manager'); ?></h3>
            <div class="wp-licensing-stat-number">
                <?php echo esc_html(number_format($activation_stats['total_activations'])); ?>
            </div>
        </div>

        <div class="wp-licensing-stat-card">
            <h3><?php esc_html_e('Today\'s Activations', 'wp-licensing-manager'); ?></h3>
            <div class="wp-licensing-stat-number">
                <?php echo esc_html(number_format($activation_stats['activations_today'])); ?>
            </div>
        </div>
    </div>

    <div class="wp-licensing-analytics-row">
        <div class="wp-licensing-analytics-col">
            <!-- License Status Distribution -->
            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('License Status Distribution', 'wp-licensing-manager'); ?></span></h2>
                <div class="inside">
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Status', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Count', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Percentage', 'wp-licensing-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($license_stats as $stat): ?>
                                <tr>
                                    <td><?php echo wp_licensing_manager_format_status($stat->status); ?></td>
                                    <td><?php echo esc_html(number_format($stat->count)); ?></td>
                                    <td><?php echo esc_html(round(($stat->count / $total_licenses) * 100, 1)); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Product Performance -->
            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Product Performance', 'wp-licensing-manager'); ?></span></h2>
                <div class="inside">
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Total Licenses', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Active Licenses', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Activations', 'wp-licensing-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($product_stats)): ?>
                                <tr>
                                    <td colspan="4"><?php esc_html_e('No product data available.', 'wp-licensing-manager'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($product_stats as $stat): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($stat->name); ?></strong></td>
                                        <td><?php echo esc_html(number_format($stat->total_licenses)); ?></td>
                                        <td><?php echo esc_html(number_format($stat->active_licenses)); ?></td>
                                        <td><?php echo esc_html(number_format($stat->total_activations)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="wp-licensing-analytics-col">
            <!-- Recent Activity -->
            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Recent License Activity (30 days)', 'wp-licensing-manager'); ?></span></h2>
                <div class="inside">
                    <h4><?php esc_html_e('New Licenses', 'wp-licensing-manager'); ?></h4>
                    <div class="wp-licensing-chart-container">
                        <?php if (empty($recent_licenses)): ?>
                            <p><?php esc_html_e('No recent license activity.', 'wp-licensing-manager'); ?></p>
                        <?php else: ?>
                            <div class="wp-licensing-simple-chart">
                                <?php
                                $max_count = max(array_column($recent_licenses, 'count'));
                                foreach ($recent_licenses as $data):
                                    $percentage = $max_count > 0 ? ($data->count / $max_count) * 100 : 0;
                                ?>
                                    <div class="wp-licensing-chart-bar">
                                        <div class="wp-licensing-chart-label"><?php echo esc_html(date_i18n('M j', strtotime($data->date))); ?></div>
                                        <div class="wp-licensing-chart-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                        <div class="wp-licensing-chart-value"><?php echo esc_html($data->count); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h4><?php esc_html_e('New Activations', 'wp-licensing-manager'); ?></h4>
                    <div class="wp-licensing-chart-container">
                        <?php if (empty($recent_activations)): ?>
                            <p><?php esc_html_e('No recent activation activity.', 'wp-licensing-manager'); ?></p>
                        <?php else: ?>
                            <div class="wp-licensing-simple-chart">
                                <?php
                                $max_count = max(array_column($recent_activations, 'count'));
                                foreach ($recent_activations as $data):
                                    $percentage = $max_count > 0 ? ($data->count / $max_count) * 100 : 0;
                                ?>
                                    <div class="wp-licensing-chart-bar">
                                        <div class="wp-licensing-chart-label"><?php echo esc_html(date_i18n('M j', strtotime($data->date))); ?></div>
                                        <div class="wp-licensing-chart-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                        <div class="wp-licensing-chart-value"><?php echo esc_html($data->count); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Domains -->
            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Most Active Domains', 'wp-licensing-manager'); ?></span></h2>
                <div class="inside">
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Domain', 'wp-licensing-manager'); ?></th>
                                <th><?php esc_html_e('Activations', 'wp-licensing-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activation_stats['top_domains'])): ?>
                                <tr>
                                    <td colspan="2">
                                        <div style="text-align: center; padding: 20px; color: #666;">
                                            <p style="margin: 0 0 10px 0; font-size: 16px;">
                                                <span class="dashicons dashicons-info" style="font-size: 20px; vertical-align: middle;"></span>
                                                <?php esc_html_e('No license activations yet', 'wp-licensing-manager'); ?>
                                            </p>
                                            <p style="margin: 0; font-size: 14px;">
                                                <?php esc_html_e('Domains will appear here once users activate licenses on their websites.', 'wp-licensing-manager'); ?>
                                            </p>
                                            <p style="margin: 10px 0 0 0; font-size: 13px;">
                                                <strong><?php esc_html_e('To test:', 'wp-licensing-manager'); ?></strong>
                                                <?php esc_html_e('Use the API to activate a license on a domain, then refresh this page.', 'wp-licensing-manager'); ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activation_stats['top_domains'] as $domain): ?>
                                    <tr>
                                        <td><?php echo esc_html($domain->domain); ?></td>
                                        <td><?php echo esc_html(number_format($domain->activation_count)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (empty($activation_stats['top_domains']) && $activation_stats['total_activations'] == 0): ?>
    <!-- Debug Information for Empty Analytics -->
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle"><span><?php esc_html_e('Troubleshooting Guide', 'wp-licensing-manager'); ?></span></h2>
        <div class="inside">
            <h4><?php esc_html_e('Why are no domains showing?', 'wp-licensing-manager'); ?></h4>
            <p><?php esc_html_e('The most common reasons and solutions:', 'wp-licensing-manager'); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h5 style="margin: 0 0 10px 0; color: #0073aa;"><?php esc_html_e('1. No Activations Yet', 'wp-licensing-manager'); ?></h5>
                    <p style="margin: 0; font-size: 14px;"><?php esc_html_e('Users haven\'t activated licenses yet. This is normal for new installations.', 'wp-licensing-manager'); ?></p>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #00a32a;">
                    <h5 style="margin: 0 0 10px 0; color: #00a32a;"><?php esc_html_e('2. Integration Needed', 'wp-licensing-manager'); ?></h5>
                    <p style="margin: 0; font-size: 14px;"><?php esc_html_e('Add the integration code to your premium plugins so users can activate licenses.', 'wp-licensing-manager'); ?></p>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #d63638;">
                    <h5 style="margin: 0 0 10px 0; color: #d63638;"><?php esc_html_e('3. Test Activation', 'wp-licensing-manager'); ?></h5>
                    <p style="margin: 0; font-size: 14px;"><?php esc_html_e('Use API testing tools to activate a license and verify the system works.', 'wp-licensing-manager'); ?></p>
                </div>
            </div>
            
            <h4><?php esc_html_e('Quick Test Instructions:', 'wp-licensing-manager'); ?></h4>
            <ol>
                <li><?php esc_html_e('Go to Licenses tab and create a test license', 'wp-licensing-manager'); ?></li>
                <li><?php esc_html_e('Copy the license key', 'wp-licensing-manager'); ?></li>
                <li><?php esc_html_e('Use API testing tool (Postman, curl, etc.) to POST to:', 'wp-licensing-manager'); ?>
                    <br><code><?php echo esc_url(rest_url('licensing/v1/activate')); ?></code>
                </li>
                <li><?php esc_html_e('Send JSON data:', 'wp-licensing-manager'); ?>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; margin: 5px 0;">{
  "license_key": "YOUR-LICENSE-KEY",
  "domain": "test-domain.com",
  "product_slug": "your-product-slug"
}</pre>
                </li>
                <li><?php esc_html_e('Refresh this Analytics page - you should see "test-domain.com" appear!', 'wp-licensing-manager'); ?></li>
            </ol>
            
            <p style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0;">
                <strong><?php esc_html_e('Note:', 'wp-licensing-manager'); ?></strong>
                <?php esc_html_e('Real domain data will populate automatically once users start activating licenses in your premium plugins/themes.', 'wp-licensing-manager'); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>

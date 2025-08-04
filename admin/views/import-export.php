<?php
/**
 * Import/Export Admin Page
 * 
 * @package WP_Licensing_Manager
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get current statistics
global $wpdb;
$license_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}licenses");
$product_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}license_products");
$activation_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}license_activations");
?>

<div class="wrap">
    <h1><?php _e('Import/Export', 'wp-licensing-manager'); ?></h1>
    <p class="description"><?php _e('Backup your license and product data, or restore from a previous backup. All exports are in JSON format for easy migration and backup.', 'wp-licensing-manager'); ?></p>

    <div class="wp-licensing-import-export-container">
        
        <!-- Export Section -->
        <div class="card">
            <h2><?php _e('📤 Export Data', 'wp-licensing-manager'); ?></h2>
            <p><?php _e('Download your licensing data as JSON files for backup or migration purposes.', 'wp-licensing-manager'); ?></p>
            
            <div class="export-stats">
                <div class="export-stat-item">
                    <span class="stat-number"><?php echo number_format($license_count); ?></span>
                    <span class="stat-label"><?php _e('Licenses', 'wp-licensing-manager'); ?></span>
                </div>
                <div class="export-stat-item">
                    <span class="stat-number"><?php echo number_format($product_count); ?></span>
                    <span class="stat-label"><?php _e('Products', 'wp-licensing-manager'); ?></span>
                </div>
                <div class="export-stat-item">
                    <span class="stat-number"><?php echo number_format($activation_count); ?></span>
                    <span class="stat-label"><?php _e('Activations', 'wp-licensing-manager'); ?></span>
                </div>
            </div>

            <div class="export-options">
                <h3><?php _e('Choose what to export:', 'wp-licensing-manager'); ?></h3>
                
                <div class="export-option">
                    <button type="button" class="button button-secondary export-btn" data-type="licenses">
                        📄 <?php _e('Export Licenses Only', 'wp-licensing-manager'); ?>
                    </button>
                    <p class="description"><?php _e('Export all license keys, customer data, and activation information.', 'wp-licensing-manager'); ?></p>
                </div>

                <div class="export-option">
                    <button type="button" class="button button-secondary export-btn" data-type="products">
                        📦 <?php _e('Export Products Only', 'wp-licensing-manager'); ?>
                    </button>
                    <p class="description"><?php _e('Export all product information, versions, and changelogs.', 'wp-licensing-manager'); ?></p>
                </div>

                <div class="export-option">
                    <button type="button" class="button button-primary export-btn" data-type="full">
                        💾 <?php _e('Export Everything (Full Backup)', 'wp-licensing-manager'); ?>
                    </button>
                    <p class="description"><?php _e('Export all licenses, products, and activations in one complete backup file.', 'wp-licensing-manager'); ?></p>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="card">
            <h2><?php _e('📥 Import Data', 'wp-licensing-manager'); ?></h2>
            <p><?php _e('Restore data from a previously exported JSON file. Choose how to handle existing data.', 'wp-licensing-manager'); ?></p>
            
            <form id="import-form" enctype="multipart/form-data">
                <?php wp_nonce_field('wp_licensing_manager_import_export', 'wp_licensing_manager_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_file"><?php _e('Select Import File', 'wp-licensing-manager'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="import_file" name="import_file" accept=".json" required>
                            <p class="description"><?php _e('Choose a JSON file exported from WP Licensing Manager.', 'wp-licensing-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="import_mode"><?php _e('Import Mode', 'wp-licensing-manager'); ?></label>
                        </th>
                        <td>
                            <select id="import_mode" name="import_mode">
                                <option value="skip"><?php _e('Skip Existing (Default)', 'wp-licensing-manager'); ?></option>
                                <option value="update"><?php _e('Update Existing', 'wp-licensing-manager'); ?></option>
                            </select>
                            <p class="description">
                                <strong><?php _e('Skip Existing:', 'wp-licensing-manager'); ?></strong> <?php _e('Keep current data, only import new items.', 'wp-licensing-manager'); ?><br>
                                <strong><?php _e('Update Existing:', 'wp-licensing-manager'); ?></strong> <?php _e('Overwrite existing items with imported data.', 'wp-licensing-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="import-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Start Import', 'wp-licensing-manager'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </form>
            
            <div id="import-results" style="display: none;">
                <h3><?php _e('Import Results', 'wp-licensing-manager'); ?></h3>
                <div id="import-results-content"></div>
            </div>
        </div>

        <!-- Import/Export History -->
        <div class="card">
            <h2><?php _e('📊 Data Overview', 'wp-licensing-manager'); ?></h2>
            <p><?php _e('Current data in your system:', 'wp-licensing-manager'); ?></p>
            
            <div class="data-overview">
                <div class="overview-section">
                    <h4><?php _e('Licenses', 'wp-licensing-manager'); ?></h4>
                    <ul>
                        <li><?php printf(__('Total Licenses: %s', 'wp-licensing-manager'), '<strong>' . number_format($license_count) . '</strong>'); ?></li>
                        <?php
                        $active_licenses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE status = 'active'");
                        $expired_licenses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}licenses WHERE status = 'expired'");
                        ?>
                        <li><?php printf(__('Active Licenses: %s', 'wp-licensing-manager'), '<strong>' . number_format($active_licenses) . '</strong>'); ?></li>
                        <li><?php printf(__('Expired Licenses: %s', 'wp-licensing-manager'), '<strong>' . number_format($expired_licenses) . '</strong>'); ?></li>
                    </ul>
                </div>
                
                <div class="overview-section">
                    <h4><?php _e('Products', 'wp-licensing-manager'); ?></h4>
                    <ul>
                        <li><?php printf(__('Total Products: %s', 'wp-licensing-manager'), '<strong>' . number_format($product_count) . '</strong>'); ?></li>
                        <li><?php printf(__('Total Activations: %s', 'wp-licensing-manager'), '<strong>' . number_format($activation_count) . '</strong>'); ?></li>
                        <?php
                        $recent_activations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}license_activations WHERE activated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                        ?>
                        <li><?php printf(__('Recent Activations (30 days): %s', 'wp-licensing-manager'), '<strong>' . number_format($recent_activations) . '</strong>'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Best Practices -->
        <div class="card">
            <h2><?php _e('💡 Best Practices', 'wp-licensing-manager'); ?></h2>
            <div class="best-practices">
                <div class="practice-section">
                    <h4><?php _e('📤 For Exports:', 'wp-licensing-manager'); ?></h4>
                    <ul>
                        <li><?php _e('Export regularly to create backups', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Store backup files in a secure location', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Use "Full Backup" for complete data migration', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Test imports on a staging site first', 'wp-licensing-manager'); ?></li>
                    </ul>
                </div>
                
                <div class="practice-section">
                    <h4><?php _e('📥 For Imports:', 'wp-licensing-manager'); ?></h4>
                    <ul>
                        <li><?php _e('Always backup your current data before importing', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Use "Skip Existing" mode to avoid duplicates', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Use "Update Existing" mode to sync changes', 'wp-licensing-manager'); ?></li>
                        <li><?php _e('Review import results carefully', 'wp-licensing-manager'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wp-licensing-import-export-container .card {
    margin-bottom: 20px;
}

.export-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.export-stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #007cba;
}

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 4px;
}

.export-options {
    margin-top: 20px;
}

.export-option {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fafafa;
}

.export-option:hover {
    background: #f0f9ff;
    border-color: #007cba;
}

.export-btn {
    margin-bottom: 8px;
}

.import-actions {
    margin-top: 20px;
}

.import-actions .spinner {
    float: none;
    margin-left: 10px;
}

#import-results {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.data-overview {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}

.overview-section {
    flex: 1;
}

.overview-section h4 {
    margin-bottom: 10px;
    color: #333;
}

.overview-section ul {
    list-style: none;
    padding: 0;
}

.overview-section li {
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.best-practices {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}

.practice-section {
    flex: 1;
}

.practice-section h4 {
    margin-bottom: 10px;
    color: #333;
}

.practice-section ul {
    padding-left: 20px;
}

.practice-section li {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .export-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .data-overview,
    .best-practices {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Export functionality
    $('.export-btn').on('click', function() {
        var exportType = $(this).data('type');
        var button = $(this);
        
        button.prop('disabled', true);
        button.text('<?php _e('Exporting...', 'wp-licensing-manager'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_licensing_manager_export_data',
                export_type: exportType,
                wp_licensing_manager_nonce: $('[name="wp_licensing_manager_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    // Create download
                    var blob = new Blob([JSON.stringify(response.data.data, null, 2)], {
                        type: 'application/json'
                    });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    // Show success message
                    showNotice('success', '<?php _e('Export completed successfully!', 'wp-licensing-manager'); ?>');
                } else {
                    showNotice('error', '<?php _e('Export failed:', 'wp-licensing-manager'); ?> ' + response.data);
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Export failed due to network error.', 'wp-licensing-manager'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
                button.text(button.text().replace('<?php _e('Exporting...', 'wp-licensing-manager'); ?>', 
                    button.data('type') === 'licenses' ? '📄 <?php _e('Export Licenses Only', 'wp-licensing-manager'); ?>' :
                    button.data('type') === 'products' ? '📦 <?php _e('Export Products Only', 'wp-licensing-manager'); ?>' :
                    '💾 <?php _e('Export Everything (Full Backup)', 'wp-licensing-manager'); ?>'));
            }
        });
    });
    
    // Import functionality
    $('#import-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'wp_licensing_manager_import_data');
        
        var submitBtn = $(this).find('button[type="submit"]');
        var spinner = $(this).find('.spinner');
        
        submitBtn.prop('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showImportResults(response.data);
                    showNotice('success', '<?php _e('Import completed successfully!', 'wp-licensing-manager'); ?>');
                    
                    // Refresh page after successful import
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    showNotice('error', '<?php _e('Import failed:', 'wp-licensing-manager'); ?> ' + response.data);
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Import failed due to network error.', 'wp-licensing-manager'); ?>');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
    function showImportResults(results) {
        var html = '<div class="import-summary">';
        
        if (results.total_imported !== undefined) {
            // Full import results
            html += '<h4><?php _e('Import Summary', 'wp-licensing-manager'); ?></h4>';
            html += '<ul>';
            html += '<li><?php _e('Total Imported:', 'wp-licensing-manager'); ?> <strong>' + results.total_imported + '</strong></li>';
            html += '<li><?php _e('Total Skipped:', 'wp-licensing-manager'); ?> <strong>' + results.total_skipped + '</strong></li>';
            html += '<li><?php _e('Total Errors:', 'wp-licensing-manager'); ?> <strong>' + results.total_errors + '</strong></li>';
            html += '</ul>';
            
            if (results.products) {
                html += '<h5><?php _e('Products:', 'wp-licensing-manager'); ?></h5>';
                html += '<ul>';
                html += '<li><?php _e('Imported:', 'wp-licensing-manager'); ?> ' + results.products.imported + '</li>';
                html += '<li><?php _e('Skipped:', 'wp-licensing-manager'); ?> ' + results.products.skipped + '</li>';
                html += '<li><?php _e('Errors:', 'wp-licensing-manager'); ?> ' + results.products.errors + '</li>';
                html += '</ul>';
            }
            
            if (results.licenses) {
                html += '<h5><?php _e('Licenses:', 'wp-licensing-manager'); ?></h5>';
                html += '<ul>';
                html += '<li><?php _e('Imported:', 'wp-licensing-manager'); ?> ' + results.licenses.imported + '</li>';
                html += '<li><?php _e('Skipped:', 'wp-licensing-manager'); ?> ' + results.licenses.skipped + '</li>';
                html += '<li><?php _e('Errors:', 'wp-licensing-manager'); ?> ' + results.licenses.errors + '</li>';
                html += '</ul>';
            }
        } else {
            // Single type import results
            html += '<h4><?php _e('Import Results', 'wp-licensing-manager'); ?></h4>';
            html += '<ul>';
            html += '<li><?php _e('Imported:', 'wp-licensing-manager'); ?> <strong>' + results.imported + '</strong></li>';
            html += '<li><?php _e('Skipped:', 'wp-licensing-manager'); ?> <strong>' + results.skipped + '</strong></li>';
            html += '<li><?php _e('Errors:', 'wp-licensing-manager'); ?> <strong>' + results.errors + '</strong></li>';
            html += '</ul>';
        }
        
        html += '</div>';
        
        $('#import-results-content').html(html);
        $('#import-results').show();
    }
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script>
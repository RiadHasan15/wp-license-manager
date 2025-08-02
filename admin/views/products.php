<?php
/**
 * Products admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$product_manager = new WP_Licensing_Manager_Product_Manager();

// Handle file upload
if (isset($_POST['upload_update_file']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_licensing_manager_upload')) {
    $product_id = absint($_POST['product_id']);
    $product = $product_manager->get_product($product_id);
    
    if ($product && isset($_FILES['update_file'])) {
        $new_version = sanitize_text_field($_POST['new_version']);
        $new_changelog = wp_kses_post($_POST['new_changelog']);
        
        // Validate version format
        if (empty($new_version) || !preg_match('/^\d+\.\d+(\.\d+)?/', $new_version)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a valid version number (e.g., 1.0.5).', 'wp-licensing-manager') . '</p></div>';
        } else {
            $file_path = $product_manager->handle_update_file_upload($_FILES['update_file'], $product->slug);
            
            if ($file_path) {
                // Update product with new version, changelog, and file path
                $update_result = $product_manager->update_product($product_id, array(
                    'latest_version' => $new_version,
                    'changelog' => $new_changelog,
                    'update_file_path' => $file_path
                ));
                
                if ($update_result) {
                    // Clear update caches so clients can immediately detect the new version
                    $product_manager->clear_update_caches($product->slug);
                    
                    echo '<div class="notice notice-success"><p>' . sprintf(
                        esc_html__('Update uploaded successfully! Version %s is now available for download. Update caches have been cleared.', 'wp-licensing-manager'),
                        esc_html($new_version)
                    ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update product information.', 'wp-licensing-manager') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to upload update file. Please ensure it is a valid ZIP file.', 'wp-licensing-manager') . '</p></div>';
            }
        }
    }
}

// Get filters
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Get products
$products_data = $product_manager->get_products(array(
    'page' => $page,
    'per_page' => 20,
    'search' => $search
));

$products = $products_data['products'];
$total_pages = $products_data['pages'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Products', 'wp-licensing-manager'); ?></h1>
    <a href="#" class="page-title-action" id="add-product-btn"><?php esc_html_e('Add New', 'wp-licensing-manager'); ?></a>
    <hr class="wp-header-end">

    <!-- Search -->
    <div class="tablenav top">
        <form method="get" class="alignright">
            <input type="hidden" name="page" value="wp-licensing-manager-products" />
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search products...', 'wp-licensing-manager'); ?>" />
            <?php submit_button(__('Search', 'wp-licensing-manager'), 'secondary', 'search_action', false); ?>
        </form>
    </div>

    <!-- Products Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Name', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Slug', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Version', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Update File', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Licenses', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Created', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Last Update', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'wp-licensing-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8"><?php esc_html_e('No products found.', 'wp-licensing-manager'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php $stats = $product_manager->get_product_stats($product->id); ?>
                    <tr>
                        <td><strong><?php echo esc_html($product->name); ?></strong></td>
                        <td><code><?php echo esc_html($product->slug); ?></code></td>
                        <td><?php echo esc_html($product->latest_version); ?></td>
                        <td>
                            <?php if (!empty($product->update_file_path)): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: green;" title="<?php esc_attr_e('Update file available', 'wp-licensing-manager'); ?>"></span>
                                <a href="#" class="upload-update-file" data-product-id="<?php echo esc_attr($product->id); ?>" data-current-version="<?php echo esc_attr($product->latest_version); ?>"><?php esc_html_e('Update', 'wp-licensing-manager'); ?></a>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: orange;" title="<?php esc_attr_e('No update file', 'wp-licensing-manager'); ?>"></span>
                                <a href="#" class="upload-update-file" data-product-id="<?php echo esc_attr($product->id); ?>" data-current-version="<?php echo esc_attr($product->latest_version); ?>"><?php esc_html_e('Upload', 'wp-licensing-manager'); ?></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-licensing-manager&product_id=' . $product->id)); ?>">
                                <?php echo esc_html($stats['total_licenses']); ?> 
                                (<?php echo esc_html($stats['active_licenses']); ?> <?php esc_html_e('active', 'wp-licensing-manager'); ?>)
                            </a>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($product->created_at))); ?></td>
                        <td>
                            <?php 
                            if (!empty($product->updated_at) && $product->updated_at !== $product->created_at) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($product->updated_at)));
                            } else {
                                echo '<span style="color: #666;">' . esc_html__('Never', 'wp-licensing-manager') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="#" class="button button-small edit-product" data-product-id="<?php echo esc_attr($product->id); ?>"><?php esc_html_e('Edit', 'wp-licensing-manager'); ?></a>
                            <a href="#" class="button button-small view-integration" data-product-slug="<?php echo esc_attr($product->slug); ?>"><?php esc_html_e('Integration', 'wp-licensing-manager'); ?></a>
                            <a href="#" class="button button-small button-link-delete delete-product" data-product-id="<?php echo esc_attr($product->id); ?>"><?php esc_html_e('Delete', 'wp-licensing-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url('admin.php?page=wp-licensing-manager-products');
                if ($search) $base_url .= '&search=' . urlencode($search);

                $pagination_args = array(
                    'base' => $base_url . '%_%',
                    'format' => '&paged=%#%',
                    'total' => $total_pages,
                    'current' => $page,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                );
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Product Modal -->
<div id="product-modal" class="wp-licensing-modal" style="display: none;">
    <div class="wp-licensing-modal-content">
        <span class="wp-licensing-modal-close">&times;</span>
        <h2 id="product-modal-title"><?php esc_html_e('Add Product', 'wp-licensing-manager'); ?></h2>
        
        <form id="product-form">
            <input type="hidden" id="product-id" name="product_id" value="" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="product-slug"><?php esc_html_e('Slug', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="product-slug" name="slug" class="regular-text" required />
                        <p class="description"><?php esc_html_e('Unique identifier for the product (lowercase, no spaces)', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="product-name"><?php esc_html_e('Name', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="product-name" name="name" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="product-version"><?php esc_html_e('Latest Version', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="product-version" name="latest_version" value="1.0.0" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="product-changelog"><?php esc_html_e('Changelog', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <textarea id="product-changelog" name="changelog" rows="5" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e('Describe what\'s new in this version', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Product', 'wp-licensing-manager'); ?></button>
                <button type="button" class="button wp-licensing-modal-close"><?php esc_html_e('Cancel', 'wp-licensing-manager'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Upload Update File Modal -->
<div id="upload-modal" class="wp-licensing-modal" style="display: none;">
    <div class="wp-licensing-modal-content">
        <span class="wp-licensing-modal-close">&times;</span>
        <h2><?php esc_html_e('Upload Update File', 'wp-licensing-manager'); ?></h2>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wp_licensing_manager_upload'); ?>
            <input type="hidden" id="upload-product-id" name="product_id" value="" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="update-version"><?php esc_html_e('New Version', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="update-version" name="new_version" class="regular-text" required />
                        <p class="description"><?php esc_html_e('Version number for this update (e.g., 1.0.5)', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="update-changelog"><?php esc_html_e('Changelog', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <textarea id="update-changelog" name="new_changelog" rows="4" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e('What\'s new in this version?', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="update-file"><?php esc_html_e('Update File', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="file" id="update-file" name="update_file" accept=".zip" required />
                        <p class="description"><?php esc_html_e('Upload a ZIP file containing the latest version of your plugin/theme', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="upload_update_file" class="button button-primary"><?php esc_html_e('Upload File', 'wp-licensing-manager'); ?></button>
                <button type="button" class="button wp-licensing-modal-close"><?php esc_html_e('Cancel', 'wp-licensing-manager'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Integration Code Modal -->
<div id="integration-modal" class="wp-licensing-modal" style="display: none;">
    <div class="wp-licensing-modal-content large">
        <span class="wp-licensing-modal-close">&times;</span>
        <h2><?php esc_html_e('Integration Code', 'wp-licensing-manager'); ?></h2>
        
        <p><?php esc_html_e('Copy and paste this code into your premium plugin to enable licensing and automatic updates:', 'wp-licensing-manager'); ?></p>
        
        <textarea id="integration-code" readonly rows="20" style="width: 100%; font-family: monospace; font-size: 12px;"></textarea>
        
        <p class="submit">
            <button type="button" id="copy-integration-code" class="button button-primary"><?php esc_html_e('Copy Code', 'wp-licensing-manager'); ?></button>
            <button type="button" class="button wp-licensing-modal-close"><?php esc_html_e('Close', 'wp-licensing-manager'); ?></button>
        </p>
    </div>
</div>

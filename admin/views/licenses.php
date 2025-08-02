<?php
/**
 * Licenses admin page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$license_manager = new WP_Licensing_Manager_License_Manager();
$product_manager = new WP_Licensing_Manager_Product_Manager();

// Get filters
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$product_filter = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Get licenses
$licenses_data = $license_manager->get_licenses(array(
    'page' => $page,
    'per_page' => 20,
    'search' => $search,
    'status' => $status_filter,
    'product_id' => $product_filter
));

$licenses = $licenses_data['licenses'];
$total_pages = $licenses_data['pages'];

// Get products for filter dropdown
$products = $product_manager->get_products_for_select();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Licenses', 'wp-licensing-manager'); ?></h1>
    <a href="#" class="page-title-action" id="add-license-btn"><?php esc_html_e('Add New', 'wp-licensing-manager'); ?></a>
    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" class="alignleft actions">
            <input type="hidden" name="page" value="wp-licensing-manager" />
            
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'wp-licensing-manager'); ?></option>
                <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'wp-licensing-manager'); ?></option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php esc_html_e('Inactive', 'wp-licensing-manager'); ?></option>
                <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'wp-licensing-manager'); ?></option>
            </select>

            <select name="product_id">
                <option value=""><?php esc_html_e('All Products', 'wp-licensing-manager'); ?></option>
                <?php foreach ($products as $product_id => $product_name): ?>
                    <option value="<?php echo esc_attr($product_id); ?>" <?php selected($product_filter, $product_id); ?>><?php echo esc_html($product_name); ?></option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(__('Filter', 'wp-licensing-manager'), 'secondary', 'filter_action', false); ?>
        </form>

        <form method="get" class="alignright">
            <input type="hidden" name="page" value="wp-licensing-manager" />
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search licenses...', 'wp-licensing-manager'); ?>" />
            <?php submit_button(__('Search', 'wp-licensing-manager'), 'secondary', 'search_action', false); ?>
        </form>
    </div>

    <!-- Licenses Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('License Key', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Product', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Customer', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Activations', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Expires', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Created', 'wp-licensing-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'wp-licensing-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)): ?>
                <tr>
                    <td colspan="8"><?php esc_html_e('No licenses found.', 'wp-licensing-manager'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><code><?php echo esc_html($license->license_key); ?></code></td>
                        <td>
                            <?php
                            $product = $product_manager->get_product($license->product_id);
                            echo esc_html($product ? $product->name : 'Unknown Product');
                            ?>
                        </td>
                        <td><?php echo esc_html($license->customer_email); ?></td>
                        <td><?php echo wp_licensing_manager_format_status($license->status); ?></td>
                        <td><?php echo esc_html($license->activations . ' / ' . $license->max_activations); ?></td>
                        <td>
                            <?php
                            if (empty($license->expires_at) || $license->expires_at === '0000-00-00') {
                                esc_html_e('Never', 'wp-licensing-manager');
                            } else {
                                echo esc_html(date_i18n(get_option('date_format'), strtotime($license->expires_at)));
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license->created_at))); ?></td>
                        <td>
                            <a href="#" class="button button-small edit-license" data-license-id="<?php echo esc_attr($license->id); ?>"><?php esc_html_e('Edit', 'wp-licensing-manager'); ?></a>
                            <a href="#" class="button button-small button-link-delete delete-license" data-license-id="<?php echo esc_attr($license->id); ?>"><?php esc_html_e('Delete', 'wp-licensing-manager'); ?></a>
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
                $base_url = admin_url('admin.php?page=wp-licensing-manager');
                if ($search) $base_url .= '&search=' . urlencode($search);
                if ($status_filter) $base_url .= '&status=' . urlencode($status_filter);
                if ($product_filter) $base_url .= '&product_id=' . $product_filter;

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

<!-- License Modal -->
<div id="license-modal" class="wp-licensing-modal" style="display: none;">
    <div class="wp-licensing-modal-content">
        <span class="wp-licensing-modal-close">&times;</span>
        <h2 id="license-modal-title"><?php esc_html_e('Add License', 'wp-licensing-manager'); ?></h2>
        
        <form id="license-form">
            <input type="hidden" id="license-id" name="license_id" value="" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="product-id"><?php esc_html_e('Product', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <select id="product-id" name="product_id" required>
                            <option value=""><?php esc_html_e('Select Product', 'wp-licensing-manager'); ?></option>
                            <?php foreach ($products as $product_id => $product_name): ?>
                                <option value="<?php echo esc_attr($product_id); ?>"><?php echo esc_html($product_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="customer-email"><?php esc_html_e('Customer Email', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="customer-email" name="customer_email" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="status"><?php esc_html_e('Status', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="active"><?php esc_html_e('Active', 'wp-licensing-manager'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'wp-licensing-manager'); ?></option>
                            <option value="expired"><?php esc_html_e('Expired', 'wp-licensing-manager'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="expires-at"><?php esc_html_e('Expires At', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="expires-at" name="expires_at" />
                        <p class="description"><?php esc_html_e('Leave empty for lifetime license', 'wp-licensing-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max-activations"><?php esc_html_e('Max Activations', 'wp-licensing-manager'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max-activations" name="max_activations" value="1" min="1" />
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save License', 'wp-licensing-manager'); ?></button>
                <button type="button" class="button wp-licensing-modal-close"><?php esc_html_e('Cancel', 'wp-licensing-manager'); ?></button>
            </p>
        </form>
    </div>
</div>

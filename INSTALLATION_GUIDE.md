# WP Licensing Manager - Installation Guide

## Quick Setup Instructions

### 1. Plugin Installation
1. Upload the entire `wp-licensing-manager` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Make sure WooCommerce is installed and active

### 2. Check WooCommerce Integration

After activating the plugin, you should see licensing options in two places:

#### Option A: In the General Product Data tab
- Go to Products → Add New (or edit existing product)
- In the Product Data section, look in the **General tab**
- You should see "Licensed Product" checkbox at the bottom

#### Option B: In the dedicated Licensing tab
- Go to Products → Add New (or edit existing product) 
- In the Product Data section, look for a **"Licensing" tab**
- Click the Licensing tab to see all licensing options

### 3. If You Don't See Licensing Options

Try these troubleshooting steps:

1. **Check Plugin Activation**
   - Go to Plugins → Installed Plugins
   - Make sure "WP Licensing Manager" shows as active

2. **Check WooCommerce**
   - Go to Plugins → Installed Plugins  
   - Make sure WooCommerce is active
   - Try deactivating and reactivating WooCommerce

3. **Check for Plugin Conflicts**
   - Temporarily deactivate all other plugins except WooCommerce
   - Check if licensing options appear
   - If they do, reactivate plugins one by one to find conflicts

4. **Check Theme Compatibility**
   - Temporarily switch to a default WordPress theme (Twenty Twenty-Four)
   - Check if licensing options appear

5. **Check for Errors**
   - Go to WooCommerce → Status → Logs
   - Look for any error messages related to licensing

### 4. Setting Up Licensed Products

Once you can see the licensing options:

1. **Create/Edit a Product**
   - Go to Products → Add New
   - Fill in basic product details
   - In the Product Data section, find the licensing options

2. **Configure Licensing**
   - Check "Licensed Product" to enable licensing
   - Set "License Expiry (Days)" (365 for 1 year, 0 for lifetime)
   - Set "Max Activations" (how many sites can use this license)

3. **Save the Product**
   - Click "Update" or "Publish"

### 5. Testing License Generation

1. **Create a Test Order**
   - Add your licensed product to cart
   - Complete checkout process
   - Mark order as "Completed"

2. **Check License Generation**
   - Go to WP Licensing Manager → Licenses in admin
   - You should see a new license generated
   - Customer should receive email with license key

### 6. Common Issues

**"Licensed Product" option not showing:**
- Plugin might not be properly activated
- WooCommerce functions not loading correctly
- Theme or plugin conflict

**License not generated after order:**
- Product not marked as "Licensed Product"
- Order status not set to "Completed"
- Email delivery issues

**Licensing tab missing:**
- Browser cache issue - try hard refresh (Ctrl+F5)
- JavaScript conflicts - check browser console for errors

### 7. Getting Help

If licensing options still don't appear after trying these steps:

1. Check the WordPress error logs (usually in `/wp-content/debug.log`)
2. Enable WordPress debugging by adding to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Try creating a fresh WordPress installation to test plugin compatibility

The plugin includes both hooks to the General tab and creates its own Licensing tab, so licensing options should appear in one of these locations when properly installed.
# WP Licensing Manager

A comprehensive licensing system for WordPress plugins and themes with WooCommerce integration.

## Description

WP Licensing Manager provides a complete self-hosted licensing solution for your premium WordPress plugins and themes. It integrates seamlessly with WooCommerce to automatically generate and manage license keys when customers purchase your products.

## Features

### Core Licensing System
- **Multi-Product Support**: Manage unlimited products in one system
- **Automatic License Generation**: Create licenses automatically on WooCommerce order completion
- **Flexible Licensing**: Set custom expiration dates and activation limits per product
- **Domain Binding**: Track and limit activations per domain
- **License Validation**: Comprehensive API for license validation and management

### WooCommerce Integration
- **Seamless Integration**: Add licensing checkbox to any WooCommerce product
- **Automatic Processing**: Generate licenses when orders are completed
- **Customer Portal**: Customers can view their licenses in My Account
- **Email Notifications**: Automatic license delivery via email

### REST API
Complete REST API with the following endpoints:
- `/wp-json/licensing/v1/validate` - Validate license keys
- `/wp-json/licensing/v1/activate` - Activate licenses on domains
- `/wp-json/licensing/v1/deactivate` - Deactivate licenses from domains  
- `/wp-json/licensing/v1/update-check` - Check for product updates
- `/wp-json/licensing/v1/update-download` - Download updates for licensed users
- `/wp-json/licensing/v1/stats` - Get licensing statistics (admin only)

### Automatic Updates
- **Licensed Updates**: Provide automatic updates only to licensed customers
- **Version Management**: Track latest versions and changelogs per product
- **Secure Downloads**: Protected update downloads with license validation
- **Integration Code**: Generate ready-to-use integration code for your plugins

### Admin Dashboard
- **License Management**: Create, edit, search, and delete licenses
- **Product Management**: Manage products, versions, and update files
- **Analytics**: View activation trends, license statistics, and domain usage
- **Settings**: Configure default expiration, activation limits, and email templates

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- MySQL 5.6 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/wp-licensing-manager/`
2. Activate the plugin through the WordPress admin panel
3. Ensure WooCommerce is installed and activated
4. Navigate to **Licensing Manager** in the admin menu to configure settings

## Configuration

### 1. Basic Settings
Go to **Licensing Manager > Settings** to configure:
- Default license expiration (days)
- Default maximum activations per license
- Email templates for license delivery

### 2. Create Products
Before you can generate licenses, create products:
1. Go to **Licensing Manager > Products**
2. Click **Add New**
3. Enter product details (name, slug, version, changelog)
4. Upload update files as needed

### 3. Configure WooCommerce Products
For each WooCommerce product that should generate licenses:
1. Edit the product in WooCommerce
2. Check **Licensed Product** in the General tab
3. Select the corresponding **License Product**
4. Set custom expiration and activation limits (optional)

## Usage

### For Customers
After purchasing a licensed product:
1. License key is automatically emailed to the customer
2. License details are visible in **My Account > My Licenses**
3. Customers can view activation status and expiration dates

### For Developers
Integrate licensing into your premium plugins:

1. **Get Integration Code**: 
   - Go to **Licensing Manager > Products**
   - Click **Integration** next to your product
   - Copy the generated integration code

2. **Add to Your Plugin**:
   - Paste the integration code into your main plugin file
   - Customize the license settings page as needed
   - The code handles license validation and automatic updates

### API Usage
Use the REST API to validate licenses from your applications:

```php
// Validate a license
$response = wp_remote_post('https://yoursite.com/wp-json/licensing/v1/validate', array(
    'body' => array(
        'license_key' => 'ABCD1234...',
        'product_slug' => 'my-premium-plugin'
    ),
    'sslverify' => true
));

$body = json_decode(wp_remote_retrieve_body($response), true);
if ($body['success']) {
    // License is valid
}

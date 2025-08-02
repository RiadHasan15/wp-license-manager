# WP Licensing Manager

## Overview

WP Licensing Manager is a comprehensive WordPress plugin that provides a self-hosted licensing system for premium WordPress plugins and themes. The system integrates with WooCommerce to automatically generate and manage licenses, includes a REST API for license validation, and provides automatic update functionality for licensed products.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### WordPress Plugin Architecture
- **Plugin Type**: WordPress plugin following WordPress.org development guidelines
- **License**: GPL-compatible with proper security practices
- **Standards**: WordPress Coding Standards (PHPCS) compliance
- **Security**: CSRF protection via nonces, SQL injection prevention with $wpdb->prepare(), XSS protection with proper sanitization

### Frontend-Backend Integration
- **Admin Interface**: WordPress admin dashboard integration with custom pages for license management
- **Customer Interface**: WooCommerce My Account integration for customer license viewing
- **Asset Management**: Proper enqueueing of CSS/JS files using WordPress standards

## Key Components

### 1. License Management System
- **Multi-Product Support**: Handles unlimited products (plugins/themes) in a single system
- **License Generation**: Creates unique 32-character license keys automatically
- **Activation Tracking**: Monitors domain activations with configurable limits
- **Status Management**: Tracks license states (active, inactive, expired)

### 2. WooCommerce Integration
- **Product Meta**: Adds `_is_licensed` checkbox to WooCommerce products
- **Order Processing**: Automatic license generation on order completion
- **Customer Portal**: License viewing in WooCommerce My Account section
- **Email Notifications**: Automatic license delivery system

### 3. REST API
- **License Validation**: `/wp-json/licensing/v1/validate` endpoint
- **Activation Management**: Activate/deactivate endpoints for domain binding
- **Update System**: Update check and download endpoints for licensed users
- **Analytics**: Statistics endpoint for admin users
- **Security**: HTTPS-only API calls with proper authentication

### 4. Update Management
- **Version Control**: Tracks product versions and changelogs
- **Licensed Updates**: Provides updates only to valid license holders
- **Secure Downloads**: Protected update downloads with license validation
- **Integration Code**: Generates ready-to-use code for plugin integration

## Data Flow

### License Creation Flow
1. Customer purchases WooCommerce product with licensing enabled
2. Order completion triggers license generation
3. Unique 32-character license key created
4. License data stored with product association, expiration, and activation limits
5. Customer receives license via email notification

### License Validation Flow
1. Plugin/theme makes API call to validation endpoint
2. System verifies license key, product association, and domain
3. Activation limits and expiration checked
4. Response includes validation status and any restrictions
5. Domain activation recorded if successful

### Update Distribution Flow
1. Licensed plugin checks for updates via API
2. System validates license and product association
3. Update availability determined based on license status
4. Secure download provided for valid licenses
5. Version tracking updated upon successful download

## External Dependencies

### Required WordPress Environment
- **WordPress**: Version 5.0 or higher
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.6 or higher
- **WooCommerce**: Version 5.0 or higher for e-commerce integration

### WordPress Standards
- **Security**: CSRF tokens, SQL injection prevention, XSS protection
- **Asset Loading**: wp_enqueue_script and wp_enqueue_style usage
- **Coding Standards**: WordPress PHPCS compliance
- **Sanitization**: Proper use of sanitize_text_field, esc_html, etc.

## Deployment Strategy

### Plugin Structure
- Standard WordPress plugin architecture with main plugin file
- Organized directory structure separating admin, frontend, and API components
- Proper asset organization with CSS and JavaScript files
- Database schema management for license and product data

### Security Considerations
- All API communications require HTTPS
- Nonce verification for admin actions
- Prepared statements for database queries
- Input sanitization and output escaping throughout
- GPL licensing compliance for distribution

### Integration Points
- WooCommerce product meta integration
- WordPress admin menu integration
- REST API endpoint registration
- Customer account page integration
- Email system integration for notifications

The architecture emphasizes security, scalability, and WordPress best practices while providing a comprehensive licensing solution that integrates seamlessly with existing WordPress and WooCommerce installations.
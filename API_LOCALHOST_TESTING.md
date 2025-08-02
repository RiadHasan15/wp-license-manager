# API Localhost Testing Guide

## Overview
The WP Licensing Manager API now supports localhost testing alongside production HTTPS requirements. This allows developers to test the licensing system locally without SSL certificates while maintaining security for production deployments.

## Localhost Detection
The plugin automatically detects and allows these localhost environments:

### Supported Localhost Addresses
- `localhost` (any port)
- `127.0.0.1` (any port)
- `::1` (IPv6 localhost)
- `0.0.0.0` (any port)
- Local network ranges: `192.168.x.x`, `10.x.x.x`, `172.x.x.x`

### Example Working URLs
```
http://localhost:5000/wp-json/licensing/v1/validate
http://127.0.0.1:8080/wp-json/licensing/v1/activate
http://192.168.1.100/wordpress/wp-json/licensing/v1/validate
```

## API Endpoints for Testing

### 1. License Validation
**Endpoint**: `POST /wp-json/licensing/v1/validate`

**Test with cURL**:
```bash
curl -X POST "http://localhost:5000/wp-json/licensing/v1/validate" \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "YOUR_LICENSE_KEY_HERE",
    "product_slug": "your-product-slug"
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "license": {
    "status": "active",
    "expires_at": "2025-12-31",
    "max_activations": 5,
    "activations": 2
  }
}
```

### 2. License Activation
**Endpoint**: `POST /wp-json/licensing/v1/activate`

**Test with cURL**:
```bash
curl -X POST "http://localhost:5000/wp-json/licensing/v1/activate" \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "YOUR_LICENSE_KEY_HERE",
    "domain": "example.com",
    "product_slug": "your-product-slug"
  }'
```

### 3. License Deactivation
**Endpoint**: `POST /wp-json/licensing/v1/deactivate`

**Test with cURL**:
```bash
curl -X POST "http://localhost:5000/wp-json/licensing/v1/deactivate" \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "YOUR_LICENSE_KEY_HERE",
    "domain": "example.com",
    "product_slug": "your-product-slug"
  }'
```

### 4. Update Check
**Endpoint**: `POST /wp-json/licensing/v1/update-check`

**Test with cURL**:
```bash
curl -X POST "http://localhost:5000/wp-json/licensing/v1/update-check" \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "YOUR_LICENSE_KEY_HERE",
    "product_slug": "your-product-slug",
    "current_version": "1.0.0"
  }'
```

### 5. Update Download
**Endpoint**: `GET /wp-json/licensing/v1/update-download`

**Test with cURL**:
```bash
curl "http://localhost:5000/wp-json/licensing/v1/update-download?license_key=YOUR_LICENSE_KEY&product_slug=your-product-slug" \
  --output update.zip
```

## Testing with Different Tools

### Using Postman
1. Set base URL to `http://localhost:5000` (or your local WordPress URL)
2. Add `/wp-json/licensing/v1/` to the endpoint path
3. Set method to POST for most endpoints
4. Add JSON body with required parameters
5. Send request - localhost will be automatically allowed

### Using Browser JavaScript
```javascript
fetch('http://localhost:5000/wp-json/licensing/v1/validate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    license_key: 'your-license-key',
    product_slug: 'your-product-slug'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Using PHP (Client Plugin)
```php
$response = wp_remote_post('http://localhost:5000/wp-json/licensing/v1/validate', array(
    'headers' => array('Content-Type' => 'application/json'),
    'body' => json_encode(array(
        'license_key' => 'your-license-key',
        'product_slug' => 'your-product-slug'
    ))
));

$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);
```

## Development Workflow

### 1. Local Development Setup
1. Install WordPress locally (XAMPP, WAMP, Local, etc.)
2. Install WP Licensing Manager plugin
3. Configure products and licenses
4. Test API endpoints using localhost URLs

### 2. Plugin Integration Testing
1. Create test plugin that calls licensing API
2. Use localhost URLs during development
3. Test license validation, activation, deactivation
4. Verify update checking functionality

### 3. Pre-Production Testing
1. Test on staging server with HTTPS
2. Verify all functionality works with SSL
3. Test error handling for invalid licenses
4. Confirm update downloads work correctly

## Security Notes

### Development vs Production
- **Development**: HTTP localhost allowed for testing convenience
- **Production**: HTTPS required for security (license keys are sensitive)

### What's Protected
- License keys in transit (HTTPS in production)
- Update file downloads (protected by license validation)
- API access (rate limiting and validation)

### Best Practices
1. Use HTTPS in production environments
2. Never expose license keys in client-side code
3. Implement proper error handling
4. Test thoroughly in localhost before deploying

## Troubleshooting

### Common Issues
1. **"HTTPS required" error on localhost**
   - Check that your URL starts with `localhost`, `127.0.0.1`, or local IP
   - Verify the localhost detection function is working

2. **CORS errors in browser**
   - Add CORS headers if testing from different origin
   - Use server-side requests instead of browser JavaScript

3. **License not found errors**
   - Ensure license exists in database
   - Verify product slug matches exactly
   - Check license status is "active"

### Debug Mode
Enable WordPress debug mode to see detailed error logs:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log` for detailed API request information.

## Example Integration Code

### Basic License Check
```php
function check_my_plugin_license() {
    $license_key = get_option('my_plugin_license_key');
    $response = wp_remote_post('http://localhost:5000/wp-json/licensing/v1/validate', array(
        'body' => array(
            'license_key' => $license_key,
            'product_slug' => 'my-awesome-plugin'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return isset($data['success']) && $data['success'];
}
```

The API is now fully configured for localhost testing while maintaining production security requirements.
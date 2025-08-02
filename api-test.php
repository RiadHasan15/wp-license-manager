<!DOCTYPE html>
<html>
<head>
    <title>WP Licensing Manager - API Test Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #0073aa; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        .result { margin: 20px 0; padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .endpoint-section { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .localhost-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>WP Licensing Manager - API Test Tool</h1>
        
        <div class="localhost-info">
            <h3>🌐 Localhost Testing Enabled</h3>
            <p>This tool automatically detects localhost environments and allows API testing without HTTPS:</p>
            <ul>
                <li><strong>Allowed:</strong> localhost, 127.0.0.1, 192.168.x.x, 10.x.x.x</li>
                <li><strong>Production:</strong> HTTPS required for security</li>
            </ul>
        </div>

        <!-- License Validation Test -->
        <div class="endpoint-section">
            <h3>1. License Validation Test</h3>
            <form id="validateForm">
                <div class="form-group">
                    <label for="validate_base_url">Base URL:</label>
                    <input type="text" id="validate_base_url" value="http://localhost:5000" placeholder="http://localhost:5000">
                </div>
                <div class="form-group">
                    <label for="validate_license_key">License Key:</label>
                    <input type="text" id="validate_license_key" placeholder="Enter license key to test">
                </div>
                <div class="form-group">
                    <label for="validate_product_slug">Product Slug (optional):</label>
                    <input type="text" id="validate_product_slug" placeholder="my-product-slug">
                </div>
                <button type="submit">Test License Validation</button>
            </form>
            <div id="validateResult"></div>
        </div>

        <!-- License Activation Test -->
        <div class="endpoint-section">
            <h3>2. License Activation Test</h3>
            <form id="activateForm">
                <div class="form-group">
                    <label for="activate_base_url">Base URL:</label>
                    <input type="text" id="activate_base_url" value="http://localhost:5000">
                </div>
                <div class="form-group">
                    <label for="activate_license_key">License Key:</label>
                    <input type="text" id="activate_license_key" placeholder="Enter license key">
                </div>
                <div class="form-group">
                    <label for="activate_domain">Domain:</label>
                    <input type="text" id="activate_domain" value="example.com" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label for="activate_product_slug">Product Slug:</label>
                    <input type="text" id="activate_product_slug" placeholder="my-product-slug">
                </div>
                <button type="submit">Test License Activation</button>
            </form>
            <div id="activateResult"></div>
        </div>

        <!-- Update Check Test -->
        <div class="endpoint-section">
            <h3>3. Update Check Test</h3>
            <form id="updateForm">
                <div class="form-group">
                    <label for="update_base_url">Base URL:</label>
                    <input type="text" id="update_base_url" value="http://localhost:5000">
                </div>
                <div class="form-group">
                    <label for="update_license_key">License Key:</label>
                    <input type="text" id="update_license_key" placeholder="Enter license key">
                </div>
                <div class="form-group">
                    <label for="update_product_slug">Product Slug:</label>
                    <input type="text" id="update_product_slug" placeholder="my-product-slug">
                </div>
                <div class="form-group">
                    <label for="update_current_version">Current Version:</label>
                    <input type="text" id="update_current_version" value="1.0.0" placeholder="1.0.0">
                </div>
                <button type="submit">Test Update Check</button>
            </form>
            <div id="updateResult"></div>
        </div>

        <!-- Stats Test -->
        <div class="endpoint-section">
            <h3>4. Stats Test (Admin Only)</h3>
            <form id="statsForm">
                <div class="form-group">
                    <label for="stats_base_url">Base URL:</label>
                    <input type="text" id="stats_base_url" value="http://localhost:5000">
                </div>
                <button type="submit">Test Stats Endpoint</button>
            </form>
            <div id="statsResult"></div>
        </div>
    </div>

    <script>
        function makeApiCall(url, method, data, resultElementId) {
            const resultElement = document.getElementById(resultElementId);
            resultElement.innerHTML = '<div class="info">Making API call...</div>';
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                }
            };
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            fetch(url, options)
                .then(response => {
                    return response.text().then(text => {
                        try {
                            const json = JSON.parse(text);
                            return { json, status: response.status, ok: response.ok };
                        } catch (e) {
                            return { text, status: response.status, ok: response.ok };
                        }
                    });
                })
                .then(result => {
                    const className = result.ok ? 'success' : 'error';
                    const content = result.json ? 
                        `Status: ${result.status}\n\n${JSON.stringify(result.json, null, 2)}` :
                        `Status: ${result.status}\n\n${result.text}`;
                    
                    resultElement.innerHTML = `<div class="${className}">${content}</div>`;
                })
                .catch(error => {
                    resultElement.innerHTML = `<div class="error">Error: ${error.message}\n\nThis might be a CORS issue. Try testing from the same domain as your WordPress installation.</div>`;
                });
        }

        // License Validation
        document.getElementById('validateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const baseUrl = document.getElementById('validate_base_url').value.replace(/\/$/, '');
            const url = `${baseUrl}/wp-json/licensing/v1/validate`;
            const data = {
                license_key: document.getElementById('validate_license_key').value,
                product_slug: document.getElementById('validate_product_slug').value
            };
            makeApiCall(url, 'POST', data, 'validateResult');
        });

        // License Activation
        document.getElementById('activateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const baseUrl = document.getElementById('activate_base_url').value.replace(/\/$/, '');
            const url = `${baseUrl}/wp-json/licensing/v1/activate`;
            const data = {
                license_key: document.getElementById('activate_license_key').value,
                domain: document.getElementById('activate_domain').value,
                product_slug: document.getElementById('activate_product_slug').value
            };
            makeApiCall(url, 'POST', data, 'activateResult');
        });

        // Update Check
        document.getElementById('updateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const baseUrl = document.getElementById('update_base_url').value.replace(/\/$/, '');
            const url = `${baseUrl}/wp-json/licensing/v1/update-check`;
            const data = {
                license_key: document.getElementById('update_license_key').value,
                product_slug: document.getElementById('update_product_slug').value,
                current_version: document.getElementById('update_current_version').value
            };
            makeApiCall(url, 'POST', data, 'updateResult');
        });

        // Stats
        document.getElementById('statsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const baseUrl = document.getElementById('stats_base_url').value.replace(/\/$/, '');
            const url = `${baseUrl}/wp-json/licensing/v1/stats`;
            makeApiCall(url, 'GET', null, 'statsResult');
        });
    </script>
</body>
</html>
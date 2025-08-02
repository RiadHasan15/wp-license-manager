jQuery(document).ready(function($) {
    'use strict';

    // Modal functionality
    var currentModal = null;

    function openModal(modalId) {
        var modal = $('#' + modalId);
        if (modal.length) {
            modal.show();
            currentModal = modal;
            $('body').addClass('modal-open');
        }
    }

    function closeModal() {
        if (currentModal) {
            currentModal.hide();
            currentModal = null;
            $('body').removeClass('modal-open');
        }
    }

    // Close modal when clicking close button or outside modal
    $(document).on('click', '.wp-licensing-modal-close', closeModal);
    $(document).on('click', '.wp-licensing-modal', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Escape key closes modal
    $(document).keyup(function(e) {
        if (e.keyCode === 27 && currentModal) {
            closeModal();
        }
    });

    // License Management
    $('#add-license-btn').on('click', function(e) {
        e.preventDefault();
        $('#license-form')[0].reset();
        $('#license-id').val('');
        $('#license-modal-title').text(wpLicensingManager.strings.addLicense || 'Add License');
        openModal('license-modal');
    });

    $(document).on('click', '.edit-license', function(e) {
        e.preventDefault();
        var licenseId = $(this).data('license-id');
        var row = $(this).closest('tr');
        
        // Populate form with existing data
        $('#license-id').val(licenseId);
        $('#license-modal-title').text('Edit License');
        
        // Extract data from table row (this is a simplified version)
        // In a real implementation, you'd make an AJAX call to get the full license data
        var status = row.find('td:nth-child(4)').text().toLowerCase().trim();
        $('#status').val(status.replace(/[^a-z]/g, ''));
        
        openModal('license-modal');
    });

    $('#license-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'wp_licensing_manager_save_license',
            nonce: wpLicensingManager.nonce,
            license_id: $('#license-id').val(),
            product_id: $('#product-id').val(),
            customer_email: $('#customer-email').val(),
            status: $('#status').val(),
            expires_at: $('#expires-at').val(),
            max_activations: $('#max-activations').val()
        };

        var submitButton = $(this).find('button[type="submit"]');
        var originalText = submitButton.text();
        
        submitButton.text('Saving...').prop('disabled', true);

        $.post(wpLicensingManager.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    closeModal();
                    location.reload(); // Refresh page to show changes
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Request failed. Please try again.');
            })
            .always(function() {
                submitButton.text(originalText).prop('disabled', false);
            });
    });

    $(document).on('click', '.delete-license', function(e) {
        e.preventDefault();
        
        if (!confirm(wpLicensingManager.strings.confirmDelete)) {
            return;
        }

        var licenseId = $(this).data('license-id');
        var row = $(this).closest('tr');

        $.post(wpLicensingManager.ajaxUrl, {
            action: 'wp_licensing_manager_delete_license',
            nonce: wpLicensingManager.nonce,
            license_id: licenseId
        })
        .done(function(response) {
            if (response.success) {
                row.fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        })
        .fail(function() {
            alert('Request failed. Please try again.');
        });
    });

    // Product Management
    $('#add-product-btn').on('click', function(e) {
        e.preventDefault();
        $('#product-form')[0].reset();
        $('#product-id').val('');
        $('#product-modal-title').text('Add Product');
        openModal('product-modal');
    });

    $(document).on('click', '.edit-product', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        var row = $(this).closest('tr');
        
        // Populate form with existing data
        $('#product-id').val(productId);
        $('#product-modal-title').text('Edit Product');
        
        // Extract data from table row
        $('#product-name').val(row.find('td:first strong').text());
        $('#product-slug').val(row.find('td:nth-child(2) code').text());
        $('#product-version').val(row.find('td:nth-child(3)').text());
        
        openModal('product-modal');
    });

    $('#product-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'wp_licensing_manager_save_product',
            nonce: wpLicensingManager.nonce,
            product_id: $('#product-id').val(),
            slug: $('#product-slug').val(),
            name: $('#product-name').val(),
            latest_version: $('#product-version').val(),
            changelog: $('#product-changelog').val()
        };

        var submitButton = $(this).find('button[type="submit"]');
        var originalText = submitButton.text();
        
        submitButton.text('Saving...').prop('disabled', true);

        $.post(wpLicensingManager.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Request failed. Please try again.');
            })
            .always(function() {
                submitButton.text(originalText).prop('disabled', false);
            });
    });

    $(document).on('click', '.delete-product', function(e) {
        e.preventDefault();
        
        if (!confirm(wpLicensingManager.strings.confirmDelete)) {
            return;
        }

        var productId = $(this).data('product-id');
        var row = $(this).closest('tr');

        $.post(wpLicensingManager.ajaxUrl, {
            action: 'wp_licensing_manager_delete_product',
            nonce: wpLicensingManager.nonce,
            product_id: productId
        })
        .done(function(response) {
            if (response.success) {
                row.fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Error: ' + (response.data || 'Failed to delete product'));
            }
        })
        .fail(function() {
            alert('Request failed. Please try again.');
        });
    });

    // Upload Update File
    $(document).on('click', '.upload-update-file', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        var currentVersion = $(this).data('current-version') || '1.0.0';
        
        $('#upload-product-id').val(productId);
        
        // Auto-suggest next version number
        var versionParts = currentVersion.split('.');
        if (versionParts.length >= 3) {
            versionParts[2] = (parseInt(versionParts[2]) + 1).toString();
        } else if (versionParts.length === 2) {
            versionParts.push('1');
        } else {
            versionParts = ['1', '0', '1'];
        }
        var suggestedVersion = versionParts.join('.');
        
        $('#update-version').val(suggestedVersion);
        $('#update-changelog').val('Bug fixes and improvements');
        
        openModal('upload-modal');
    });

    // Integration Code
    $(document).on('click', '.view-integration', function(e) {
        e.preventDefault();
        var productSlug = $(this).data('product-slug');
        
        // Make AJAX request to get integration code
        $.post(wpLicensingManager.ajaxUrl, {
            action: 'wp_licensing_manager_get_integration_code',
            nonce: wpLicensingManager.nonce,
            product_slug: productSlug
        })
        .done(function(response) {
            if (response.success && response.data) {
                $('#integration-code').val(response.data);
                openModal('integration-modal');
            } else {
                // Fallback - generate basic integration code
                var integrationCode = generateBasicIntegrationCode(productSlug);
                $('#integration-code').val(integrationCode);
                openModal('integration-modal');
            }
        })
        .fail(function() {
            // Fallback - generate basic integration code
            var integrationCode = generateBasicIntegrationCode(productSlug);
            $('#integration-code').val(integrationCode);
            openModal('integration-modal');
        });
    });

    // Copy integration code
    $('#copy-integration-code').on('click', function() {
        var codeTextarea = $('#integration-code')[0];
        codeTextarea.select();
        codeTextarea.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            document.execCommand('copy');
            $(this).text('Copied!');
            setTimeout(function() {
                $('#copy-integration-code').text('Copy Code');
            }, 2000);
        } catch (err) {
            alert('Failed to copy code. Please select and copy manually.');
        }
    });

    // Generate basic integration code (fallback)
    function generateBasicIntegrationCode(productSlug) {
        var baseUrl = window.location.origin;
        return `<?php
/**
 * Basic License Integration for ${productSlug}
 */

class ${productSlug.replace(/-/g, '_').toUpperCase()}_License_Manager {
    private $plugin_file;
    private $version;
    private $license_server_url;
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->version = $version;
        $this->license_server_url = '${baseUrl}';
        
        add_action('admin_menu', array($this, 'license_menu'));
    }
    
    public function license_menu() {
        add_options_page(
            '${productSlug} License',
            '${productSlug} License',
            'manage_options',
            '${productSlug}-license',
            array($this, 'license_page')
        );
    }
    
    public function license_page() {
        // Add your license form here
        echo '<div class="wrap">';
        echo '<h1>${productSlug} License Settings</h1>';
        echo '<p>License management interface goes here.</p>';
        echo '</div>';
    }
}

// Initialize
// new ${productSlug.replace(/-/g, '_').toUpperCase()}_License_Manager(__FILE__, '1.0.0');`;
    }

    // Auto-generate slug from name
    $('#product-name').on('input', function() {
        if ($('#product-id').val() === '') { // Only for new products
            var name = $(this).val();
            var slug = name.toLowerCase()
                .replace(/[^a-z0-9 -]/g, '') // Remove invalid chars
                .replace(/\s+/g, '-') // Replace spaces with -
                .replace(/-+/g, '-') // Replace multiple - with single -
                .replace(/^-+|-+$/g, ''); // Remove leading/trailing -
            $('#product-slug').val(slug);
        }
    });

    // Form validation
    $('form').on('submit', function() {
        var form = $(this);
        var requiredFields = form.find('[required]');
        var isValid = true;

        requiredFields.each(function() {
            var field = $(this);
            if (!field.val().trim()) {
                field.addClass('error');
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });

        if (!isValid) {
            alert('Please fill in all required fields.');
            return false;
        }
    });

    // Remove error class on input
    $('[required]').on('input change', function() {
        $(this).removeClass('error');
    });

    // Add styles for error fields
    $('<style>')
        .prop('type', 'text/css')
        .html('.error { border-color: #d63638 !important; box-shadow: 0 0 2px rgba(214, 54, 56, 0.8) !important; }')
        .appendTo('head');
});

/**
 * WP Licensing Manager Email Admin JavaScript
 * 
 * Handles the email automation admin interface functionality including
 * tab switching, live preview, test emails, and form interactions.
 * 
 * @package WP_Licensing_Manager
 * @since 1.2.0
 */

(function($) {
    'use strict';

    var EmailAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initFormToggles();
        },

        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.switchTab);
            
            // Form toggles
            $(document).on('change', '#renewal_reminders_enabled', this.toggleRenewalSettings);
            $(document).on('change', '#usage_tips_enabled', this.toggleUsageTipsSettings);
            
            // Email actions
            $(document).on('click', '.preview-email', this.previewEmail);
            $(document).on('click', '.test-email', this.testEmail);
            
            // Modal controls
            $(document).on('click', '.email-modal-close, .email-modal', this.closeModal);
            $(document).on('click', '.email-modal-content', this.preventModalClose);
            
            // Form validation
            $(document).on('submit', '#email-settings-form', this.validateForm);
            
            // Auto-save drafts (optional enhancement)
            $(document).on('change keyup', 'input[type="text"], textarea', this.debounce(this.autoSave, 2000));
        },

        initTabs: function() {
            // Ensure first tab is active on load
            if (!$('.nav-tab.nav-tab-active').length) {
                $('.nav-tab:first').addClass('nav-tab-active');
                $('.tab-content:first').addClass('active');
            }
        },

        initFormToggles: function() {
            // Initialize toggle states based on current settings
            this.toggleRenewalSettings();
            this.toggleUsageTipsSettings();
        },

        switchTab: function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var targetId = $this.attr('href');
            
            // Update nav states
            $('.nav-tab').removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');
            
            // Update content visibility
            $('.tab-content').removeClass('active');
            $(targetId).addClass('active');
            
            // Focus on first input in new tab
            setTimeout(function() {
                $(targetId).find('input:first').focus();
            }, 100);
        },

        toggleRenewalSettings: function() {
            var isEnabled = $('#renewal_reminders_enabled').is(':checked');
            $('.renewal-settings').toggle(isEnabled);
            
            if (isEnabled) {
                $('.renewal-settings').slideDown(300);
            } else {
                $('.renewal-settings').slideUp(300);
            }
        },

        toggleUsageTipsSettings: function() {
            var isEnabled = $('#usage_tips_enabled').is(':checked');
            $('.usage-tips-settings').toggle(isEnabled);
            
            if (isEnabled) {
                $('.usage-tips-settings').slideDown(300);
            } else {
                $('.usage-tips-settings').slideUp(300);
            }
        },

        previewEmail: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var templateType = $button.data('template');
            var content = EmailAdmin.getEditorContent(templateType);
            var heading = $('#template_' + templateType + '_heading').val();
            
            if (!content.trim()) {
                alert(wpLicensingEmailAdmin.strings.empty_content || 'Please add some content to preview.');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(wpLicensingEmailAdmin.strings.generating || 'Generating...');
            
            $.ajax({
                url: wpLicensingEmailAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_licensing_preview_email',
                    template_type: templateType,
                    content: content,
                    heading: heading,
                    nonce: wpLicensingEmailAdmin.nonce
                },
                success: function(response) {
                    // Show modal with preview
                    $('#email-preview-iframe').attr('srcdoc', response);
                    $('#email-preview-modal').fadeIn(300);
                },
                error: function(xhr, status, error) {
                    console.error('Preview failed:', error);
                    alert(wpLicensingEmailAdmin.strings.preview_failed || 'Failed to generate preview. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text(wpLicensingEmailAdmin.strings.preview_email || 'Preview Email');
                }
            });
        },

        testEmail: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var templateType = $button.data('template');
            var originalText = $button.text();
            
            // Confirm action
            if (!confirm(wpLicensingEmailAdmin.strings.confirm_test)) {
                return;
            }
            
            // Validate content before sending
            var content = EmailAdmin.getEditorContent(templateType);
            if (!content.trim()) {
                alert(wpLicensingEmailAdmin.strings.empty_content || 'Please add some content before sending a test.');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(wpLicensingEmailAdmin.strings.sending || 'Sending...');
            
            $.ajax({
                url: wpLicensingEmailAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_licensing_test_email',
                    template_type: templateType,
                    nonce: wpLicensingEmailAdmin.nonce
                },
                success: function(response) {
                    try {
                        var data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data.success) {
                            EmailAdmin.showNotice(wpLicensingEmailAdmin.strings.test_email_sent, 'success');
                        } else {
                            EmailAdmin.showNotice(
                                wpLicensingEmailAdmin.strings.test_email_failed + 
                                (data.message ? ' ' + data.message : ''), 
                                'error'
                            );
                        }
                    } catch (e) {
                        console.error('Invalid response:', response);
                        EmailAdmin.showNotice(wpLicensingEmailAdmin.strings.test_email_failed, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Test email failed:', error);
                    EmailAdmin.showNotice(wpLicensingEmailAdmin.strings.test_email_failed, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        getEditorContent: function(templateType) {
            var editorId = 'template_' + templateType + '_content';
            
            // Try to get content from TinyMCE editor first
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get(editorId);
                if (editor && !editor.isHidden()) {
                    return editor.getContent();
                }
            }
            
            // Fallback to textarea
            return $('#' + editorId).val() || '';
        },

        closeModal: function(e) {
            if (e.target === this || $(e.target).hasClass('email-modal-close')) {
                $('#email-preview-modal').fadeOut(300);
            }
        },

        preventModalClose: function(e) {
            e.stopPropagation();
        },

        validateForm: function(e) {
            var isValid = true;
            var errors = [];
            
            // Validate from email
            var fromEmail = $('#from_email').val();
            if (fromEmail && !EmailAdmin.isValidEmail(fromEmail)) {
                errors.push(wpLicensingEmailAdmin.strings.invalid_email || 'Please enter a valid email address.');
                isValid = false;
            }
            
            // Validate reminder days (at least one should be selected if reminders are enabled)
            if ($('#renewal_reminders_enabled').is(':checked')) {
                if (!$('input[name="reminder_days[]"]:checked').length) {
                    errors.push(wpLicensingEmailAdmin.strings.no_reminder_days || 'Please select at least one reminder day.');
                    isValid = false;
                }
            }
            
            // Show errors
            if (!isValid) {
                e.preventDefault();
                EmailAdmin.showNotice(errors.join('\n'), 'error');
                return false;
            }
            
            return true;
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.wp-licensing-notice').remove();
            
            // Create new notice
            var notice = $('<div class="notice notice-' + type + ' wp-licensing-notice is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.wrap h1').after(notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: notice.offset().top - 50
            }, 300);
        },

        autoSave: function() {
            // Optional: Auto-save draft functionality
            // This could save current form state to localStorage or send AJAX request
            console.log('Auto-saving draft...');
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EmailAdmin.init();
        
        // Add keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                $('#email-settings-form').submit();
            }
            
            // Escape to close modal
            if (e.keyCode === 27) {
                $('#email-preview-modal').fadeOut(300);
            }
        });
        
        // Add visual feedback for form changes
        var formChanged = false;
        $('#email-settings-form').on('change input', function() {
            if (!formChanged) {
                formChanged = true;
                $(window).on('beforeunload', function() {
                    return wpLicensingEmailAdmin.strings.unsaved_changes || 'You have unsaved changes. Are you sure you want to leave?';
                });
            }
        });
        
        // Remove beforeunload warning on form submit
        $('#email-settings-form').on('submit', function() {
            $(window).off('beforeunload');
        });
    });

})(jQuery);
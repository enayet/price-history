/**
 * Admin JavaScript for WooCommerce Price History & Sale Compliance Plugin
 */
jQuery(document).ready(function($) {
    'use strict';

    // Initialize color pickers
    if ($.fn.wpColorPicker) {
        $('.color-picker').wpColorPicker({
            change: function(event, ui) {
                updateColorPreview($(this), ui.color.toString());
            },
            clear: function() {
                updateColorPreview($(this), '');
            }
        });
    }

    // Initialize admin functionality
    initializeAdminSettings();
    initializeReportsPage();
    handleFormValidation();
    setupNotifications();

    /**
     * Initialize admin settings page functionality
     */
    function initializeAdminSettings() {
        const settingsForm = $('#wc-price-history-settings-form');
        
        if (settingsForm.length) {
            // Handle settings form submission
            settingsForm.on('submit', function(e) {
                if (!validateSettingsForm()) {
                    e.preventDefault();
                    return false;
                }
                
                showLoadingState($(this));
            });

            // Handle dependency toggles
            handleSettingsDependencies();
            
            // Preview functionality
            setupPreview();
        }
    }

    /**
     * Initialize reports page functionality
     */
    function initializeReportsPage() {
        const reportsPage = $('.wc-price-history-reports');
        
        if (reportsPage.length) {
            // Handle export functionality
            $('.export-btn').on('click', function(e) {
                e.preventDefault();
                handleExport($(this).data('format'));
            });

            // Handle bulk actions
            setupBulkActions();
            
            // Auto-refresh functionality
            setupAutoRefresh();
            
            // Filter functionality
            setupFilters();
        }
    }

    /**
     * Handle settings dependencies
     */
    function handleSettingsDependencies() {
        const enablePlugin = $('input[name="enable_plugin"]');
        const displayLowestPrice = $('input[name="display_lowest_price"]');
        const showPriceChart = $('input[name="show_price_chart"]');

        // Toggle dependent fields based on main plugin toggle
        enablePlugin.on('change', function() {
            const isEnabled = $(this).is(':checked');
            toggleDependentFields('.plugin-dependent', isEnabled);
        }).trigger('change');

        // Toggle chart-specific options
        showPriceChart.on('change', function() {
            const isEnabled = $(this).is(':checked');
            toggleDependentFields('.chart-dependent', isEnabled);
        }).trigger('change');

        // Toggle price display options
        displayLowestPrice.on('change', function() {
            const isEnabled = $(this).is(':checked');
            toggleDependentFields('.price-display-dependent', isEnabled);
        }).trigger('change');
    }

    /**
     * Toggle dependent form fields
     */
    function toggleDependentFields(selector, enabled) {
        const fields = $(selector);
        
        if (enabled) {
            fields.removeClass('disabled').find('input, select, textarea').prop('disabled', false);
            fields.fadeTo(200, 1);
        } else {
            fields.addClass('disabled').find('input, select, textarea').prop('disabled', true);
            fields.fadeTo(200, 0.5);
        }
    }

    /**
     * Setup preview functionality
     */
    function setupPreview() {
        const previewContainer = $('#settings-preview');
        
        if (previewContainer.length) {
            // Update preview when settings change
            $('input[name="lowest_price_text"], select[name="chart_days"], input[name="chart_colors[]"]').on('change keyup', debounce(updatePreview, 500));
            
            // Initial preview
            updatePreview();
        }
    }

    /**
     * Update settings preview
     */
    function updatePreview() {
        const previewContainer = $('#settings-preview');
        const lowestPriceText = $('input[name="lowest_price_text"]').val();
        const chartDays = $('select[name="chart_days"]').val();
        
        // Update preview text
        const samplePrice = '$99.99';
        const previewText = lowestPriceText.replace('%s', samplePrice);
        previewContainer.find('.preview-lowest-price').text(previewText);
        
        // Update chart preview info
        previewContainer.find('.preview-chart-days').text(chartDays + ' days');
    }

    /**
     * Update color picker preview
     */
    function updateColorPreview(element, color) {
        const previewElement = element.closest('tr').find('.color-preview');
        if (previewElement.length) {
            previewElement.css('background-color', color);
        }
    }

    /**
     * Validate settings form
     */
    function validateSettingsForm() {
        let isValid = true;
        const errors = [];

        // Validate lowest price text
        const lowestPriceText = $('input[name="lowest_price_text"]').val().trim();
        if (lowestPriceText && !lowestPriceText.includes('%s')) {
            errors.push('Lowest price text must include %s placeholder for the price.');
            isValid = false;
        }

        // Validate chart days
        const chartDays = parseInt($('select[name="chart_days"]').val());
        if (chartDays < 1 || chartDays > 365) {
            errors.push('Chart days must be between 1 and 365.');
            isValid = false;
        }

        // Show errors if any
        if (!isValid) {
            showValidationErrors(errors);
        } else {
            clearValidationErrors();
        }

        return isValid;
    }

    /**
     * Show validation errors
     */
    function showValidationErrors(errors) {
        const errorContainer = $('#settings-validation-errors');
        
        if (errorContainer.length === 0) {
            $('<div id="settings-validation-errors" class="notice notice-error"><ul></ul></div>')
                .prependTo('.wrap');
        }

        const errorList = $('#settings-validation-errors ul');
        errorList.empty();
        
        errors.forEach(function(error) {
            errorList.append('<li>' + escapeHtml(error) + '</li>');
        });

        // Scroll to errors
        $('html, body').animate({
            scrollTop: $('#settings-validation-errors').offset().top - 50
        }, 500);
    }

    /**
     * Clear validation errors
     */
    function clearValidationErrors() {
        $('#settings-validation-errors').remove();
    }

    /**
     * Show loading state
     */
    function showLoadingState(form) {
        const submitButton = form.find('input[type="submit"]');
        const originalText = submitButton.val();
        
        submitButton.val('Saving...').prop('disabled', true);
        
        // Re-enable after form submission (fallback)
        setTimeout(function() {
            submitButton.val(originalText).prop('disabled', false);
        }, 5000);
    }

    /**
     * Handle form validation
     */
    function handleFormValidation() {
        // Real-time validation for specific fields
        $('input[name="lowest_price_text"]').on('blur', function() {
            const value = $(this).val().trim();
            const feedback = $(this).siblings('.field-feedback');
            
            if (value && !value.includes('%s')) {
                showFieldError($(this), 'Must include %s placeholder');
            } else {
                clearFieldError($(this));
            }
        });
    }

    /**
     * Show field-specific error
     */
    function showFieldError(field, message) {
        field.addClass('error');
        let feedback = field.siblings('.field-feedback');
        
        if (feedback.length === 0) {
            feedback = $('<span class="field-feedback error"></span>').insertAfter(field);
        }
        
        feedback.text(message).addClass('error');
    }

    /**
     * Clear field-specific error
     */
    function clearFieldError(field) {
        field.removeClass('error');
        field.siblings('.field-feedback').remove();
    }

    /**
     * Setup notifications
     */
    function setupNotifications() {
        // Handle dismissible notices
        $(document).on('click', '.notice-dismiss', function() {
            const notice = $(this).closest('.notice');
            notice.fadeOut(300, function() {
                notice.remove();
            });
        });

        // Auto-dismiss success notices
        $('.notice-success').delay(5000).fadeOut(300);
    }

    /**
     * Setup bulk actions for reports
     */
    function setupBulkActions() {
        const bulkActionSelect = $('#bulk-action-selector-top');
        const bulkActionButton = $('#doaction');
        
        if (bulkActionSelect.length && bulkActionButton.length) {
            bulkActionButton.on('click', function(e) {
                const action = bulkActionSelect.val();
                const checkedItems = $('.check-column input:checked');
                
                if (action === '-1') {
                    e.preventDefault();
                    alert('Please select an action.');
                    return false;
                }
                
                if (checkedItems.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one item.');
                    return false;
                }
                
                // Confirm destructive actions
                if (action === 'delete' || action === 'reset') {
                    const confirmed = confirm('Are you sure you want to perform this action? This cannot be undone.');
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }
    }

    /**
     * Setup auto-refresh for reports
     */
    function setupAutoRefresh() {
        const autoRefreshCheckbox = $('#auto-refresh');
        let refreshInterval;
        
        if (autoRefreshCheckbox.length) {
            autoRefreshCheckbox.on('change', function() {
                if ($(this).is(':checked')) {
                    refreshInterval = setInterval(function() {
                        location.reload();
                    }, 60000); // Refresh every minute
                } else {
                    clearInterval(refreshInterval);
                }
            });
        }
    }

    /**
     * Setup filters for reports
     */
    function setupFilters() {
        const filterForm = $('#reports-filters');
        
        if (filterForm.length) {
            // Handle filter changes
            filterForm.find('select, input').on('change', debounce(function() {
                filterForm.submit();
            }, 500));

            // Handle date range picker
            if ($.fn.datepicker) {
                $('.date-picker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: 0 // No future dates
                });
            }
        }
    }

    /**
     * Handle export functionality
     */
    function handleExport(format) {
        const exportButton = $('.export-btn[data-format="' + format + '"]');
        const originalText = exportButton.text();
        
        // Show loading state
        exportButton.text('Exporting...').prop('disabled', true);
        
        // Create export URL
        const exportUrl = new URL(window.location.href);
        exportUrl.searchParams.set('action', 'wc_price_history_export_' + format);
        exportUrl.searchParams.set('_wpnonce', $('#export-nonce').val());
        
        // Trigger download
        window.location.href = exportUrl.toString();
        
        // Reset button state
        setTimeout(function() {
            exportButton.text(originalText).prop('disabled', false);
        }, 2000);
    }

    /**
     * Debounce function to limit function calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize tooltips
     */
    function initializeTooltips() {
        if ($.fn.tooltip) {
            $('.has-tooltip').tooltip({
                position: {
                    my: "center bottom-20",
                    at: "center top",
                    using: function(position, feedback) {
                        $(this).css(position);
                        $("<div>")
                            .addClass("arrow")
                            .addClass(feedback.vertical)
                            .addClass(feedback.horizontal)
                            .appendTo(this);
                    }
                }
            });
        }
    }

    // Initialize tooltips
    initializeTooltips();

    /**
     * Handle tab navigation in settings
     */
    function initializeTabNavigation() {
        const tabs = $('.nav-tab-wrapper .nav-tab');
        const tabContents = $('.tab-content');
        
        if (tabs.length && tabContents.length) {
            tabs.on('click', function(e) {
                e.preventDefault();
                
                const targetTab = $(this).attr('href').substring(1);
                
                // Update active tab
                tabs.removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show corresponding content
                tabContents.hide();
                $('#' + targetTab).show();
                
                // Update URL hash
                window.location.hash = targetTab;
            });
            
            // Show initial tab based on URL hash
            const initialTab = window.location.hash.substring(1);
            if (initialTab && $('#' + initialTab).length) {
                tabs.filter('[href="#' + initialTab + '"]').trigger('click');
            } else {
                tabs.first().trigger('click');
            }
        }
    }

    // Initialize tab navigation
    initializeTabNavigation();
});
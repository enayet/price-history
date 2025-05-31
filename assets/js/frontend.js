/**
 * Frontend JavaScript for WooCommerce Price History & Sale Compliance Plugin
 */
jQuery(document).ready(function($) {
    'use strict';

    // Initialize price history chart if data is available
    if (typeof priceHistoryData !== 'undefined' && priceHistoryData.labels && priceHistoryData.labels.length > 0) {
        initializePriceChart();
    }

    /**
     * Initialize the price history chart
     */
    function initializePriceChart() {
        const ctx = document.getElementById('price-history-chart');
        if (!ctx) {
            return;
        }

        // Prepare chart configuration
        const config = {
            type: 'line',
            data: priceHistoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return formatPrice(value);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatTooltip(context);
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        };

        // Create the chart
        new Chart(ctx, config);
    }

    /**
     * Format price for display
     */
    function formatPrice(value) {
        // Use WooCommerce price format if available
        if (typeof wc_price_params !== 'undefined') {
            const symbol = wc_price_params.currency_symbol || '$';
            const position = wc_price_params.currency_pos || 'left';
            const decimals = wc_price_params.num_decimals || 2;
            const decimalSeparator = wc_price_params.decimal_separator || '.';
            const thousandSeparator = wc_price_params.thousand_separator || ',';
            
            const formattedValue = parseFloat(value).toFixed(decimals)
                .replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator)
                .replace('.', decimalSeparator);
            
            switch (position) {
                case 'left':
                    return symbol + formattedValue;
                case 'right':
                    return formattedValue + symbol;
                case 'left_space':
                    return symbol + ' ' + formattedValue;
                case 'right_space':
                    return formattedValue + ' ' + symbol;
                default:
                    return symbol + formattedValue;
            }
        }
        
        // Fallback formatting
        return '$' + parseFloat(value).toFixed(2);
    }

    /**
     * Format tooltip content
     */
    function formatTooltip(context) {
        const label = context.dataset.label || '';
        const value = formatPrice(context.parsed.y);
        return label + ': ' + value;
    }

    /**
     * Add smooth animations to price history elements
     */
    function addAnimations() {
        const priceElements = $('.wc-price-history-lowest-price, .wc-price-history-chart-container');
        
        priceElements.each(function() {
            const $element = $(this);
            
            // Add fade-in animation
            $element.css({
                'opacity': '0',
                'transform': 'translateY(20px)',
                'transition': 'all 0.5s ease-in-out'
            });
            
            // Trigger animation when element is in viewport
            setTimeout(function() {
                $element.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, 100);
        });
    }

    // Add animations
    addAnimations();

    /**
     * Handle chart container responsive behavior
     */
    function handleResponsiveChart() {
        const chartContainer = $('.wc-price-history-chart-container');
        
        if (chartContainer.length) {
            // Adjust chart height based on container width
            const containerWidth = chartContainer.width();
            const chartCanvas = chartContainer.find('canvas');
            
            if (containerWidth < 480) {
                chartCanvas.attr('height', '150');
            } else if (containerWidth < 768) {
                chartCanvas.attr('height', '180');
            } else {
                chartCanvas.attr('height', '200');
            }
        }
    }

    // Handle responsive chart on window resize
    $(window).on('resize', debounce(handleResponsiveChart, 250));

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
     * Add accessibility improvements
     */
    function addAccessibilityFeatures() {
        const chartContainer = $('.wc-price-history-chart-container');
        const lowestPriceMessage = $('.wc-price-history-lowest-price');
        
        // Add ARIA labels
        if (chartContainer.length) {
            chartContainer.attr('role', 'img');
            chartContainer.attr('aria-label', 'Price history chart showing price changes over time');
            
            const canvas = chartContainer.find('canvas');
            canvas.attr('tabindex', '0');
            canvas.attr('aria-label', 'Interactive price history chart');
        }
        
        if (lowestPriceMessage.length) {
            lowestPriceMessage.attr('role', 'status');
            lowestPriceMessage.attr('aria-live', 'polite');
        }
    }

    // Add accessibility features
    addAccessibilityFeatures();

    /**
     * Handle chart interactions for mobile devices
     */
    function handleMobileInteractions() {
        const chartCanvas = $('#price-history-chart');
        
        if (chartCanvas.length && 'ontouchstart' in window) {
            chartCanvas.on('touchstart', function(e) {
                e.preventDefault(); // Prevent zoom on double tap
            });
        }
    }

    // Handle mobile interactions
    handleMobileInteractions();
});
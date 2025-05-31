<?php
/**
 * Frontend display class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend display class
 */
class WC_Price_History_Frontend_Display {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_lowest_price_message' ), 25 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_price_chart' ), 30 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Shortcode support
        add_shortcode( 'wc_price_history', array( $this, 'price_history_shortcode' ) );
        add_shortcode( 'wc_lowest_price', array( $this, 'lowest_price_shortcode' ) );
        
        // Block support for WooCommerce Blocks
        add_action( 'init', array( $this, 'register_blocks' ) );
        
        // Widget support
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if ( ! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }

        // Only load Chart.js if charts are enabled
        $plugin = WC_Price_History_Compliance::get_instance();
        if ( 'yes' === $plugin->get_option( 'show_price_chart' ) ) {
            wp_enqueue_script( 'chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true );
        }

        wp_enqueue_script( 
            'wc-price-history-frontend', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/js/frontend.js', 
            array( 'jquery' ), 
            WC_PRICE_HISTORY_VERSION, 
            true 
        );
        
        wp_enqueue_style( 
            'wc-price-history-frontend', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            WC_PRICE_HISTORY_VERSION 
        );

        // Localize script for AJAX and settings
        wp_localize_script( 'wc-price-history-frontend', 'wcPriceHistory', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_price_history_frontend' ),
            'currency' => array(
                'symbol' => get_woocommerce_currency_symbol(),
                'position' => get_option( 'woocommerce_currency_pos' ),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals()
            ),
            'i18n' => array(
                'loading' => esc_html__( 'Loading...', 'wc-price-history-compliance' ),
                'error' => esc_html__( 'Error loading price history.', 'wc-price-history-compliance' ),
                'no_data' => esc_html__( 'No price history available.', 'wc-price-history-compliance' )
            )
        ) );
    }

    /**
     * Display lowest price message during sales
     */
    public function display_lowest_price_message() {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_plugin' ) || 'yes' !== $plugin->get_option( 'display_lowest_price' ) ) {
            return;
        }

        global $product;
        
        if ( ! $product || ! $product->is_on_sale() ) {
            return;
        }

        $this->render_lowest_price_message( $product->get_id() );
    }

    /**
     * Render lowest price message for a specific product
     */
    public function render_lowest_price_message( $product_id ) {
        $database = new WC_Price_History_Database();
        $lowest_price = $database->get_lowest_price( $product_id, 30 );
        
        if ( ! $lowest_price ) {
            return;
        }

        $plugin = WC_Price_History_Compliance::get_instance();
        $lowest_price_formatted = wc_price( $lowest_price );
        $message_template = $plugin->get_option( 'lowest_price_text', esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ) );
        $message = sprintf( $message_template, $lowest_price_formatted );
        
        $classes = apply_filters( 'wc_price_history_lowest_price_classes', array( 'wc-price-history-lowest-price' ), $product_id );
        
        echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-product-id="' . esc_attr( $product_id ) . '">';
        echo '<p class="lowest-price-message">' . wp_kses_post( $message ) . '</p>';
        echo '</div>';
    }

    /**
     * Display price history chart
     */
    public function display_price_chart() {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_plugin' ) || 'yes' !== $plugin->get_option( 'show_price_chart' ) ) {
            return;
        }

        global $product;
        
        if ( ! $product ) {
            return;
        }

        $this->render_price_chart( $product->get_id() );
    }

    /**
     * Render price chart for a specific product
     */
    public function render_price_chart( $product_id ) {
        $chart_generator = new WC_Price_History_Chart_Generator();
        $chart_data = $chart_generator->get_chart_data( $product_id );
        
        if ( empty( $chart_data ) || empty( $chart_data['labels'] ) ) {
            return;
        }

        $classes = apply_filters( 'wc_price_history_chart_classes', array( 'wc-price-history-chart-container' ), $product_id );
        $chart_title = apply_filters( 'wc_price_history_chart_title', esc_html__( 'Price History', 'wc-price-history-compliance' ), $product_id );
        
        echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-product-id="' . esc_attr( $product_id ) . '">';
        echo '<h3>' . esc_html( $chart_title ) . '</h3>';
        echo '<div class="chart-wrapper">';
        echo '<canvas id="price-history-chart-' . esc_attr( $product_id ) . '" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';
        
        // Output chart data as JSON for JavaScript
        wp_localize_script( 'wc-price-history-frontend', 'priceHistoryData_' . $product_id, $chart_data );
    }

    /**
     * Price history shortcode
     */
    public function price_history_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'product_id' => get_the_ID(),
            'show_chart' => 'true',
            'show_lowest_price' => 'true',
            'days' => 30
        ), $atts, 'wc_price_history' );

        if ( empty( $atts['product_id'] ) ) {
            return '';
        }

        $product_id = intval( $atts['product_id'] );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return '';
        }

        ob_start();
        
        echo '<div class="wc-price-history-shortcode" data-product-id="' . esc_attr( $product_id ) . '">';
        
        // Show lowest price if enabled and product is on sale
        if ( 'true' === $atts['show_lowest_price'] && $product->is_on_sale() ) {
            $this->render_lowest_price_message( $product_id );
        }
        
        // Show chart if enabled
        if ( 'true' === $atts['show_chart'] ) {
            $this->render_price_chart( $product_id );
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }

    /**
     * Lowest price shortcode
     */
    public function lowest_price_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'product_id' => get_the_ID(),
            'days' => 30,
            'format' => 'message' // 'message' or 'price_only'
        ), $atts, 'wc_lowest_price' );

        if ( empty( $atts['product_id'] ) ) {
            return '';
        }

        $product_id = intval( $atts['product_id'] );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return '';
        }

        $database = new WC_Price_History_Database();
        $lowest_price = $database->get_lowest_price( $product_id, intval( $atts['days'] ) );
        
        if ( ! $lowest_price ) {
            return '';
        }

        if ( 'price_only' === $atts['format'] ) {
            return wc_price( $lowest_price );
        }

        $plugin = WC_Price_History_Compliance::get_instance();
        $lowest_price_formatted = wc_price( $lowest_price );
        $message_template = $plugin->get_option( 'lowest_price_text', esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ) );
        $message = sprintf( $message_template, $lowest_price_formatted );
        
        return '<span class="wc-price-history-lowest-price-shortcode">' . wp_kses_post( $message ) . '</span>';
    }

    /**
     * Register blocks for WooCommerce Blocks compatibility
     */
    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        // Register price history block
        register_block_type( 'wc-price-history/price-history', array(
            'render_callback' => array( $this, 'render_price_history_block' ),
            'attributes' => array(
                'showChart' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showLowestPrice' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'days' => array(
                    'type' => 'number',
                    'default' => 30
                )
            )
        ) );
    }

    /**
     * Render price history block
     */
    public function render_price_history_block( $attributes ) {
        global $product;
        
        if ( ! $product ) {
            return '';
        }

        $product_id = $product->get_id();
        
        ob_start();
        
        echo '<div class="wp-block-wc-price-history-price-history" data-product-id="' . esc_attr( $product_id ) . '">';
        
        // Show lowest price if enabled and product is on sale
        if ( $attributes['showLowestPrice'] && $product->is_on_sale() ) {
            $this->render_lowest_price_message( $product_id );
        }
        
        // Show chart if enabled
        if ( $attributes['showChart'] ) {
            $this->render_price_chart( $product_id );
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget( 'WC_Price_History_Widget' );
    }

    /**
     * AJAX handler for getting price history data
     */
    public function ajax_get_price_history() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_frontend' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 30;
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID', 'wc-price-history-compliance' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( esc_html__( 'Product not found', 'wc-price-history-compliance' ) );
        }

        $chart_generator = new WC_Price_History_Chart_Generator();
        $chart_data = $chart_generator->get_chart_data( $product_id, $days );
        
        if ( empty( $chart_data ) ) {
            wp_send_json_error( esc_html__( 'No price history available', 'wc-price-history-compliance' ) );
        }

        wp_send_json_success( $chart_data );
    }

    /**
     * Get price history for product variations
     */
    public function display_variation_price_info() {
        // This can be extended to show price history for variations
        global $product;
        
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_plugin' ) ) {
            return;
        }

        // Add JavaScript to handle variation price history
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('form.variations_form').on('show_variation', function(event, variation) {
                // Update price history for selected variation
                if (variation.variation_id) {
                    updateVariationPriceHistory(variation.variation_id);
                }
            });
            
            function updateVariationPriceHistory(variationId) {
                // AJAX call to get variation price history
                $.post(wcPriceHistory.ajaxUrl, {
                    action: 'wc_price_history_get_variation_data',
                    variation_id: variationId,
                    nonce: wcPriceHistory.nonce
                }, function(response) {
                    if (response.success) {
                        updatePriceHistoryDisplay(response.data);
                    }
                });
            }
            
            function updatePriceHistoryDisplay(data) {
                // Update the price history display with variation data
                if (data.lowest_price_message) {
                    $('.wc-price-history-lowest-price').html(data.lowest_price_message);
                }
                if (data.chart_data) {
                    // Update chart with new data
                    updatePriceChart(data.chart_data);
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Filter product price HTML to add price history info
     */
    public function filter_price_html( $price_html, $product ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_plugin' ) ) {
            return $price_html;
        }

        // Only show on single product pages or if specifically requested
        if ( ! is_product() && ! apply_filters( 'wc_price_history_show_in_loops', false ) ) {
            return $price_html;
        }

        $product_id = $product->get_id();
        
        if ( $product->is_on_sale() ) {
            $database = new WC_Price_History_Database();
            $lowest_price = $database->get_lowest_price( $product_id, 30 );
            
            if ( $lowest_price ) {
                $current_sale_price = floatval( $product->get_sale_price() );
                
                // Add a note if current sale price equals the lowest price
                if ( abs( $current_sale_price - floatval( $lowest_price ) ) < 0.01 ) {
                    $price_html .= '<span class="wc-price-history-best-price-note">' . 
                                   esc_html__( ' (Best price in 30 days)', 'wc-price-history-compliance' ) . 
                                   '</span>';
                }
            }
        }

        return $price_html;
    }

    /**
     * Add structured data for price history
     */
    public function add_structured_data() {
        global $product;
        
        if ( ! $product || ! is_product() ) {
            return;
        }

        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_plugin' ) ) {
            return;
        }

        $product_id = $product->get_id();
        $database = new WC_Price_History_Database();
        $price_history = $database->get_price_history( $product_id, 30 );
        
        if ( empty( $price_history ) ) {
            return;
        }

        $structured_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'priceHistory' => array()
        );

        foreach ( $price_history as $record ) {
            $price = ! empty( $record->sale_price ) ? $record->sale_price : $record->regular_price;
            $structured_data['priceHistory'][] = array(
                'price' => $price,
                'currency' => get_woocommerce_currency(),
                'validFrom' => $record->price_date
            );
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $structured_data ) . '</script>';
    }
}
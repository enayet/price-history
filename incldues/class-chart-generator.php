<?php
/**
 * Chart generator class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Chart generator class
 */
class WC_Price_History_Chart_Generator {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_wc_price_history_get_chart_data', array( $this, 'ajax_get_chart_data' ) );
        add_action( 'wp_ajax_nopriv_wc_price_history_get_chart_data', array( $this, 'ajax_get_chart_data' ) );
    }

    /**
     * Get chart data for a product
     */
    public function get_chart_data( $product_id, $days = null ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        $days = $days ?: intval( $plugin->get_option( 'chart_days', 30 ) );
        
        $database = new WC_Price_History_Database();
        $price_history = $database->get_price_history( $product_id, $days );
        
        if ( empty( $price_history ) ) {
            return array();
        }

        // Sort by date ascending for proper chart display
        $price_history = array_reverse( $price_history );

        $labels = array();
        $regular_prices = array();
        $sale_prices = array();
        $effective_prices = array();
        
        foreach ( $price_history as $record ) {
            $date_label = $this->format_date_label( $record->price_date, $days );
            $labels[] = $date_label;
            
            $regular_price = floatval( $record->regular_price );
            $sale_price = ! empty( $record->sale_price ) ? floatval( $record->sale_price ) : null;
            $effective_price = $sale_price ?: $regular_price;
            
            $regular_prices[] = $regular_price;
            $sale_prices[] = $sale_price;
            $effective_prices[] = $effective_price;
        }

        $colors = $plugin->get_option( 'chart_colors', array( 
            'primary' => '#007cba', 
            'secondary' => '#50575e',
            'sale' => '#e74c3c'
        ) );
        
        $chart_type = $plugin->get_option( 'chart_type', 'line' );
        $datasets = $this->prepare_datasets( $regular_prices, $sale_prices, $effective_prices, $colors, $chart_type );
        
        return array(
            'type' => $chart_type,
            'data' => array(
                'labels' => $labels,
                'datasets' => $datasets
            ),
            'options' => $this->get_chart_options( $colors )
        );
    }

    /**
     * Prepare chart datasets
     */
    private function prepare_datasets( $regular_prices, $sale_prices, $effective_prices, $colors, $chart_type ) {
        $datasets = array();
        
        // Main price line (effective prices)
        $datasets[] = array(
            'label' => esc_html__( 'Price', 'wc-price-history-compliance' ),
            'data' => $effective_prices,
            'borderColor' => $colors['primary'],
            'backgroundColor' => $this->hex_to_rgba( $colors['primary'], 0.1 ),
            'fill' => 'line' === $chart_type,
            'tension' => 0.4,
            'pointRadius' => 4,
            'pointHoverRadius' => 6,
            'pointBackgroundColor' => $colors['primary'],
            'pointBorderColor' => '#ffffff',
            'pointBorderWidth' => 2
        );

        // Show separate sale price line if there are sale prices
        if ( array_filter( $sale_prices ) ) {
            $datasets[] = array(
                'label' => esc_html__( 'Sale Price', 'wc-price-history-compliance' ),
                'data' => $sale_prices,
                'borderColor' => $colors['sale'] ?? '#e74c3c',
                'backgroundColor' => $this->hex_to_rgba( $colors['sale'] ?? '#e74c3c', 0.1 ),
                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
                'pointBackgroundColor' => $colors['sale'] ?? '#e74c3c',
                'pointBorderColor' => '#ffffff',
                'pointBorderWidth' => 1,
                'borderDash' => array( 5, 5 )
            );
        }

        // Add average price line for reference
        if ( count( $effective_prices ) > 1 ) {
            $average_price = array_sum( $effective_prices ) / count( $effective_prices );
            $average_line = array_fill( 0, count( $effective_prices ), $average_price );
            
            $datasets[] = array(
                'label' => esc_html__( 'Average', 'wc-price-history-compliance' ),
                'data' => $average_line,
                'borderColor' => $colors['secondary'],
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0,
                'pointRadius' => 0,
                'pointHoverRadius' => 0,
                'borderDash' => array( 2, 2 ),
                'borderWidth' => 1
            );
        }

        return $datasets;
    }

    /**
     * Get chart options
     */
    private function get_chart_options( $colors ) {
        return array(
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => array(
                'intersect' => false,
                'mode' => 'index'
            ),
            'scales' => array(
                'x' => array(
                    'display' => true,
                    'title' => array(
                        'display' => true,
                        'text' => esc_html__( 'Date', 'wc-price-history-compliance' )
                    ),
                    'grid' => array(
                        'display' => true,
                        'color' => 'rgba(0,0,0,0.1)'
                    )
                ),
                'y' => array(
                    'display' => true,
                    'title' => array(
                        'display' => true,
                        'text' => esc_html__( 'Price', 'wc-price-history-compliance' )
                    ),
                    'beginAtZero' => false,
                    'grid' => array(
                        'display' => true,
                        'color' => 'rgba(0,0,0,0.1)'
                    ),
                    'ticks' => array(
                        'callback' => 'formatPrice'
                    )
                )
            ),
            'plugins' => array(
                'legend' => array(
                    'display' => true,
                    'position' => 'top',
                    'labels' => array(
                        'usePointStyle' => true,
                        'padding' => 15
                    )
                ),
                'tooltip' => array(
                    'enabled' => true,
                    'backgroundColor' => 'rgba(0,0,0,0.8)',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'borderColor' => $colors['primary'],
                    'borderWidth' => 1,
                    'cornerRadius' => 6,
                    'displayColors' => true,
                    'callbacks' => array(
                        'label' => 'formatTooltip',
                        'title' => 'formatTooltipTitle'
                    )
                )
            ),
            'elements' => array(
                'point' => array(
                    'hoverRadius' => 8
                ),
                'line' => array(
                    'tension' => 0.4
                )
            ),
            'animation' => array(
                'duration' => 1000,
                'easing' => 'easeInOutQuart'
            )
        );
    }

    /**
     * Format date label based on time range
     */
    private function format_date_label( $date, $days ) {
        $timestamp = strtotime( $date );
        
        if ( $days <= 7 ) {
            // Show day and time for week view
            return gmdate( 'M j, H:i', $timestamp );
        } elseif ( $days <= 30 ) {
            // Show month and day for month view
            return gmdate( 'M j', $timestamp );
        } elseif ( $days <= 90 ) {
            // Show month and day for quarterly view
            return gmdate( 'M j', $timestamp );
        } else {
            // Show month and year for longer periods
            return gmdate( 'M Y', $timestamp );
        }
    }

    /**
     * Convert hex color to rgba
     */
    private function hex_to_rgba( $hex, $alpha = 1 ) {
        $hex = str_replace( '#', '', $hex );
        
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /**
     * Get chart data for multiple products (comparison)
     */
    public function get_comparison_chart_data( $product_ids, $days = 30 ) {
        if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
            return array();
        }

        $database = new WC_Price_History_Database();
        $all_labels = array();
        $datasets = array();
        $colors = array( '#007cba', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c' );
        
        foreach ( $product_ids as $index => $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $price_history = $database->get_price_history( $product_id, $days );
            if ( empty( $price_history ) ) {
                continue;
            }

            $price_history = array_reverse( $price_history );
            $labels = array();
            $prices = array();
            
            foreach ( $price_history as $record ) {
                $date_label = $this->format_date_label( $record->price_date, $days );
                $labels[] = $date_label;
                $all_labels[] = $date_label;
                
                $price = ! empty( $record->sale_price ) ? floatval( $record->sale_price ) : floatval( $record->regular_price );
                $prices[] = $price;
            }

            $color = $colors[ $index % count( $colors ) ];
            
            $datasets[] = array(
                'label' => $product->get_name(),
                'data' => $prices,
                'borderColor' => $color,
                'backgroundColor' => $this->hex_to_rgba( $color, 0.1 ),
                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 3,
                'pointHoverRadius' => 6
            );
        }

        // Get unique labels and sort them
        $unique_labels = array_unique( $all_labels );
        sort( $unique_labels );
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $unique_labels,
                'datasets' => $datasets
            ),
            'options' => $this->get_comparison_chart_options()
        );
    }

    /**
     * Get comparison chart options
     */
    private function get_comparison_chart_options() {
        return array(
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => array(
                'intersect' => false,
                'mode' => 'index'
            ),
            'scales' => array(
                'x' => array(
                    'display' => true,
                    'title' => array(
                        'display' => true,
                        'text' => esc_html__( 'Date', 'wc-price-history-compliance' )
                    )
                ),
                'y' => array(
                    'display' => true,
                    'title' => array(
                        'display' => true,
                        'text' => esc_html__( 'Price', 'wc-price-history-compliance' )
                    ),
                    'beginAtZero' => false,
                    'ticks' => array(
                        'callback' => 'formatPrice'
                    )
                )
            ),
            'plugins' => array(
                'legend' => array(
                    'display' => true,
                    'position' => 'top'
                ),
                'tooltip' => array(
                    'callbacks' => array(
                        'label' => 'formatTooltip'
                    )
                )
            )
        );
    }

    /**
     * Generate chart for admin dashboard widget
     */
    public function get_dashboard_chart_data( $days = 7 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        // Get price changes by day
        $sql = $wpdb->prepare(
            "SELECT DATE(price_date) as date, COUNT(*) as changes 
             FROM {$table_name} 
             WHERE price_date >= %s 
             GROUP BY DATE(price_date) 
             ORDER BY date ASC",
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $daily_changes = $wpdb->get_results( $sql );
        
        $labels = array();
        $data = array();
        
        // Fill in missing days with zero
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $labels[] = gmdate( 'M j', strtotime( $date ) );
            
            // Find data for this date
            $changes_count = 0;
            foreach ( $daily_changes as $change ) {
                if ( $change->date === $date ) {
                    $changes_count = intval( $change->changes );
                    break;
                }
            }
            $data[] = $changes_count;
        }
        
        return array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => esc_html__( 'Price Changes', 'wc-price-history-compliance' ),
                        'data' => $data,
                        'backgroundColor' => '#007cba',
                        'borderColor' => '#005a87',
                        'borderWidth' => 1
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        )
                    )
                ),
                'plugins' => array(
                    'legend' => array(
                        'display' => false
                    )
                )
            )
        );
    }

    /**
     * AJAX handler for getting chart data
     */
    public function ajax_get_chart_data() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_frontend' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 30;
        $chart_type = isset( $_POST['chart_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_type'] ) ) : 'single';
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID', 'wc-price-history-compliance' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( esc_html__( 'Product not found', 'wc-price-history-compliance' ) );
        }

        if ( 'comparison' === $chart_type && isset( $_POST['product_ids'] ) ) {
            $product_ids = array_map( 'intval', $_POST['product_ids'] );
            $chart_data = $this->get_comparison_chart_data( $product_ids, $days );
        } else {
            $chart_data = $this->get_chart_data( $product_id, $days );
        }
        
        if ( empty( $chart_data ) ) {
            wp_send_json_error( esc_html__( 'No price history available', 'wc-price-history-compliance' ) );
        }

        wp_send_json_success( $chart_data );
    }

    /**
     * Generate chart image for email reports
     */
    public function generate_chart_image( $product_id, $days = 30, $width = 800, $height = 400 ) {
        // This would require a chart generation library like phpChart or integration with a service
        // For now, return a placeholder or URL to generate chart via external service
        
        $chart_data = $this->get_chart_data( $product_id, $days );
        
        if ( empty( $chart_data ) ) {
            return false;
        }

        // Example using Chart.js via QuickChart.io (external service)
        $chart_config = wp_json_encode( $chart_data );
        $chart_url = 'https://quickchart.io/chart?width=' . $width . '&height=' . $height . '&c=' . urlencode( $chart_config );
        
        return $chart_url;
    }

    /**
     * Get price trend analysis
     */
    public function get_price_trend_analysis( $product_id, $days = 30 ) {
        $database = new WC_Price_History_Database();
        $price_history = $database->get_price_history( $product_id, $days );
        
        if ( empty( $price_history ) || count( $price_history ) < 2 ) {
            return array(
                'trend' => 'insufficient_data',
                'direction' => 'none',
                'change_percentage' => 0,
                'volatility' => 'low'
            );
        }

        // Sort by date ascending
        $price_history = array_reverse( $price_history );
        
        $prices = array();
        foreach ( $price_history as $record ) {
            $price = ! empty( $record->sale_price ) ? floatval( $record->sale_price ) : floatval( $record->regular_price );
            $prices[] = $price;
        }

        $first_price = $prices[0];
        $last_price = end( $prices );
        $change_percentage = ( ( $last_price - $first_price ) / $first_price ) * 100;
        
        // Determine trend direction
        $direction = 'stable';
        if ( abs( $change_percentage ) > 5 ) {
            $direction = $change_percentage > 0 ? 'increasing' : 'decreasing';
        }

        // Calculate volatility
        $volatility = $this->calculate_price_volatility( $prices );
        
        // Determine trend strength
        $trend = 'stable';
        if ( abs( $change_percentage ) > 20 ) {
            $trend = 'strong';
        } elseif ( abs( $change_percentage ) > 10 ) {
            $trend = 'moderate';
        } elseif ( abs( $change_percentage ) > 5 ) {
            $trend = 'weak';
        }

        return array(
            'trend' => $trend,
            'direction' => $direction,
            'change_percentage' => round( $change_percentage, 2 ),
            'volatility' => $volatility,
            'price_range' => array(
                'min' => min( $prices ),
                'max' => max( $prices ),
                'average' => array_sum( $prices ) / count( $prices )
            ),
            'data_points' => count( $prices )
        );
    }

    /**
     * Calculate price volatility
     */
    private function calculate_price_volatility( $prices ) {
        if ( count( $prices ) < 2 ) {
            return 'low';
        }

        $mean = array_sum( $prices ) / count( $prices );
        $variance = 0;
        
        foreach ( $prices as $price ) {
            $variance += pow( $price - $mean, 2 );
        }
        
        $variance = $variance / count( $prices );
        $std_deviation = sqrt( $variance );
        $coefficient_of_variation = ( $std_deviation / $mean ) * 100;
        
        if ( $coefficient_of_variation > 20 ) {
            return 'high';
        } elseif ( $coefficient_of_variation > 10 ) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Generate price forecast (simple linear regression)
     */
    public function generate_price_forecast( $product_id, $days = 30, $forecast_days = 7 ) {
        $database = new WC_Price_History_Database();
        $price_history = $database->get_price_history( $product_id, $days );
        
        if ( empty( $price_history ) || count( $price_history ) < 3 ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'Insufficient data for forecasting', 'wc-price-history-compliance' )
            );
        }

        // Sort by date ascending
        $price_history = array_reverse( $price_history );
        
        $x_values = array();
        $y_values = array();
        
        foreach ( $price_history as $index => $record ) {
            $x_values[] = $index;
            $price = ! empty( $record->sale_price ) ? floatval( $record->sale_price ) : floatval( $record->regular_price );
            $y_values[] = $price;
        }

        // Simple linear regression
        $regression = $this->linear_regression( $x_values, $y_values );
        
        if ( ! $regression ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'Unable to generate forecast', 'wc-price-history-compliance' )
            );
        }

        // Generate forecast
        $forecast = array();
        $last_x = count( $x_values ) - 1;
        
        for ( $i = 1; $i <= $forecast_days; $i++ ) {
            $forecast_x = $last_x + $i;
            $forecast_price = $regression['slope'] * $forecast_x + $regression['intercept'];
            
            $forecast_date = gmdate( 'Y-m-d', strtotime( "+{$i} days" ) );
            
            $forecast[] = array(
                'date' => $forecast_date,
                'price' => max( 0, $forecast_price ), // Ensure non-negative price
                'confidence' => max( 0, 100 - ( $i * 10 ) ) // Decreasing confidence over time
            );
        }

        return array(
            'success' => true,
            'forecast' => $forecast,
            'regression' => $regression,
            'r_squared' => $this->calculate_r_squared( $x_values, $y_values, $regression )
        );
    }

    /**
     * Simple linear regression calculation
     */
    private function linear_regression( $x_values, $y_values ) {
        $n = count( $x_values );
        
        if ( $n < 2 || count( $y_values ) !== $n ) {
            return false;
        }

        $sum_x = array_sum( $x_values );
        $sum_y = array_sum( $y_values );
        $sum_xy = 0;
        $sum_x_squared = 0;
        
        for ( $i = 0; $i < $n; $i++ ) {
            $sum_xy += $x_values[ $i ] * $y_values[ $i ];
            $sum_x_squared += $x_values[ $i ] * $x_values[ $i ];
        }

        $denominator = ( $n * $sum_x_squared ) - ( $sum_x * $sum_x );
        
        if ( 0 === $denominator ) {
            return false;
        }

        $slope = ( ( $n * $sum_xy ) - ( $sum_x * $sum_y ) ) / $denominator;
        $intercept = ( $sum_y - ( $slope * $sum_x ) ) / $n;
        
        return array(
            'slope' => $slope,
            'intercept' => $intercept
        );
    }

    /**
     * Calculate R-squared value
     */
    private function calculate_r_squared( $x_values, $y_values, $regression ) {
        $n = count( $y_values );
        $y_mean = array_sum( $y_values ) / $n;
        
        $ss_tot = 0;
        $ss_res = 0;
        
        for ( $i = 0; $i < $n; $i++ ) {
            $y_pred = $regression['slope'] * $x_values[ $i ] + $regression['intercept'];
            $ss_res += pow( $y_values[ $i ] - $y_pred, 2 );
            $ss_tot += pow( $y_values[ $i ] - $y_mean, 2 );
        }
        
        if ( 0 === $ss_tot ) {
            return 0;
        }
        
        return 1 - ( $ss_res / $ss_tot );
    }

    /**
     * Get chart data for widget display
     */
    public function get_widget_chart_data( $product_id, $widget_type = 'mini' ) {
        $days = 'mini' === $widget_type ? 7 : 30;
        $chart_data = $this->get_chart_data( $product_id, $days );
        
        if ( empty( $chart_data ) ) {
            return array();
        }

        // Simplify data for widget display
        $simplified_data = $chart_data;
        
        if ( 'mini' === $widget_type ) {
            // Remove some visual elements for mini widgets
            $simplified_data['options']['plugins']['legend']['display'] = false;
            $simplified_data['options']['scales']['x']['title']['display'] = false;
            $simplified_data['options']['scales']['y']['title']['display'] = false;
            $simplified_data['options']['scales']['x']['ticks']['display'] = false;
        }

        return $simplified_data;
    }

    /**
     * Export chart data to different formats
     */
    public function export_chart_data( $product_id, $days = 30, $format = 'json' ) {
        $chart_data = $this->get_chart_data( $product_id, $days );
        
        if ( empty( $chart_data ) ) {
            return false;
        }

        switch ( $format ) {
            case 'csv':
                return $this->chart_data_to_csv( $chart_data );
            case 'xml':
                return $this->chart_data_to_xml( $chart_data );
            case 'json':
            default:
                return wp_json_encode( $chart_data );
        }
    }

    /**
     * Convert chart data to CSV format
     */
    private function chart_data_to_csv( $chart_data ) {
        $csv = "Date,Price\n";
        
        $labels = $chart_data['data']['labels'];
        $prices = $chart_data['data']['datasets'][0]['data'];
        
        for ( $i = 0; $i < count( $labels ); $i++ ) {
            $csv .= $labels[ $i ] . ',' . $prices[ $i ] . "\n";
        }
        
        return $csv;
    }

    /**
     * Convert chart data to XML format
     */
    private function chart_data_to_xml( $chart_data ) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<price_history>\n";
        
        $labels = $chart_data['data']['labels'];
        $prices = $chart_data['data']['datasets'][0]['data'];
        
        for ( $i = 0; $i < count( $labels ); $i++ ) {
            $xml .= "  <record>\n";
            $xml .= "    <date>" . esc_xml( $labels[ $i ] ) . "</date>\n";
            $xml .= "    <price>" . esc_xml( $prices[ $i ] ) . "</price>\n";
            $xml .= "  </record>\n";
        }
        
        $xml .= "</price_history>";
        
        return $xml;
    }
}
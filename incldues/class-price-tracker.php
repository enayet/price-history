<?php
/**
 * Price tracking class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Price tracking class
 */
class WC_Price_History_Tracker {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_process_product_meta', array( $this, 'track_price_changes' ), 10, 1 );
        add_action( 'woocommerce_product_set_regular_price', array( $this, 'track_regular_price_change' ), 10, 2 );
        add_action( 'woocommerce_product_set_sale_price', array( $this, 'track_sale_price_change' ), 10, 2 );
        add_action( 'woocommerce_variation_set_regular_price', array( $this, 'track_variation_regular_price_change' ), 10, 2 );
        add_action( 'woocommerce_variation_set_sale_price', array( $this, 'track_variation_sale_price_change' ), 10, 2 );
        
        // Hook for bulk price updates
        add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'track_bulk_variation_changes' ), 10, 1 );
        
        // Hook for import/sync tools
        add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'track_imported_product_price' ), 10, 2 );
    }

    /**
     * Track price changes when product is saved
     */
    public function track_price_changes( $product_id ) {
        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['_regular_price'] ) ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $regular_price = sanitize_text_field( wp_unslash( $_POST['_regular_price'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $sale_price = isset( $_POST['_sale_price'] ) ? sanitize_text_field( wp_unslash( $_POST['_sale_price'] ) ) : '';

        $database = new WC_Price_History_Database();
        
        // Check if price has actually changed
        $current_regular = $product->get_regular_price();
        $current_sale = $product->get_sale_price();
        
        if ( $regular_price !== $current_regular || $sale_price !== $current_sale ) {
            $price_type = ! empty( $sale_price ) ? 'sale' : 'regular';
            $database->insert_price_record( $product_id, $regular_price, $sale_price, $price_type );
            
            // Trigger action for other plugins/themes to hook into
            do_action( 'wc_price_history_price_changed', $product_id, $regular_price, $sale_price, $price_type );
        }
    }

    /**
     * Track regular price changes
     */
    public function track_regular_price_change( $value, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $product_id = $product->get_id();
        $old_price = $product->get_regular_price();
        
        if ( $value !== $old_price ) {
            $database = new WC_Price_History_Database();
            $sale_price = $product->get_sale_price();
            $database->insert_price_record( $product_id, $value, $sale_price, 'regular' );
            
            // Trigger action
            do_action( 'wc_price_history_regular_price_changed', $product_id, $value, $old_price );
        }
    }

    /**
     * Track sale price changes
     */
    public function track_sale_price_change( $value, $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $product_id = $product->get_id();
        $regular_price = $product->get_regular_price();
        
        $database = new WC_Price_History_Database();
        $price_type = ! empty( $value ) ? 'sale' : 'regular';
        $database->insert_price_record( $product_id, $regular_price, $value, $price_type );
        
        // Trigger action
        do_action( 'wc_price_history_sale_price_changed', $product_id, $value );
        
        // Check for compliance if this is a sale
        if ( ! empty( $value ) ) {
            $this->check_sale_compliance( $product_id, $value );
        }
    }

    /**
     * Track variation regular price changes
     */
    public function track_variation_regular_price_change( $value, $variation ) {
        if ( ! is_a( $variation, 'WC_Product_Variation' ) ) {
            return;
        }

        $variation_id = $variation->get_id();
        $old_price = $variation->get_regular_price();
        
        if ( $value !== $old_price ) {
            $database = new WC_Price_History_Database();
            $sale_price = $variation->get_sale_price();
            $database->insert_price_record( $variation_id, $value, $sale_price, 'regular' );
            
            // Trigger action
            do_action( 'wc_price_history_variation_regular_price_changed', $variation_id, $value, $old_price );
        }
    }

    /**
     * Track variation sale price changes
     */
    public function track_variation_sale_price_change( $value, $variation ) {
        if ( ! is_a( $variation, 'WC_Product_Variation' ) ) {
            return;
        }

        $variation_id = $variation->get_id();
        $regular_price = $variation->get_regular_price();
        
        $database = new WC_Price_History_Database();
        $price_type = ! empty( $value ) ? 'sale' : 'regular';
        $database->insert_price_record( $variation_id, $regular_price, $value, $price_type );
        
        // Trigger action
        do_action( 'wc_price_history_variation_sale_price_changed', $variation_id, $value );
        
        // Check for compliance if this is a sale
        if ( ! empty( $value ) ) {
            $this->check_sale_compliance( $variation_id, $value );
        }
    }

    /**
     * Track bulk variation changes
     */
    public function track_bulk_variation_changes( $product_id ) {
        // This handles bulk updates to variations
        $product = wc_get_product( $product_id );
        
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        $variations = $product->get_children();
        
        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                // Track current prices for variations that might have been updated
                $this->track_current_variation_price( $variation );
            }
        }
    }

    /**
     * Track current variation price (helper method)
     */
    private function track_current_variation_price( $variation ) {
        $variation_id = $variation->get_id();
        $regular_price = $variation->get_regular_price();
        $sale_price = $variation->get_sale_price();
        
        $database = new WC_Price_History_Database();
        
        // Only insert if we don't have a recent record (within last hour)
        if ( ! $this->has_recent_price_record( $variation_id, 1 ) ) {
            $price_type = ! empty( $sale_price ) ? 'sale' : 'regular';
            $database->insert_price_record( $variation_id, $regular_price, $sale_price, $price_type );
        }
    }

    /**
     * Track imported product prices
     */
    public function track_imported_product_price( $product, $data ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $product_id = $product->get_id();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        
        if ( ! empty( $regular_price ) ) {
            $database = new WC_Price_History_Database();
            $price_type = ! empty( $sale_price ) ? 'sale' : 'regular';
            $database->insert_price_record( $product_id, $regular_price, $sale_price, $price_type );
            
            // Trigger action
            do_action( 'wc_price_history_product_imported', $product_id, $regular_price, $sale_price );
        }
    }

    /**
     * Check if product has recent price record
     */
    private function has_recent_price_record( $product_id, $hours = 1 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE product_id = %d AND price_date >= %s",
            $product_id,
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var( $sql );
        
        return $count > 0;
    }

    /**
     * Check sale compliance
     */
    private function check_sale_compliance( $product_id, $sale_price ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_alerts' ) ) {
            return;
        }

        $database = new WC_Price_History_Database();
        $lowest_price = $database->get_lowest_price( $product_id, 30 );
        
        if ( $lowest_price && floatval( $sale_price ) > floatval( $lowest_price ) ) {
            // Sale price is higher than lowest price in last 30 days
            $this->trigger_compliance_alert( $product_id, $sale_price, $lowest_price );
        }
    }

    /**
     * Trigger compliance alert
     */
    private function trigger_compliance_alert( $product_id, $sale_price, $lowest_price ) {
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return;
        }

        $alert_data = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'sale_price' => $sale_price,
            'lowest_price' => $lowest_price,
            'difference' => floatval( $sale_price ) - floatval( $lowest_price ),
            'timestamp' => current_time( 'mysql' )
        );
        
        // Store alert for admin notices
        $existing_alerts = get_option( 'wc_price_history_pending_alerts', array() );
        $existing_alerts[] = $alert_data;
        update_option( 'wc_price_history_pending_alerts', $existing_alerts );
        
        // Trigger action for other systems (email notifications, etc.)
        do_action( 'wc_price_history_compliance_alert', $alert_data );
    }

    /**
     * Initialize price tracking for existing products
     */
    public function initialize_existing_products() {
        $products = wc_get_products( array(
            'limit' => -1,
            'status' => 'publish',
            'type' => array( 'simple', 'variable' )
        ) );

        $database = new WC_Price_History_Database();
        
        foreach ( $products as $product ) {
            $product_id = $product->get_id();
            
            // Check if we already have price history
            if ( ! $database->has_price_history( $product_id, 365 ) ) {
                $regular_price = $product->get_regular_price();
                $sale_price = $product->get_sale_price();
                
                if ( ! empty( $regular_price ) ) {
                    $price_type = ! empty( $sale_price ) ? 'sale' : 'regular';
                    $database->insert_price_record( $product_id, $regular_price, $sale_price, $price_type );
                }
            }
            
            // Handle variations
            if ( $product->is_type( 'variable' ) ) {
                $variations = $product->get_children();
                
                foreach ( $variations as $variation_id ) {
                    if ( ! $database->has_price_history( $variation_id, 365 ) ) {
                        $variation = wc_get_product( $variation_id );
                        if ( $variation ) {
                            $regular_price = $variation->get_regular_price();
                            $sale_price = $variation->get_sale_price();
                            
                            if ( ! empty( $regular_price ) ) {
                                $price_type = ! empty( $sale_price ) ? 'sale' : 'regular';
                                $database->insert_price_record( $variation_id, $regular_price, $sale_price, $price_type );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Clean up old price records
     */
    public function cleanup_old_records() {
        $database = new WC_Price_History_Database();
        $days_to_keep = apply_filters( 'wc_price_history_days_to_keep', 365 );
        
        $deleted_count = $database->cleanup_old_records( $days_to_keep );
        
        if ( $deleted_count > 0 ) {
            do_action( 'wc_price_history_records_cleaned', $deleted_count );
        }
        
        return $deleted_count;
    }
}
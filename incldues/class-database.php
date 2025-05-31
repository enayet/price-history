<?php
/**
 * Database management class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database management class
 */
class WC_Price_History_Database {

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be empty as we'll call methods directly
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            regular_price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            price_date datetime DEFAULT CURRENT_TIMESTAMP,
            price_type varchar(20) NOT NULL DEFAULT 'regular',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY price_date (price_date),
            KEY price_type (price_type)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get price history for a product
     */
    public function get_price_history( $product_id, $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE product_id = %d AND price_date >= %s ORDER BY price_date DESC",
            $product_id,
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    /**
     * Get lowest price in specified days
     */
    public function get_lowest_price( $product_id, $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as lowest_price 
             FROM {$table_name} 
             WHERE product_id = %d AND price_date >= %s",
            $product_id,
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var( $sql );
    }

    /**
     * Insert price record
     */
    public function insert_price_record( $product_id, $regular_price, $sale_price = null, $price_type = 'regular' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        $data = array(
            'product_id' => $product_id,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'price_type' => $price_type,
            'price_date' => current_time( 'mysql' ),
            'created_at' => current_time( 'mysql' )
        );
        
        $formats = array( '%d', '%f', '%f', '%s', '%s', '%s' );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert( $table_name, $data, $formats );
    }

    /**
     * Get recent price changes
     */
    public function get_recent_price_changes( $days = 30, $limit = 100 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE price_date >= %s ORDER BY price_date DESC LIMIT %d",
            $date_from,
            $limit
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    /**
     * Delete old price records
     */
    public function cleanup_old_records( $days = 365 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE price_date < %s",
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query( $sql );
    }

    /**
     * Get price statistics for a product
     */
    public function get_price_statistics( $product_id, $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT 
                MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as min_price,
                MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as max_price,
                AVG(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as avg_price,
                COUNT(*) as total_changes
             FROM {$table_name} 
             WHERE product_id = %d AND price_date >= %s",
            $product_id,
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row( $sql );
    }

    /**
     * Check if product has price history
     */
    public function has_price_history( $product_id, $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
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
     * Get products with price changes
     */
    public function get_products_with_price_changes( $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$table_name} WHERE price_date >= %s",
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col( $sql );
    }
}
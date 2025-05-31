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
     * Table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_price_history';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            regular_price decimal(10,4) DEFAULT NULL,
            sale_price decimal(10,4) DEFAULT NULL,
            price_date datetime DEFAULT CURRENT_TIMESTAMP,
            price_type varchar(20) NOT NULL DEFAULT 'regular',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_date (product_id, price_date),
            KEY idx_price_type_date (price_type, price_date),
            KEY idx_product_id (product_id),
            KEY idx_price_date (price_date)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // Add version option to track database schema
        update_option( 'wc_price_history_db_version', '1.0' );
    }

    /**
     * Update database tables if needed
     *
     * @param string $from_version The version we're updating from.
     */
    public function maybe_update_tables( $from_version ) {
        $db_version = get_option( 'wc_price_history_db_version', '0' );
        
        if ( version_compare( $db_version, '1.0', '<' ) ) {
            $this->update_to_v1();
        }
        
        // Future updates can be added here
        // if ( version_compare( $db_version, '1.1', '<' ) ) {
        //     $this->update_to_v1_1();
        // }
    }

    /**
     * Update database to version 1.0
     */
    private function update_to_v1() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $this->table_name 
            ) 
        );
        
        if ( ! $table_exists ) {
            $this->create_tables();
            return;
        }
        
        // Add missing indexes if they don't exist
        $indexes = array(
            'idx_product_date' => 'ADD INDEX idx_product_date (product_id, price_date)',
            'idx_price_type_date' => 'ADD INDEX idx_price_type_date (price_type, price_date)'
        );
        
        foreach ( $indexes as $index_name => $sql ) {
            $index_exists = $wpdb->get_var( 
                $wpdb->prepare(
                    "SHOW INDEX FROM {$this->table_name} WHERE Key_name = %s",
                    $index_name
                )
            );
            
            if ( ! $index_exists ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query( "ALTER TABLE {$this->table_name} {$sql}" );
            }
        }
        
        update_option( 'wc_price_history_db_version', '1.0' );
    }

    /**
     * Get price history for a product with caching
     *
     * @param int $product_id Product ID.
     * @param int $days Number of days to look back.
     * @return array|false Array of price records or false on error.
     */
    public function get_price_history( $product_id, $days = 30 ) {
        global $wpdb;
        
        // Input validation
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return false;
        }
        
        $days = max( 1, intval( $days ) );
        
        // Check cache first
        $cache_key = "wc_price_history_{$product_id}_{$days}";
        $cached_result = wp_cache_get( $cache_key, 'wc_price_history' );
        
        if ( false !== $cached_result ) {
            return $cached_result;
        }
        
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE product_id = %d AND price_date >= %s 
             ORDER BY price_date DESC",
            $product_id,
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results( $sql );
            
            if ( false === $results ) {
                wc_price_history_log( 'Failed to retrieve price history for product ' . $product_id, 'error' );
                return false;
            }
            
            // Cache for 5 minutes
            wp_cache_set( $cache_key, $results, 'wc_price_history', 300 );
            
            return $results;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_price_history: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Get lowest price in specified days with caching
     *
     * @param int $product_id Product ID.
     * @param int $days Number of days to look back.
     * @return float|false Lowest price or false on error.
     */
    public function get_lowest_price( $product_id, $days = 30 ) {
        global $wpdb;
        
        // Input validation
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return false;
        }
        
        $days = max( 1, intval( $days ) );
        
        // Check cache first
        $cache_key = "wc_price_history_lowest_{$product_id}_{$days}";
        $cached_result = wp_cache_get( $cache_key, 'wc_price_history' );
        
        if ( false !== $cached_result ) {
            return $cached_result;
        }
        
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT MIN(CASE 
                WHEN sale_price IS NOT NULL AND sale_price > 0 
                THEN sale_price 
                ELSE regular_price 
             END) as lowest_price 
             FROM {$this->table_name} 
             WHERE product_id = %d AND price_date >= %s",
            $product_id,
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var( $sql );
            
            if ( null === $result ) {
                return false;
            }
            
            $lowest_price = floatval( $result );
            
            // Cache for 5 minutes
            wp_cache_set( $cache_key, $lowest_price, 'wc_price_history', 300 );
            
            return $lowest_price;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_lowest_price: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Insert price record with validation
     *
     * @param int    $product_id Product ID.
     * @param float  $regular_price Regular price.
     * @param float  $sale_price Sale price.
     * @param string $price_type Price type (regular/sale).
     * @return int|false Insert ID or false on error.
     */
    public function insert_price_record( $product_id, $regular_price, $sale_price = null, $price_type = 'regular' ) {
        global $wpdb;
        
        // Input validation
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            wc_price_history_log( 'Invalid product ID provided to insert_price_record: ' . $product_id, 'error' );
            return false;
        }
        
        // Validate price type
        $allowed_types = array( 'regular', 'sale' );
        if ( ! in_array( $price_type, $allowed_types, true ) ) {
            $price_type = 'regular';
        }
        
        // Convert prices to float
        $regular_price = ! empty( $regular_price ) ? floatval( $regular_price ) : null;
        $sale_price = ! empty( $sale_price ) ? floatval( $sale_price ) : null;
        
        // Don't insert if regular price is invalid
        if ( null === $regular_price || $regular_price < 0 ) {
            return false;
        }
        
        // Validate sale price
        if ( null !== $sale_price && $sale_price < 0 ) {
            $sale_price = null;
        }
        
        // Check for duplicate recent entries (within last hour)
        if ( $this->has_recent_price_record( $product_id, $regular_price, $sale_price, 1 ) ) {
            return false; // Avoid duplicate entries
        }
        
        $data = array(
            'product_id' => $product_id,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'price_type' => $price_type,
            'price_date' => current_time( 'mysql' ),
            'created_at' => current_time( 'mysql' )
        );
        
        $formats = array( '%d', '%f', '%f', '%s', '%s', '%s' );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert( $this->table_name, $data, $formats );
            
            if ( false === $result ) {
                wc_price_history_log( 'Failed to insert price record for product ' . $product_id, 'error' );
                return false;
            }
            
            // Clear related caches
            $this->clear_product_cache( $product_id );
            
            return $wpdb->insert_id;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in insert_price_record: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Check if product has recent identical price record
     *
     * @param int   $product_id Product ID.
     * @param float $regular_price Regular price.
     * @param float $sale_price Sale price.
     * @param int   $hours Hours to look back.
     * @return bool
     */
    private function has_recent_price_record( $product_id, $regular_price, $sale_price, $hours = 1 ) {
        global $wpdb;
        
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE product_id = %d 
             AND regular_price = %f 
             AND (sale_price = %f OR (sale_price IS NULL AND %f IS NULL))
             AND price_date >= %s",
            $product_id,
            $regular_price,
            $sale_price,
            $sale_price,
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var( $sql );
        
        return $count > 0;
    }

    /**
     * Get recent price changes with pagination
     *
     * @param int $days Number of days to look back.
     * @param int $limit Maximum number of results.
     * @param int $offset Offset for pagination.
     * @return array Array of price records.
     */
    public function get_recent_price_changes( $days = 30, $limit = 100, $offset = 0 ) {
        global $wpdb;
        
        $days = max( 1, intval( $days ) );
        $limit = max( 1, min( 1000, intval( $limit ) ) ); // Cap at 1000
        $offset = max( 0, intval( $offset ) );
        
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE price_date >= %s 
             ORDER BY price_date DESC 
             LIMIT %d OFFSET %d",
            $date_from,
            $limit,
            $offset
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_recent_price_changes: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Delete old price records
     *
     * @param int $days Number of days to keep.
     * @return int|false Number of deleted records or false on error.
     */
    public function cleanup_old_records( $days = 365 ) {
        global $wpdb;
        
        if ( $days <= 0 ) {
            return 0; // Don't delete anything if retention is unlimited
        }
        
        $date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE price_date < %s",
            $date_cutoff
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted_count = $wpdb->query( $sql );
            
            if ( false === $deleted_count ) {
                wc_price_history_log( 'Failed to cleanup old records', 'error' );
                return false;
            }
            
            // Clear all caches after cleanup
            wp_cache_flush_group( 'wc_price_history' );
            
            wc_price_history_log( "Cleaned up {$deleted_count} old price records", 'info' );
            
            return $deleted_count;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in cleanup_old_records: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Get price statistics for a product
     *
     * @param int $product_id Product ID.
     * @param int $days Number of days to analyze.
     * @return object|false Statistics object or false on error.
     */
    public function get_price_statistics( $product_id, $days = 30 ) {
        global $wpdb;
        
        // Input validation
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return false;
        }
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT 
                MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as min_price,
                MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as max_price,
                AVG(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as avg_price,
                COUNT(*) as total_changes,
                COUNT(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN 1 END) as sale_periods,
                STDDEV(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as price_volatility
             FROM {$this->table_name} 
             WHERE product_id = %d AND price_date >= %s",
            $product_id,
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_row( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_price_statistics: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Check if product has price history
     *
     * @param int $product_id Product ID.
     * @param int $days Number of days to check.
     * @return bool
     */
    public function has_price_history( $product_id, $days = 30 ) {
        global $wpdb;
        
        // Input validation
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return false;
        }
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE product_id = %d AND price_date >= %s 
             LIMIT 1",
            $product_id,
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var( $sql );
            
            return $count > 0;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in has_price_history: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Get products with price changes
     *
     * @param int $days Number of days to look back.
     * @return array Array of product IDs.
     */
    public function get_products_with_price_changes( $days = 30 ) {
        global $wpdb;
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$this->table_name} 
             WHERE price_date >= %s 
             ORDER BY product_id",
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_col( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_products_with_price_changes: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Get bulk price history for multiple products
     *
     * @param array $product_ids Array of product IDs.
     * @param int   $days Number of days to look back.
     * @return array Associative array keyed by product ID.
     */
    public function get_bulk_price_history( $product_ids, $days = 30 ) {
        global $wpdb;
        
        if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
            return array();
        }
        
        // Sanitize product IDs
        $product_ids = array_map( 'intval', $product_ids );
        $product_ids = array_filter( $product_ids, function( $id ) {
            return $id > 0;
        });
        
        if ( empty( $product_ids ) ) {
            return array();
        }
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
        $sql_args = array_merge( $product_ids, array( $date_from ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE product_id IN ({$placeholders}) 
             AND price_date >= %s 
             ORDER BY product_id, price_date DESC",
            $sql_args
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results( $sql );
            
            // Group results by product ID
            $grouped_results = array();
            foreach ( $results as $record ) {
                $grouped_results[ $record->product_id ][] = $record;
            }
            
            return $grouped_results;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_bulk_price_history: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Get compliance alerts for products on sale
     *
     * @return array Array of non-compliant products.
     */
    public function get_compliance_alerts() {
        global $wpdb;
        
        // Get products currently on sale
        $sale_products = wc_get_product_ids_on_sale();
        
        if ( empty( $sale_products ) ) {
            return array();
        }
        
        $alerts = array();
        
        foreach ( $sale_products as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }
            
            $current_sale_price = floatval( $product->get_sale_price() );
            $lowest_price = $this->get_lowest_price( $product_id, 30 );
            
            if ( $lowest_price && $current_sale_price > $lowest_price ) {
                $alerts[] = (object) array(
                    'product_id' => $product_id,
                    'current_sale_price' => $current_sale_price,
                    'lowest_price' => $lowest_price,
                    'difference' => $current_sale_price - $lowest_price,
                    'message' => sprintf(
                        /* translators: 1: Current sale price, 2: Lowest price */
                        esc_html__( 'Current sale price (%1$s) is higher than the lowest price in the last 30 days (%2$s).', 'wc-price-history-compliance' ),
                        wc_price( $current_sale_price ),
                        wc_price( $lowest_price )
                    )
                );
            }
        }
        
        return $alerts;
    }

    /**
     * Clear product-specific caches
     *
     * @param int $product_id Product ID.
     */
    private function clear_product_cache( $product_id ) {
        $cache_patterns = array(
            "wc_price_history_{$product_id}_*",
            "wc_price_history_lowest_{$product_id}_*",
            "wc_price_history_stats_{$product_id}_*"
        );
        
        foreach ( $cache_patterns as $pattern ) {
            wp_cache_delete( $pattern, 'wc_price_history' );
        }
    }

    /**
     * Get database table size and statistics
     *
     * @return array Database statistics.
     */
    public function get_database_stats() {
        global $wpdb;
        
        try {
            // Get table size
            $table_stats = $wpdb->get_row( $wpdb->prepare(
                "SELECT 
                    table_rows as total_records,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $this->table_name
            ) );
            
            // Get record counts by time periods
            $counts = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN price_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days,
                    SUM(CASE WHEN price_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_7_days,
                    COUNT(DISTINCT product_id) as unique_products
                 FROM {$this->table_name}"
            );
            
            return array(
                'total_records' => $table_stats ? intval( $table_stats->total_records ) : 0,
                'size_mb' => $table_stats ? floatval( $table_stats->size_mb ) : 0,
                'records_last_30_days' => $counts ? intval( $counts->last_30_days ) : 0,
                'records_last_7_days' => $counts ? intval( $counts->last_7_days ) : 0,
                'unique_products' => $counts ? intval( $counts->unique_products ) : 0
            );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_database_stats: ' . $e->getMessage(), 'error' );
            return array(
                'total_records' => 0,
                'size_mb' => 0,
                'records_last_30_days' => 0,
                'records_last_7_days' => 0,
                'unique_products' => 0
            );
        }
    }

    /**
     * Optimize database table
     *
     * @return bool Success status.
     */
    public function optimize_table() {
        global $wpdb;
        
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $result = $wpdb->query( "OPTIMIZE TABLE {$this->table_name}" );
            
            if ( false !== $result ) {
                wc_price_history_log( 'Database table optimized successfully', 'info' );
                return true;
            }
            
            return false;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in optimize_table: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Export all data to array
     *
     * @return array All price history data.
     */
    public function export_all_data() {
        global $wpdb;
        
        try {
            $sql = "SELECT 
                        ph.*,
                        p.post_title as product_name,
                        p.post_status as product_status
                    FROM {$this->table_name} ph
                    LEFT JOIN {$wpdb->posts} p ON ph.product_id = p.ID
                    ORDER BY ph.price_date DESC";
            
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in export_all_data: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Truncate table (remove all data)
     *
     * @return bool Success status.
     */
    public function truncate_table() {
        global $wpdb;
        
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $result = $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
            
            if ( false !== $result ) {
                // Clear all caches
                wp_cache_flush_group( 'wc_price_history' );
                wc_price_history_log( 'Price history table truncated', 'info' );
                return true;
            }
            
            return false;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in truncate_table: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Drop table (used during uninstall)
     *
     * @return bool Success status.
     */
    public function drop_table() {
        global $wpdb;
        
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $result = $wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
            
            if ( false !== $result ) {
                // Clear all caches
                wp_cache_flush_group( 'wc_price_history' );
                
                // Remove database version option
                delete_option( 'wc_price_history_db_version' );
                
                wc_price_history_log( 'Price history table dropped', 'info' );
                return true;
            }
            
            return false;
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in drop_table: ' . $e->getMessage(), 'error' );
            return false;
        }
    }

    /**
     * Get price changes for a specific date range
     *
     * @param string $date_from Start date (Y-m-d format).
     * @param string $date_to End date (Y-m-d format).
     * @param array  $filters Additional filters.
     * @return array Array of price records.
     */
    public function get_price_changes_by_date_range( $date_from, $date_to, $filters = array() ) {
        global $wpdb;
        
        // Validate dates
        if ( ! $this->validate_date( $date_from ) || ! $this->validate_date( $date_to ) ) {
            return array();
        }
        
        $where_clauses = array();
        $params = array();
        
        // Date range
        $where_clauses[] = 'price_date >= %s';
        $params[] = $date_from . ' 00:00:00';
        
        $where_clauses[] = 'price_date <= %s';
        $params[] = $date_to . ' 23:59:59';
        
        // Product ID filter
        if ( ! empty( $filters['product_id'] ) && is_numeric( $filters['product_id'] ) ) {
            $where_clauses[] = 'product_id = %d';
            $params[] = intval( $filters['product_id'] );
        }
        
        // Price type filter
        if ( ! empty( $filters['price_type'] ) && in_array( $filters['price_type'], array( 'regular', 'sale' ), true ) ) {
            $where_clauses[] = 'price_type = %s';
            $params[] = $filters['price_type'];
        }
        
        // Build query
        $sql = "SELECT * FROM {$this->table_name}";
        
        if ( ! empty( $where_clauses ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $sql .= ' ORDER BY price_date DESC';
        
        // Add limit if specified
        if ( ! empty( $filters['limit'] ) && is_numeric( $filters['limit'] ) ) {
            $sql .= ' LIMIT ' . intval( $filters['limit'] );
        }
        
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_price_changes_by_date_range: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Validate date format
     *
     * @param string $date Date to validate.
     * @param string $format Expected format.
     * @return bool
     */
    private function validate_date( $date, $format = 'Y-m-d' ) {
        $d = DateTime::createFromFormat( $format, $date );
        return $d && $d->format( $format ) === $date;
    }

    /**
     * Get price trend for a product
     *
     * @param int $product_id Product ID.
     * @param int $days Number of days to analyze.
     * @return array Trend data.
     */
    public function get_price_trend( $product_id, $days = 30 ) {
        global $wpdb;
        
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return array();
        }
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(price_date) as date,
                AVG(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as avg_price,
                MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as min_price,
                MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as max_price,
                COUNT(*) as price_changes
             FROM {$this->table_name} 
             WHERE product_id = %d AND price_date >= %s
             GROUP BY DATE(price_date)
             ORDER BY date ASC",
            $product_id,
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_price_trend: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Get products with highest price volatility
     *
     * @param int $days Number of days to analyze.
     * @param int $limit Number of products to return.
     * @return array Array of products with volatility data.
     */
    public function get_most_volatile_products( $days = 30, $limit = 10 ) {
        global $wpdb;
        
        $days = max( 1, intval( $days ) );
        $limit = max( 1, min( 100, intval( $limit ) ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT 
                product_id,
                COUNT(*) as price_changes,
                MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as min_price,
                MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as max_price,
                AVG(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as avg_price,
                STDDEV(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) as price_stddev,
                (MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END) - 
                 MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END)) as price_range
             FROM {$this->table_name} 
             WHERE price_date >= %s
             GROUP BY product_id
             HAVING price_changes > 1
             ORDER BY price_stddev DESC, price_range DESC
             LIMIT %d",
            $date_from,
            $limit
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_most_volatile_products: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Get daily price change summary
     *
     * @param int $days Number of days to analyze.
     * @return array Daily summary data.
     */
    public function get_daily_price_summary( $days = 30 ) {
        global $wpdb;
        
        $days = max( 1, intval( $days ) );
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(price_date) as date,
                COUNT(*) as total_changes,
                COUNT(DISTINCT product_id) as products_changed,
                SUM(CASE WHEN price_type = 'sale' THEN 1 ELSE 0 END) as sale_changes,
                SUM(CASE WHEN price_type = 'regular' THEN 1 ELSE 0 END) as regular_changes
             FROM {$this->table_name} 
             WHERE price_date >= %s
             GROUP BY DATE(price_date)
             ORDER BY date DESC",
            $date_from
        );
        
        try {
            // Necessary for custom table operations - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results( $sql );
            
        } catch ( Exception $e ) {
            wc_price_history_log( 'Database error in get_daily_price_summary: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Check database table health
     *
     * @return array Health check results.
     */
    public function check_table_health() {
        global $wpdb;
        
        $health_status = array(
            'table_exists' => false,
            'indexes_exist' => array(),
            'missing_indexes' => array(),
            'table_size' => 0,
            'record_count' => 0,
            'issues' => array()
        );
        
        try {
            // Check if table exists
            $table_exists = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW TABLES LIKE %s", 
                    $this->table_name 
                ) 
            );
            
            $health_status['table_exists'] = ! empty( $table_exists );
            
            if ( ! $health_status['table_exists'] ) {
                $health_status['issues'][] = 'Database table does not exist';
                return $health_status;
            }
            
            // Check indexes
            $indexes = $wpdb->get_results( 
                "SHOW INDEX FROM {$this->table_name}"
            );
            
            $existing_indexes = array();
            foreach ( $indexes as $index ) {
                $existing_indexes[] = $index->Key_name;
            }
            
            $required_indexes = array( 'PRIMARY', 'idx_product_date', 'idx_price_type_date' );
            $health_status['indexes_exist'] = array_intersect( $required_indexes, $existing_indexes );
            $health_status['missing_indexes'] = array_diff( $required_indexes, $existing_indexes );
            
            // Get table stats
            $stats = $this->get_database_stats();
            $health_status['table_size'] = $stats['size_mb'];
            $health_status['record_count'] = $stats['total_records'];
            
            // Check for potential issues
            if ( ! empty( $health_status['missing_indexes'] ) ) {
                $health_status['issues'][] = 'Missing database indexes: ' . implode( ', ', $health_status['missing_indexes'] );
            }
            
            if ( $health_status['table_size'] > 100 ) { // > 100MB
                $health_status['issues'][] = 'Large table size may impact performance';
            }
            
        } catch ( Exception $e ) {
            $health_status['issues'][] = 'Database health check failed: ' . $e->getMessage();
            wc_price_history_log( 'Database health check error: ' . $e->getMessage(), 'error' );
        }
        
        return $health_status;
    }

    /**
     * Repair missing indexes
     *
     * @return bool Success status.
     */
    public function repair_indexes() {
        global $wpdb;
        
        $health = $this->check_table_health();
        
        if ( empty( $health['missing_indexes'] ) ) {
            return true; // Nothing to repair
        }
        
        $index_sql = array(
            'idx_product_date' => 'ADD INDEX idx_product_date (product_id, price_date)',
            'idx_price_type_date' => 'ADD INDEX idx_price_type_date (price_type, price_date)'
        );
        
        $repaired = 0;
        
        foreach ( $health['missing_indexes'] as $missing_index ) {
            if ( isset( $index_sql[ $missing_index ] ) ) {
                try {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $result = $wpdb->query( "ALTER TABLE {$this->table_name} {$index_sql[ $missing_index ]}" );
                    
                    if ( false !== $result ) {
                        $repaired++;
                        wc_price_history_log( "Repaired index: {$missing_index}", 'info' );
                    }
                    
                } catch ( Exception $e ) {
                    wc_price_history_log( "Failed to repair index {$missing_index}: " . $e->getMessage(), 'error' );
                }
            }
        }
        
        return $repaired > 0;
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
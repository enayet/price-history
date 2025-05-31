<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 * @author     Your Name <email@example.com>
 */
class WPHC_Activator {

    /**
     * Plugin activation handler.
     *
     * Performs all necessary setup tasks when the plugin is activated:
     * - Creates custom database table for price history
     * - Sets up default plugin options
     * - Schedules cron jobs for price tracking
     * - Checks system requirements
     * - Sets up initial data if needed
     *
     * @since 1.0.0
     */
    public static function activate() {
        // Check system requirements first
        self::check_requirements();
        
        // Create custom database table
        self::create_database_table();
        
        // Set up default plugin options
        self::setup_default_options();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Set activation flag
        update_option('wphc_activation_time', current_time('timestamp'));
        update_option('wphc_plugin_version', WPHC_VERSION);
        
        // Log activation
        wphc_log('Plugin activated successfully', 'info');
        
        // Clear any existing caches
        self::clear_caches();
    }

    /**
     * Check system requirements before activation.
     *
     * @since 1.0.0
     * @throws Exception If requirements are not met.
     */
    private static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(
                esc_html__('WooCommerce Price History & Sale Compliance requires PHP version 7.4 or higher.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            wp_die(
                esc_html__('WooCommerce Price History & Sale Compliance requires WordPress version 5.0 or higher.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_die(
                esc_html__('WooCommerce Price History & Sale Compliance requires WooCommerce to be installed and active.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '<')) {
            wp_die(
                esc_html__('WooCommerce Price History & Sale Compliance requires WooCommerce version 5.0 or higher.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Check database write permissions
        global $wpdb;
        $test_table = $wpdb->prefix . 'wphc_test_' . wp_rand(1000, 9999);
        
        // Necessary for activation table creation test - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                "CREATE TABLE IF NOT EXISTS %i (id int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (id))",
                $test_table
            )
        );

        if (false === $result) {
            wp_die(
                esc_html__('Unable to create database tables. Please check your database permissions.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Clean up test table
        // Necessary for activation table cleanup test - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare("DROP TABLE IF EXISTS %i", $test_table)
        );
    }

    /**
     * Create custom database table for price history storage.
     *
     * @since 1.0.0
     */
    private static function create_database_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . WPHC_TABLE_NAME;
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            regular_price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            effective_price decimal(10,2) NOT NULL,
            price_type varchar(20) NOT NULL DEFAULT 'regular',
            date_recorded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) unsigned DEFAULT NULL,
            notes text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY date_recorded (date_recorded),
            KEY effective_price (effective_price),
            KEY price_type (price_type),
            KEY product_date (product_id, date_recorded)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $result = dbDelta($sql);
        
        // Store table version for future upgrades
        update_option('wphc_db_version', '1.0.0');
        
        // Log table creation result
        if (empty($result)) {
            wphc_log('Price history table created/updated successfully', 'info');
        } else {
            wphc_log('Price history table creation result: ' . print_r($result, true), 'info'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }

        // Verify table was created
        // Necessary for table verification after creation - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );

        if ($table_exists !== $table_name) {
            wphc_log('Failed to create price history table', 'error');
            wp_die(
                esc_html__('Failed to create required database table. Please check your database permissions and try again.', 'woocommerce-price-history-compliance'),
                esc_html__('Plugin Activation Error', 'woocommerce-price-history-compliance'),
                array('back_link' => true)
            );
        }

        // Create indexes for better performance
        self::create_database_indexes($table_name);
    }

    /**
     * Create additional database indexes for performance optimization.
     *
     * @since 1.0.0
     * @param string $table_name The table name.
     */
    private static function create_database_indexes($table_name) {
        global $wpdb;

        $indexes = array(
            "CREATE INDEX idx_wphc_product_price ON $table_name (product_id, effective_price)",
            "CREATE INDEX idx_wphc_date_price ON $table_name (date_recorded, effective_price)",
            "CREATE INDEX idx_wphc_recent_prices ON $table_name (product_id, date_recorded DESC, effective_price)",
        );

        foreach ($indexes as $index_sql) {
            // Necessary for index creation - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($index_sql);
        }

        wphc_log('Database indexes created for performance optimization', 'info');
    }

    /**
     * Set up default plugin options.
     *
     * @since 1.0.0
     */
    private static function setup_default_options() {
        $default_options = array(
            // General Settings
            'enable_plugin' => true,
            'enable_price_tracking' => true,
            'enable_compliance_display' => true,
            'enable_price_history_chart' => false,
            'price_history_days' => 30,
            'chart_display_days' => 30,
            
            // Compliance Settings
            'compliance_message' => esc_html__('Lowest price in the last 30 days: {price}', 'woocommerce-price-history-compliance'),
            'compliance_message_sale_only' => true,
            'compliance_position' => 'after_price',
            'compliance_css_class' => 'wphc-compliance-message',
            
            // Chart Settings
            'chart_type' => 'line',
            'chart_height' => 300,
            'chart_width' => '100%',
            'chart_colors' => array(
                'line' => '#0073aa',
                'background' => '#f9f9f9',
                'grid' => '#e1e1e1'
            ),
            'chart_show_points' => true,
            'chart_show_grid' => true,
            
            // Alert Settings
            'enable_admin_alerts' => true,
            'alert_email' => get_option('admin_email'),
            'alert_on_price_increase' => true,
            'alert_on_price_decrease' => true,
            'alert_on_sale_start' => false,
            'alert_on_sale_end' => false,
            'alert_threshold_percentage' => 10,
            
            // Performance Settings
            'cleanup_old_records' => true,
            'cleanup_days' => 365,
            'batch_size' => 100,
            'enable_caching' => true,
            'cache_duration' => 3600,
            
            // Advanced Settings
            'track_variation_prices' => true,
            'exclude_product_types' => array(),
            'exclude_product_categories' => array(),
            'minimum_price_change' => 0.01,
            'enable_debug_logging' => false,
            
            // Display Settings
            'show_on_shop_page' => false,
            'show_on_category_page' => false,
            'show_on_single_product' => true,
            'mobile_responsive' => true,
            
            // Legal Settings
            'gdpr_compliance' => true,
            'data_retention_days' => 1095, // 3 years
            'anonymize_old_data' => true,
        );

        foreach ($default_options as $option_name => $default_value) {
            if (false === get_option('wphc_' . $option_name, false)) {
                update_option('wphc_' . $option_name, $default_value);
            }
        }

        // Set initial plugin state
        update_option('wphc_first_activation', true);
        update_option('wphc_setup_completed', false);
        
        wphc_log('Default plugin options set up successfully', 'info');
    }

    /**
     * Schedule cron jobs for automated tasks.
     *
     * @since 1.0.0
     */
    private static function schedule_cron_jobs() {
        // Schedule price tracking cleanup job (daily)
        if (!wp_next_scheduled('wphc_daily_cleanup')) {
            wp_schedule_event(time(), 'wphc_daily', 'wphc_daily_cleanup');
        }

        // Schedule price tracking validation job (hourly)
        if (!wp_next_scheduled('wphc_hourly_validation')) {
            wp_schedule_event(time(), 'wphc_hourly', 'wphc_hourly_validation');
        }

        // Schedule database maintenance job (weekly)
        if (!wp_next_scheduled('wphc_weekly_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'wphc_weekly_maintenance');
        }

        wphc_log('Cron jobs scheduled successfully', 'info');
    }

    /**
     * Clear any existing caches.
     *
     * @since 1.0.0
     */
    private static function clear_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear WooCommerce caches if available
        if (function_exists('wc_delete_product_transients')) {
            // This will be handled in the price tracker class
            wphc_log('WooCommerce cache clearing will be handled by price tracker', 'info');
        }
        
        // Clear plugin-specific transients
        global $wpdb;
        
        // Necessary for transient cleanup - no WP API available for bulk transient deletion
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wphc_%' OR option_name LIKE '_transient_timeout_wphc_%'"
        );
        
        wphc_log('Plugin caches cleared', 'info');
    }

    /**
     * Create sample data for testing (only in development mode).
     *
     * @since 1.0.0
     */
    private static function create_sample_data() {
        // Only create sample data in development mode
        if (!wphc_is_development_mode()) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . WPHC_TABLE_NAME;

        // Get first WooCommerce product for sample data
        $sample_product_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 1"
        );

        if (!$sample_product_id) {
            return;
        }

        // Create sample price history entries
        $sample_data = array();
        $base_price = 100.00;
        $current_time = current_time('timestamp');

        for ($i = 30; $i >= 0; $i--) {
            $date = date('Y-m-d H:i:s', $current_time - ($i * DAY_IN_SECONDS));
            $price_variation = ($i % 7 === 0) ? wp_rand(-20, 20) : wp_rand(-5, 5);
            $price = max(50, $base_price + $price_variation);
            
            $sample_data[] = array(
                'product_id' => $sample_product_id,
                'regular_price' => $base_price,
                'sale_price' => ($i % 10 === 0) ? $price * 0.8 : null,
                'effective_price' => ($i % 10 === 0) ? $price * 0.8 : $price,
                'price_type' => ($i % 10 === 0) ? 'sale' : 'regular',
                'date_recorded' => $date,
                'notes' => 'Sample data created during activation',
            );
        }

        foreach ($sample_data as $data) {
            // Necessary for sample data insertion - no WP API available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($table_name, $data);
        }

        wphc_log('Sample data created for development', 'info');
    }

    /**
     * Handle plugin upgrade from previous versions.
     *
     * @since 1.0.0
     */
    private static function handle_upgrade() {
        $current_version = get_option('wphc_plugin_version', '0.0.0');
        
        if (version_compare($current_version, WPHC_VERSION, '<')) {
            // Perform upgrade tasks
            self::upgrade_database_schema();
            self::migrate_old_options();
            
            // Update version
            update_option('wphc_plugin_version', WPHC_VERSION);
            
            wphc_log("Plugin upgraded from version {$current_version} to " . WPHC_VERSION, 'info');
        }
    }

    /**
     * Upgrade database schema if needed.
     *
     * @since 1.0.0
     */
    private static function upgrade_database_schema() {
        $current_db_version = get_option('wphc_db_version', '0.0.0');
        
        if (version_compare($current_db_version, '1.0.0', '<')) {
            // Re-create table with current schema
            self::create_database_table();
        }
    }

    /**
     * Migrate options from older plugin versions.
     *
     * @since 1.0.0
     */
    private static function migrate_old_options() {
        // This method will be used in future versions to migrate settings
        // from older versions of the plugin
        wphc_log('Option migration checked (no migration needed for fresh install)', 'info');
    }

    /**
     * Verify activation was successful.
     *
     * @since 1.0.0
     * @return bool True if activation was successful.
     */
    public static function verify_activation() {
        global $wpdb;
        $table_name = $wpdb->prefix . WPHC_TABLE_NAME;
        
        // Check if table exists
        // Necessary for activation verification - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        
        // Check if options are set
        $options_set = (false !== get_option('wphc_enable_plugin', false));
        
        // Check if cron jobs are scheduled
        $cron_scheduled = wp_next_scheduled('wphc_daily_cleanup');
        
        $activation_successful = ($table_exists === $table_name) && $options_set && $cron_scheduled;
        
        if ($activation_successful) {
            wphc_log('Plugin activation verification successful', 'info');
        } else {
            wphc_log('Plugin activation verification failed', 'error');
        }
        
        return $activation_successful;
    }
}
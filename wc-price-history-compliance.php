<?php
/**
 * Plugin Name: WooCommerce Price History & Sale Compliance
 * Plugin URI: https://yoursite.com/
 * Description: Automatically track product price history and ensure compliance with EU Omnibus Directive by displaying the lowest price in the last 30 days during sales. Features price history charts, compliance alerts, and detailed reporting.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com/
 * Text Domain: wc-price-history-compliance
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WC_PRICE_HISTORY_VERSION', '1.0.0' );
define( 'WC_PRICE_HISTORY_PLUGIN_FILE', __FILE__ );
define( 'WC_PRICE_HISTORY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_PRICE_HISTORY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_PRICE_HISTORY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check for required PHP version
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p><strong>' . 
             esc_html__( 'WooCommerce Price History & Sale Compliance', 'wc-price-history-compliance' ) . 
             '</strong> ' . 
             esc_html__( 'requires PHP 7.4 or higher.', 'wc-price-history-compliance' ) . 
             '</p></div>';
    });
    return;
}

/**
 * Plugin activation hook - Must be outside the class to work properly
 */
function wc_price_history_compliance_activate() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 
            esc_html__( 'WooCommerce Price History & Sale Compliance requires WooCommerce to be installed and active.', 'wc-price-history-compliance' ),
            esc_html__( 'Plugin Activation Error', 'wc-price-history-compliance' ),
            array( 'back_link' => true )
        );
    }

    // Include the database class
    include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-database.php';
    
    // Create database tables
    $database = new WC_Price_History_Database();
    $database->create_tables();
    
    // Set default options
    $default_options = array(
        'enable_plugin' => 'yes',
        'display_lowest_price' => 'yes',
        'show_price_chart' => 'yes',
        'chart_days' => 30,
        'chart_type' => 'line',
        'chart_height' => 200,
        'lowest_price_text' => esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ),
        'enable_alerts' => 'yes',
        'compliance_days' => 30,
        'email_notifications' => 'no',
        'notification_email' => get_option( 'admin_email' ),
        'auto_compliance_check' => 'yes',
        'data_retention_days' => 365,
        'display_position' => 25,
        'show_in_loops' => 'no',
        'tracked_product_types' => array( 'simple', 'variable' ),
        'chart_colors' => array(
            'primary' => '#007cba',
            'secondary' => '#50575e',
            'sale' => '#e74c3c'
        )
    );
    
    foreach ( $default_options as $key => $value ) {
        if ( ! get_option( 'wc_price_history_' . $key ) ) {
            update_option( 'wc_price_history_' . $key, $value );
        }
    }
    
    // Set plugin version
    update_option( 'wc_price_history_version', WC_PRICE_HISTORY_VERSION );
    
    // Schedule cleanup event
    if ( ! wp_next_scheduled( 'wc_price_history_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'wc_price_history_cleanup' );
    }
    
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
function wc_price_history_compliance_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'wc_price_history_cleanup' );
    wp_clear_scheduled_hook( 'wc_price_history_daily_report' );
    wp_clear_scheduled_hook( 'wc_price_history_weekly_report' );
    
    flush_rewrite_rules();
}

/**
 * Register hooks
 */
register_activation_hook( WC_PRICE_HISTORY_PLUGIN_FILE, 'wc_price_history_compliance_activate' );
register_deactivation_hook( WC_PRICE_HISTORY_PLUGIN_FILE, 'wc_price_history_compliance_deactivate' );

/**
 * Main plugin class
 */
class WC_Price_History_Compliance {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    public $version = WC_PRICE_HISTORY_VERSION;

    /**
     * Database table name
     */
    public $table_name;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_price_history';
        
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
        
        // Check if WooCommerce is active
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Check and handle plugin updates
        add_action( 'plugins_loaded', array( $this, 'check_version' ) );

        $this->includes();
        $this->init_hooks();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize plugin components
        do_action( 'wc_price_history_init' );
    }

    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 
            'wc-price-history-compliance', 
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages/' 
        );
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . 
             esc_html__( 'WooCommerce Price History & Sale Compliance', 'wc-price-history-compliance' ) . 
             '</strong> ' . 
             esc_html__( 'requires WooCommerce to be installed and active.', 'wc-price-history-compliance' ) . 
             '</p></div>';
    }

    /**
     * Check plugin version and run updates if necessary
     */
    public function check_version() {
        $current_version = get_option( 'wc_price_history_version' );
        
        if ( version_compare( $current_version, WC_PRICE_HISTORY_VERSION, '<' ) ) {
            $this->run_update( $current_version );
        }
    }

    /**
     * Run plugin updates
     */
    private function run_update( $from_version ) {
        // Include database class for updates
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-database.php';
        
        $database = new WC_Price_History_Database();
        
        // Run database updates if needed
        $database->maybe_update_tables( $from_version );
        
        // Update version
        update_option( 'wc_price_history_version', WC_PRICE_HISTORY_VERSION );
        
        // Clear any cached data
        wp_cache_flush();
        
        do_action( 'wc_price_history_updated', $from_version, WC_PRICE_HISTORY_VERSION );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-database.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-price-tracker.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-frontend-display.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-admin-settings.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-reports.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-chart-generator.php';
        
        // Optional classes
        if ( is_admin() ) {
            include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-admin-notices.php';
        }
        
        // Widgets and blocks
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-widget.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-blocks.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize classes
        new WC_Price_History_Database();
        new WC_Price_History_Tracker();
        new WC_Price_History_Frontend_Display();
        new WC_Price_History_Chart_Generator();
        
        if ( is_admin() ) {
            new WC_Price_History_Admin_Settings();
            new WC_Price_History_Reports();
            new WC_Price_History_Admin_Notices();
        }
        
        // Add cleanup hook
        add_action( 'wc_price_history_cleanup', array( $this, 'daily_cleanup' ) );
        
        // Add plugin action links
        add_filter( 'plugin_action_links_' . WC_PRICE_HISTORY_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
        
        // Add plugin row meta
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }

    /**
     * Add plugin action links
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-price-history-settings' ) . '">' . 
                        esc_html__( 'Settings', 'wc-price-history-compliance' ) . '</a>';
        $reports_link = '<a href="' . admin_url( 'admin.php?page=wc-price-history-reports' ) . '">' . 
                       esc_html__( 'Reports', 'wc-price-history-compliance' ) . '</a>';
        
        array_unshift( $links, $settings_link, $reports_link );
        
        return $links;
    }

    /**
     * Add plugin row meta
     */
    public function plugin_row_meta( $links, $file ) {
        if ( WC_PRICE_HISTORY_PLUGIN_BASENAME === $file ) {
            $row_meta = array(
                'docs' => '<a href="#" target="_blank">' . esc_html__( 'Documentation', 'wc-price-history-compliance' ) . '</a>',
                'support' => '<a href="#" target="_blank">' . esc_html__( 'Support', 'wc-price-history-compliance' ) . '</a>',
            );
            
            return array_merge( $links, $row_meta );
        }
        
        return $links;
    }

    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        $retention_days = $this->get_option( 'data_retention_days', 365 );
        
        if ( $retention_days > 0 ) {
            $tracker = new WC_Price_History_Tracker();
            $deleted_count = $tracker->cleanup_old_records( $retention_days );
            
            // Log cleanup activity
            if ( $deleted_count > 0 ) {
                error_log( sprintf( 
                    'WC Price History: Cleaned up %d old records (older than %d days)', 
                    $deleted_count, 
                    $retention_days 
                ) );
            }
        }
        
        // Clean up dismissed alerts older than 30 days
        $dismissed_alerts = get_option( 'wc_price_history_dismissed_alerts', array() );
        $current_time = time();
        $cleaned_alerts = array();
        
        foreach ( $dismissed_alerts as $product_id => $expiry_time ) {
            if ( $expiry_time > $current_time ) {
                $cleaned_alerts[ $product_id ] = $expiry_time;
            }
        }
        
        update_option( 'wc_price_history_dismissed_alerts', $cleaned_alerts );
        
        do_action( 'wc_price_history_daily_cleanup_completed' );
    }

    /**
     * Get plugin option with caching
     */
    public function get_option( $key, $default = '' ) {
        static $cache = array();
        
        if ( ! isset( $cache[ $key ] ) ) {
            $cache[ $key ] = get_option( 'wc_price_history_' . $key, $default );
        }
        
        return $cache[ $key ];
    }

    /**
     * Update plugin option and clear cache
     */
    public function update_option( $key, $value ) {
        // Clear static cache
        static $cache = array();
        unset( $cache[ $key ] );
        
        return update_option( 'wc_price_history_' . $key, $value );
    }

    /**
     * Delete plugin option and clear cache
     */
    public function delete_option( $key ) {
        // Clear static cache
        static $cache = array();
        unset( $cache[ $key ] );
        
        return delete_option( 'wc_price_history_' . $key );
    }

    /**
     * Get all plugin options
     */
    public function get_all_options() {
        global $wpdb;
        
        $options = array();
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wc_price_history_%'
            )
        );
        
        foreach ( $results as $result ) {
            $key = str_replace( 'wc_price_history_', '', $result->option_name );
            $options[ $key ] = maybe_unserialize( $result->option_value );
        }
        
        return $options;
    }

    /**
     * Import settings from array
     */
    public function import_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return false;
        }
        
        $updated = 0;
        
        foreach ( $settings as $key => $value ) {
            if ( $this->update_option( $key, $value ) ) {
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Reset all plugin settings to defaults
     */
    public function reset_settings() {
        $default_options = array(
            'enable_plugin' => 'yes',
            'display_lowest_price' => 'yes',
            'show_price_chart' => 'yes',
            'chart_days' => 30,
            'chart_type' => 'line',
            'chart_height' => 200,
            'lowest_price_text' => esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ),
            'enable_alerts' => 'yes',
            'compliance_days' => 30,
            'email_notifications' => 'no',
            'notification_email' => get_option( 'admin_email' ),
            'auto_compliance_check' => 'yes',
            'data_retention_days' => 365,
            'display_position' => 25,
            'show_in_loops' => 'no',
            'tracked_product_types' => array( 'simple', 'variable' ),
            'chart_colors' => array(
                'primary' => '#007cba',
                'secondary' => '#50575e',
                'sale' => '#e74c3c'
            )
        );
        
        foreach ( $default_options as $key => $value ) {
            $this->update_option( $key, $value );
        }
        
        return true;
    }

    /**
     * Check if plugin feature is enabled
     */
    public function is_feature_enabled( $feature ) {
        switch ( $feature ) {
            case 'tracking':
                return 'yes' === $this->get_option( 'enable_plugin' );
            case 'charts':
                return 'yes' === $this->get_option( 'show_price_chart' );
            case 'alerts':
                return 'yes' === $this->get_option( 'enable_alerts' );
            case 'email_notifications':
                return 'yes' === $this->get_option( 'email_notifications' );
            default:
                return false;
        }
    }

    /**
     * Get plugin info
     */
    public function get_plugin_info() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return get_plugin_data( WC_PRICE_HISTORY_PLUGIN_FILE );
    }

    /**
     * Log plugin events
     */
    public function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = sprintf( 
                '[WC Price History] [%s] %s', 
                strtoupper( $level ), 
                $message 
            );
            error_log( $log_message );
        }
    }
}

// Initialize the plugin
function wc_price_history_compliance_init() {
    WC_Price_History_Compliance::get_instance();
}
add_action( 'plugins_loaded', 'wc_price_history_compliance_init' );

// HPOS compatibility declaration
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
            'custom_order_tables', 
            __FILE__, 
            true 
        );
    }
} );

// WooCommerce Blocks compatibility
add_action( 'woocommerce_blocks_loaded', function() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry' ) ) {
        // Block integration will be handled in separate class
        do_action( 'wc_price_history_blocks_init' );
    }
} );

// Helper functions
if ( ! function_exists( 'wc_price_history_get_instance' ) ) {
    /**
     * Get plugin instance
     */
    function wc_price_history_get_instance() {
        return WC_Price_History_Compliance::get_instance();
    }
}

if ( ! function_exists( 'wc_price_history_log' ) ) {
    /**
     * Log helper function
     */
    function wc_price_history_log( $message, $level = 'info' ) {
        WC_Price_History_Compliance::get_instance()->log( $message, $level );
    }
}
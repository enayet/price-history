<?php
/**
 * Plugin Name: WooCommerce Price History & Sale Compliance
 * Plugin URI: https://yoursite.com/
 * Description: Automatically track product price history and ensure compliance with EU Omnibus Directive by displaying the lowest price in the last 30 days during sales.
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

/**
 * Plugin activation hook - Must be outside the class to work properly
 */
function wc_price_history_compliance_activate() {
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
        'lowest_price_text' => esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ),
        'enable_alerts' => 'yes',
        'chart_colors' => array(
            'primary' => '#007cba',
            'secondary' => '#50575e'
        )
    );
    
    foreach ( $default_options as $key => $value ) {
        if ( ! get_option( 'wc_price_history_' . $key ) ) {
            update_option( 'wc_price_history_' . $key, $value );
        }
    }
    
    flush_rewrite_rules();
}

/**
 * Register activation hook - This must be called immediately, not inside a class
 */
register_activation_hook( WC_PRICE_HISTORY_PLUGIN_FILE, 'wc_price_history_compliance_activate' );

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
        load_plugin_textdomain( 'wc-price-history-compliance', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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
     * Include required files
     */
    private function includes() {
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-database.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-price-tracker.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-frontend-display.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-admin-settings.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-reports.php';
        include_once WC_PRICE_HISTORY_PLUGIN_PATH . 'includes/class-chart-generator.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_deactivation_hook( WC_PRICE_HISTORY_PLUGIN_FILE, array( $this, 'deactivate' ) );
        
        // Initialize classes
        new WC_Price_History_Database();
        new WC_Price_History_Tracker();
        new WC_Price_History_Frontend_Display();
        new WC_Price_History_Admin_Settings();
        new WC_Price_History_Reports();
        new WC_Price_History_Chart_Generator();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Get plugin option
     */
    public function get_option( $key, $default = '' ) {
        return get_option( 'wc_price_history_' . $key, $default );
    }

    /**
     * Update plugin option
     */
    public function update_option( $key, $value ) {
        return update_option( 'wc_price_history_' . $key, $value );
    }
}

// Initialize the plugin
function wc_price_history_compliance_init() {
    WC_Price_History_Compliance::get_instance();
}
add_action( 'plugins_loaded', 'wc_price_history_compliance_init' );

// HPOS compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// WooCommerce Blocks compatibility
add_action( 'woocommerce_blocks_loaded', function() {
    // Register block integration if needed
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry' ) ) {
        // Block integration code would go here
    }
} );
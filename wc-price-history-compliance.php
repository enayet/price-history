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

        //$this->includes();
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



/**
 * Database management class
 */
class WC_Price_History_Database {

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
    }
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
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if ( ! is_product() ) {
            return;
        }

        wp_enqueue_script( 'chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true );
        wp_enqueue_script( 
            'wc-price-history-frontend', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/js/frontend.js', 
            array( 'jquery', 'chart-js' ), 
            WC_PRICE_HISTORY_VERSION, 
            true 
        );
        wp_enqueue_style( 
            'wc-price-history-frontend', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            WC_PRICE_HISTORY_VERSION 
        );
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

        $database = new WC_Price_History_Database();
        $lowest_price = $database->get_lowest_price( $product->get_id(), 30 );
        
        if ( ! $lowest_price ) {
            return;
        }

        $lowest_price_formatted = wc_price( $lowest_price );
        $message_template = $plugin->get_option( 'lowest_price_text', esc_html__( 'Lowest price in the last 30 days: %s', 'wc-price-history-compliance' ) );
        $message = sprintf( $message_template, $lowest_price_formatted );
        
        echo '<div class="wc-price-history-lowest-price">';
        echo '<p class="lowest-price-message">' . wp_kses_post( $message ) . '</p>';
        echo '</div>';
    }
    
    /**
     * Display price history chart
     * Updated method for WC_Price_History_Frontend_Display class
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

        $chart_generator = new WC_Price_History_Chart_Generator();
        $chart_data = $chart_generator->get_chart_data( $product->get_id() );

        if ( empty( $chart_data ) ) {
            return;
        }

        echo '<div class="wc-price-history-chart-container">';
        echo '<h3>' . esc_html__( 'Price History', 'wc-price-history-compliance' ) . '</h3>';
        echo '<canvas id="price-history-chart" width="400" height="200"></canvas>';
        echo '</div>';

        // Add inline script to initialize the chart immediately
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof Chart !== 'undefined') {
                const chartData = <?php echo wp_json_encode( $chart_data ); ?>;
                const ctx = document.getElementById('price-history-chart');

                if (ctx && chartData) {
                    new Chart(ctx, {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('<?php echo esc_js( get_locale() ); ?>', {
                                                style: 'currency',
                                                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                                            }).format(value);
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
                                            return context.dataset.label + ': ' + 
                                                   new Intl.NumberFormat('<?php echo esc_js( get_locale() ); ?>', {
                                                       style: 'currency',
                                                       currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                                                   }).format(context.parsed.y);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } else {
                console.error('Chart.js library not loaded');
            }
        });
        </script>
        <?php
    }
    
}


/**
 * Chart generator class
 */
class WC_Price_History_Chart_Generator {

    /**
     * Get chart data for a product
     */
    public function get_chart_data( $product_id ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        $days = intval( $plugin->get_option( 'chart_days', 30 ) );
        
        $database = new WC_Price_History_Database();
        $price_history = $database->get_price_history( $product_id, $days );
        
        if ( empty( $price_history ) ) {
            return array();
        }

        $labels = array();
        $prices = array();
        
        foreach ( array_reverse( $price_history ) as $record ) {
            $labels[] = gmdate( 'M j', strtotime( $record->price_date ) );
            $price = ! empty( $record->sale_price ) ? floatval( $record->sale_price ) : floatval( $record->regular_price );
            $prices[] = $price;
        }

        $colors = $plugin->get_option( 'chart_colors', array( 'primary' => '#007cba', 'secondary' => '#50575e' ) );
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => esc_html__( 'Price', 'wc-price-history-compliance' ),
                    'data' => $prices,
                    'borderColor' => $colors['primary'],
                    'backgroundColor' => $colors['primary'] . '20',
                    'fill' => true,
                    'tension' => 0.4
                )
            ),
            'options' => array(
                'responsive' => true,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => false,
                        'ticks' => array(
                            'callback' => 'formatPrice'
                        )
                    )
                ),
                'plugins' => array(
                    'legend' => array(
                        'display' => false
                    ),
                    'tooltip' => array(
                        'callbacks' => array(
                            'label' => 'formatTooltip'
                        )
                    )
                )
            )
        );
    }
}

/**
 * Admin settings class
 */
class WC_Price_History_Admin_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__( 'Price History & Compliance', 'wc-price-history-compliance' ),
            esc_html__( 'Price History', 'wc-price-history-compliance' ),
            'manage_woocommerce',
            'wc-price-history-settings',
            array( $this, 'admin_page' )
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_wc-price-history-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script( 
            'wc-price-history-admin', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery', 'wp-color-picker' ), 
            WC_PRICE_HISTORY_VERSION, 
            true 
        );
        wp_enqueue_style( 
            'wc-price-history-admin', 
            WC_PRICE_HISTORY_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            WC_PRICE_HISTORY_VERSION 
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting( 'wc_price_history_settings', 'wc_price_history_options' );
    }

    /**
     * Admin settings page
     */
    public function admin_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->save_settings();
        }
        
        $plugin = WC_Price_History_Compliance::get_instance();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'WooCommerce Price History & Sale Compliance Settings', 'wc-price-history-compliance' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'wc_price_history_settings_save', 'wc_price_history_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Enable Plugin', 'wc-price-history-compliance' ); ?></th>
                        <td>
                            <input type="checkbox" name="enable_plugin" value="yes" <?php checked( 'yes', $plugin->get_option( 'enable_plugin' ) ); ?> />
                            <p class="description"><?php echo esc_html__( 'Enable price history tracking and compliance features.', 'wc-price-history-compliance' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Display Lowest Price', 'wc-price-history-compliance' ); ?></th>
                        <td>
                            <input type="checkbox" name="display_lowest_price" value="yes" <?php checked( 'yes', $plugin->get_option( 'display_lowest_price' ) ); ?> />
                            <p class="description"><?php echo esc_html__( 'Show the lowest price in the last 30 days during sales.', 'wc-price-history-compliance' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Lowest Price Message', 'wc-price-history-compliance' ); ?></th>
                        <td>
                            <input type="text" name="lowest_price_text" value="<?php echo esc_attr( $plugin->get_option( 'lowest_price_text' ) ); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__( 'Use %s as placeholder for the price. Example: "Lowest price in the last 30 days: %s"', 'wc-price-history-compliance' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Show Price Chart', 'wc-price-history-compliance' ); ?></th>
                        <td>
                            <input type="checkbox" name="show_price_chart" value="yes" <?php checked( 'yes', $plugin->get_option( 'show_price_chart' ) ); ?> />
                            <p class="description"><?php echo esc_html__( 'Display price history chart on product pages.', 'wc-price-history-compliance' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Chart Days', 'wc-price-history-compliance' ); ?></th>
                        <td>
                            <select name="chart_days">
                                <option value="30" <?php selected( 30, $plugin->get_option( 'chart_days' ) ); ?>>30 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                                <option value="60" <?php selected( 60, $plugin->get_option( 'chart_days' ) ); ?>>60 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                                <option value="90" <?php selected( 90, $plugin->get_option( 'chart_days' ) ); ?>>90 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                                <option value="180" <?php selected( 180, $plugin->get_option( 'chart_days' ) ); ?>>180 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__( 'Number of days to show in the price history chart.', 'wc-price-history-compliance' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        // Verify nonce
        if ( ! isset( $_POST['wc_price_history_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_price_history_nonce'] ) ), 'wc_price_history_settings_save' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        $plugin = WC_Price_History_Compliance::get_instance();
        
        $enable_plugin = isset( $_POST['enable_plugin'] ) ? 'yes' : 'no';
        $display_lowest_price = isset( $_POST['display_lowest_price'] ) ? 'yes' : 'no';
        $show_price_chart = isset( $_POST['show_price_chart'] ) ? 'yes' : 'no';
        $lowest_price_text = isset( $_POST['lowest_price_text'] ) ? sanitize_text_field( wp_unslash( $_POST['lowest_price_text'] ) ) : '';
        $chart_days = isset( $_POST['chart_days'] ) ? intval( $_POST['chart_days'] ) : 30;
        
        $plugin->update_option( 'enable_plugin', $enable_plugin );
        $plugin->update_option( 'display_lowest_price', $display_lowest_price );
        $plugin->update_option( 'show_price_chart', $show_price_chart );
        $plugin->update_option( 'lowest_price_text', $lowest_price_text );
        $plugin->update_option( 'chart_days', $chart_days );
        
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'wc-price-history-compliance' ) . '</p></div>';
    }
}

/**
 * Reports class
 */
class WC_Price_History_Reports {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_reports_menu' ) );
        add_action( 'wp_ajax_wc_price_history_export_csv', array( $this, 'export_csv' ) );
        add_action( 'admin_init', array( $this, 'handle_alerts' ) );
    }

    /**
     * Add reports menu
     */
    public function add_reports_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__( 'Price History Reports', 'wc-price-history-compliance' ),
            esc_html__( 'Price Reports', 'wc-price-history-compliance' ),
            'manage_woocommerce',
            'wc-price-history-reports',
            array( $this, 'reports_page' )
        );
    }

    /**
     * Reports page
     */
    public function reports_page() {
        $database = new WC_Price_History_Database();
        
        // Get recent price changes
        $recent_changes = $this->get_recent_price_changes( 30 );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Price History Reports', 'wc-price-history-compliance' ); ?></h1>
            
            <div class="wc-price-history-reports">
                <div class="report-section">
                    <h2><?php echo esc_html__( 'Recent Price Changes (Last 30 Days)', 'wc-price-history-compliance' ); ?></h2>
                    
                    <div class="report-actions">
                        <button type="button" class="button" onclick="exportToCSV()">
                            <?php echo esc_html__( 'Export to CSV', 'wc-price-history-compliance' ); ?>
                        </button>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                                <th><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></th>
                                <th><?php echo esc_html__( 'Sale Price', 'wc-price-history-compliance' ); ?></th>
                                <th><?php echo esc_html__( 'Price Type', 'wc-price-history-compliance' ); ?></th>
                                <th><?php echo esc_html__( 'Date Changed', 'wc-price-history-compliance' ); ?></th>
                                <th><?php echo esc_html__( 'Compliance Status', 'wc-price-history-compliance' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $recent_changes ) ) : ?>
                                <?php foreach ( $recent_changes as $change ) : ?>
                                    <?php
                                    $product = wc_get_product( $change->product_id );
                                    if ( ! $product ) {
                                        continue;
                                    }
                                    
                                    $compliance_status = $this->check_compliance_status( $change );
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url( get_edit_post_link( $change->product_id ) ); ?>">
                                                <?php echo esc_html( $product->get_name() ); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $change->regular_price ? wc_price( $change->regular_price ) : '—'; ?></td>
                                        <td><?php echo $change->sale_price ? wc_price( $change->sale_price ) : '—'; ?></td>
                                        <td><?php echo esc_html( ucfirst( $change->price_type ) ); ?></td>
                                        <td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', strtotime( $change->price_date ) ) ); ?></td>
                                        <td>
                                            <span class="compliance-status <?php echo esc_attr( $compliance_status['class'] ); ?>">
                                                <?php echo esc_html( $compliance_status['text'] ); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6"><?php echo esc_html__( 'No price changes found in the last 30 days.', 'wc-price-history-compliance' ); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="report-section">
                    <h2><?php echo esc_html__( 'Compliance Alerts', 'wc-price-history-compliance' ); ?></h2>
                    <?php $this->display_compliance_alerts(); ?>
                </div>
            </div>
        </div>
        
        <script>
        function exportToCSV() {
            window.location.href = ajaxurl + '?action=wc_price_history_export_csv&_wpnonce=<?php echo esc_js( wp_create_nonce( 'wc_price_history_export' ) ); ?>';
        }
        </script>
        <?php
    }

    /**
     * Get recent price changes
     */
    private function get_recent_price_changes( $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE price_date >= %s ORDER BY price_date DESC LIMIT 100",
            $date_from
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    /**
     * Check compliance status for a price change
     */
    private function check_compliance_status( $price_record ) {
        if ( 'sale' !== $price_record->price_type ) {
            return array(
                'class' => 'compliant',
                'text' => esc_html__( 'Compliant', 'wc-price-history-compliance' )
            );
        }

        $database = new WC_Price_History_Database();
        $lowest_price = $database->get_lowest_price( $price_record->product_id, 30 );
        
        if ( ! $lowest_price ) {
            return array(
                'class' => 'unknown',
                'text' => esc_html__( 'Unknown', 'wc-price-history-compliance' )
            );
        }

        $current_sale_price = floatval( $price_record->sale_price );
        
        if ( $current_sale_price <= $lowest_price ) {
            return array(
                'class' => 'compliant',
                'text' => esc_html__( 'Compliant', 'wc-price-history-compliance' )
            );
        } else {
            return array(
                'class' => 'non-compliant',
                'text' => esc_html__( 'Requires Attention', 'wc-price-history-compliance' )
            );
        }
    }

    /**
     * Display compliance alerts
     */
    private function display_compliance_alerts() {
        $alerts = $this->get_compliance_alerts();
        
        if ( empty( $alerts ) ) {
            echo '<p>' . esc_html__( 'No compliance issues found.', 'wc-price-history-compliance' ) . '</p>';
            return;
        }

        echo '<div class="compliance-alerts">';
        foreach ( $alerts as $alert ) {
            $product = wc_get_product( $alert->product_id );
            if ( ! $product ) {
                continue;
            }
            
            echo '<div class="alert-item">';
            echo '<h4>' . esc_html( $product->get_name() ) . '</h4>';
            echo '<p>' . esc_html( $alert->message ) . '</p>';
            echo '<a href="' . esc_url( get_edit_post_link( $alert->product_id ) ) . '" class="button">' . 
                 esc_html__( 'Edit Product', 'wc-price-history-compliance' ) . '</a>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Get compliance alerts
     */
    private function get_compliance_alerts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $alerts = array();
        
        // Get products currently on sale
        $products_on_sale = wc_get_product_ids_on_sale();
        
        foreach ( $products_on_sale as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $database = new WC_Price_History_Database();
            $lowest_price = $database->get_lowest_price( $product_id, 30 );
            $current_sale_price = floatval( $product->get_sale_price() );
            
            if ( $lowest_price && $current_sale_price > $lowest_price ) {
                $alert = new stdClass();
                $alert->product_id = $product_id;
                $alert->message = sprintf(
                    esc_html__( 'Sale price (%s) is higher than the lowest price in the last 30 days (%s).', 'wc-price-history-compliance' ),
                    wc_price( $current_sale_price ),
                    wc_price( $lowest_price )
                );
                $alerts[] = $alert;
            }
        }
        
        return $alerts;
    }

    /**
     * Export data to CSV
     */
    public function export_csv() {
        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wc_price_history_export' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        $recent_changes = $this->get_recent_price_changes( 30 );
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="price-history-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
        
        $output = fopen( 'php://output', 'w' );
        
        // CSV headers
        fputcsv( $output, array(
            'Product ID',
            'Product Name',
            'Regular Price',
            'Sale Price',
            'Price Type',
            'Date Changed',
            'Compliance Status'
        ) );
        
        foreach ( $recent_changes as $change ) {
            $product = wc_get_product( $change->product_id );
            if ( ! $product ) {
                continue;
            }
            
            $compliance_status = $this->check_compliance_status( $change );
            
            fputcsv( $output, array(
                $change->product_id,
                $product->get_name(),
                $change->regular_price,
                $change->sale_price,
                $change->price_type,
                $change->price_date,
                $compliance_status['text']
            ) );
        }
        
        fclose( $output );
        exit;
    }

    /**
     * Handle compliance alerts
     */
    public function handle_alerts() {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_alerts' ) ) {
            return;
        }

        // Check for compliance issues and send notifications if needed
        $alerts = $this->get_compliance_alerts();
        
        if ( ! empty( $alerts ) ) {
            $this->send_alert_notifications( $alerts );
        }
    }

    /**
     * Send alert notifications
     */
    private function send_alert_notifications( $alerts ) {
        // This can be extended to send email notifications
        // For now, we'll just set a transient to show admin notices
        
        $alert_count = count( $alerts );
        $message = sprintf(
            _n(
                '%d product has potential compliance issues.',
                '%d products have potential compliance issues.',
                $alert_count,
                'wc-price-history-compliance'
            ),
            $alert_count
        );
        
        set_transient( 'wc_price_history_compliance_alert', $message, DAY_IN_SECONDS );
        
        add_action( 'admin_notices', array( $this, 'show_compliance_notice' ) );
    }

    /**
     * Show compliance notice
     */
    public function show_compliance_notice() {
        $message = get_transient( 'wc_price_history_compliance_alert' );
        
        if ( $message ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__( 'Price History Compliance Alert:', 'wc-price-history-compliance' ) . '</strong> ';
            echo esc_html( $message );
            echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-price-history-reports' ) ) . '">' . 
                 esc_html__( 'View Details', 'wc-price-history-compliance' ) . '</a></p>';
            echo '</div>';
            
            delete_transient( 'wc_price_history_compliance_alert' );
        }
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
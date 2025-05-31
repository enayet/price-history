<?php
/**
 * The core plugin class
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks. Also maintains the unique identifier of this
 * plugin as well as the current version of the plugin.
 *
 * @since      1.0.0
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 * @author     Your Name <email@example.com>
 */
class WPHC_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The instance of the database class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Database    $database    Database operations handler.
     */
    protected $database;

    /**
     * The instance of the price tracker class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Price_Tracker    $price_tracker    Price tracking functionality.
     */
    protected $price_tracker;

    /**
     * The instance of the compliance class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Compliance    $compliance    Compliance display logic.
     */
    protected $compliance;

    /**
     * The instance of the chart generator class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Chart_Generator    $chart_generator    Chart generation functionality.
     */
    protected $chart_generator;

    /**
     * The instance of the security class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Security    $security    Security and nonce handling.
     */
    protected $security;

    /**
     * The instance of the compatibility class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Compatibility    $compatibility    WooCommerce compatibility checks.
     */
    protected $compatibility;

    /**
     * The instance of the internationalization class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_i18n    $i18n    Internationalization functionality.
     */
    protected $i18n;

    /**
     * The instance of the admin class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Admin    $admin    Admin functionality.
     */
    protected $admin;

    /**
     * The instance of the public class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WPHC_Public    $public    Public-facing functionality.
     */
    protected $public;

    /**
     * Plugin initialization status.
     *
     * @since    1.0.0
     * @access   protected
     * @var      bool    $initialized    Whether the plugin has been initialized.
     */
    protected $initialized;

    /**
     * Array to store component instances.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $components    Component instances.
     */
    protected $components;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('WPHC_VERSION')) {
            $this->version = WPHC_VERSION;
        } else {
            $this->version = '1.0.0';
        }

        $this->plugin_name = 'woocommerce-price-history-compliance';
        $this->initialized = false;
        $this->components = array();

        $this->load_dependencies();
        $this->set_locale();
        $this->init_core_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->setup_cron_hooks();
        $this->setup_woocommerce_hooks();
        
        // Mark as initialized
        $this->initialized = true;
        
        // Log initialization
        wphc_log('Core plugin class initialized successfully', 'info');
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        $this->loader = new WPHC_Loader();
        
        wphc_log('Plugin dependencies loaded', 'info');
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the WPHC_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $this->i18n = new WPHC_i18n();
        $this->components['i18n'] = $this->i18n;
        
        $this->i18n->register_hooks($this->loader);
        
        wphc_log('Plugin locale set', 'info');
    }

    /**
     * Initialize core plugin components.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_core_components() {
        // Initialize security first
        $this->security = new WPHC_Security();
        $this->components['security'] = $this->security;

        // Initialize compatibility checker
        $this->compatibility = new WPHC_Compatibility();
        $this->components['compatibility'] = $this->compatibility;

        // Check compatibility before proceeding
        if (!$this->compatibility->is_environment_compatible()) {
            wphc_log('Environment compatibility check failed', 'error');
            return;
        }

        // Initialize database handler
        $this->database = new WPHC_Database();
        $this->components['database'] = $this->database;

        // Initialize price tracker
        $this->price_tracker = new WPHC_Price_Tracker($this->database, $this->security);
        $this->components['price_tracker'] = $this->price_tracker;

        // Initialize compliance handler
        $this->compliance = new WPHC_Compliance($this->database, $this->security);
        $this->components['compliance'] = $this->compliance;

        // Initialize chart generator
        $this->chart_generator = new WPHC_Chart_Generator($this->database, $this->security);
        $this->components['chart_generator'] = $this->chart_generator;

        wphc_log('Core plugin components initialized', 'info');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }

        $this->admin = new WPHC_Admin($this->get_plugin_name(), $this->get_version(), $this->database, $this->security);
        $this->components['admin'] = $this->admin;

        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');

        // Add admin menu pages
        $this->loader->add_action('admin_menu', $this->admin, 'add_plugin_admin_menu');

        // Add settings link on plugin page
        $plugin_basename = plugin_basename(plugin_dir_path(realpath(dirname(__FILE__))) . $this->plugin_name . '.php');
        $this->loader->add_filter('plugin_action_links_' . $plugin_basename, $this->admin, 'add_action_links');

        // Handle admin AJAX requests
        $this->loader->add_action('wp_ajax_wphc_admin_action', $this->admin, 'handle_ajax_request');

        // Add product meta boxes
        $this->loader->add_action('add_meta_boxes', $this->admin, 'add_product_meta_boxes');
        $this->loader->add_action('woocommerce_process_product_meta', $this->admin, 'save_product_meta_boxes');

        // Admin notices
        $this->loader->add_action('admin_notices', $this->admin, 'display_admin_notices');

        wphc_log('Admin hooks defined', 'info');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $this->public = new WPHC_Public($this->get_plugin_name(), $this->get_version(), $this->compliance, $this->chart_generator, $this->security);
        $this->components['public'] = $this->public;

        // Enqueue public styles and scripts
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');

        // Register shortcodes
        $shortcodes = new WPHC_Shortcodes($this->compliance, $this->chart_generator, $this->security);
        $this->components['shortcodes'] = $shortcodes;
        
        $this->loader->add_action('init', $shortcodes, 'init_shortcodes');

        // Handle public AJAX requests
        $this->loader->add_action('wp_ajax_wphc_public_action', $this->public, 'handle_ajax_request');
        $this->loader->add_action('wp_ajax_nopriv_wphc_public_action', $this->public, 'handle_ajax_request');

        // Add frontend functionality
        $this->loader->add_action('init', $this->public, 'init_public_features');

        wphc_log('Public hooks defined', 'info');
    }

    /**
     * Set up cron job hooks for automated tasks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setup_cron_hooks() {
        // Daily cleanup job
        $this->loader->add_action('wphc_daily_cleanup', $this->database, 'cleanup_old_records');
        
        // Hourly validation job
        $this->loader->add_action('wphc_hourly_validation', $this->price_tracker, 'validate_price_data');
        
        // Weekly maintenance job
        $this->loader->add_action('wphc_weekly_maintenance', $this->database, 'optimize_database_tables');

        wphc_log('Cron hooks set up', 'info');
    }

    /**
     * Set up WooCommerce-specific hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setup_woocommerce_hooks() {
        // Price tracking hooks
        $this->loader->add_action('woocommerce_product_set_regular_price', $this->price_tracker, 'track_regular_price_change', 10, 2);
        $this->loader->add_action('woocommerce_product_set_sale_price', $this->price_tracker, 'track_sale_price_change', 10, 2);
        $this->loader->add_action('woocommerce_variation_set_regular_price', $this->price_tracker, 'track_variation_regular_price_change', 10, 2);
        $this->loader->add_action('woocommerce_variation_set_sale_price', $this->price_tracker, 'track_variation_sale_price_change', 10, 2);

        // Product display hooks for compliance messages
        $this->loader->add_action('woocommerce_single_product_summary', $this->compliance, 'display_compliance_message', 25);
        $this->loader->add_action('woocommerce_after_shop_loop_item_title', $this->compliance, 'display_shop_compliance_message', 15);

        // Price display filters
        $this->loader->add_filter('woocommerce_get_price_html', $this->compliance, 'modify_price_html', 10, 2);

        // Cart and checkout hooks (for blocks compatibility)
        $this->loader->add_action('woocommerce_blocks_loaded', $this, 'register_blocks_integration');

        // HPOS compatibility hooks
        if ($this->compatibility->is_hpos_enabled()) {
            $this->setup_hpos_hooks();
        }

        wphc_log('WooCommerce hooks set up', 'info');
    }

    /**
     * Set up HPOS (High Performance Order Storage) hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setup_hpos_hooks() {
        // Add HPOS-specific hooks if needed
        $this->loader->add_action('woocommerce_new_order', $this->price_tracker, 'handle_hpos_order_price_tracking', 10, 1);
        
        wphc_log('HPOS hooks set up', 'info');
    }

    /**
     * Register blocks integration for WooCommerce Blocks.
     *
     * @since    1.0.0
     */
    public function register_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
            // Register block integration - placeholder for future implementation
            wphc_log('WooCommerce Blocks integration registered', 'info');
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
        
        // Perform post-initialization tasks
        $this->post_init_tasks();
        
        wphc_log('Plugin loader executed and running', 'info');
    }

    /**
     * Perform tasks after plugin initialization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function post_init_tasks() {
        // Check if this is the first run after activation
        if (wphc_get_option('first_activation', false)) {
            $this->handle_first_activation();
        }

        // Check for plugin updates
        $this->check_plugin_updates();

        // Validate plugin settings
        $this->validate_plugin_settings();

        // Set up performance monitoring if in debug mode
        if (wphc_is_development_mode()) {
            $this->setup_performance_monitoring();
        }
    }

    /**
     * Handle first activation tasks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function handle_first_activation() {
        // Create welcome notice
        set_transient('wphc_show_welcome_notice', true, WEEK_IN_SECONDS);
        
        // Reset first activation flag
        wphc_update_option('first_activation', false);
        
        wphc_log('First activation tasks completed', 'info');
    }

    /**
     * Check for plugin updates and handle migration.
     *
     * @since    1.0.0
     * @access   private
     */
    private function check_plugin_updates() {
        $current_version = wphc_get_option('plugin_version', '0.0.0');
        
        if (version_compare($current_version, $this->version, '<')) {
            $this->handle_plugin_update($current_version, $this->version);
        }
    }

    /**
     * Handle plugin updates and migrations.
     *
     * @since    1.0.0
     * @access   private
     * @param    string $old_version    The old version.
     * @param    string $new_version    The new version.
     */
    private function handle_plugin_update($old_version, $new_version) {
        // Perform version-specific updates
        if (version_compare($old_version, '1.0.0', '<')) {
            // Handle updates from pre-1.0.0 versions
            $this->migrate_to_1_0_0();
        }

        // Update the stored version
        wphc_update_option('plugin_version', $new_version);
        
        wphc_log("Plugin updated from {$old_version} to {$new_version}", 'info');
    }

    /**
     * Migrate to version 1.0.0.
     *
     * @since    1.0.0
     * @access   private
     */
    private function migrate_to_1_0_0() {
        // Database migration tasks
        if ($this->database) {
            $this->database->maybe_upgrade_database();
        }
        
        wphc_log('Migration to 1.0.0 completed', 'info');
    }

    /**
     * Validate plugin settings.
     *
     * @since    1.0.0
     * @access   private
     */
    private function validate_plugin_settings() {
        $validation_errors = array();

        // Validate required settings
        $required_settings = array(
            'enable_plugin' => 'boolean',
            'enable_price_tracking' => 'boolean',
            'price_history_days' => 'integer',
            'compliance_message' => 'string',
        );

        foreach ($required_settings as $setting => $type) {
            $value = wphc_get_option($setting);
            
            if ($value === false) {
                $validation_errors[] = sprintf(
                    /* translators: %s: Setting name */
                    esc_html__('Required setting "%s" is missing', 'woocommerce-price-history-compliance'),
                    $setting
                );
            } elseif (!$this->validate_setting_type($value, $type)) {
                $validation_errors[] = sprintf(
                    /* translators: %1$s: Setting name, %2$s: Expected type */
                    esc_html__('Setting "%1$s" has invalid type, expected %2$s', 'woocommerce-price-history-compliance'),
                    $setting,
                    $type
                );
            }
        }

        if (!empty($validation_errors)) {
            wphc_log('Plugin settings validation errors: ' . implode(', ', $validation_errors), 'warning');
        }
    }

    /**
     * Validate setting type.
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed  $value    The value to validate.
     * @param    string $type     The expected type.
     * @return   bool             True if valid, false otherwise.
     */
    private function validate_setting_type($value, $type) {
        switch ($type) {
            case 'boolean':
                return is_bool($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'string':
                return is_string($value);
            default:
                return true;
        }
    }

    /**
     * Set up performance monitoring for debug mode.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setup_performance_monitoring() {
        // Add performance monitoring hooks
        $this->loader->add_action('shutdown', $this, 'log_performance_metrics');
        
        wphc_log('Performance monitoring set up', 'info');
    }

    /**
     * Log performance metrics (debug mode only).
     *
     * @since    1.0.0
     */
    public function log_performance_metrics() {
        if (!wphc_is_development_mode()) {
            return;
        }

        $memory_usage = memory_get_peak_usage(true);
        $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);
        
        $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        wphc_log("Performance - Memory: {$memory_usage_mb}MB, Execution: " . round($execution_time, 4) . 's', 'info');
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    WPHC_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get a specific component instance.
     *
     * @since     1.0.0
     * @param     string $component_name    The name of the component.
     * @return    object|null               The component instance or null if not found.
     */
    public function get_component($component_name) {
        return isset($this->components[$component_name]) ? $this->components[$component_name] : null;
    }

    /**
     * Get all component instances.
     *
     * @since     1.0.0
     * @return    array    Array of component instances.
     */
    public function get_components() {
        return $this->components;
    }

    /**
     * Check if plugin is fully initialized.
     *
     * @since     1.0.0
     * @return    bool    True if initialized, false otherwise.
     */
    public function is_initialized() {
        return $this->initialized;
    }

    /**
     * Get plugin status information.
     *
     * @since     1.0.0
     * @return    array    Array of status information.
     */
    public function get_status() {
        return array(
            'plugin_name' => $this->plugin_name,
            'version' => $this->version,
            'initialized' => $this->initialized,
            'components_loaded' => count($this->components),
            'hooks_registered' => $this->loader ? $this->loader->get_hook_counts() : array(),
            'compatibility' => $this->compatibility ? $this->compatibility->get_compatibility_status() : array(),
            'database_version' => get_option('wphc_db_version', '0.0.0'),
        );
    }

    /**
     * Deactivate plugin features gracefully.
     *
     * @since     1.0.0
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wphc_daily_cleanup');
        wp_clear_scheduled_hook('wphc_hourly_validation');
        wp_clear_scheduled_hook('wphc_weekly_maintenance');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear caches
        wp_cache_flush();

        wphc_log('Plugin deactivated gracefully', 'info');
    }

    /**
     * Handle plugin errors and exceptions.
     *
     * @since     1.0.0
     * @param     Exception $exception    The exception to handle.
     */
    public function handle_exception($exception) {
        $error_message = sprintf(
            'WPHC Plugin Error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        wphc_log($error_message, 'error');

        // Show admin notice if in admin area
        if (is_admin()) {
            add_action('admin_notices', function() use ($error_message) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__('WooCommerce Price History & Sale Compliance encountered an error. Please check the error logs.', 'woocommerce-price-history-compliance')
                );
            });
        }
    }

    /**
     * Emergency shutdown procedure.
     *
     * @since     1.0.0
     */
    public function emergency_shutdown() {
        // Disable all plugin functionality
        remove_all_actions('woocommerce_product_set_regular_price');
        remove_all_actions('woocommerce_product_set_sale_price');
        remove_all_actions('woocommerce_single_product_summary');
        
        // Log emergency shutdown
        wphc_log('Emergency shutdown initiated', 'error');
        
        // Show admin notice
        if (is_admin()) {
            add_action('admin_notices', function() {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__('WooCommerce Price History & Sale Compliance has been disabled due to a critical error. Please contact support.', 'woocommerce-price-history-compliance')
                );
            });
        }
    }

    /**
     * Get plugin debug information.
     *
     * @since     1.0.0
     * @return    array    Debug information array.
     */
    public function get_debug_info() {
        if (!wphc_is_development_mode()) {
            return array('error' => 'Debug mode not enabled');
        }

        $debug_info = array(
            'plugin_status' => $this->get_status(),
            'wp_environment' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ),
            'woocommerce_info' => array(
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
                'hpos_enabled' => $this->compatibility ? $this->compatibility->is_hpos_enabled() : false,
                'blocks_active' => class_exists('Automattic\WooCommerce\Blocks\Package'),
            ),
            'plugin_settings' => array(
                'enable_plugin' => wphc_get_option('enable_plugin', false),
                'enable_price_tracking' => wphc_get_option('enable_price_tracking', false),
                'price_history_days' => wphc_get_option('price_history_days', 30),
            ),
        );

        return $debug_info;
    }
}
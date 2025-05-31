<?php
/**
 * Plugin Name: WooCommerce Price History & Sale Compliance
 * Plugin URI: https://example.com/woocommerce-price-history-compliance
 * Description: A WooCommerce plugin that helps store owners comply with the EU's Omnibus Directive by automatically tracking product price history and displaying the lowest price in the last 30 days during sales.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-price-history-compliance
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_Price_History_Compliance
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WPHC_VERSION', '1.0.0');

/**
 * Plugin base name.
 */
define('WPHC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin directory path.
 */
define('WPHC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('WPHC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin name for internal use.
 */
define('WPHC_PLUGIN_NAME', 'woocommerce-price-history-compliance');

/**
 * Database table name for price history.
 */
define('WPHC_TABLE_NAME', 'wphc_price_history');

/**
 * Check if WooCommerce is active before initializing the plugin.
 */
function wphc_check_woocommerce_dependency() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wphc_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function wphc_woocommerce_missing_notice() {
    $message = sprintf(
        /* translators: %s: Plugin name */
        esc_html__('%s requires WooCommerce to be installed and active.', 'woocommerce-price-history-compliance'),
        '<strong>' . esc_html__('WooCommerce Price History & Sale Compliance', 'woocommerce-price-history-compliance') . '</strong>'
    );
    
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        wp_kses_post($message)
    );
}

/**
 * Check if current WooCommerce version meets minimum requirements.
 */
function wphc_check_woocommerce_version() {
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '<')) {
        add_action('admin_notices', 'wphc_woocommerce_version_notice');
        return false;
    }
    return true;
}

/**
 * Display admin notice if WooCommerce version is too old.
 */
function wphc_woocommerce_version_notice() {
    $message = sprintf(
        /* translators: %1$s: Plugin name, %2$s: Required WooCommerce version */
        esc_html__('%1$s requires WooCommerce version %2$s or higher.', 'woocommerce-price-history-compliance'),
        '<strong>' . esc_html__('WooCommerce Price History & Sale Compliance', 'woocommerce-price-history-compliance') . '</strong>',
        '5.0'
    );
    
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        wp_kses_post($message)
    );
}

/**
 * Initialize the plugin after all plugins are loaded.
 */
function wphc_init() {
    // Check WooCommerce dependency
    if (!wphc_check_woocommerce_dependency()) {
        return;
    }
    
    // Check WooCommerce version
    if (!wphc_check_woocommerce_version()) {
        return;
    }
    
    // Load plugin files
    wphc_load_plugin_files();
    
    // Initialize the main plugin class
    new WPHC_Core();
}
add_action('plugins_loaded', 'wphc_init');

/**
 * Load all required plugin files.
 */
function wphc_load_plugin_files() {
    // Core includes
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-loader.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-i18n.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-core.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-database.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-price-tracker.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-compliance.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-chart-generator.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-security.php';
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-compatibility.php';
    
    // Admin includes
    if (is_admin()) {
        require_once WPHC_PLUGIN_DIR . 'admin/class-wphc-admin.php';
        require_once WPHC_PLUGIN_DIR . 'admin/class-wphc-admin-settings.php';
        require_once WPHC_PLUGIN_DIR . 'admin/class-wphc-admin-reports.php';
        require_once WPHC_PLUGIN_DIR . 'admin/class-wphc-admin-alerts.php';
    }
    
    // Public includes
    require_once WPHC_PLUGIN_DIR . 'public/class-wphc-public.php';
    require_once WPHC_PLUGIN_DIR . 'public/class-wphc-shortcodes.php';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wphc-activator.php
 */
function wphc_activate() {
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-activator.php';
    WPHC_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wphc-deactivator.php
 */
function wphc_deactivate() {
    require_once WPHC_PLUGIN_DIR . 'includes/class-wphc-deactivator.php';
    WPHC_Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'wphc_activate');
register_deactivation_hook(__FILE__, 'wphc_deactivate');

/**
 * Add custom action links on plugin page.
 *
 * @param array $links Existing action links.
 * @return array Modified action links.
 */
function wphc_add_action_links($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=wphc-settings')),
        esc_html__('Settings', 'woocommerce-price-history-compliance')
    );
    
    array_unshift($links, $settings_link);
    
    return $links;
}
add_filter('plugin_action_links_' . WPHC_PLUGIN_BASENAME, 'wphc_add_action_links');

/**
 * Add custom meta links on plugin page.
 *
 * @param array  $links Existing meta links.
 * @param string $file  Plugin file.
 * @return array Modified meta links.
 */
function wphc_add_meta_links($links, $file) {
    if (WPHC_PLUGIN_BASENAME === $file) {
        $meta_links = array(
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url('https://example.com/docs'),
                esc_html__('Documentation', 'woocommerce-price-history-compliance')
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url('https://example.com/support'),
                esc_html__('Support', 'woocommerce-price-history-compliance')
            ),
        );
        
        $links = array_merge($links, $meta_links);
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'wphc_add_meta_links', 10, 2);

/**
 * Load plugin textdomain for translations.
 */
function wphc_load_textdomain() {
    load_plugin_textdomain(
        'woocommerce-price-history-compliance',
        false,
        dirname(WPHC_PLUGIN_BASENAME) . '/languages/'
    );
}
add_action('plugins_loaded', 'wphc_load_textdomain');

/**
 * Declare HPOS compatibility.
 */
function wphc_declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
}
add_action('before_woocommerce_init', 'wphc_declare_hpos_compatibility');

/**
 * Declare WooCommerce Blocks compatibility.
 */
function wphc_declare_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
}
add_action('before_woocommerce_init', 'wphc_declare_blocks_compatibility');

/**
 * Add custom cron schedules for price tracking.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified cron schedules.
 */
function wphc_add_cron_schedules($schedules) {
    $schedules['wphc_hourly'] = array(
        'interval' => HOUR_IN_SECONDS,
        'display'  => esc_html__('Every Hour (WPHC)', 'woocommerce-price-history-compliance'),
    );
    
    $schedules['wphc_daily'] = array(
        'interval' => DAY_IN_SECONDS,
        'display'  => esc_html__('Daily (WPHC)', 'woocommerce-price-history-compliance'),
    );
    
    return $schedules;
}
add_filter('cron_schedules', 'wphc_add_cron_schedules');

/**
 * Log function for debugging (only in WP_DEBUG mode).
 *
 * @param mixed  $message Message to log.
 * @param string $level   Log level (info, warning, error).
 */
function wphc_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }
        
        $log_message = sprintf(
            '[%s] WPHC %s: %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        
        error_log($log_message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

/**
 * Get plugin option with default value.
 *
 * @param string $option_name Option name.
 * @param mixed  $default     Default value.
 * @return mixed Option value or default.
 */
function wphc_get_option($option_name, $default = false) {
    return get_option('wphc_' . $option_name, $default);
}

/**
 * Update plugin option.
 *
 * @param string $option_name Option name.
 * @param mixed  $value       Option value.
 * @return bool True if updated, false otherwise.
 */
function wphc_update_option($option_name, $value) {
    return update_option('wphc_' . $option_name, $value);
}

/**
 * Delete plugin option.
 *
 * @param string $option_name Option name.
 * @return bool True if deleted, false otherwise.
 */
function wphc_delete_option($option_name) {
    return delete_option('wphc_' . $option_name);
}

/**
 * Check if current user can manage plugin settings.
 *
 * @return bool True if user can manage settings.
 */
function wphc_current_user_can_manage() {
    return current_user_can('manage_woocommerce');
}

/**
 * Get formatted currency symbol for display.
 *
 * @return string Currency symbol.
 */
function wphc_get_currency_symbol() {
    return get_woocommerce_currency_symbol();
}

/**
 * Format price for display with currency.
 *
 * @param float $price Price to format.
 * @return string Formatted price.
 */
function wphc_format_price($price) {
    return wc_price($price);
}

/**
 * Get current timestamp in WordPress timezone.
 *
 * @return int Current timestamp.
 */
function wphc_current_time() {
    return current_time('timestamp');
}

/**
 * Check if plugin is in development mode.
 *
 * @return bool True if in development mode.
 */
function wphc_is_development_mode() {
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Plugin initialization check - runs once after activation.
 */
function wphc_maybe_initialize() {
    $initialized = get_option('wphc_initialized', false);
    
    if (!$initialized) {
        // Set default options
        $defaults = array(
            'enable_price_tracking' => true,
            'enable_compliance_display' => true,
            'enable_price_history_chart' => false,
            'price_history_days' => 30,
            'chart_type' => 'line',
            'compliance_message' => esc_html__('Lowest price in the last 30 days: {price}', 'woocommerce-price-history-compliance'),
            'enable_admin_alerts' => true,
            'alert_email' => get_option('admin_email'),
        );
        
        foreach ($defaults as $key => $value) {
            wphc_update_option($key, $value);
        }
        
        update_option('wphc_initialized', true);
        
        wphc_log('Plugin initialized with default settings');
    }
}
add_action('admin_init', 'wphc_maybe_initialize');
<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 * Note: This does not delete data - that happens during uninstall.
 *
 * @since      1.0.0
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 * @author     Your Name <email@example.com>
 */
class WPHC_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * Performs cleanup tasks when the plugin is deactivated:
     * - Clears scheduled cron jobs
     * - Flushes rewrite rules
     * - Clears plugin caches
     * - Logs deactivation event
     * - Preserves user data and settings for potential reactivation
     *
     * Note: This method does NOT delete user data, settings, or database tables.
     * Data deletion only occurs during uninstall via uninstall.php
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_scheduled_events();
        
        // Flush rewrite rules to clean up any custom endpoints
        self::flush_rewrite_rules();
        
        // Clear plugin caches and transients
        self::clear_plugin_caches();
        
        // Clear WooCommerce cache entries related to our plugin
        self::clear_woocommerce_caches();
        
        // Set deactivation timestamp
        update_option('wphc_deactivation_time', current_time('timestamp'));
        update_option('wphc_plugin_active', false);
        
        // Log deactivation
        wphc_log('Plugin deactivated successfully', 'info');
        
        // Send deactivation notification if enabled
        self::send_deactivation_notification();
        
        // Perform final cleanup
        self::final_cleanup();
    }

    /**
     * Clear all scheduled cron events related to the plugin.
     *
     * @since 1.0.0
     */
    private static function clear_scheduled_events() {
        $cron_hooks = array(
            'wphc_daily_cleanup',
            'wphc_hourly_validation',
            'wphc_weekly_maintenance',
            'wphc_price_tracking_job',
            'wphc_compliance_check',
            'wphc_database_optimization',
            'wphc_alert_notifications'
        );

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                wphc_log("Cleared scheduled event: {$hook}", 'info');
            }
        }

        // Clear any recurring schedules
        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        wphc_log('All scheduled cron events cleared', 'info');
    }

    /**
     * Flush rewrite rules to clean up any custom endpoints.
     *
     * @since 1.0.0
     */
    private static function flush_rewrite_rules() {
        // Flush rewrite rules without hard refresh
        flush_rewrite_rules(false);
        
        wphc_log('Rewrite rules flushed', 'info');
    }

    /**
     * Clear plugin-specific caches and transients.
     *
     * @since 1.0.0
     */
    private static function clear_plugin_caches() {
        global $wpdb;

        // Clear plugin-specific transients
        // Necessary for transient cleanup - no WP API available for bulk transient deletion
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wphc_%' OR option_name LIKE '_transient_timeout_wphc_%'"
        );

        if ($deleted !== false) {
            wphc_log("Cleared {$deleted} plugin transients", 'info');
        }

        // Clear object cache entries
        $cache_keys = array(
            'wphc_settings',
            'wphc_product_prices',
            'wphc_compliance_data',
            'wphc_chart_data',
            'wphc_reports_data'
        );

        foreach ($cache_keys as $cache_key) {
            wp_cache_delete($cache_key, 'wphc');
        }

        // Clear WordPress object cache
        wp_cache_flush();
        
        wphc_log('Plugin caches cleared', 'info');
    }

    /**
     * Clear WooCommerce-related caches that might contain our plugin data.
     *
     * @since 1.0.0
     */
    private static function clear_woocommerce_caches() {
        // Only proceed if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Clear WooCommerce transients
        if (function_exists('wc_delete_product_transients')) {
            // Get all products and clear their transients
            $product_ids = get_posts(array(
                'post_type' => 'product',
                'numberposts' => -1,
                'post_status' => 'any',
                'fields' => 'ids'
            ));

            foreach ($product_ids as $product_id) {
                wc_delete_product_transients($product_id);
            }
        }

        // Clear WooCommerce cache groups
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('woocommerce');
            wp_cache_delete_group('wc_session_id');
        }

        // Clear specific WooCommerce transients that might contain our data
        $wc_transients = array(
            'wc_products_onsale',
            'wc_var_prices',
            'wc_product_loop',
            'woocommerce_cache_excluded_uris'
        );

        foreach ($wc_transients as $transient) {
            delete_transient($transient);
        }

        wphc_log('WooCommerce caches cleared', 'info');
    }

    /**
     * Send deactivation notification to admin if enabled.
     *
     * @since 1.0.0
     */
    private static function send_deactivation_notification() {
        $send_notifications = wphc_get_option('enable_admin_alerts', true);
        $admin_email = wphc_get_option('alert_email', get_option('admin_email'));

        if (!$send_notifications || !$admin_email) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            esc_html__('[%s] WooCommerce Price History & Sale Compliance Plugin Deactivated', 'woocommerce-price-history-compliance'),
            get_bloginfo('name')
        );

        $message = sprintf(
            /* translators: %1$s: Site name, %2$s: Site URL, %3$s: Deactivation time */
            esc_html__('The WooCommerce Price History & Sale Compliance plugin has been deactivated on %1$s (%2$s) at %3$s.

This is an automated notification. Your price history data and settings have been preserved and will be restored if you reactivate the plugin.

If this deactivation was unexpected, please check your site immediately.

---
WooCommerce Price History & Sale Compliance Plugin', 'woocommerce-price-history-compliance'),
            get_bloginfo('name'),
            get_site_url(),
            current_time('Y-m-d H:i:s')
        );

        // Send email notification
        $sent = wp_mail(
            sanitize_email($admin_email),
            $subject,
            $message,
            array('Content-Type: text/plain; charset=UTF-8')
        );

        if ($sent) {
            wphc_log('Deactivation notification sent to admin', 'info');
        } else {
            wphc_log('Failed to send deactivation notification', 'warning');
        }
    }

    /**
     * Perform final cleanup tasks.
     *
     * @since 1.0.0
     */
    private static function final_cleanup() {
        // Remove temporary files if any exist
        self::cleanup_temporary_files();
        
        // Clear any remaining plugin-specific options that shouldn't persist
        self::clear_temporary_options();
        
        // Set final deactivation flag
        update_option('wphc_clean_deactivation', true);
        
        wphc_log('Final cleanup completed', 'info');
    }

    /**
     * Clean up temporary files created by the plugin.
     *
     * @since 1.0.0
     */
    private static function cleanup_temporary_files() {
        $upload_dir = wp_upload_dir();
        $plugin_temp_dir = $upload_dir['basedir'] . '/wphc-temp/';

        if (is_dir($plugin_temp_dir)) {
            // Remove all files in the temporary directory
            $files = glob($plugin_temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }

            // Remove the directory if empty
            if (is_dir($plugin_temp_dir) && count(scandir($plugin_temp_dir)) === 2) {
                rmdir($plugin_temp_dir);
            }

            wphc_log('Temporary files cleaned up', 'info');
        }
    }

    /**
     * Clear temporary options that shouldn't persist after deactivation.
     *
     * @since 1.0.0
     */
    private static function clear_temporary_options() {
        $temporary_options = array(
            'wphc_last_cron_run',
            'wphc_processing_lock',
            'wphc_maintenance_mode',
            'wphc_current_batch',
            'wphc_temp_settings',
            'wphc_migration_status',
            'wphc_cache_invalidated'
        );

        foreach ($temporary_options as $option) {
            delete_option($option);
        }

        wphc_log('Temporary options cleared', 'info');
    }

    /**
     * Preserve important data during deactivation.
     *
     * This method ensures that critical user data and settings are preserved
     * for potential reactivation.
     *
     * @since 1.0.0
     */
    private static function preserve_user_data() {
        // Create backup of critical settings
        $critical_settings = array(
            'wphc_enable_plugin',
            'wphc_enable_price_tracking',
            'wphc_enable_compliance_display',
            'wphc_compliance_message',
            'wphc_alert_email',
            'wphc_price_history_days',
            'wphc_chart_type'
        );

        $backup_data = array();
        foreach ($critical_settings as $setting) {
            $backup_data[$setting] = get_option($setting);
        }

        // Store backup
        update_option('wphc_settings_backup', $backup_data);
        update_option('wphc_backup_created', current_time('timestamp'));

        wphc_log('User data preserved for potential reactivation', 'info');
    }

    /**
     * Check if deactivation should be prevented.
     *
     * This method can be used to prevent deactivation under certain conditions,
     * such as when critical operations are in progress.
     *
     * @since 1.0.0
     * @return bool True if deactivation should be prevented.
     */
    public static function should_prevent_deactivation() {
        // Check if critical operations are in progress
        $maintenance_mode = get_option('wphc_maintenance_mode', false);
        $processing_lock = get_option('wphc_processing_lock', false);
        
        if ($maintenance_mode || $processing_lock) {
            return true;
        }

        // Check if there are pending cron jobs that shouldn't be interrupted
        $critical_jobs = array(
            'wphc_database_migration',
            'wphc_data_export'
        );

        foreach ($critical_jobs as $job) {
            if (wp_next_scheduled($job)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle forced deactivation when prevention is overridden.
     *
     * @since 1.0.0
     */
    public static function handle_forced_deactivation() {
        // Log forced deactivation
        wphc_log('Plugin deactivation was forced despite active processes', 'warning');
        
        // Clear any locks that might prevent cleanup
        delete_option('wphc_maintenance_mode');
        delete_option('wphc_processing_lock');
        
        // Proceed with normal deactivation
        self::deactivate();
    }

    /**
     * Get deactivation status and statistics.
     *
     * @since 1.0.0
     * @return array Deactivation status information.
     */
    public static function get_deactivation_status() {
        return array(
            'deactivated_at' => get_option('wphc_deactivation_time'),
            'clean_deactivation' => get_option('wphc_clean_deactivation', false),
            'settings_preserved' => (false !== get_option('wphc_settings_backup', false)),
            'cron_jobs_cleared' => !wp_next_scheduled('wphc_daily_cleanup'),
            'caches_cleared' => true, // Always true after deactivation
        );
    }

    /**
     * Verify deactivation was successful.
     *
     * @since 1.0.0
     * @return bool True if deactivation was successful.
     */
    public static function verify_deactivation() {
        // Check if cron jobs were cleared
        $cron_hooks = array(
            'wphc_daily_cleanup',
            'wphc_hourly_validation',
            'wphc_weekly_maintenance'
        );

        foreach ($cron_hooks as $hook) {
            if (wp_next_scheduled($hook)) {
                wphc_log("Cron job still scheduled after deactivation: {$hook}", 'warning');
                return false;
            }
        }

        // Check if deactivation timestamp was set
        $deactivation_time = get_option('wphc_deactivation_time');
        if (!$deactivation_time) {
            wphc_log('Deactivation timestamp not set', 'warning');
            return false;
        }

        // Check if clean deactivation flag was set
        $clean_deactivation = get_option('wphc_clean_deactivation', false);
        if (!$clean_deactivation) {
            wphc_log('Clean deactivation flag not set', 'warning');
            return false;
        }

        wphc_log('Plugin deactivation verification successful', 'info');
        return true;
    }

    /**
     * Prepare for potential reactivation.
     *
     * This method sets up data structures that will help with smooth reactivation.
     *
     * @since 1.0.0
     */
    private static function prepare_for_reactivation() {
        // Store current plugin version for version comparison on reactivation
        update_option('wphc_last_active_version', WPHC_VERSION);
        
        // Store current WooCommerce version for compatibility checking
        if (defined('WC_VERSION')) {
            update_option('wphc_last_wc_version', WC_VERSION);
        }
        
        // Store deactivation context
        update_option('wphc_deactivation_context', array(
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'active_plugins' => get_option('active_plugins', array()),
            'active_theme' => get_option('stylesheet'),
        ));

        wphc_log('Prepared data for potential reactivation', 'info');
    }
}
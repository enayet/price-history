<?php
/**
 * Admin settings class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        add_action( 'wp_ajax_wc_price_history_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_wc_price_history_reset_data', array( $this, 'ajax_reset_data' ) );
        add_action( 'wp_ajax_wc_price_history_initialize_products', array( $this, 'ajax_initialize_products' ) );
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

        // Localize script
        wp_localize_script( 'wc-price-history-admin', 'wcPriceHistoryAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_price_history_admin' ),
            'i18n' => array(
                'saving' => esc_html__( 'Saving...', 'wc-price-history-compliance' ),
                'saved' => esc_html__( 'Settings saved!', 'wc-price-history-compliance' ),
                'error' => esc_html__( 'Error saving settings.', 'wc-price-history-compliance' ),
                'confirm_reset' => esc_html__( 'Are you sure you want to reset all data? This cannot be undone.', 'wc-price-history-compliance' ),
                'confirm_initialize' => esc_html__( 'This will create price history records for all existing products. Continue?', 'wc-price-history-compliance' )
            )
        ) );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting( 'wc_price_history_settings', 'wc_price_history_options' );
        
        // Add settings sections
        add_settings_section(
            'wc_price_history_general',
            esc_html__( 'General Settings', 'wc-price-history-compliance' ),
            array( $this, 'general_section_callback' ),
            'wc_price_history_settings'
        );

        add_settings_section(
            'wc_price_history_display',
            esc_html__( 'Display Settings', 'wc-price-history-compliance' ),
            array( $this, 'display_section_callback' ),
            'wc_price_history_settings'
        );

        add_settings_section(
            'wc_price_history_chart',
            esc_html__( 'Chart Settings', 'wc-price-history-compliance' ),
            array( $this, 'chart_section_callback' ),
            'wc_price_history_settings'
        );

        add_settings_section(
            'wc_price_history_compliance',
            esc_html__( 'Compliance Settings', 'wc-price-history-compliance' ),
            array( $this, 'compliance_section_callback' ),
            'wc_price_history_settings'
        );
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__( 'Configure the basic plugin settings.', 'wc-price-history-compliance' ) . '</p>';
    }

    /**
     * Display section callback
     */
    public function display_section_callback() {
        echo '<p>' . esc_html__( 'Customize how price history information is displayed to customers.', 'wc-price-history-compliance' ) . '</p>';
    }

    /**
     * Chart section callback
     */
    public function chart_section_callback() {
        echo '<p>' . esc_html__( 'Configure the price history chart appearance and behavior.', 'wc-price-history-compliance' ) . '</p>';
    }

    /**
     * Compliance section callback
     */
    public function compliance_section_callback() {
        echo '<p>' . esc_html__( 'Settings for EU Omnibus Directive compliance and alerts.', 'wc-price-history-compliance' ) . '</p>';
    }

    /**
     * Admin settings page
     */
    public function admin_page() {
        $plugin = WC_Price_History_Compliance::get_instance();
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        ?>
        <div class="wrap wc-price-history-admin">
            <h1><?php echo esc_html__( 'WooCommerce Price History & Sale Compliance Settings', 'wc-price-history-compliance' ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wc-price-history-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'General', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-settings&tab=display" class="nav-tab <?php echo 'display' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Display', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-settings&tab=chart" class="nav-tab <?php echo 'chart' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Charts', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-settings&tab=compliance" class="nav-tab <?php echo 'compliance' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Compliance', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-settings&tab=tools" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Tools', 'wc-price-history-compliance' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'display':
                        $this->render_display_tab();
                        break;
                    case 'chart':
                        $this->render_chart_tab();
                        break;
                    case 'compliance':
                        $this->render_compliance_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    default:
                        $this->render_general_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab() {
        $plugin = WC_Price_History_Compliance::get_instance();
        ?>
        <form method="post" action="" id="wc-price-history-settings-form">
            <?php wp_nonce_field( 'wc_price_history_settings_save', 'wc_price_history_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Enable Plugin', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="enable_plugin">
                            <input type="checkbox" name="enable_plugin" id="enable_plugin" value="yes" <?php checked( 'yes', $plugin->get_option( 'enable_plugin' ) ); ?> />
                            <?php echo esc_html__( 'Enable price history tracking and compliance features', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Turn this off to completely disable the plugin functionality.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
                
                <tr class="plugin-dependent">
                    <th scope="row"><?php echo esc_html__( 'Data Retention', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <select name="data_retention_days">
                            <option value="90" <?php selected( 90, $plugin->get_option( 'data_retention_days', 365 ) ); ?>>90 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                            <option value="180" <?php selected( 180, $plugin->get_option( 'data_retention_days', 365 ) ); ?>>180 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                            <option value="365" <?php selected( 365, $plugin->get_option( 'data_retention_days', 365 ) ); ?>>1 <?php echo esc_html__( 'year', 'wc-price-history-compliance' ); ?></option>
                            <option value="730" <?php selected( 730, $plugin->get_option( 'data_retention_days', 365 ) ); ?>>2 <?php echo esc_html__( 'years', 'wc-price-history-compliance' ); ?></option>
                            <option value="-1" <?php selected( -1, $plugin->get_option( 'data_retention_days', 365 ) ); ?>><?php echo esc_html__( 'Forever', 'wc-price-history-compliance' ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__( 'How long to keep price history data.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr class="plugin-dependent">
                    <th scope="row"><?php echo esc_html__( 'Track Product Types', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <?php
                        $product_types = wc_get_product_types();
                        $tracked_types = $plugin->get_option( 'tracked_product_types', array( 'simple', 'variable' ) );
                        foreach ( $product_types as $type => $label ) :
                        ?>
                        <label>
                            <input type="checkbox" name="tracked_product_types[]" value="<?php echo esc_attr( $type ); ?>" <?php echo in_array( $type, $tracked_types, true ) ? 'checked' : ''; ?> />
                            <?php echo esc_html( $label ); ?>
                        </label><br>
                        <?php endforeach; ?>
                        <p class="description"><?php echo esc_html__( 'Select which product types to track price history for.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render display settings tab
     */
    private function render_display_tab() {
        $plugin = WC_Price_History_Compliance::get_instance();
        ?>
        <form method="post" action="" id="wc-price-history-settings-form">
            <?php wp_nonce_field( 'wc_price_history_settings_save', 'wc_price_history_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Display Lowest Price', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="display_lowest_price">
                            <input type="checkbox" name="display_lowest_price" id="display_lowest_price" value="yes" <?php checked( 'yes', $plugin->get_option( 'display_lowest_price' ) ); ?> />
                            <?php echo esc_html__( 'Show the lowest price in the last 30 days during sales', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Required for EU Omnibus Directive compliance.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
                
                <tr class="price-display-dependent">
                    <th scope="row"><?php echo esc_html__( 'Lowest Price Message', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <input type="text" name="lowest_price_text" value="<?php echo esc_attr( $plugin->get_option( 'lowest_price_text' ) ); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__( 'Use %s as placeholder for the price. Example: "Lowest price in the last 30 days: %s"', 'wc-price-history-compliance' ); ?></p>
                        
                        <div class="settings-preview">
                            <h4><?php echo esc_html__( 'Preview:', 'wc-price-history-compliance' ); ?></h4>
                            <div class="preview-lowest-price"><?php echo esc_html__( 'Lowest price in the last 30 days: $99.99', 'wc-price-history-compliance' ); ?></div>
                        </div>
                    </td>
                </tr>

                <tr class="price-display-dependent">
                    <th scope="row"><?php echo esc_html__( 'Display Position', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <select name="display_position">
                            <option value="25" <?php selected( 25, $plugin->get_option( 'display_position', 25 ) ); ?>><?php echo esc_html__( 'After price', 'wc-price-history-compliance' ); ?></option>
                            <option value="15" <?php selected( 15, $plugin->get_option( 'display_position', 25 ) ); ?>><?php echo esc_html__( 'Before add to cart', 'wc-price-history-compliance' ); ?></option>
                            <option value="35" <?php selected( 35, $plugin->get_option( 'display_position', 25 ) ); ?>><?php echo esc_html__( 'After add to cart', 'wc-price-history-compliance' ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Where to display the lowest price message on product pages.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr class="price-display-dependent">
                    <th scope="row"><?php echo esc_html__( 'Show in Product Loops', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="show_in_loops">
                            <input type="checkbox" name="show_in_loops" id="show_in_loops" value="yes" <?php checked( 'yes', $plugin->get_option( 'show_in_loops' ) ); ?> />
                            <?php echo esc_html__( 'Show lowest price info in shop and category pages', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Display price history information in product listings.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render chart settings tab
     */
    private function render_chart_tab() {
        $plugin = WC_Price_History_Compliance::get_instance();
        ?>
        <form method="post" action="" id="wc-price-history-settings-form">
            <?php wp_nonce_field( 'wc_price_history_settings_save', 'wc_price_history_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Show Price Chart', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="show_price_chart">
                            <input type="checkbox" name="show_price_chart" id="show_price_chart" value="yes" <?php checked( 'yes', $plugin->get_option( 'show_price_chart' ) ); ?> />
                            <?php echo esc_html__( 'Display price history chart on product pages', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Show visual price history to build customer trust.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
                
                <tr class="chart-dependent">
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

                <tr class="chart-dependent">
                    <th scope="row"><?php echo esc_html__( 'Chart Type', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <select name="chart_type">
                            <option value="line" <?php selected( 'line', $plugin->get_option( 'chart_type', 'line' ) ); ?>><?php echo esc_html__( 'Line Chart', 'wc-price-history-compliance' ); ?></option>
                            <option value="bar" <?php selected( 'bar', $plugin->get_option( 'chart_type', 'line' ) ); ?>><?php echo esc_html__( 'Bar Chart', 'wc-price-history-compliance' ); ?></option>
                            <option value="area" <?php selected( 'area', $plugin->get_option( 'chart_type', 'line' ) ); ?>><?php echo esc_html__( 'Area Chart', 'wc-price-history-compliance' ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Visual style for the price history chart.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr class="chart-dependent">
                    <th scope="row"><?php echo esc_html__( 'Chart Colors', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <?php
                        $chart_colors = $plugin->get_option( 'chart_colors', array( 'primary' => '#007cba', 'secondary' => '#50575e' ) );
                        ?>
                        <div class="color-picker-container">
                            <label for="chart_color_primary"><?php echo esc_html__( 'Primary Color:', 'wc-price-history-compliance' ); ?></label>
                            <input type="text" name="chart_colors[primary]" id="chart_color_primary" value="<?php echo esc_attr( $chart_colors['primary'] ); ?>" class="color-picker" />
                            <div class="color-preview" style="background-color: <?php echo esc_attr( $chart_colors['primary'] ); ?>"></div>
                        </div>
                        <br>
                        <div class="color-picker-container">
                            <label for="chart_color_secondary"><?php echo esc_html__( 'Secondary Color:', 'wc-price-history-compliance' ); ?></label>
                            <input type="text" name="chart_colors[secondary]" id="chart_color_secondary" value="<?php echo esc_attr( $chart_colors['secondary'] ); ?>" class="color-picker" />
                            <div class="color-preview" style="background-color: <?php echo esc_attr( $chart_colors['secondary'] ); ?>"></div>
                        </div>
                        <p class="description"><?php echo esc_html__( 'Customize chart colors to match your theme.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr class="chart-dependent">
                    <th scope="row"><?php echo esc_html__( 'Chart Height', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <input type="number" name="chart_height" value="<?php echo esc_attr( $plugin->get_option( 'chart_height', 200 ) ); ?>" min="150" max="500" step="10" />
                        <span><?php echo esc_html__( 'pixels', 'wc-price-history-compliance' ); ?></span>
                        <p class="description"><?php echo esc_html__( 'Height of the price history chart.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render compliance settings tab
     */
    private function render_compliance_tab() {
        $plugin = WC_Price_History_Compliance::get_instance();
        ?>
        <form method="post" action="" id="wc-price-history-settings-form">
            <?php wp_nonce_field( 'wc_price_history_settings_save', 'wc_price_history_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Enable Compliance Alerts', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="enable_alerts">
                            <input type="checkbox" name="enable_alerts" id="enable_alerts" value="yes" <?php checked( 'yes', $plugin->get_option( 'enable_alerts' ) ); ?> />
                            <?php echo esc_html__( 'Get notified when products may not be compliant', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Receive alerts when sale prices are higher than the lowest price in 30 days.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Compliance Period', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <select name="compliance_days">
                            <option value="30" <?php selected( 30, $plugin->get_option( 'compliance_days', 30 ) ); ?>>30 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                            <option value="60" <?php selected( 60, $plugin->get_option( 'compliance_days', 30 ) ); ?>>60 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                            <option value="90" <?php selected( 90, $plugin->get_option( 'compliance_days', 30 ) ); ?>>90 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Period to check for lowest price compliance (EU requires 30 days).', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Email Notifications', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="email_notifications">
                            <input type="checkbox" name="email_notifications" id="email_notifications" value="yes" <?php checked( 'yes', $plugin->get_option( 'email_notifications' ) ); ?> />
                            <?php echo esc_html__( 'Send email notifications for compliance issues', 'wc-price-history-compliance' ); ?>
                        </label>
                        <br><br>
                        <label for="notification_email"><?php echo esc_html__( 'Notification Email:', 'wc-price-history-compliance' ); ?></label>
                        <input type="email" name="notification_email" id="notification_email" value="<?php echo esc_attr( $plugin->get_option( 'notification_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__( 'Email address to receive compliance notifications.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Automatic Compliance Check', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <label for="auto_compliance_check">
                            <input type="checkbox" name="auto_compliance_check" id="auto_compliance_check" value="yes" <?php checked( 'yes', $plugin->get_option( 'auto_compliance_check' ) ); ?> />
                            <?php echo esc_html__( 'Automatically check compliance when prices are updated', 'wc-price-history-compliance' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Warn administrators immediately when non-compliant prices are set.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render tools tab
     */
    private function render_tools_tab() {
        $database = new WC_Price_History_Database();
        
        // Get some statistics
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $unique_products = $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table_name}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $recent_records = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE price_date >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) );
        ?>
        <div class="tools-section">
            <h3><?php echo esc_html__( 'Database Statistics', 'wc-price-history-compliance' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html__( 'Total Price Records:', 'wc-price-history-compliance' ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $total_records ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__( 'Products with Price History:', 'wc-price-history-compliance' ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $unique_products ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__( 'Records from Last 30 Days:', 'wc-price-history-compliance' ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $recent_records ) ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="tools-section">
            <h3><?php echo esc_html__( 'Maintenance Tools', 'wc-price-history-compliance' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Initialize Existing Products', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <button type="button" class="button" id="initialize-products">
                            <?php echo esc_html__( 'Initialize Price History', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Create initial price history records for all existing products that don\'t have any history yet.', 'wc-price-history-compliance' ); ?></p>
                        <div id="initialize-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p class="progress-text"><?php echo esc_html__( 'Initializing...', 'wc-price-history-compliance' ); ?></p>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Clean Old Records', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <button type="button" class="button" id="cleanup-records">
                            <?php echo esc_html__( 'Clean Up Old Data', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Remove price history records older than the configured retention period.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Export Data', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <button type="button" class="button" id="export-data">
                            <?php echo esc_html__( 'Export All Data', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Export all price history data to CSV format.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Reset All Data', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="reset-data">
                            <?php echo esc_html__( 'Reset All Data', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Warning: This will permanently delete all price history data. This action cannot be undone.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tools-section">
            <h3><?php echo esc_html__( 'Import/Export Settings', 'wc-price-history-compliance' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Export Settings', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <button type="button" class="button" id="export-settings">
                            <?php echo esc_html__( 'Export Settings', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Download current plugin settings as a JSON file.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Import Settings', 'wc-price-history-compliance' ); ?></th>
                    <td>
                        <input type="file" id="import-settings-file" accept=".json" />
                        <button type="button" class="button" id="import-settings">
                            <?php echo esc_html__( 'Import Settings', 'wc-price-history-compliance' ); ?>
                        </button>
                        <p class="description"><?php echo esc_html__( 'Import plugin settings from a JSON file.', 'wc-price-history-compliance' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Save settings via AJAX
     */
    public function ajax_save_settings() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_admin' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        // Process and save settings
        $plugin = WC_Price_History_Compliance::get_instance();
        
        $settings = array(
            'enable_plugin',
            'display_lowest_price',
            'show_price_chart',
            'lowest_price_text',
            'chart_days',
            'chart_type',
            'chart_height',
            'enable_alerts',
            'compliance_days',
            'email_notifications',
            'notification_email',
            'auto_compliance_check',
            'data_retention_days',
            'display_position',
            'show_in_loops'
        );

        foreach ( $settings as $setting ) {
            if ( isset( $_POST[ $setting ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $setting ] ) );
                $plugin->update_option( $setting, $value );
            } else {
                // Handle checkboxes that might not be set
                if ( in_array( $setting, array( 'enable_plugin', 'display_lowest_price', 'show_price_chart', 'enable_alerts', 'email_notifications', 'auto_compliance_check', 'show_in_loops' ), true ) ) {
                    $plugin->update_option( $setting, 'no' );
                }
            }
        }

        // Handle array settings
        if ( isset( $_POST['chart_colors'] ) && is_array( $_POST['chart_colors'] ) ) {
            $chart_colors = array();
            foreach ( $_POST['chart_colors'] as $key => $color ) {
                $chart_colors[ sanitize_key( $key ) ] = sanitize_hex_color( $color );
            }
            $plugin->update_option( 'chart_colors', $chart_colors );
        }

        if ( isset( $_POST['tracked_product_types'] ) && is_array( $_POST['tracked_product_types'] ) ) {
            $tracked_types = array();
            foreach ( $_POST['tracked_product_types'] as $type ) {
                $tracked_types[] = sanitize_text_field( $type );
            }
            $plugin->update_option( 'tracked_product_types', $tracked_types );
        }

        wp_send_json_success( esc_html__( 'Settings saved successfully!', 'wc-price-history-compliance' ) );
    }

    /**
     * Reset all data via AJAX
     */
    public function ajax_reset_data() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_admin' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );
        
        if ( false === $result ) {
            wp_send_json_error( esc_html__( 'Error resetting data.', 'wc-price-history-compliance' ) );
        }

        wp_send_json_success( esc_html__( 'All data has been reset successfully.', 'wc-price-history-compliance' ) );
    }

    /**
     * Initialize products via AJAX
     */
    public function ajax_initialize_products() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_admin' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        $tracker = new WC_Price_History_Tracker();
        $tracker->initialize_existing_products();

        wp_send_json_success( esc_html__( 'Products initialized successfully.', 'wc-price-history-compliance' ) );
    }
}
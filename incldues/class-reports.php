<?php
/**
 * Reports class
 *
 * @package WC_Price_History_Compliance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        add_action( 'wp_ajax_wc_price_history_export_pdf', array( $this, 'export_pdf' ) );
        add_action( 'admin_init', array( $this, 'handle_alerts' ) );
        add_action( 'admin_notices', array( $this, 'show_compliance_notices' ) );
        add_action( 'wp_ajax_wc_price_history_dismiss_alert', array( $this, 'dismiss_alert' ) );
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
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'recent_changes';
        
        // Handle filter parameters
        $days_filter = isset( $_GET['days'] ) ? intval( $_GET['days'] ) : 30;
        $product_filter = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
        $type_filter = isset( $_GET['price_type'] ) ? sanitize_text_field( wp_unslash( $_GET['price_type'] ) ) : '';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Price History Reports', 'wc-price-history-compliance' ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wc-price-history-reports&tab=recent_changes" class="nav-tab <?php echo 'recent_changes' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Recent Changes', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-reports&tab=compliance" class="nav-tab <?php echo 'compliance' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Compliance', 'wc-price-history-compliance' ); ?>
                </a>
                <a href="?page=wc-price-history-reports&tab=statistics" class="nav-tab <?php echo 'statistics' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Statistics', 'wc-price-history-compliance' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'recent_changes':
                        $this->render_recent_changes_tab( $days_filter, $product_filter, $type_filter );
                        break;
                    case 'compliance':
                        $this->render_compliance_tab();
                        break;
                    case 'statistics':
                        $this->render_statistics_tab();
                        break;
                    default:
                        $this->render_recent_changes_tab( $days_filter, $product_filter, $type_filter );
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent changes tab
     */
    private function render_recent_changes_tab( $days_filter, $product_filter, $type_filter ) {
        $database = new WC_Price_History_Database();
        $recent_changes = $this->get_filtered_recent_changes( $days_filter, $product_filter, $type_filter );
        
        ?>
        <div class="wc-price-history-reports">
            <div class="report-filters">
                <form method="get" id="reports-filters">
                    <input type="hidden" name="page" value="wc-price-history-reports" />
                    <input type="hidden" name="tab" value="recent_changes" />
                    
                    <label for="days"><?php echo esc_html__( 'Days:', 'wc-price-history-compliance' ); ?></label>
                    <select name="days" id="days">
                        <option value="7" <?php selected( 7, $days_filter ); ?>>7 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                        <option value="30" <?php selected( 30, $days_filter ); ?>>30 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                        <option value="60" <?php selected( 60, $days_filter ); ?>>60 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                        <option value="90" <?php selected( 90, $days_filter ); ?>>90 <?php echo esc_html__( 'days', 'wc-price-history-compliance' ); ?></option>
                    </select>
                    
                    <label for="price_type"><?php echo esc_html__( 'Type:', 'wc-price-history-compliance' ); ?></label>
                    <select name="price_type" id="price_type">
                        <option value="" <?php selected( '', $type_filter ); ?>><?php echo esc_html__( 'All Types', 'wc-price-history-compliance' ); ?></option>
                        <option value="regular" <?php selected( 'regular', $type_filter ); ?>><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></option>
                        <option value="sale" <?php selected( 'sale', $type_filter ); ?>><?php echo esc_html__( 'Sale Price', 'wc-price-history-compliance' ); ?></option>
                    </select>
                    
                    <input type="submit" class="button" value="<?php echo esc_attr__( 'Filter', 'wc-price-history-compliance' ); ?>" />
                </form>
            </div>

            <div class="report-section">
                <h2><?php echo esc_html__( 'Recent Price Changes', 'wc-price-history-compliance' ); ?></h2>
                
                <div class="report-actions">
                    <button type="button" class="button" onclick="exportToCSV()">
                        <?php echo esc_html__( 'Export to CSV', 'wc-price-history-compliance' ); ?>
                    </button>
                    <button type="button" class="button" onclick="exportToPDF()">
                        <?php echo esc_html__( 'Export to PDF', 'wc-price-history-compliance' ); ?>
                    </button>
                </div>
                
                <table class="wp-list-table widefat fixed striped wc-price-history-table">
                    <thead>
                        <tr>
                            <th class="column-product"><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Sale Price', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Price Type', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Date Changed', 'wc-price-history-compliance' ); ?></th>
                            <th class="column-compliance"><?php echo esc_html__( 'Compliance Status', 'wc-price-history-compliance' ); ?></th>
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
                                        <br><small>ID: <?php echo esc_html( $change->product_id ); ?></small>
                                    </td>
                                    <td><?php echo $change->regular_price ? wc_price( $change->regular_price ) : '—'; ?></td>
                                    <td><?php echo $change->sale_price ? wc_price( $change->sale_price ) : '—'; ?></td>
                                    <td>
                                        <span class="price-type-<?php echo esc_attr( $change->price_type ); ?>">
                                            <?php echo esc_html( ucfirst( $change->price_type ) ); ?>
                                        </span>
                                    </td>
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
                                <td colspan="6"><?php echo esc_html__( 'No price changes found for the selected criteria.', 'wc-price-history-compliance' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'wc_price_history_export_csv');
            params.set('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'wc_price_history_export' ) ); ?>');
            window.location.href = ajaxurl + '?' + params.toString();
        }
        
        function exportToPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'wc_price_history_export_pdf');
            params.set('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'wc_price_history_export' ) ); ?>');
            window.location.href = ajaxurl + '?' + params.toString();
        }
        </script>
        <?php
    }

    /**
     * Render compliance tab
     */
    private function render_compliance_tab() {
        $alerts = $this->get_compliance_alerts();
        $products_on_sale = $this->get_products_on_sale_analysis();
        
        ?>
        <div class="wc-price-history-reports">
            <div class="report-section">
                <h2><?php echo esc_html__( 'Compliance Overview', 'wc-price-history-compliance' ); ?></h2>
                
                <div class="compliance-summary">
                    <div class="summary-card">
                        <h3><?php echo esc_html( count( $products_on_sale ) ); ?></h3>
                        <p><?php echo esc_html__( 'Products on Sale', 'wc-price-history-compliance' ); ?></p>
                    </div>
                    <div class="summary-card alert">
                        <h3><?php echo esc_html( count( $alerts ) ); ?></h3>
                        <p><?php echo esc_html__( 'Compliance Issues', 'wc-price-history-compliance' ); ?></p>
                    </div>
                    <div class="summary-card success">
                        <h3><?php echo esc_html( count( $products_on_sale ) - count( $alerts ) ); ?></h3>
                        <p><?php echo esc_html__( 'Compliant Products', 'wc-price-history-compliance' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h2><?php echo esc_html__( 'Compliance Alerts', 'wc-price-history-compliance' ); ?></h2>
                <?php $this->display_compliance_alerts( $alerts ); ?>
            </div>

            <div class="report-section">
                <h2><?php echo esc_html__( 'Products on Sale Analysis', 'wc-price-history-compliance' ); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Current Sale Price', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Lowest Price (30 days)', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Compliance', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Actions', 'wc-price-history-compliance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $products_on_sale as $product_data ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $product_data['product']->get_id() ) ); ?>">
                                        <?php echo esc_html( $product_data['product']->get_name() ); ?>
                                    </a>
                                </td>
                                <td><?php echo wc_price( $product_data['sale_price'] ); ?></td>
                                <td><?php echo $product_data['lowest_price'] ? wc_price( $product_data['lowest_price'] ) : '—'; ?></td>
                                <td>
                                    <span class="compliance-status <?php echo esc_attr( $product_data['compliance_class'] ); ?>">
                                        <?php echo esc_html( $product_data['compliance_text'] ); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $product_data['product']->get_id() ) ); ?>" class="button button-small">
                                        <?php echo esc_html__( 'Edit', 'wc-price-history-compliance' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render statistics tab
     */
    private function render_statistics_tab() {
        $stats = $this->get_price_statistics();
        
        ?>
        <div class="wc-price-history-reports">
            <div class="report-section">
                <h2><?php echo esc_html__( 'Price History Statistics', 'wc-price-history-compliance' ); ?></h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo esc_html( number_format( $stats['total_records'] ) ); ?></h3>
                        <p><?php echo esc_html__( 'Total Price Records', 'wc-price-history-compliance' ); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo esc_html( number_format( $stats['unique_products'] ) ); ?></h3>
                        <p><?php echo esc_html__( 'Products Tracked', 'wc-price-history-compliance' ); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo esc_html( number_format( $stats['recent_changes'] ) ); ?></h3>
                        <p><?php echo esc_html__( 'Changes Last 30 Days', 'wc-price-history-compliance' ); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo esc_html( number_format( $stats['sale_records'] ) ); ?></h3>
                        <p><?php echo esc_html__( 'Sale Price Records', 'wc-price-history-compliance' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h2><?php echo esc_html__( 'Top Products by Price Changes', 'wc-price-history-compliance' ); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Price Changes', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Last Change', 'wc-price-history-compliance' ); ?></th>
                            <th><?php echo esc_html__( 'Current Status', 'wc-price-history-compliance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['top_changing_products'] as $product_stat ) : ?>
                            <?php $product = wc_get_product( $product_stat->product_id ); ?>
                            <?php if ( $product ) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>">
                                            <?php echo esc_html( $product->get_name() ); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html( $product_stat->change_count ); ?></td>
                                    <td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $product_stat->last_change ) ) ); ?></td>
                                    <td>
                                        <?php if ( $product->is_on_sale() ) : ?>
                                            <span class="status-sale"><?php echo esc_html__( 'On Sale', 'wc-price-history-compliance' ); ?></span>
                                        <?php else : ?>
                                            <span class="status-regular"><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get filtered recent price changes
     */
    private function get_filtered_recent_changes( $days, $product_id = 0, $type = '' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        $date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $where_conditions = array( "price_date >= %s" );
        $where_values = array( $date_from );
        
        if ( $product_id > 0 ) {
            $where_conditions[] = "product_id = %d";
            $where_values[] = $product_id;
        }
        
        if ( ! empty( $type ) ) {
            $where_conditions[] = "price_type = %s";
            $where_values[] = $type;
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY price_date DESC LIMIT 100";
        $prepared_sql = $wpdb->prepare( $sql, $where_values );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $prepared_sql );
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
    private function display_compliance_alerts( $alerts = null ) {
        if ( null === $alerts ) {
            $alerts = $this->get_compliance_alerts();
        }
        
        if ( empty( $alerts ) ) {
            echo '<div class="notice notice-success">';
            echo '<p>' . esc_html__( 'No compliance issues found. All products on sale are compliant with pricing regulations.', 'wc-price-history-compliance' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="compliance-alerts">';
        foreach ( $alerts as $alert ) {
            $product = wc_get_product( $alert->product_id );
            if ( ! $product ) {
                continue;
            }
            
            echo '<div class="alert-item" data-product-id="' . esc_attr( $alert->product_id ) . '">';
            echo '<div class="alert-content">';
            echo '<h4>' . esc_html( $product->get_name() ) . '</h4>';
            echo '<p>' . esc_html( $alert->message ) . '</p>';
            echo '<div class="alert-meta">';
            echo '<span>Current Sale Price: ' . wc_price( $alert->sale_price ) . '</span>';
            echo '<span>Lowest Price (30 days): ' . wc_price( $alert->lowest_price ) . '</span>';
            echo '<span>Difference: ' . wc_price( $alert->difference ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="alert-actions">';
            echo '<a href="' . esc_url( get_edit_post_link( $alert->product_id ) ) . '" class="button button-primary">' . 
                 esc_html__( 'Edit Product', 'wc-price-history-compliance' ) . '</a>';
            echo '<button class="button dismiss-alert" data-product-id="' . esc_attr( $alert->product_id ) . '">' . 
                 esc_html__( 'Dismiss', 'wc-price-history-compliance' ) . '</button>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Get compliance alerts
     */
    private function get_compliance_alerts() {
        $alerts = array();
        
        // Get products currently on sale
        $products_on_sale = wc_get_product_ids_on_sale();
        
        $database = new WC_Price_History_Database();
        
        foreach ( $products_on_sale as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $lowest_price = $database->get_lowest_price( $product_id, 30 );
            $current_sale_price = floatval( $product->get_sale_price() );
            
            if ( $lowest_price && $current_sale_price > $lowest_price ) {
                $alert = new stdClass();
                $alert->product_id = $product_id;
                $alert->sale_price = $current_sale_price;
                $alert->lowest_price = $lowest_price;
                $alert->difference = $current_sale_price - $lowest_price;
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
     * Get products on sale analysis
     */
    private function get_products_on_sale_analysis() {
        $products_on_sale = wc_get_product_ids_on_sale();
        $analysis = array();
        
        $database = new WC_Price_History_Database();
        
        foreach ( $products_on_sale as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $sale_price = floatval( $product->get_sale_price() );
            $lowest_price = $database->get_lowest_price( $product_id, 30 );
            
            $compliance_class = 'compliant';
            $compliance_text = esc_html__( 'Compliant', 'wc-price-history-compliance' );
            
            if ( $lowest_price && $sale_price > $lowest_price ) {
                $compliance_class = 'non-compliant';
                $compliance_text = esc_html__( 'Non-Compliant', 'wc-price-history-compliance' );
            } elseif ( ! $lowest_price ) {
                $compliance_class = 'unknown';
                $compliance_text = esc_html__( 'Insufficient Data', 'wc-price-history-compliance' );
            }
            
            $analysis[] = array(
                'product' => $product,
                'sale_price' => $sale_price,
                'lowest_price' => $lowest_price,
                'compliance_class' => $compliance_class,
                'compliance_text' => $compliance_text
            );
        }
        
        return $analysis;
    }

    /**
     * Get price statistics
     */
    private function get_price_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $unique_products = $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table_name}" );
        
        $date_30_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $recent_changes = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE price_date >= %s", $date_30_days_ago ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $sale_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE price_type = 'sale'" );
        
        // Get top changing products
        $sql = "SELECT product_id, COUNT(*) as change_count, MAX(price_date) as last_change 
                FROM {$table_name} 
                GROUP BY product_id 
                ORDER BY change_count DESC 
                LIMIT 10";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $top_changing_products = $wpdb->get_results( $sql );
        
        return array(
            'total_records' => $total_records,
            'unique_products' => $unique_products,
            'recent_changes' => $recent_changes,
            'sale_records' => $sale_records,
            'top_changing_products' => $top_changing_products
        );
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

        $days = isset( $_GET['days'] ) ? intval( $_GET['days'] ) : 30;
        $recent_changes = $this->get_filtered_recent_changes( $days );
        
        // Set content type for PDF-ready HTML
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="price-history-report-' . gmdate( 'Y-m-d' ) . '.html"' );
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html__( 'Price History Report', 'wc-price-history-compliance' ); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .compliance-compliant { color: green; }
                .compliance-non-compliant { color: red; }
                .compliance-unknown { color: orange; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html__( 'Price History Report', 'wc-price-history-compliance' ); ?></h1>
            <p><?php echo esc_html__( 'Generated on:', 'wc-price-history-compliance' ); ?> <?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?></p>
            <p><?php echo esc_html__( 'Period:', 'wc-price-history-compliance' ); ?> <?php echo esc_html( sprintf( __( 'Last %d days', 'wc-price-history-compliance' ), $days ) ); ?></p>
            
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                        <th><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></th>
                        <th><?php echo esc_html__( 'Sale Price', 'wc-price-history-compliance' ); ?></th>
                        <th><?php echo esc_html__( 'Price Type', 'wc-price-history-compliance' ); ?></th>
                        <th><?php echo esc_html__( 'Date Changed', 'wc-price-history-compliance' ); ?></th>
                        <th><?php echo esc_html__( 'Compliance', 'wc-price-history-compliance' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_changes as $change ) : ?>
                        <?php
                        $product = wc_get_product( $change->product_id );
                        if ( ! $product ) {
                            continue;
                        }
                        $compliance_status = $this->check_compliance_status( $change );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $product->get_name() ); ?></td>
                            <td><?php echo $change->regular_price ? esc_html( wc_price( $change->regular_price ) ) : '—'; ?></td>
                            <td><?php echo $change->sale_price ? esc_html( wc_price( $change->sale_price ) ) : '—'; ?></td>
                            <td><?php echo esc_html( ucfirst( $change->price_type ) ); ?></td>
                            <td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', strtotime( $change->price_date ) ) ); ?></td>
                            <td class="compliance-<?php echo esc_attr( $compliance_status['class'] ); ?>">
                                <?php echo esc_html( $compliance_status['text'] ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
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
        $plugin = WC_Price_History_Compliance::get_instance();
        
        // Check if we should send email notifications
        if ( 'yes' === $plugin->get_option( 'email_notifications' ) ) {
            $this->send_email_notifications( $alerts );
        }
        
        // Set transient for admin notices
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
    }

    /**
     * Send email notifications
     */
    private function send_email_notifications( $alerts ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        $notification_email = $plugin->get_option( 'notification_email', get_option( 'admin_email' ) );
        
        if ( empty( $notification_email ) ) {
            return;
        }

        // Check if we've already sent an email today to avoid spam
        $last_email_date = get_option( 'wc_price_history_last_email_date' );
        $today = gmdate( 'Y-m-d' );
        
        if ( $last_email_date === $today ) {
            return; // Already sent email today
        }

        $subject = sprintf(
            esc_html__( '[%s] Price Compliance Alert - %d Issues Found', 'wc-price-history-compliance' ),
            get_bloginfo( 'name' ),
            count( $alerts )
        );

        $message = esc_html__( 'The following products may not be compliant with pricing regulations:', 'wc-price-history-compliance' ) . "\n\n";

        foreach ( $alerts as $alert ) {
            $product = wc_get_product( $alert->product_id );
            if ( $product ) {
                $message .= sprintf(
                    "- %s\n  Current Sale Price: %s\n  Lowest Price (30 days): %s\n  Edit: %s\n\n",
                    $product->get_name(),
                    wc_price( $alert->sale_price ),
                    wc_price( $alert->lowest_price ),
                    get_edit_post_link( $alert->product_id )
                );
            }
        }

        $message .= sprintf(
            esc_html__( 'View all compliance issues: %s', 'wc-price-history-compliance' ),
            admin_url( 'admin.php?page=wc-price-history-reports&tab=compliance' )
        );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        
        if ( wp_mail( $notification_email, $subject, $message, $headers ) ) {
            update_option( 'wc_price_history_last_email_date', $today );
        }
    }

    /**
     * Show compliance notices in admin
     */
    public function show_compliance_notices() {
        $message = get_transient( 'wc_price_history_compliance_alert' );
        
        if ( $message && current_user_can( 'manage_woocommerce' ) ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__( 'Price History Compliance Alert:', 'wc-price-history-compliance' ) . '</strong> ';
            echo esc_html( $message );
            echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-price-history-reports&tab=compliance' ) ) . '">' . 
                 esc_html__( 'View Details', 'wc-price-history-compliance' ) . '</a></p>';
            echo '</div>';
            
            delete_transient( 'wc_price_history_compliance_alert' );
        }
    }

    /**
     * Dismiss alert via AJAX
     */
    public function dismiss_alert() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_admin' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( esc_html__( 'You do not have sufficient permissions.', 'wc-price-history-compliance' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID', 'wc-price-history-compliance' ) );
        }

        // Store dismissed alerts to avoid showing them again for a period
        $dismissed_alerts = get_option( 'wc_price_history_dismissed_alerts', array() );
        $dismissed_alerts[ $product_id ] = time() + ( 7 * DAY_IN_SECONDS ); // Dismiss for 7 days
        update_option( 'wc_price_history_dismissed_alerts', $dismissed_alerts );

        wp_send_json_success( esc_html__( 'Alert dismissed', 'wc-price-history-compliance' ) );
    }

    /**
     * Schedule automated reports
     */
    public function schedule_automated_reports() {
        if ( ! wp_next_scheduled( 'wc_price_history_daily_report' ) ) {
            wp_schedule_event( time(), 'daily', 'wc_price_history_daily_report' );
        }
        
        if ( ! wp_next_scheduled( 'wc_price_history_weekly_report' ) ) {
            wp_schedule_event( time(), 'weekly', 'wc_price_history_weekly_report' );
        }
        
        // Hook the scheduled events
        add_action( 'wc_price_history_daily_report', array( $this, 'send_daily_report' ) );
        add_action( 'wc_price_history_weekly_report', array( $this, 'send_weekly_report' ) );
    }

    /**
     * Send daily compliance report
     */
    public function send_daily_report() {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'daily_reports' ) ) {
            return;
        }

        $alerts = $this->get_compliance_alerts();
        
        if ( empty( $alerts ) ) {
            return; // No issues to report
        }

        // Send daily report email
        $this->send_email_notifications( $alerts );
    }

    /**
     * Send weekly summary report
     */
    public function send_weekly_report() {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'weekly_reports' ) ) {
            return;
        }

        $stats = $this->get_price_statistics();
        $notification_email = $plugin->get_option( 'notification_email', get_option( 'admin_email' ) );
        
        if ( empty( $notification_email ) ) {
            return;
        }

        $subject = sprintf(
            esc_html__( '[%s] Weekly Price History Summary', 'wc-price-history-compliance' ),
            get_bloginfo( 'name' )
        );

        $message = esc_html__( 'Weekly Price History Summary:', 'wc-price-history-compliance' ) . "\n\n";
        $message .= sprintf( esc_html__( 'Total Price Records: %s', 'wc-price-history-compliance' ), number_format( $stats['total_records'] ) ) . "\n";
        $message .= sprintf( esc_html__( 'Products Tracked: %s', 'wc-price-history-compliance' ), number_format( $stats['unique_products'] ) ) . "\n";
        $message .= sprintf( esc_html__( 'Changes Last 7 Days: %s', 'wc-price-history-compliance' ), number_format( $stats['recent_changes'] ) ) . "\n";
        $message .= sprintf( esc_html__( 'Sale Price Records: %s', 'wc-price-history-compliance' ), number_format( $stats['sale_records'] ) ) . "\n\n";

        $alerts = $this->get_compliance_alerts();
        if ( ! empty( $alerts ) ) {
            $message .= sprintf( esc_html__( 'Current Compliance Issues: %d', 'wc-price-history-compliance' ), count( $alerts ) ) . "\n";
        } else {
            $message .= esc_html__( 'No compliance issues found.', 'wc-price-history-compliance' ) . "\n";
        }

        $message .= "\n" . sprintf(
            esc_html__( 'View detailed reports: %s', 'wc-price-history-compliance' ),
            admin_url( 'admin.php?page=wc-price-history-reports' )
        );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        wp_mail( $notification_email, $subject, $message, $headers );
    }

    /**
     * Clean up scheduled events on deactivation
     */
    public function cleanup_scheduled_events() {
        wp_clear_scheduled_hook( 'wc_price_history_daily_report' );
        wp_clear_scheduled_hook( 'wc_price_history_weekly_report' );
    }
}once'] ) ), 'wc_price_history_export' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        $days = isset( $_GET['days'] ) ? intval( $_GET['days'] ) : 30;
        $product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
        $type = isset( $_GET['price_type'] ) ? sanitize_text_field( wp_unslash( $_GET['price_type'] ) ) : '';
        
        $recent_changes = $this->get_filtered_recent_changes( $days, $product_id, $type );
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="price-history-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
        
        $output = fopen( 'php://output', 'w' );
        
        // CSV headers
        fputcsv( $output, array(
            'Product ID',
            'Product Name',
            'SKU',
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
                $product->get_sku(),
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
     * Export data to PDF
     */
    public function export_pdf() {
        // For basic PDF export - this would require a PDF library like TCPDF or mPDF
        // For now, we'll create a simple HTML version that can be printed to PDF
        
        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpn<?php

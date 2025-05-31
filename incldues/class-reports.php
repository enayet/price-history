<?php
/**
 * Reports class for WooCommerce Price History & Sale Compliance
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
     * Database instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new WC_Price_History_Database();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_reports_menu' ) );
        add_action( 'wp_ajax_wc_price_history_export_csv', array( $this, 'export_csv' ) );
        add_action( 'wp_ajax_wc_price_history_export_pdf', array( $this, 'export_pdf' ) );
        add_action( 'admin_init', array( $this, 'handle_alerts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_wc_price_history_get_report_data', array( $this, 'ajax_get_report_data' ) );
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
     * Enqueue admin scripts for reports page
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_wc-price-history-reports' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-datepicker', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css', array(), '1.12.1' );
        
        wp_localize_script( 
            'wc-price-history-admin', 
            'wc_price_history_reports', 
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wc_price_history_reports_nonce' ),
                'export_nonce' => wp_create_nonce( 'wc_price_history_export' )
            )
        );
    }

    /**
     * Reports page
     */
    public function reports_page() {
        // Handle filter parameters
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
        $product_search = isset( $_GET['product_search'] ) ? sanitize_text_field( wp_unslash( $_GET['product_search'] ) ) : '';
        $compliance_filter = isset( $_GET['compliance_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['compliance_filter'] ) ) : 'all';
        
        // Get filtered data
        $recent_changes = $this->get_filtered_price_changes( $date_from, $date_to, $product_search, $compliance_filter );
        $compliance_alerts = $this->get_compliance_alerts();
        $summary_stats = $this->get_summary_statistics( $date_from, $date_to );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Price History Reports', 'wc-price-history-compliance' ); ?></h1>
            
            <!-- Summary Statistics -->
            <div class="wc-price-history-summary">
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3><?php echo esc_html__( 'Total Price Changes', 'wc-price-history-compliance' ); ?></h3>
                        <span class="summary-number"><?php echo esc_html( $summary_stats['total_changes'] ); ?></span>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo esc_html__( 'Products on Sale', 'wc-price-history-compliance' ); ?></h3>
                        <span class="summary-number"><?php echo esc_html( $summary_stats['products_on_sale'] ); ?></span>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo esc_html__( 'Compliance Issues', 'wc-price-history-compliance' ); ?></h3>
                        <span class="summary-number warning"><?php echo esc_html( count( $compliance_alerts ) ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="reports-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wc-price-history-reports" />
                    
                    <div class="filter-group">
                        <label for="date_from"><?php echo esc_html__( 'From Date:', 'wc-price-history-compliance' ); ?></label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to"><?php echo esc_html__( 'To Date:', 'wc-price-history-compliance' ); ?></label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
                    </div>
                    
                    <div class="filter-group">
                        <label for="product_search"><?php echo esc_html__( 'Product Search:', 'wc-price-history-compliance' ); ?></label>
                        <input type="text" id="product_search" name="product_search" value="<?php echo esc_attr( $product_search ); ?>" placeholder="<?php echo esc_attr__( 'Product name or ID', 'wc-price-history-compliance' ); ?>" />
                    </div>
                    
                    <div class="filter-group">
                        <label for="compliance_filter"><?php echo esc_html__( 'Compliance Status:', 'wc-price-history-compliance' ); ?></label>
                        <select id="compliance_filter" name="compliance_filter">
                            <option value="all" <?php selected( 'all', $compliance_filter ); ?>><?php echo esc_html__( 'All', 'wc-price-history-compliance' ); ?></option>
                            <option value="compliant" <?php selected( 'compliant', $compliance_filter ); ?>><?php echo esc_html__( 'Compliant', 'wc-price-history-compliance' ); ?></option>
                            <option value="non-compliant" <?php selected( 'non-compliant', $compliance_filter ); ?>><?php echo esc_html__( 'Non-Compliant', 'wc-price-history-compliance' ); ?></option>
                            <option value="unknown" <?php selected( 'unknown', $compliance_filter ); ?>><?php echo esc_html__( 'Unknown', 'wc-price-history-compliance' ); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <input type="submit" class="button" value="<?php echo esc_attr__( 'Filter', 'wc-price-history-compliance' ); ?>" />
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-price-history-reports' ) ); ?>" class="button"><?php echo esc_html__( 'Reset', 'wc-price-history-compliance' ); ?></a>
                    </div>
                </form>
            </div>
            
            <div class="wc-price-history-reports">
                <!-- Price Changes Report -->
                <div class="report-section">
                    <h2><?php echo esc_html__( 'Price Changes Report', 'wc-price-history-compliance' ); ?></h2>
                    
                    <div class="report-actions">
                        <button type="button" class="button export-btn" data-format="csv">
                            <?php echo esc_html__( 'Export to CSV', 'wc-price-history-compliance' ); ?>
                        </button>
                        <button type="button" class="button export-btn" data-format="pdf">
                            <?php echo esc_html__( 'Export to PDF', 'wc-price-history-compliance' ); ?>
                        </button>
                    </div>
                    
                    <div class="table-container">
                        <?php $this->render_price_changes_table( $recent_changes ); ?>
                    </div>
                </div>
                
                <!-- Compliance Alerts Section -->
                <div class="report-section">
                    <h2><?php echo esc_html__( 'Compliance Alerts', 'wc-price-history-compliance' ); ?></h2>
                    <?php $this->display_compliance_alerts( $compliance_alerts ); ?>
                </div>
            </div>
        </div>
        
        <!-- Hidden nonce for AJAX calls -->
        <input type="hidden" id="export-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wc_price_history_export' ) ); ?>" />
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Export functionality
            $('.export-btn').on('click', function() {
                const format = $(this).data('format');
                const params = new URLSearchParams(window.location.search);
                params.set('action', 'wc_price_history_export_' + format);
                params.set('_wpnonce', $('#export-nonce').val());
                
                const exportUrl = ajaxurl + '?' + params.toString();
                window.location.href = exportUrl;
            });
        });
        </script>
        <?php
    }

    /**
     * Render price changes table
     */
    private function render_price_changes_table( $price_changes ) {
        if ( empty( $price_changes ) ) {
            echo '<p>' . esc_html__( 'No price changes found for the selected criteria.', 'wc-price-history-compliance' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped wc-price-history-table">
            <thead>
                <tr>
                    <th class="column-product"><?php echo esc_html__( 'Product', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-regular-price"><?php echo esc_html__( 'Regular Price', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-sale-price"><?php echo esc_html__( 'Sale Price', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-price-type"><?php echo esc_html__( 'Price Type', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-date"><?php echo esc_html__( 'Date Changed', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-compliance"><?php echo esc_html__( 'Compliance Status', 'wc-price-history-compliance' ); ?></th>
                    <th class="column-actions"><?php echo esc_html__( 'Actions', 'wc-price-history-compliance' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $price_changes as $change ) : ?>
                    <?php
                    $product = wc_get_product( $change->product_id );
                    if ( ! $product ) {
                        continue;
                    }
                    
                    $compliance_status = $this->check_compliance_status( $change );
                    ?>
                    <tr>
                        <td class="column-product">
                            <strong>
                                <a href="<?php echo esc_url( get_edit_post_link( $change->product_id ) ); ?>">
                                    <?php echo esc_html( $product->get_name() ); ?>
                                </a>
                            </strong>
                            <br>
                            <small>ID: <?php echo esc_html( $change->product_id ); ?></small>
                        </td>
                        <td class="column-regular-price">
                            <?php echo $change->regular_price ? wp_kses_post( wc_price( $change->regular_price ) ) : '—'; ?>
                        </td>
                        <td class="column-sale-price">
                            <?php echo $change->sale_price ? wp_kses_post( wc_price( $change->sale_price ) ) : '—'; ?>
                        </td>
                        <td class="column-price-type">
                            <span class="price-type <?php echo esc_attr( $change->price_type ); ?>">
                                <?php echo esc_html( ucfirst( $change->price_type ) ); ?>
                            </span>
                        </td>
                        <td class="column-date">
                            <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $change->price_date ) ) ); ?>
                        </td>
                        <td class="column-compliance">
                            <span class="compliance-status <?php echo esc_attr( $compliance_status['class'] ); ?>">
                                <?php echo esc_html( $compliance_status['text'] ); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url( get_edit_post_link( $change->product_id ) ); ?>" class="button button-small">
                                <?php echo esc_html__( 'Edit', 'wc-price-history-compliance' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button button-small" target="_blank">
                                <?php echo esc_html__( 'View', 'wc-price-history-compliance' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get filtered price changes
     */
    private function get_filtered_price_changes( $date_from, $date_to, $product_search = '', $compliance_filter = 'all' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        $sql = "SELECT ph.* FROM {$table_name} ph";
        $where_clauses = array();
        $params = array();
        
        // Date filters
        if ( $date_from ) {
            $where_clauses[] = 'ph.price_date >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ( $date_to ) {
            $where_clauses[] = 'ph.price_date <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        
        // Product search filter
        if ( ! empty( $product_search ) ) {
            $sql .= " LEFT JOIN {$wpdb->posts} p ON ph.product_id = p.ID";
            
            if ( is_numeric( $product_search ) ) {
                $where_clauses[] = 'ph.product_id = %d';
                $params[] = intval( $product_search );
            } else {
                $where_clauses[] = 'p.post_title LIKE %s';
                $params[] = '%' . $wpdb->esc_like( $product_search ) . '%';
            }
        }
        
        if ( ! empty( $where_clauses ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $sql .= ' ORDER BY ph.price_date DESC LIMIT 200';
        
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( $sql );
        
        // Apply compliance filter if needed
        if ( 'all' !== $compliance_filter ) {
            $filtered_results = array();
            foreach ( $results as $result ) {
                $compliance_status = $this->check_compliance_status( $result );
                if ( $compliance_status['class'] === $compliance_filter ) {
                    $filtered_results[] = $result;
                }
            }
            return $filtered_results;
        }
        
        return $results;
    }

    /**
     * Get summary statistics
     */
    private function get_summary_statistics( $date_from, $date_to ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        // Total price changes in date range
        $total_changes_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE price_date >= %s AND price_date <= %s",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_changes = $wpdb->get_var( $total_changes_sql );
        
        // Products currently on sale
        $products_on_sale = count( wc_get_product_ids_on_sale() );
        
        return array(
            'total_changes' => intval( $total_changes ),
            'products_on_sale' => $products_on_sale,
        );
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

        $lowest_price = $this->database->get_lowest_price( $price_record->product_id, 30 );
        
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
            echo '<div class="compliance-alerts-empty">';
            echo '<p class="success">' . esc_html__( 'No compliance issues found. All products on sale comply with the EU Omnibus Directive.', 'wc-price-history-compliance' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="compliance-alerts">';
        echo '<p class="alert-summary">' . sprintf(
            _n(
                '%d product requires attention for compliance.',
                '%d products require attention for compliance.',
                count( $alerts ),
                'wc-price-history-compliance'
            ),
            count( $alerts )
        ) . '</p>';
        
        foreach ( $alerts as $alert ) {
            $product = wc_get_product( $alert->product_id );
            if ( ! $product ) {
                continue;
            }
            
            echo '<div class="alert-item">';
            echo '<div class="alert-header">';
            echo '<h4>' . esc_html( $product->get_name() ) . ' <span class="product-id">(ID: ' . esc_html( $alert->product_id ) . ')</span></h4>';
            echo '<span class="alert-severity high">' . esc_html__( 'High Priority', 'wc-price-history-compliance' ) . '</span>';
            echo '</div>';
            echo '<div class="alert-content">';
            echo '<p>' . wp_kses_post( $alert->message ) . '</p>';
            echo '<div class="alert-details">';
            echo '<span><strong>' . esc_html__( 'Current Sale Price:', 'wc-price-history-compliance' ) . '</strong> ' . wp_kses_post( wc_price( $alert->current_sale_price ) ) . '</span>';
            echo '<span><strong>' . esc_html__( 'Lowest 30-day Price:', 'wc-price-history-compliance' ) . '</strong> ' . wp_kses_post( wc_price( $alert->lowest_price ) ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="alert-actions">';
            echo '<a href="' . esc_url( get_edit_post_link( $alert->product_id ) ) . '" class="button button-primary">' . 
                 esc_html__( 'Edit Product', 'wc-price-history-compliance' ) . '</a>';
            echo '<a href="' . esc_url( $product->get_permalink() ) . '" class="button" target="_blank">' . 
                 esc_html__( 'View Product', 'wc-price-history-compliance' ) . '</a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Get compliance alerts
     */
    private function get_compliance_alerts() {
        return $this->database->get_compliance_alerts();
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

        // Get filter parameters
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
        $product_search = isset( $_GET['product_search'] ) ? sanitize_text_field( wp_unslash( $_GET['product_search'] ) ) : '';
        $compliance_filter = isset( $_GET['compliance_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['compliance_filter'] ) ) : 'all';

        $recent_changes = $this->get_filtered_price_changes( $date_from, $date_to, $product_search, $compliance_filter );
        
        // Set headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="price-history-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        $output = fopen( 'php://output', 'w' );
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );
        
        // CSV headers
        fputcsv( $output, array(
            'Product ID',
            'Product Name',
            'SKU',
            'Regular Price',
            'Sale Price',
            'Price Type',
            'Date Changed',
            'Compliance Status',
            'Lowest 30-day Price',
            'Product URL'
        ) );
        
        foreach ( $recent_changes as $change ) {
            $product = wc_get_product( $change->product_id );
            if ( ! $product ) {
                continue;
            }
            
            $compliance_status = $this->check_compliance_status( $change );
            $lowest_price = $this->database->get_lowest_price( $change->product_id, 30 );
            
            fputcsv( $output, array(
                $change->product_id,
                $product->get_name(),
                $product->get_sku(),
                $change->regular_price,
                $change->sale_price,
                ucfirst( $change->price_type ),
                $change->price_date,
                $compliance_status['text'],
                $lowest_price,
                $product->get_permalink()
            ) );
        }
        
        fclose( $output );
        exit;
    }

    /**
     * Export data to PDF (placeholder for future implementation)
     */
    public function export_pdf() {
        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wc_price_history_export' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        // For now, redirect to CSV export
        // In future versions, implement PDF generation
        wp_safe_redirect( add_query_arg( 'action', 'wc_price_history_export_csv', wp_get_referer() ) );
        exit;
    }

    /**
     * AJAX handler for getting report data
     */
    public function ajax_get_report_data() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wc_price_history_reports_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wc-price-history-compliance' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-price-history-compliance' ) );
        }

        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
        $date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
        
        $data = $this->get_chart_data_for_period( $date_from, $date_to );
        
        wp_send_json_success( $data );
    }

    /**
     * Get chart data for a specific period
     */
    private function get_chart_data_for_period( $date_from, $date_to ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_price_history';
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(price_date) as date,
                COUNT(*) as total_changes,
                SUM(CASE WHEN price_type = 'sale' THEN 1 ELSE 0 END) as sale_changes,
                SUM(CASE WHEN price_type = 'regular' THEN 1 ELSE 0 END) as regular_changes
             FROM {$table_name} 
             WHERE price_date >= %s AND price_date <= %s
             GROUP BY DATE(price_date)
             ORDER BY date ASC",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        );
        
        // Necessary for custom table operations - no WP API available
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
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
        // Check if we've already sent notifications today
        $last_notification = get_option( 'wc_price_history_last_notification', '' );
        $today = gmdate( 'Y-m-d' );
        
        if ( $last_notification === $today ) {
            return; // Already sent today
        }
        
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
        
        // Set transient for admin notice
        set_transient( 'wc_price_history_compliance_alert', $message, DAY_IN_SECONDS );
        
        // Send email notification if enabled
        $this->send_email_notification( $alerts );
        
        // Update last notification date
        update_option( 'wc_price_history_last_notification', $today );
        
        add_action( 'admin_notices', array( $this, 'show_compliance_notice' ) );
    }

    /**
     * Send email notification for compliance issues
     */
    private function send_email_notification( $alerts ) {
        $plugin = WC_Price_History_Compliance::get_instance();
        
        if ( 'yes' !== $plugin->get_option( 'enable_email_alerts', 'no' ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        
        $subject = sprintf(
            esc_html__( '[%s] Price Compliance Alert - %d Products Need Attention', 'wc-price-history-compliance' ),
            $site_name,
            count( $alerts )
        );
        
        $message = esc_html__( 'The following products have potential compliance issues with the EU Omnibus Directive:', 'wc-price-history-compliance' ) . "\n\n";
        
        foreach ( $alerts as $alert ) {
            $product = wc_get_product( $alert->product_id );
            if ( ! $product ) {
                continue;
            }
            
            $message .= sprintf(
                "- %s (ID: %d)\n  Current Sale Price: %s\n  Lowest 30-day Price: %s\n  Edit: %s\n\n",
                $product->get_name(),
                $alert->product_id,
                strip_tags( wc_price( $alert->current_sale_price ) ),
                strip_tags( wc_price( $alert->lowest_price ) ),
                admin_url( 'post.php?post=' . $alert->product_id . '&action=edit' )
            );
        }
        
        $message .= esc_html__( 'Please review and adjust the pricing to ensure compliance.', 'wc-price-history-compliance' ) . "\n\n";
        $message .= sprintf(
            esc_html__( 'View full report: %s', 'wc-price-history-compliance' ),
            admin_url( 'admin.php?page=wc-price-history-reports' )
        );
        
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        
        wp_mail( $admin_email, $subject, $message, $headers );
    }

    /**
     * Show compliance notice in admin
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

    /**
     * Get recent price changes (legacy method for backward compatibility)
     */
    private function get_recent_price_changes( $days = 30 ) {
        return $this->database->get_recent_price_changes( $days );
    }
}
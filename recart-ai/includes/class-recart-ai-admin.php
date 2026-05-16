<?php
/**
 * Admin panel class.
 *
 * Provides settings pages with tabs: Popup, Anti-Abuse, Webhook, Appearance, Logs.
 * Includes blacklist management and event log viewer.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Admin {

    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Initialize admin hooks.
     */
    public function init(): void {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Handle blacklist actions
        add_action( 'admin_post_recart_ai_add_blacklist', array( $this, 'handle_add_blacklist' ) );
        add_action( 'admin_post_recart_ai_remove_blacklist', array( $this, 'handle_remove_blacklist' ) );

        // Settings link on plugins page
        add_filter( 'plugin_action_links_' . RECART_AI_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Add admin menu pages.
     */
    public function add_menu_pages(): void {
        add_menu_page(
            __( 'ReCart AI', 'recart-ai' ),
            __( 'ReCart AI', 'recart-ai' ),
            'manage_woocommerce',
            'recart-ai',
            array( $this, 'render_dashboard_page' ),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'recart-ai',
            __( 'Dashboard', 'recart-ai' ),
            __( 'Dashboard', 'recart-ai' ),
            'manage_woocommerce',
            'recart-ai',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'recart-ai',
            __( 'Settings', 'recart-ai' ),
            __( 'Settings', 'recart-ai' ),
            'manage_woocommerce',
            'recart-ai-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'recart-ai',
            __( 'Blacklist', 'recart-ai' ),
            __( 'Blacklist', 'recart-ai' ),
            'manage_woocommerce',
            'recart-ai-blacklist',
            array( $this, 'render_blacklist_page' )
        );

        add_submenu_page(
            'recart-ai',
            __( 'Logs', 'recart-ai' ),
            __( 'Logs', 'recart-ai' ),
            'manage_woocommerce',
            'recart-ai-logs',
            array( $this, 'render_logs_page' )
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'recart-ai' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'recart-ai-admin',
            RECART_AI_PLUGIN_URL . 'assets/css/recart-ai-admin.css',
            array(),
            RECART_AI_VERSION
        );

        wp_enqueue_script(
            'recart-ai-admin',
            RECART_AI_PLUGIN_URL . 'assets/js/recart-ai-admin.js',
            array( 'jquery' ),
            RECART_AI_VERSION,
            true
        );

        // Color picker
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    /**
     * Register all settings.
     */
    public function register_settings(): void {
        // General
        register_setting( 'recart_ai_general', 'recart_ai_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_general', 'recart_ai_gdpr_mode', array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Popup
        register_setting( 'recart_ai_popup', 'recart_ai_popup_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_message', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_button_text', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_show_products', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_show_phone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_delay', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_popup', 'recart_ai_popup_mobile_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Appearance
        register_setting( 'recart_ai_appearance', 'recart_ai_popup_color_primary', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'recart_ai_appearance', 'recart_ai_popup_color_bg', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'recart_ai_appearance', 'recart_ai_popup_color_text', array( 'sanitize_callback' => 'sanitize_hex_color' ) );

        // Anti-abuse
        register_setting( 'recart_ai_antiabuse', 'recart_ai_antiabuse_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_min_cart_total', array( 'sanitize_callback' => 'floatval' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_discount_type', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_discount_value', array( 'sanitize_callback' => 'floatval' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_coupon_expiry_hours', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_max_codes_30days', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_anomaly_threshold', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_anomaly_days', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_min_session_time', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_free_delivery_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_antiabuse', 'recart_ai_free_delivery_threshold', array( 'sanitize_callback' => 'floatval' ) );

        // Webhook
        register_setting( 'recart_ai_webhook', 'recart_ai_webhook_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_webhook', 'recart_ai_webhook_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'recart_ai_webhook', 'recart_ai_webhook_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_webhook', 'recart_ai_abandonment_timeout', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'recart_ai_webhook', 'recart_ai_formspree_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'recart_ai_webhook', 'recart_ai_formspree_endpoint', array( 'sanitize_callback' => 'esc_url_raw' ) );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard_page(): void {
        global $wpdb;

        // Get stats
        $fp_table      = $wpdb->prefix . 'recart_fingerprints';
        $carts_table   = $wpdb->prefix . 'recart_abandoned_carts';
        $coupons_table = $wpdb->prefix . 'recart_coupons';

        $total_fingerprints = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fp_table}" );
        $total_abandoned    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table}" );
        $total_recovered    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table} WHERE recovered = 1" );
        $total_coupons      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$coupons_table}" );
        $used_coupons       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$coupons_table} WHERE used = 1" );
        $blocked_fps        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fp_table} WHERE is_blocked = 1" );

        $recovery_rate = $total_abandoned > 0 ? round( ( $total_recovered / $total_abandoned ) * 100, 1 ) : 0;
        $coupon_usage  = $total_coupons > 0 ? round( ( $used_coupons / $total_coupons ) * 100, 1 ) : 0;

        // Recent abandoned carts (last 7 days)
        $recent_abandoned = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$carts_table} WHERE abandon_time >= %s", gmdate( 'Y-m-d', strtotime( '-7 days' ) ) )
        );
        $recent_recovered = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$carts_table} WHERE recovered = 1 AND abandon_time >= %s", gmdate( 'Y-m-d', strtotime( '-7 days' ) ) )
        );

        $saved_revenue = (float) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(SUM(cart_total), 0) FROM {$carts_table} WHERE recovered = 1 AND abandon_time >= %s", gmdate( 'Y-m-d', strtotime( '-30 days' ) ) )
        );

        // ============================================================
        // SALES ANALYTICS - Orders completed since plugin activation
        // ============================================================
        $activation_date = get_option( 'recart_ai_activated', 0 );
        $activation_formatted = $activation_date ? gmdate( 'Y-m-d H:i:s', (int) $activation_date ) : gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $sales_data = $this->get_sales_analytics( $activation_formatted );

        ?>
        <div class="wrap recart-ai-admin">
            <h1 class="recart-ai-admin-title">
                <span class="recart-ai-logo">🛒</span>
                <?php esc_html_e( 'ReCart AI Dashboard', 'recart-ai' ); ?>
            </h1>

            <?php if ( $activation_date ) : ?>
                <p class="recart-ai-activation-info">
                    <?php printf(
                        esc_html__( 'Plugin active since: %s', 'recart-ai' ),
                        '<strong>' . esc_html( wp_date( 'd.m.Y H:i', (int) $activation_date ) ) . '</strong>'
                    ); ?>
                </p>
            <?php endif; ?>

            <!-- Sales Analytics Section -->
            <div class="recart-ai-section">
                <h2 class="recart-ai-section-title"><?php esc_html_e( 'Sales Analytics (since plugin activation)', 'recart-ai' ); ?></h2>

                <div class="recart-ai-stats-grid recart-ai-sales-grid">
                    <div class="recart-ai-stat-card recart-ai-stat-revenue">
                        <div class="recart-ai-stat-icon">💵</div>
                        <div class="recart-ai-stat-value"><?php echo wp_kses_post( wc_price( $sales_data['total_revenue'] ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Total Revenue', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">📦</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $sales_data['total_orders'] ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Total Orders', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card recart-ai-stat-success">
                        <div class="recart-ai-stat-icon">🔄</div>
                        <div class="recart-ai-stat-value"><?php echo wp_kses_post( wc_price( $sales_data['recovered_revenue'] ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Revenue from Recovered Carts', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card recart-ai-stat-success">
                        <div class="recart-ai-stat-icon">🎯</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $sales_data['recovered_orders'] ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Orders via ReCart Coupons', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">📈</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( $sales_data['recart_share'] ); ?>%</div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'ReCart Share of Revenue', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">🧾</div>
                        <div class="recart-ai-stat-value"><?php echo wp_kses_post( wc_price( $sales_data['avg_order_value'] ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Average Order Value', 'recart-ai' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Sales Breakdown by Period -->
            <div class="recart-ai-section">
                <h2 class="recart-ai-section-title"><?php esc_html_e( 'Revenue Breakdown', 'recart-ai' ); ?></h2>

                <table class="wp-list-table widefat fixed striped recart-ai-sales-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Period', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Total Orders', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Total Revenue', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'ReCart Orders', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'ReCart Revenue', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'ReCart %', 'recart-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sales_data['periods'] as $period ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $period['label'] ); ?></strong></td>
                                <td><?php echo esc_html( number_format_i18n( $period['total_orders'] ) ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( $period['total_revenue'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $period['recart_orders'] ) ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( $period['recart_revenue'] ) ); ?></td>
                                <td>
                                    <span class="recart-ai-percentage-bar">
                                        <span class="recart-ai-percentage-fill" style="width: <?php echo esc_attr( min( 100, $period['recart_percent'] ) ); ?>%;"></span>
                                        <span class="recart-ai-percentage-text"><?php echo esc_html( $period['recart_percent'] ); ?>%</span>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Original Stats Section -->
            <div class="recart-ai-section">
                <h2 class="recart-ai-section-title"><?php esc_html_e( 'Cart Recovery Stats', 'recart-ai' ); ?></h2>

                <div class="recart-ai-stats-grid">
                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">👤</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $total_fingerprints ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Unique Visitors Tracked', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">🛒</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $total_abandoned ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Abandoned Carts', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card recart-ai-stat-success">
                        <div class="recart-ai-stat-icon">✅</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $total_recovered ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Recovered Carts', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">📊</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( $recovery_rate ); ?>%</div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Recovery Rate', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">🎟️</div>
                        <div class="recart-ai-stat-value"><?php echo esc_html( number_format_i18n( $total_coupons ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Coupons Generated', 'recart-ai' ); ?></div>
                    </div>

                    <div class="recart-ai-stat-card">
                        <div class="recart-ai-stat-icon">💰</div>
                        <div class="recart-ai-stat-value"><?php echo wp_kses_post( wc_price( $saved_revenue ) ); ?></div>
                        <div class="recart-ai-stat-label"><?php esc_html_e( 'Revenue Saved (30 days)', 'recart-ai' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Top Recovered Products -->
            <?php if ( ! empty( $sales_data['top_products'] ) ) : ?>
            <div class="recart-ai-section">
                <h2 class="recart-ai-section-title"><?php esc_html_e( 'Top Products in Recovered Orders', 'recart-ai' ); ?></h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Product', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Times Recovered', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Revenue', 'recart-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sales_data['top_products'] as $product ) : ?>
                            <tr>
                                <td>
                                    <?php if ( $product['url'] ) : ?>
                                        <a href="<?php echo esc_url( $product['url'] ); ?>" target="_blank"><?php echo esc_html( $product['name'] ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $product['name'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( number_format_i18n( $product['count'] ) ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( $product['revenue'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="recart-ai-info-cards">
                <div class="recart-ai-info-card">
                    <h3><?php esc_html_e( 'Last 7 Days', 'recart-ai' ); ?></h3>
                    <p><?php printf( esc_html__( '%d abandoned carts, %d recovered', 'recart-ai' ), $recent_abandoned, $recent_recovered ); ?></p>
                </div>

                <div class="recart-ai-info-card">
                    <h3><?php esc_html_e( 'Anti-Abuse', 'recart-ai' ); ?></h3>
                    <p><?php printf( esc_html__( '%d blocked fingerprints, %s coupon usage rate', 'recart-ai' ), $blocked_fps, $coupon_usage . '%' ); ?></p>
                </div>

                <div class="recart-ai-info-card">
                    <h3><?php esc_html_e( 'Quick Links', 'recart-ai' ); ?></h3>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=recart-ai-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'recart-ai' ); ?></a> |
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=recart-ai-blacklist' ) ); ?>"><?php esc_html_e( 'Blacklist', 'recart-ai' ); ?></a> |
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=recart-ai-logs' ) ); ?>"><?php esc_html_e( 'Logs', 'recart-ai' ); ?></a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get sales analytics data since plugin activation.
     *
     * Queries WooCommerce orders to calculate total revenue,
     * orders recovered via ReCart coupons, and period breakdowns.
     *
     * @param string $since_date Date from which to calculate (Y-m-d H:i:s format).
     * @return array Sales analytics data.
     */
    private function get_sales_analytics( string $since_date ): array {
        global $wpdb;

        $result = array(
            'total_revenue'     => 0,
            'total_orders'      => 0,
            'recovered_revenue' => 0,
            'recovered_orders'  => 0,
            'recart_share'      => 0,
            'avg_order_value'   => 0,
            'periods'           => array(),
            'top_products'      => array(),
        );

        // Use HPOS-compatible queries if available
        $orders_table = $wpdb->prefix . 'wc_orders';
        $orders_meta  = $wpdb->prefix . 'wc_orders_meta';
        $use_hpos     = $wpdb->get_var( "SHOW TABLES LIKE '{$orders_table}'" ) === $orders_table;

        if ( $use_hpos ) {
            // HPOS (High-Performance Order Storage) queries
            $result['total_orders'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$orders_table} WHERE status IN ('wc-completed', 'wc-processing') AND date_created_gmt >= %s",
                    $since_date
                )
            );

            $result['total_revenue'] = (float) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM {$orders_table} WHERE status IN ('wc-completed', 'wc-processing') AND date_created_gmt >= %s",
                    $since_date
                )
            );

            // Orders with ReCart coupons
            $recart_orders = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT o.id, o.total_amount, o.date_created_gmt
                     FROM {$orders_table} o
                     INNER JOIN {$wpdb->prefix}wc_order_coupon_lookup cl ON o.id = cl.order_id
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = cl.coupon_id AND pm.meta_key = '_recart_ai_coupon' AND pm.meta_value = '1'
                     WHERE o.status IN ('wc-completed', 'wc-processing')
                     AND o.date_created_gmt >= %s
                     GROUP BY o.id",
                    $since_date
                )
            );

        } else {
            // Legacy post-based order storage
            $result['total_orders'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing') AND post_date >= %s",
                    $since_date
                )
            );

            $result['total_revenue'] = (float) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(pm.meta_value), 0)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND p.post_date >= %s",
                    $since_date
                )
            );

            // Orders with ReCart coupons (legacy)
            $recart_orders = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID as id, pm_total.meta_value as total_amount, p.post_date as date_created_gmt
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id AND oi.order_item_type = 'coupon'
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'coupon_id'
                     INNER JOIN {$wpdb->postmeta} pm_recart ON oim.meta_value = pm_recart.post_id AND pm_recart.meta_key = '_recart_ai_coupon' AND pm_recart.meta_value = '1'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND p.post_date >= %s
                     GROUP BY p.ID",
                    $since_date
                )
            );
        }

        // Calculate recovered stats
        if ( ! empty( $recart_orders ) ) {
            $result['recovered_orders'] = count( $recart_orders );
            $result['recovered_revenue'] = array_sum( array_map( function ( $o ) {
                return (float) $o->total_amount;
            }, $recart_orders ) );
        }

        // Calculate derived metrics
        if ( $result['total_revenue'] > 0 ) {
            $result['recart_share'] = round( ( $result['recovered_revenue'] / $result['total_revenue'] ) * 100, 1 );
        }

        if ( $result['total_orders'] > 0 ) {
            $result['avg_order_value'] = round( $result['total_revenue'] / $result['total_orders'], 2 );
        }

        // Period breakdown (last 4 weeks + current week)
        $result['periods'] = $this->get_period_breakdown( $since_date, $use_hpos );

        // Top products in recovered orders
        $result['top_products'] = $this->get_top_recovered_products( $since_date );

        return $result;
    }

    /**
     * Get revenue breakdown by weekly periods.
     *
     * @param string $since_date Start date.
     * @param bool   $use_hpos  Whether to use HPOS tables.
     * @return array Period data.
     */
    private function get_period_breakdown( string $since_date, bool $use_hpos ): array {
        global $wpdb;

        $periods = array();

        // Generate last 4 weeks + current week
        $ranges = array(
            array(
                'label' => __( 'This Week', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
            ),
            array(
                'label' => __( 'Last Week', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( 'monday last week' ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
            ),
            array(
                'label' => __( '2 Weeks Ago', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( '-2 weeks monday' ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( 'monday last week' ) ),
            ),
            array(
                'label' => __( '3 Weeks Ago', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( '-3 weeks monday' ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( '-2 weeks monday' ) ),
            ),
            array(
                'label' => __( 'This Month', 'recart-ai' ),
                'start' => gmdate( 'Y-m-01' ),
                'end'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
            ),
            array(
                'label' => __( 'Last 30 Days', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
            ),
            array(
                'label' => __( 'Since Activation', 'recart-ai' ),
                'start' => gmdate( 'Y-m-d', strtotime( $since_date ) ),
                'end'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
            ),
        );

        foreach ( $ranges as $range ) {
            if ( $use_hpos ) {
                $orders_table = $wpdb->prefix . 'wc_orders';

                $total_orders = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$orders_table} WHERE status IN ('wc-completed', 'wc-processing') AND date_created_gmt >= %s AND date_created_gmt < %s",
                        $range['start'],
                        $range['end']
                    )
                );

                $total_revenue = (float) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(total_amount), 0) FROM {$orders_table} WHERE status IN ('wc-completed', 'wc-processing') AND date_created_gmt >= %s AND date_created_gmt < %s",
                        $range['start'],
                        $range['end']
                    )
                );

                // ReCart orders in this period
                $recart_data = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT o.id) as cnt, COALESCE(SUM(o.total_amount), 0) as rev
                         FROM {$orders_table} o
                         INNER JOIN {$wpdb->prefix}wc_order_coupon_lookup cl ON o.id = cl.order_id
                         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = cl.coupon_id AND pm.meta_key = '_recart_ai_coupon' AND pm.meta_value = '1'
                         WHERE o.status IN ('wc-completed', 'wc-processing')
                         AND o.date_created_gmt >= %s AND o.date_created_gmt < %s",
                        $range['start'],
                        $range['end']
                    )
                );
            } else {
                $total_orders = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing') AND post_date >= %s AND post_date < %s",
                        $range['start'],
                        $range['end']
                    )
                );

                $total_revenue = (float) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(pm.meta_value), 0)
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                         WHERE p.post_type = 'shop_order'
                         AND p.post_status IN ('wc-completed', 'wc-processing')
                         AND p.post_date >= %s AND p.post_date < %s",
                        $range['start'],
                        $range['end']
                    )
                );

                $recart_data = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.ID) as cnt, COALESCE(SUM(pm_total.meta_value), 0) as rev
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                         INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id AND oi.order_item_type = 'coupon'
                         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'coupon_id'
                         INNER JOIN {$wpdb->postmeta} pm_recart ON oim.meta_value = pm_recart.post_id AND pm_recart.meta_key = '_recart_ai_coupon' AND pm_recart.meta_value = '1'
                         WHERE p.post_type = 'shop_order'
                         AND p.post_status IN ('wc-completed', 'wc-processing')
                         AND p.post_date >= %s AND p.post_date < %s",
                        $range['start'],
                        $range['end']
                    )
                );
            }

            $recart_orders  = $recart_data ? (int) $recart_data->cnt : 0;
            $recart_revenue = $recart_data ? (float) $recart_data->rev : 0;
            $recart_percent = $total_revenue > 0 ? round( ( $recart_revenue / $total_revenue ) * 100, 1 ) : 0;

            $periods[] = array(
                'label'          => $range['label'],
                'total_orders'   => $total_orders,
                'total_revenue'  => $total_revenue,
                'recart_orders'  => $recart_orders,
                'recart_revenue' => $recart_revenue,
                'recart_percent' => $recart_percent,
            );
        }

        return $periods;
    }

    /**
     * Get top products from recovered orders.
     *
     * @param string $since_date Start date.
     * @return array Top products data.
     */
    private function get_top_recovered_products( string $since_date ): array {
        global $wpdb;

        // Get order IDs that used ReCart coupons
        $order_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id AND oi.order_item_type = 'coupon'
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'coupon_id'
                 INNER JOIN {$wpdb->postmeta} pm_recart ON oim.meta_value = pm_recart.post_id AND pm_recart.meta_key = '_recart_ai_coupon' AND pm_recart.meta_value = '1'
                 WHERE p.post_type = 'shop_order'
                 AND p.post_status IN ('wc-completed', 'wc-processing')
                 AND p.post_date >= %s
                 LIMIT 100",
                $since_date
            )
        );

        if ( empty( $order_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT oim_pid.meta_value as product_id, COUNT(*) as cnt, SUM(oim_total.meta_value) as revenue
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
                 WHERE oi.order_id IN ({$placeholders})
                 AND oi.order_item_type = 'line_item'
                 GROUP BY oim_pid.meta_value
                 ORDER BY cnt DESC
                 LIMIT 10",
                ...$order_ids
            )
        );

        $top = array();
        foreach ( $products as $row ) {
            $product = wc_get_product( (int) $row->product_id );
            $top[] = array(
                'name'    => $product ? $product->get_name() : __( 'Deleted Product', 'recart-ai' ) . ' #' . $row->product_id,
                'count'   => (int) $row->cnt,
                'revenue' => (float) $row->revenue,
                'url'     => $product ? get_edit_post_link( $product->get_id() ) : '',
            );
        }

        return $top;
    }

    /**
     * Render settings page with tabs.
     */
    public function render_settings_page(): void {
        $active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'popup' ) );
        $tabs = array(
            'popup'      => __( 'Popup', 'recart-ai' ),
            'antiabuse'  => __( 'Anti-Abuse', 'recart-ai' ),
            'webhook'    => __( 'Webhook', 'recart-ai' ),
            'appearance' => __( 'Appearance', 'recart-ai' ),
            'general'    => __( 'General', 'recart-ai' ),
        );

        ?>
        <div class="wrap recart-ai-admin">
            <h1><?php esc_html_e( 'ReCart AI Settings', 'recart-ai' ); ?></h1>

            <nav class="nav-tab-wrapper recart-ai-tabs">
                <?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=recart-ai-settings' ) ) ); ?>"
                       class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_name ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="recart-ai-settings-content">
                <?php
                switch ( $active_tab ) {
                    case 'popup':
                        $this->render_popup_settings();
                        break;
                    case 'antiabuse':
                        $this->render_antiabuse_settings();
                        break;
                    case 'webhook':
                        $this->render_webhook_settings();
                        break;
                    case 'appearance':
                        $this->render_appearance_settings();
                        break;
                    case 'general':
                        $this->render_general_settings();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render popup settings tab.
     */
    private function render_popup_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_popup' ); ?>
            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Popup', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_popup_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_popup_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Show exit-intent popup to visitors with items in cart', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Popup Title', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_popup_title" class="large-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_title' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Popup Message', 'recart-ai' ); ?></th>
                    <td>
                        <textarea name="recart_ai_popup_message" class="large-text" rows="3"><?php echo esc_textarea( get_option( 'recart_ai_popup_message' ) ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Button Text', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_popup_button_text" class="regular-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_button_text' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Show Cart Products', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_popup_show_products" value="1"
                                <?php checked( get_option( 'recart_ai_popup_show_products' ), '1' ); ?> />
                            <?php esc_html_e( 'Display product images from cart in the popup', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Show Phone Field', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_popup_show_phone" value="1"
                                <?php checked( get_option( 'recart_ai_popup_show_phone' ), '1' ); ?> />
                            <?php esc_html_e( 'Show optional phone number field', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Display Delay (seconds)', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_popup_delay" class="small-text" min="0" max="30"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_delay', 0 ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Delay in seconds after exit-intent is detected before showing popup.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Mobile Support', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_popup_mobile_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_popup_mobile_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Enable exit-intent detection on mobile devices', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render anti-abuse settings tab.
     */
    private function render_antiabuse_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_antiabuse' ); ?>
            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Anti-Abuse', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_antiabuse_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_antiabuse_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Enable fingerprint-based anti-abuse protection', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Minimum Cart Total', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_min_cart_total" class="small-text" min="0" step="0.01"
                               value="<?php echo esc_attr( get_option( 'recart_ai_min_cart_total', 150 ) ); ?>" />
                        <span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                        <p class="description"><?php esc_html_e( 'Minimum cart value required to receive a discount code.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Discount Type', 'recart-ai' ); ?></th>
                    <td>
                        <select name="recart_ai_discount_type">
                            <option value="percent" <?php selected( get_option( 'recart_ai_discount_type' ), 'percent' ); ?>><?php esc_html_e( 'Percentage', 'recart-ai' ); ?></option>
                            <option value="fixed_cart" <?php selected( get_option( 'recart_ai_discount_type' ), 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed Amount', 'recart-ai' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Discount Value', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_discount_value" class="small-text" min="0" step="0.01"
                               value="<?php echo esc_attr( get_option( 'recart_ai_discount_value', 10 ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Percentage or fixed amount of the discount.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Free Delivery Option', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_free_delivery_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_free_delivery_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Offer free delivery as first intervention (before percentage discount)', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Free Delivery Threshold', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_free_delivery_threshold" class="small-text" min="0" step="0.01"
                               value="<?php echo esc_attr( get_option( 'recart_ai_free_delivery_threshold', 200 ) ); ?>" />
                        <span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                        <p class="description"><?php esc_html_e( 'Carts below this value get free delivery offer. Above this value get percentage discount.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Coupon Expiry', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_coupon_expiry_hours" class="small-text" min="1" max="168"
                               value="<?php echo esc_attr( get_option( 'recart_ai_coupon_expiry_hours', 48 ) ); ?>" />
                        <span><?php esc_html_e( 'hours', 'recart-ai' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Max Codes per 30 Days', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_max_codes_30days" class="small-text" min="1" max="10"
                               value="<?php echo esc_attr( get_option( 'recart_ai_max_codes_30days', 1 ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Maximum number of discount codes per fingerprint in 30 days.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Anomaly Detection Threshold', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_anomaly_threshold" class="small-text" min="1" max="20"
                               value="<?php echo esc_attr( get_option( 'recart_ai_anomaly_threshold', 3 ) ); ?>" />
                        <span><?php esc_html_e( 'abandonments in', 'recart-ai' ); ?></span>
                        <input type="number" name="recart_ai_anomaly_days" class="small-text" min="1" max="30"
                               value="<?php echo esc_attr( get_option( 'recart_ai_anomaly_days', 7 ) ); ?>" />
                        <span><?php esc_html_e( 'days', 'recart-ai' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Block fingerprint and show "contact us" message when threshold is exceeded.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Minimum Session Time', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_min_session_time" class="small-text" min="0" max="300"
                               value="<?php echo esc_attr( get_option( 'recart_ai_min_session_time', 30 ) ); ?>" />
                        <span><?php esc_html_e( 'seconds', 'recart-ai' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Minimum time on site before a visitor qualifies for a discount (alternative to second contact).', 'recart-ai' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render webhook settings tab.
     */
    private function render_webhook_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_webhook' ); ?>
            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Webhook', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_webhook_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_webhook_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Send abandoned cart data to external endpoint', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Webhook URL', 'recart-ai' ); ?></th>
                    <td>
                        <input type="url" name="recart_ai_webhook_url" class="large-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_webhook_url' ) ); ?>"
                               placeholder="https://your-n8n-instance.com/webhook/recart" />
                        <p class="description"><?php esc_html_e( 'URL of your n8n, Langflow, or ReCart AI backend webhook endpoint.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Webhook Secret', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_webhook_secret" class="large-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_webhook_secret' ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Secret key for HMAC-SHA256 signature verification.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Abandonment Timeout', 'recart-ai' ); ?></th>
                    <td>
                        <input type="number" name="recart_ai_abandonment_timeout" class="small-text" min="5" max="120"
                               value="<?php echo esc_attr( get_option( 'recart_ai_abandonment_timeout', 30 ) ); ?>" />
                        <span><?php esc_html_e( 'minutes', 'recart-ai' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Time after last cart activity before sending abandonment webhook.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><h3><?php esc_html_e( 'Formspree Integration', 'recart-ai' ); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Formspree', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_formspree_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_formspree_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Send abandoned cart notifications via Formspree', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Formspree Endpoint', 'recart-ai' ); ?></th>
                    <td>
                        <input type="url" name="recart_ai_formspree_endpoint" class="large-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_formspree_endpoint' ) ); ?>"
                               placeholder="https://formspree.io/f/your-form-id" />
                        <p class="description"><?php esc_html_e( 'Your Formspree form endpoint URL.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Webhook Payload Example', 'recart-ai' ); ?></h3>
            <pre class="recart-ai-code-block">{
    "event": "cart_abandoned",
    "fingerprint_id": "abc123def456",
    "email": "customer@example.com",
    "phone": "+48123456789",
    "cart_items": [
        {
            "product_id": 123,
            "name": "Product Name",
            "price": 99.99,
            "quantity": 1,
            "image": "https://shop.com/image.jpg"
        }
    ],
    "cart_total": 99.99,
    "currency": "PLN",
    "session_id": "sess_abc123",
    "abandon_time": "2026-01-15 14:30:00",
    "is_first_contact": false,
    "store_url": "https://shop.com",
    "store_name": "My Shop",
    "timestamp": "2026-01-15T14:30:00+01:00"
}</pre>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render appearance settings tab.
     */
    private function render_appearance_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_appearance' ); ?>
            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Primary Color', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_popup_color_primary" class="recart-ai-color-picker"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_color_primary', '#4F46E5' ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Button and accent color.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Background Color', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_popup_color_bg" class="recart-ai-color-picker"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_color_bg', '#ffffff' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Text Color', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_popup_color_text" class="recart-ai-color-picker"
                               value="<?php echo esc_attr( get_option( 'recart_ai_popup_color_text', '#1f2937' ) ); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render general settings tab.
     */
    private function render_general_settings(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_general' ); ?>
            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Plugin', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_enabled" value="1"
                                <?php checked( get_option( 'recart_ai_enabled' ), '1' ); ?> />
                            <?php esc_html_e( 'Enable ReCart AI cart recovery system', 'recart-ai' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'GDPR Mode', 'recart-ai' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="recart_ai_gdpr_mode" value="1"
                                <?php checked( get_option( 'recart_ai_gdpr_mode' ), '1' ); ?> />
                            <?php esc_html_e( 'Disable browser fingerprinting (use session-based tracking only)', 'recart-ai' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When enabled, FingerprintJS will not be loaded. Anti-abuse protection will be less effective.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr />
        <h3><?php esc_html_e( 'Plugin Information', 'recart-ai' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Version', 'recart-ai' ); ?></th>
                <td><?php echo esc_html( RECART_AI_VERSION ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Database Version', 'recart-ai' ); ?></th>
                <td><?php echo esc_html( get_option( 'recart_ai_db_version', 'N/A' ) ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Website', 'recart-ai' ); ?></th>
                <td><a href="https://asystent.io" target="_blank">asystent.io</a></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render blacklist management page.
     */
    public function render_blacklist_page(): void {
        $antiabuse = new Recart_AI_Antiabuse( new Recart_AI_Fingerprint(), $this->logger );
        $blacklist = $antiabuse->get_blacklist();

        ?>
        <div class="wrap recart-ai-admin">
            <h1><?php esc_html_e( 'ReCart AI Blacklist', 'recart-ai' ); ?></h1>

            <?php if ( isset( $_GET['message'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        $msg = sanitize_text_field( wp_unslash( $_GET['message'] ) );
                        if ( $msg === 'added' ) {
                            esc_html_e( 'Entry added to blacklist.', 'recart-ai' );
                        } elseif ( $msg === 'removed' ) {
                            esc_html_e( 'Entry removed from blacklist.', 'recart-ai' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="recart-ai-blacklist-form">
                <h3><?php esc_html_e( 'Add to Blacklist', 'recart-ai' ); ?></h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="recart_ai_add_blacklist" />
                    <?php wp_nonce_field( 'recart_ai_blacklist_action' ); ?>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Type', 'recart-ai' ); ?></th>
                            <td>
                                <select name="blacklist_type">
                                    <option value="fingerprint"><?php esc_html_e( 'Fingerprint ID', 'recart-ai' ); ?></option>
                                    <option value="ip"><?php esc_html_e( 'IP Hash', 'recart-ai' ); ?></option>
                                    <option value="email"><?php esc_html_e( 'Email', 'recart-ai' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Value', 'recart-ai' ); ?></th>
                            <td>
                                <input type="text" name="blacklist_value" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Reason', 'recart-ai' ); ?></th>
                            <td>
                                <input type="text" name="blacklist_reason" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Add to Blacklist', 'recart-ai' ), 'primary' ); ?>
                </form>
            </div>

            <h3><?php esc_html_e( 'Current Blacklist', 'recart-ai' ); ?></h3>
            <?php if ( empty( $blacklist ) ) : ?>
                <p><?php esc_html_e( 'No entries in the blacklist.', 'recart-ai' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Reason', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Added', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'recart-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $blacklist as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry->id ); ?></td>
                                <td><span class="recart-ai-badge recart-ai-badge-<?php echo esc_attr( $entry->type ); ?>"><?php echo esc_html( $entry->type ); ?></span></td>
                                <td><code><?php echo esc_html( $entry->value ); ?></code></td>
                                <td><?php echo esc_html( $entry->reason ?: '—' ); ?></td>
                                <td><?php echo esc_html( $entry->created_at ); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="recart_ai_remove_blacklist" />
                                        <input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ); ?>" />
                                        <?php wp_nonce_field( 'recart_ai_blacklist_action' ); ?>
                                        <button type="submit" class="button button-small button-link-delete"
                                                onclick="return confirm('<?php esc_attr_e( 'Remove this entry?', 'recart-ai' ); ?>');">
                                            <?php esc_html_e( 'Remove', 'recart-ai' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render logs page.
     */
    public function render_logs_page(): void {
        $page       = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $event_type = sanitize_text_field( wp_unslash( $_GET['event_type'] ?? '' ) );

        $logs = $this->logger->get_logs( $page, 50, $event_type ?: null );

        // Get unique event types for filter
        global $wpdb;
        $table = $wpdb->prefix . 'recart_logs';
        $event_types = $wpdb->get_col( "SELECT DISTINCT event_type FROM {$table} ORDER BY event_type ASC" );

        ?>
        <div class="wrap recart-ai-admin">
            <h1><?php esc_html_e( 'ReCart AI Logs', 'recart-ai' ); ?></h1>

            <div class="recart-ai-log-filters">
                <form method="get">
                    <input type="hidden" name="page" value="recart-ai-logs" />
                    <select name="event_type">
                        <option value=""><?php esc_html_e( 'All Events', 'recart-ai' ); ?></option>
                        <?php foreach ( $event_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $event_type, $type ); ?>>
                                <?php echo esc_html( $type ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button( __( 'Filter', 'recart-ai' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>

            <?php if ( empty( $logs['items'] ) ) : ?>
                <p><?php esc_html_e( 'No log entries found.', 'recart-ai' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px;"><?php esc_html_e( 'ID', 'recart-ai' ); ?></th>
                            <th style="width:150px;"><?php esc_html_e( 'Event', 'recart-ai' ); ?></th>
                            <th style="width:130px;"><?php esc_html_e( 'Fingerprint', 'recart-ai' ); ?></th>
                            <th style="width:180px;"><?php esc_html_e( 'Email', 'recart-ai' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'recart-ai' ); ?></th>
                            <th style="width:160px;"><?php esc_html_e( 'Date', 'recart-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs['items'] as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log->id ); ?></td>
                                <td><span class="recart-ai-badge recart-ai-event-<?php echo esc_attr( $log->event_type ); ?>"><?php echo esc_html( $log->event_type ); ?></span></td>
                                <td><code title="<?php echo esc_attr( $log->fingerprint_id ); ?>"><?php echo esc_html( $log->fingerprint_id ? substr( $log->fingerprint_id, 0, 12 ) . '...' : '—' ); ?></code></td>
                                <td><?php echo esc_html( $log->email ?: '—' ); ?></td>
                                <td><?php echo esc_html( $log->message ); ?></td>
                                <td><?php echo esc_html( $log->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $logs['pages'] > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = array(
                                'base'    => add_query_arg( 'paged', '%#%' ),
                                'format'  => '',
                                'current' => $page,
                                'total'   => $logs['pages'],
                            );
                            echo wp_kses_post( paginate_links( $pagination_args ) );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle add to blacklist form submission.
     */
    public function handle_add_blacklist(): void {
        if ( ! check_admin_referer( 'recart_ai_blacklist_action' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $type   = sanitize_text_field( wp_unslash( $_POST['blacklist_type'] ?? '' ) );
        $value  = sanitize_text_field( wp_unslash( $_POST['blacklist_value'] ?? '' ) );
        $reason = sanitize_text_field( wp_unslash( $_POST['blacklist_reason'] ?? '' ) );

        if ( ! in_array( $type, array( 'fingerprint', 'ip', 'email' ), true ) || empty( $value ) ) {
            wp_redirect( admin_url( 'admin.php?page=recart-ai-blacklist&message=error' ) );
            exit;
        }

        $antiabuse = new Recart_AI_Antiabuse( new Recart_AI_Fingerprint(), $this->logger );
        $antiabuse->add_to_blacklist( $type, $value, $reason );

        wp_redirect( admin_url( 'admin.php?page=recart-ai-blacklist&message=added' ) );
        exit;
    }

    /**
     * Handle remove from blacklist.
     */
    public function handle_remove_blacklist(): void {
        if ( ! check_admin_referer( 'recart_ai_blacklist_action' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );

        if ( $entry_id > 0 ) {
            $antiabuse = new Recart_AI_Antiabuse( new Recart_AI_Fingerprint(), $this->logger );
            $antiabuse->remove_from_blacklist( $entry_id );
        }

        wp_redirect( admin_url( 'admin.php?page=recart-ai-blacklist&message=removed' ) );
        exit;
    }

    /**
     * Add settings link to plugins page.
     */
    public function add_settings_link( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=recart-ai-settings' ),
            __( 'Settings', 'recart-ai' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }
}

<?php
/**
 * License management class.
 *
 * Handles license key verification against the remote license server,
 * caches results, sends usage heartbeats, and enforces plan limits.
 * The plugin will NOT function without a valid, active license.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_License {

    /** @var string Default license server URL (Vercel deployment). */
    private const DEFAULT_SERVER_URL = 'https://recart-license-server.vercel.app';

    /** @var int Cache duration in seconds (12 hours). */
    private const CACHE_DURATION = 43200;

    /** @var int Grace period in seconds when server is unreachable (72 hours). */
    private const GRACE_PERIOD = 259200;

    /**
     * Initialize license hooks.
     */
    public function init(): void {
        // Admin settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Heartbeat cron
        add_action( 'recart_ai_license_heartbeat', array( $this, 'send_heartbeat' ) );

        // Schedule heartbeat if not scheduled
        if ( ! wp_next_scheduled( 'recart_ai_license_heartbeat' ) ) {
            wp_schedule_event( time(), 'daily', 'recart_ai_license_heartbeat' );
        }

        // Admin notice if license is invalid
        add_action( 'admin_notices', array( $this, 'show_license_notices' ) );
    }

    /**
     * Register license settings.
     */
    public function register_settings(): void {
        register_setting( 'recart_ai_license', 'recart_ai_license_key', array(
            'sanitize_callback' => array( $this, 'sanitize_and_verify_license' ),
        ) );
        register_setting( 'recart_ai_license', 'recart_ai_license_server_url', array(
            'sanitize_callback' => 'esc_url_raw',
        ) );
    }

    /**
     * Sanitize and immediately verify a license key when saved.
     */
    public function sanitize_and_verify_license( string $key ): string {
        $key = sanitize_text_field( trim( $key ) );

        if ( empty( $key ) ) {
            delete_transient( 'recart_ai_license_data' );
            delete_option( 'recart_ai_license_valid' );
            return $key;
        }

        // Verify immediately
        $result = $this->verify_remote( $key );

        if ( $result && $result['valid'] ) {
            update_option( 'recart_ai_license_valid', '1' );
            update_option( 'recart_ai_license_plan', $result['plan'] );
            update_option( 'recart_ai_license_plan_name', $result['plan_name'] ?? $result['plan'] );
            update_option( 'recart_ai_license_popup_limit', $result['popup_limit'] );
            update_option( 'recart_ai_license_features', $result['features'] ?? array() );
            update_option( 'recart_ai_license_expires', $result['current_period_end'] ?? '' );
            set_transient( 'recart_ai_license_data', $result, self::CACHE_DURATION );
            update_option( 'recart_ai_license_last_check', time() );

            add_settings_error( 'recart_ai_license_key', 'license_valid', __( 'License activated successfully!', 'recart-ai' ) . ' Plan: ' . ( $result['plan_name'] ?? $result['plan'] ), 'success' );
        } else {
            update_option( 'recart_ai_license_valid', '0' );
            delete_transient( 'recart_ai_license_data' );

            $error_msg = $result['error'] ?? __( 'Could not verify license. Please check your key.', 'recart-ai' );
            add_settings_error( 'recart_ai_license_key', 'license_invalid', $error_msg, 'error' );
        }

        return $key;
    }

    /**
     * Check if the plugin has a valid, active license.
     *
     * This is the main "firewall" method. Called before any plugin functionality.
     */
    public function is_valid(): bool {
        $license_key = get_option( 'recart_ai_license_key', '' );

        if ( empty( $license_key ) ) {
            return false;
        }

        // Check cached result first
        $cached = get_transient( 'recart_ai_license_data' );
        if ( $cached && isset( $cached['valid'] ) && $cached['valid'] === true ) {
            return true;
        }

        // Cache expired - try to re-verify
        $result = $this->verify_remote( $license_key );

        if ( $result && $result['valid'] ) {
            update_option( 'recart_ai_license_valid', '1' );
            update_option( 'recart_ai_license_plan', $result['plan'] );
            update_option( 'recart_ai_license_popup_limit', $result['popup_limit'] );
            update_option( 'recart_ai_license_features', $result['features'] ?? array() );
            set_transient( 'recart_ai_license_data', $result, self::CACHE_DURATION );
            update_option( 'recart_ai_license_last_check', time() );
            return true;
        }

        // Server unreachable? Check grace period
        if ( $result === null ) {
            $last_check = (int) get_option( 'recart_ai_license_last_check', 0 );
            $was_valid  = get_option( 'recart_ai_license_valid' ) === '1';

            if ( $was_valid && $last_check > 0 && ( time() - $last_check ) < self::GRACE_PERIOD ) {
                return true; // Grace period active
            }
        }

        // License is invalid
        update_option( 'recart_ai_license_valid', '0' );
        return false;
    }

    /**
     * Get the current plan ID.
     */
    public function get_plan(): string {
        return get_option( 'recart_ai_license_plan', '' );
    }

    /**
     * Get the popup limit for the current plan.
     */
    public function get_popup_limit(): int {
        return (int) get_option( 'recart_ai_license_popup_limit', 0 );
    }

    /**
     * Get enabled features for the current plan.
     */
    public function get_features(): array {
        $features = get_option( 'recart_ai_license_features', array() );
        return is_array( $features ) ? $features : array();
    }

    /**
     * Check if a specific feature is available in the current plan.
     */
    public function has_feature( string $feature ): bool {
        return in_array( $feature, $this->get_features(), true );
    }

    /**
     * Check if the popup limit has been reached for this billing period.
     */
    public function is_popup_limit_reached(): bool {
        $limit = $this->get_popup_limit();

        // -1 means unlimited
        if ( $limit === -1 ) {
            return false;
        }

        $shown = $this->get_current_period_popup_count();
        return $shown >= $limit;
    }

    /**
     * Get the number of popups shown in the current billing period.
     */
    public function get_current_period_popup_count(): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_logs';

        // Count popups since the start of the current billing period
        $period_start = $this->get_current_period_start();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_type = 'popup_shown' AND created_at >= %s",
                $period_start
            )
        );

        return (int) $count;
    }

    /**
     * Increment popup counter (called when popup is shown).
     */
    public function record_popup_shown(): void {
        $count = (int) get_option( 'recart_ai_popups_this_period', 0 );
        update_option( 'recart_ai_popups_this_period', $count + 1 );
    }

    /**
     * Get the start of the current billing period.
     */
    private function get_current_period_start(): string {
        // Use the 1st of the current month as period start
        return gmdate( 'Y-m-01 00:00:00' );
    }

    /**
     * Verify license against the remote server.
     *
     * @return array|null Array with result data, or null if server unreachable.
     */
    private function verify_remote( string $license_key ): ?array {
        $server_url = $this->get_server_url();
        $domain     = $this->get_site_domain();

        $response = wp_remote_post( $server_url . '/api/verify', array(
            'body'    => wp_json_encode( array(
                'license_key'    => $license_key,
                'domain'         => $domain,
                'plugin_version' => RECART_AI_VERSION,
            ) ),
            'headers' => array(
                'Content-Type'   => 'application/json',
                'X-License-Key'  => $license_key,
                'X-ReCart-Domain' => $domain,
            ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            // Server unreachable
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 || ! is_array( $data ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Send usage heartbeat to the license server.
     */
    public function send_heartbeat(): void {
        $license_key = get_option( 'recart_ai_license_key', '' );

        if ( empty( $license_key ) ) {
            return;
        }

        $server_url = $this->get_server_url();
        $domain     = $this->get_site_domain();

        // Gather usage stats
        $stats = $this->gather_usage_stats();

        $response = wp_remote_post( $server_url . '/api/heartbeat', array(
            'body'    => wp_json_encode( array(
                'license_key' => $license_key,
                'domain'      => $domain,
                'stats'       => $stats,
            ) ),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return;
        }

        // Handle server response
        if ( isset( $data['action'] ) && $data['action'] === 'deactivate' ) {
            update_option( 'recart_ai_license_valid', '0' );
            delete_transient( 'recart_ai_license_data' );
        }

        if ( isset( $data['warning'] ) ) {
            update_option( 'recart_ai_license_warning', $data['warning'] );
        } else {
            delete_option( 'recart_ai_license_warning' );
        }

        if ( isset( $data['popups_remaining'] ) ) {
            update_option( 'recart_ai_popups_remaining', $data['popups_remaining'] );
        }
    }

    /**
     * Gather current usage statistics.
     */
    private function gather_usage_stats(): array {
        global $wpdb;

        $period_start  = $this->get_current_period_start();
        $logs_table    = $wpdb->prefix . 'recart_logs';
        $carts_table   = $wpdb->prefix . 'recart_abandoned_carts';
        $coupons_table = $wpdb->prefix . 'recart_coupons';
        $fp_table      = $wpdb->prefix . 'recart_fingerprints';

        $popups_shown = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'popup_shown' AND created_at >= %s", $period_start )
        );

        $emails_captured = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$logs_table} WHERE event_type IN ('email_captured','eligible','first_contact') AND created_at >= %s", $period_start )
        );

        $coupons_generated = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$coupons_table} WHERE created_at >= %s", $period_start )
        );

        $carts_recovered = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$carts_table} WHERE recovered = 1 AND abandon_time >= %s", $period_start )
        );

        $revenue_recovered = (float) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(SUM(cart_total), 0) FROM {$carts_table} WHERE recovered = 1 AND abandon_time >= %s", $period_start )
        );

        $active_fingerprints = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$fp_table} WHERE updated_at >= %s", $period_start )
        );

        // Total store revenue (from WooCommerce)
        $activation_date = get_option( 'recart_ai_activated', 0 );
        $since = $activation_date ? gmdate( 'Y-m-d H:i:s', (int) $activation_date ) : $period_start;

        $total_revenue = 0;
        $orders_table = $wpdb->prefix . 'wc_orders';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$orders_table}'" ) === $orders_table ) {
            $total_revenue = (float) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM {$orders_table} WHERE status IN ('wc-completed','wc-processing') AND date_created_gmt >= %s",
                    $since
                )
            );
        }

        return array(
            'popups_shown'        => $popups_shown,
            'emails_captured'     => $emails_captured,
            'coupons_generated'   => $coupons_generated,
            'carts_recovered'     => $carts_recovered,
            'revenue_recovered'   => $revenue_recovered,
            'total_revenue'       => $total_revenue,
            'active_fingerprints' => $active_fingerprints,
            'plugin_version'      => RECART_AI_VERSION,
            'wp_version'          => get_bloginfo( 'version' ),
            'wc_version'          => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            'php_version'         => PHP_VERSION,
        );
    }

    /**
     * Show admin notices about license status.
     */
    public function show_license_notices(): void {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'recart-ai' ) === false ) {
            // Only show on ReCart AI pages (except the main notice)
            $license_key = get_option( 'recart_ai_license_key', '' );
            if ( empty( $license_key ) ) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    wp_kses(
                        __( '<strong>ReCart AI</strong> wymaga aktywnej licencji. <a href="%s">Wprowadź klucz licencji</a>, aby aktywować wtyczkę.', 'recart-ai' ),
                        array( 'strong' => array(), 'a' => array( 'href' => array() ) )
                    ),
                    esc_url( admin_url( 'admin.php?page=recart-ai-settings&tab=license' ) )
                );
                echo '</p></div>';
            }
            return;
        }

        // Warning about approaching limit
        $warning = get_option( 'recart_ai_license_warning', '' );
        if ( $warning === 'APPROACHING_LIMIT' ) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e( 'ReCart AI: Zbliżasz się do limitu popupów w tym okresie rozliczeniowym. Rozważ upgrade planu.', 'recart-ai' );
            echo '</p></div>';
        } elseif ( $warning === 'LIMIT_REACHED' ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'ReCart AI: Osiągnięto limit popupów. Popupy zostały wstrzymane do następnego okresu rozliczeniowego. Rozważ upgrade planu.', 'recart-ai' );
            echo '</p></div>';
        }
    }

    /**
     * Get the license server URL.
     */
    private function get_server_url(): string {
        $custom_url = get_option( 'recart_ai_license_server_url', '' );
        return ! empty( $custom_url ) ? rtrim( $custom_url, '/' ) : self::DEFAULT_SERVER_URL;
    }

    /**
     * Get the normalized site domain.
     */
    private function get_site_domain(): string {
        $url = home_url();
        $parsed = wp_parse_url( $url );
        return $parsed['host'] ?? $url;
    }

    /**
     * Render license settings tab content.
     */
    public function render_settings(): void {
        $license_key  = get_option( 'recart_ai_license_key', '' );
        $is_valid     = get_option( 'recart_ai_license_valid' ) === '1';
        $plan_name    = get_option( 'recart_ai_license_plan_name', '' );
        $plan         = get_option( 'recart_ai_license_plan', '' );
        $popup_limit  = (int) get_option( 'recart_ai_license_popup_limit', 0 );
        $expires      = get_option( 'recart_ai_license_expires', '' );
        $last_check   = (int) get_option( 'recart_ai_license_last_check', 0 );
        $popups_count = $this->get_current_period_popup_count();

        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'recart_ai_license' ); ?>

            <?php if ( $is_valid && ! empty( $plan ) ) : ?>
                <div class="recart-ai-license-status recart-ai-license-active">
                    <div class="recart-ai-license-badge">&#10003; <?php esc_html_e( 'License Active', 'recart-ai' ); ?></div>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Plan', 'recart-ai' ); ?></th>
                            <td><strong><?php echo esc_html( $plan_name ); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Popup Limit', 'recart-ai' ); ?></th>
                            <td>
                                <?php if ( $popup_limit === -1 ) : ?>
                                    <strong><?php esc_html_e( 'Unlimited', 'recart-ai' ); ?></strong>
                                <?php else : ?>
                                    <strong><?php echo esc_html( number_format_i18n( $popups_count ) ); ?></strong>
                                    / <?php echo esc_html( number_format_i18n( $popup_limit ) ); ?>
                                    <?php esc_html_e( 'this month', 'recart-ai' ); ?>
                                    <div class="recart-ai-usage-bar">
                                        <div class="recart-ai-usage-fill" style="width: <?php echo esc_attr( min( 100, ( $popups_count / max( 1, $popup_limit ) ) * 100 ) ); ?>%;"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ( $expires ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Current Period Ends', 'recart-ai' ); ?></th>
                            <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $expires ) ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php esc_html_e( 'Last Verified', 'recart-ai' ); ?></th>
                            <td><?php echo $last_check ? esc_html( wp_date( 'd.m.Y H:i', $last_check ) ) : '—'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Domain', 'recart-ai' ); ?></th>
                            <td><code><?php echo esc_html( $this->get_site_domain() ); ?></code></td>
                        </tr>
                    </table>
                </div>
            <?php elseif ( ! empty( $license_key ) ) : ?>
                <div class="recart-ai-license-status recart-ai-license-inactive">
                    <div class="recart-ai-license-badge recart-ai-license-badge-error">&#10007; <?php esc_html_e( 'License Inactive', 'recart-ai' ); ?></div>
                    <p><?php esc_html_e( 'Twoja licencja jest nieaktywna. Sprawdź klucz lub status subskrypcji.', 'recart-ai' ); ?></p>
                </div>
            <?php else : ?>
                <div class="recart-ai-license-status recart-ai-license-none">
                    <p><?php esc_html_e( 'Wprowadź klucz licencji, aby aktywować wtyczkę ReCart AI. Klucz otrzymasz po zakupie subskrypcji na', 'recart-ai' ); ?>
                    <a href="https://asystent.io" target="_blank">asystent.io</a>.</p>
                </div>
            <?php endif; ?>

            <table class="form-table recart-ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'License Key', 'recart-ai' ); ?></th>
                    <td>
                        <input type="text" name="recart_ai_license_key" class="large-text"
                               value="<?php echo esc_attr( $license_key ); ?>"
                               placeholder="sub_xxxxxxxxxxxxxxxxx" />
                        <p class="description"><?php esc_html_e( 'Twój klucz licencji (ID subskrypcji Stripe).', 'recart-ai' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'License Server URL', 'recart-ai' ); ?></th>
                    <td>
                        <input type="url" name="recart_ai_license_server_url" class="large-text"
                               value="<?php echo esc_attr( get_option( 'recart_ai_license_server_url', '' ) ); ?>"
                               placeholder="<?php echo esc_attr( self::DEFAULT_SERVER_URL ); ?>" />
                        <p class="description"><?php esc_html_e( 'Pozostaw puste, aby użyć domyślnego serwera.', 'recart-ai' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Activate License', 'recart-ai' ) ); ?>
        </form>
        <?php
    }
}

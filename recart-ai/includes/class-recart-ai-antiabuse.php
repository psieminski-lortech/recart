<?php
/**
 * Anti-Abuse protection class.
 *
 * Implements fingerprint-based abuse detection, anomaly detection,
 * and blacklist management.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Antiabuse {

    private Recart_AI_Fingerprint $fingerprint;
    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Fingerprint $fingerprint, Recart_AI_Logger $logger ) {
        $this->fingerprint = $fingerprint;
        $this->logger      = $logger;
    }

    /**
     * Check if a visitor is eligible for a discount code.
     *
     * Rules:
     * 1. First contact → ZERO discount code
     * 2. Discount only on second contact OR after 30+ seconds on site
     * 3. Max 1 code per fingerprint in 30 days
     * 4. Cart must meet minimum total
     * 5. Anomaly detection: >3 abandonments in 7 days → block
     * 6. Not on blacklist
     *
     * @return array{eligible: bool, reason: string, action: string}
     */
    public function check_eligibility( string $fingerprint_id, float $cart_total ): array {
        // Check if anti-abuse is enabled
        if ( get_option( 'recart_ai_antiabuse_enabled' ) !== '1' ) {
            return array(
                'eligible' => true,
                'reason'   => 'anti_abuse_disabled',
                'action'   => 'allow',
            );
        }

        // Check blacklist
        if ( $this->is_blacklisted( $fingerprint_id ) ) {
            $this->logger->log( 'blocked', 'Fingerprint is blacklisted', $fingerprint_id );
            return array(
                'eligible' => false,
                'reason'   => 'blacklisted',
                'action'   => 'block',
            );
        }

        // Check IP blacklist
        if ( $this->is_ip_blacklisted() ) {
            $this->logger->log( 'blocked', 'IP is blacklisted', $fingerprint_id );
            return array(
                'eligible' => false,
                'reason'   => 'ip_blacklisted',
                'action'   => 'block',
            );
        }

        // Get fingerprint record
        $record = $this->fingerprint->get_by_id( $fingerprint_id );

        if ( ! $record ) {
            return array(
                'eligible' => false,
                'reason'   => 'first_contact',
                'action'   => 'collect_email_only',
            );
        }

        // Check if blocked
        if ( (int) $record->is_blocked === 1 ) {
            return array(
                'eligible' => false,
                'reason'   => 'fingerprint_blocked',
                'action'   => 'show_contact_message',
            );
        }

        // Anomaly detection
        $anomaly_threshold = (int) get_option( 'recart_ai_anomaly_threshold', 3 );
        $anomaly_days      = (int) get_option( 'recart_ai_anomaly_days', 7 );
        $recent_abandonments = $this->fingerprint->get_recent_abandonments( $fingerprint_id, $anomaly_days );

        if ( $recent_abandonments > $anomaly_threshold ) {
            $this->fingerprint->block( $fingerprint_id );
            $this->logger->log(
                'anomaly_detected',
                sprintf( 'Fingerprint blocked: %d abandonments in %d days', $recent_abandonments, $anomaly_days ),
                $fingerprint_id,
                $record->email
            );
            return array(
                'eligible' => false,
                'reason'   => 'anomaly_detected',
                'action'   => 'show_contact_message',
            );
        }

        // Check minimum cart total
        $min_cart_total = (float) get_option( 'recart_ai_min_cart_total', 150 );
        if ( $cart_total < $min_cart_total ) {
            return array(
                'eligible' => false,
                'reason'   => 'cart_below_minimum',
                'action'   => 'collect_email_only',
            );
        }

        // First contact rule: no code on first interaction
        if ( $this->fingerprint->is_first_contact( $fingerprint_id ) ) {
            $this->logger->log( 'first_contact', 'First contact - no discount code', $fingerprint_id, $record->email );
            return array(
                'eligible' => false,
                'reason'   => 'first_contact',
                'action'   => 'collect_email_only',
            );
        }

        // Check session time requirement (>30 seconds)
        $min_session_time = (int) get_option( 'recart_ai_min_session_time', 30 );
        $has_sufficient_time = ( (int) $record->session_time >= $min_session_time );
        $is_returning = ( (int) $record->abandonment_count >= 1 );

        if ( ! $has_sufficient_time && ! $is_returning ) {
            return array(
                'eligible' => false,
                'reason'   => 'insufficient_engagement',
                'action'   => 'collect_email_only',
            );
        }

        // Check code limit (max 1 per 30 days)
        $max_codes = (int) get_option( 'recart_ai_max_codes_30days', 1 );
        $codes_issued = $this->fingerprint->get_codes_last_30_days( $fingerprint_id );

        if ( $codes_issued >= $max_codes ) {
            $this->logger->log( 'code_limit_reached', 'Max codes reached in 30 days', $fingerprint_id, $record->email );
            return array(
                'eligible' => false,
                'reason'   => 'code_limit_reached',
                'action'   => 'show_generic_message',
            );
        }

        // All checks passed - eligible for discount
        $this->logger->log( 'eligible', 'Visitor eligible for discount code', $fingerprint_id, $record->email );
        return array(
            'eligible' => true,
            'reason'   => 'qualified',
            'action'   => 'generate_code',
        );
    }

    /**
     * Determine the intervention type based on context.
     *
     * @return array{type: string, value: mixed}
     */
    public function determine_intervention( string $fingerprint_id, float $cart_total ): array {
        $free_delivery_enabled   = get_option( 'recart_ai_free_delivery_enabled' ) === '1';
        $free_delivery_threshold = (float) get_option( 'recart_ai_free_delivery_threshold', 200 );

        // If cart is below free delivery threshold, offer free delivery first
        if ( $free_delivery_enabled && $cart_total < $free_delivery_threshold ) {
            return array(
                'type'  => 'free_delivery',
                'value' => 0,
            );
        }

        // Otherwise, offer percentage discount
        $discount_type  = get_option( 'recart_ai_discount_type', 'percent' );
        $discount_value = (float) get_option( 'recart_ai_discount_value', 10 );

        return array(
            'type'  => $discount_type,
            'value' => $discount_value,
        );
    }

    /**
     * Check if a fingerprint is blacklisted.
     */
    public function is_blacklisted( string $fingerprint_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_blacklist';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE type = 'fingerprint' AND value = %s",
                $fingerprint_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Check if current IP is blacklisted.
     */
    public function is_ip_blacklisted(): bool {
        global $wpdb;

        $table   = $wpdb->prefix . 'recart_blacklist';
        $ip_hash = $this->get_ip_hash();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE type = 'ip' AND value = %s",
                $ip_hash
            )
        );

        return (int) $count > 0;
    }

    /**
     * Check if email is blacklisted.
     */
    public function is_email_blacklisted( string $email ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_blacklist';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE type = 'email' AND value = %s",
                $email
            )
        );

        return (int) $count > 0;
    }

    /**
     * Add to blacklist.
     */
    public function add_to_blacklist( string $type, string $value, string $reason = '' ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_blacklist';

        $result = $wpdb->insert(
            $table,
            array(
                'type'       => $type,
                'value'      => sanitize_text_field( $value ),
                'reason'     => sanitize_text_field( $reason ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            $this->logger->log( 'blacklist_add', "Added {$type}: {$value} to blacklist. Reason: {$reason}" );
        }

        return (bool) $result;
    }

    /**
     * Remove from blacklist.
     */
    public function remove_from_blacklist( int $id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_blacklist';

        return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get all blacklist entries.
     */
    public function get_blacklist(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_blacklist';

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    /**
     * Get hashed IP.
     */
    private function get_ip_hash(): string {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}

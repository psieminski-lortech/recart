<?php
/**
 * Fingerprint management class.
 *
 * Uses FingerprintJS (open-source) for client-side fingerprint generation
 * and stores visitor data in the custom database table.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Fingerprint {

    /**
     * Get or create a fingerprint record.
     */
    public function get_or_create( string $fingerprint_id, ?string $email = null, ?string $phone = null ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE fingerprint_id = %s",
                $fingerprint_id
            )
        );

        if ( $record ) {
            // Update email/phone if provided
            $update_data = array( 'updated_at' => current_time( 'mysql' ) );
            $update_format = array( '%s' );

            if ( $email && empty( $record->email ) ) {
                $update_data['email'] = sanitize_email( $email );
                $update_format[] = '%s';
            }

            if ( $phone && empty( $record->phone ) ) {
                $update_data['phone'] = sanitize_text_field( $phone );
                $update_format[] = '%s';
            }

            $wpdb->update(
                $table,
                $update_data,
                array( 'fingerprint_id' => $fingerprint_id ),
                $update_format,
                array( '%s' )
            );

            return $this->get_by_id( $fingerprint_id );
        }

        // Create new record
        $ip_hash = $this->get_ip_hash();

        $wpdb->insert(
            $table,
            array(
                'fingerprint_id'   => sanitize_text_field( $fingerprint_id ),
                'email'            => $email ? sanitize_email( $email ) : null,
                'phone'            => $phone ? sanitize_text_field( $phone ) : null,
                'cart_total'       => 0.00,
                'last_code_date'   => null,
                'code_count'       => 0,
                'ip_hash'          => $ip_hash,
                'abandonment_count' => 0,
                'first_contact_at' => current_time( 'mysql' ),
                'session_time'     => 0,
                'is_blocked'       => 0,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        return $this->get_by_id( $fingerprint_id );
    }

    /**
     * Get fingerprint record by ID.
     */
    public function get_by_id( string $fingerprint_id ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE fingerprint_id = %s",
                $fingerprint_id
            )
        );
    }

    /**
     * Update session time for a fingerprint.
     */
    public function update_session_time( string $fingerprint_id, int $seconds ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET session_time = session_time + %d, updated_at = %s WHERE fingerprint_id = %s",
                $seconds,
                current_time( 'mysql' ),
                $fingerprint_id
            )
        );
    }

    /**
     * Increment abandonment count.
     */
    public function increment_abandonment( string $fingerprint_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET abandonment_count = abandonment_count + 1, last_abandonment = %s, updated_at = %s WHERE fingerprint_id = %s",
                current_time( 'mysql' ),
                current_time( 'mysql' ),
                $fingerprint_id
            )
        );
    }

    /**
     * Update cart total for fingerprint.
     */
    public function update_cart_total( string $fingerprint_id, float $total ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $wpdb->update(
            $table,
            array(
                'cart_total'  => $total,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'fingerprint_id' => $fingerprint_id ),
            array( '%f', '%s' ),
            array( '%s' )
        );
    }

    /**
     * Record that a coupon code was issued.
     */
    public function record_code_issued( string $fingerprint_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET code_count = code_count + 1, last_code_date = %s, updated_at = %s WHERE fingerprint_id = %s",
                current_time( 'mysql' ),
                current_time( 'mysql' ),
                $fingerprint_id
            )
        );
    }

    /**
     * Get recent abandonment count (within specified days).
     */
    public function get_recent_abandonments( string $fingerprint_id, int $days = 7 ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_abandoned_carts';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE fingerprint_id = %s AND abandon_time >= %s",
                $fingerprint_id,
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
            )
        );

        return (int) $count;
    }

    /**
     * Get codes issued in last 30 days for a fingerprint.
     */
    public function get_codes_last_30_days( string $fingerprint_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_coupons';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE fingerprint_id = %s AND created_at >= %s",
                $fingerprint_id,
                gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
            )
        );

        return (int) $count;
    }

    /**
     * Check if fingerprint is a first contact (no previous interactions).
     */
    public function is_first_contact( string $fingerprint_id ): bool {
        $record = $this->get_by_id( $fingerprint_id );

        if ( ! $record ) {
            return true;
        }

        return ( (int) $record->abandonment_count === 0 && (int) $record->session_time < 30 );
    }

    /**
     * Block a fingerprint.
     */
    public function block( string $fingerprint_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_fingerprints';

        $wpdb->update(
            $table,
            array( 'is_blocked' => 1, 'updated_at' => current_time( 'mysql' ) ),
            array( 'fingerprint_id' => $fingerprint_id ),
            array( '%d', '%s' ),
            array( '%s' )
        );
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

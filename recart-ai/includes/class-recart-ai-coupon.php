<?php
/**
 * Coupon management class.
 *
 * Creates single-use WooCommerce coupons with 48h expiry,
 * tied to fingerprint IDs.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Coupon {

    private Recart_AI_Antiabuse $antiabuse;
    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Antiabuse $antiabuse, Recart_AI_Logger $logger ) {
        $this->antiabuse = $antiabuse;
        $this->logger    = $logger;

        // Hook into coupon usage to mark as used
        add_action( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 2 );
        add_action( 'woocommerce_applied_coupon', array( $this, 'on_coupon_applied' ) );
    }

    /**
     * Generate a unique discount code for a fingerprint.
     *
     * @return array{success: bool, code?: string, discount_type?: string, discount_value?: float, message?: string}
     */
    public function generate_code( string $fingerprint_id, float $cart_total, ?string $email = null ): array {
        // Check eligibility first
        $eligibility = $this->antiabuse->check_eligibility( $fingerprint_id, $cart_total );

        if ( ! $eligibility['eligible'] ) {
            return array(
                'success' => false,
                'message' => $eligibility['reason'],
                'action'  => $eligibility['action'],
            );
        }

        // Determine intervention type
        $intervention = $this->antiabuse->determine_intervention( $fingerprint_id, $cart_total );

        // Generate unique code
        $code = $this->generate_unique_code();

        // Get settings
        $expiry_hours   = (int) get_option( 'recart_ai_coupon_expiry_hours', 48 );
        $min_cart_total = (float) get_option( 'recart_ai_min_cart_total', 150 );

        // Create WooCommerce coupon
        $coupon_id = $this->create_wc_coupon( $code, $intervention, $min_cart_total, $expiry_hours, $email );

        if ( ! $coupon_id ) {
            $this->logger->log( 'coupon_error', 'Failed to create WooCommerce coupon', $fingerprint_id, $email );
            return array(
                'success' => false,
                'message' => 'coupon_creation_failed',
            );
        }

        // Record in our tracking table
        $this->record_coupon( $coupon_id, $code, $fingerprint_id, $email, $intervention, $min_cart_total, $expiry_hours );

        // Update fingerprint record
        $fingerprint_handler = new Recart_AI_Fingerprint();
        $fingerprint_handler->record_code_issued( $fingerprint_id );

        $this->logger->log(
            'coupon_generated',
            sprintf( 'Code %s generated (%s: %s)', $code, $intervention['type'], $intervention['value'] ),
            $fingerprint_id,
            $email,
            array(
                'code'           => $code,
                'discount_type'  => $intervention['type'],
                'discount_value' => $intervention['value'],
                'expires_in'     => $expiry_hours . 'h',
                'min_cart'       => $min_cart_total,
            )
        );

        return array(
            'success'        => true,
            'code'           => $code,
            'discount_type'  => $intervention['type'],
            'discount_value' => $intervention['value'],
            'expires_hours'  => $expiry_hours,
            'min_cart_total'  => $min_cart_total,
        );
    }

    /**
     * Create a WooCommerce coupon.
     */
    private function create_wc_coupon( string $code, array $intervention, float $min_amount, int $expiry_hours, ?string $email ): int {
        $coupon = new \WC_Coupon();

        $coupon->set_code( $code );
        $coupon->set_usage_limit( 1 ); // Single-use
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_individual_use( true );
        $coupon->set_minimum_amount( $min_amount );

        // Set expiry
        $expiry_date = new \DateTime( 'now', wp_timezone() );
        $expiry_date->modify( "+{$expiry_hours} hours" );
        $coupon->set_date_expires( $expiry_date->getTimestamp() );

        // Set discount type
        if ( $intervention['type'] === 'free_delivery' ) {
            $coupon->set_discount_type( 'percent' );
            $coupon->set_amount( 0 );
            $coupon->set_free_shipping( true );
        } elseif ( $intervention['type'] === 'percent' ) {
            $coupon->set_discount_type( 'percent' );
            $coupon->set_amount( $intervention['value'] );
        } else {
            $coupon->set_discount_type( 'fixed_cart' );
            $coupon->set_amount( $intervention['value'] );
        }

        // Restrict to email if available
        if ( $email ) {
            $coupon->set_email_restrictions( array( $email ) );
        }

        // Add meta for tracking
        $coupon->add_meta_data( '_recart_ai_coupon', '1', true );
        $coupon->add_meta_data( '_recart_ai_fingerprint', sanitize_text_field( $code ), true );

        $coupon_id = $coupon->save();

        return $coupon_id;
    }

    /**
     * Record coupon in tracking table.
     */
    private function record_coupon( int $coupon_id, string $code, string $fingerprint_id, ?string $email, array $intervention, float $min_cart, int $expiry_hours ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_coupons';

        $expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_hours} hours" ) );

        $wpdb->insert(
            $table,
            array(
                'coupon_id'      => $coupon_id,
                'coupon_code'    => $code,
                'fingerprint_id' => sanitize_text_field( $fingerprint_id ),
                'email'          => $email ? sanitize_email( $email ) : null,
                'discount_type'  => $intervention['type'],
                'discount_value' => $intervention['value'],
                'min_cart_total'  => $min_cart,
                'expires_at'     => $expires_at,
                'used'           => 0,
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s' )
        );
    }

    /**
     * Generate a unique coupon code.
     */
    private function generate_unique_code(): string {
        $prefix = 'RECART';
        $random = strtoupper( wp_generate_password( 6, false ) );
        $code   = $prefix . '-' . $random;

        // Ensure uniqueness
        $existing = new \WC_Coupon( $code );
        if ( $existing->get_id() > 0 ) {
            return $this->generate_unique_code();
        }

        return $code;
    }

    /**
     * Validate ReCart coupon (check if expired or already used in our system).
     */
    public function validate_coupon( bool $valid, \WC_Coupon $coupon ): bool {
        if ( ! $valid ) {
            return $valid;
        }

        $is_recart = $coupon->get_meta( '_recart_ai_coupon' );
        if ( $is_recart !== '1' ) {
            return $valid;
        }

        // Check in our tracking table
        global $wpdb;
        $table = $wpdb->prefix . 'recart_coupons';

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE coupon_code = %s",
                $coupon->get_code()
            )
        );

        if ( $record && (int) $record->used === 1 ) {
            return false;
        }

        return $valid;
    }

    /**
     * Mark coupon as used when applied.
     */
    public function on_coupon_applied( string $coupon_code ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_coupons';

        $wpdb->update(
            $table,
            array(
                'used'    => 1,
                'used_at' => current_time( 'mysql' ),
            ),
            array( 'coupon_code' => $coupon_code ),
            array( '%d', '%s' ),
            array( '%s' )
        );

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE coupon_code = %s",
                $coupon_code
            )
        );

        if ( $record ) {
            $this->logger->log(
                'coupon_used',
                sprintf( 'Coupon %s was used', $coupon_code ),
                $record->fingerprint_id,
                $record->email
            );
        }
    }

    /**
     * Cleanup expired coupons.
     */
    public function cleanup_expired(): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_coupons';

        // Get expired, unused coupons
        $expired = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE used = 0 AND expires_at < %s",
                current_time( 'mysql' )
            )
        );

        $count = 0;
        foreach ( $expired as $record ) {
            // Delete the WooCommerce coupon
            $coupon = new \WC_Coupon( $record->coupon_code );
            if ( $coupon->get_id() > 0 ) {
                $coupon->delete( true );
            }

            // Remove from tracking
            $wpdb->delete( $table, array( 'id' => $record->id ), array( '%d' ) );
            $count++;
        }

        if ( $count > 0 ) {
            $this->logger->log( 'coupons_cleanup', sprintf( 'Cleaned up %d expired coupons', $count ) );
        }

        return $count;
    }
}

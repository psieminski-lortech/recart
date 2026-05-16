<?php
/**
 * AJAX handler class.
 *
 * Handles all frontend AJAX requests for the popup,
 * fingerprint registration, and session tracking.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Ajax {

    private Recart_AI_Fingerprint $fingerprint;
    private Recart_AI_Antiabuse $antiabuse;
    private Recart_AI_Coupon $coupon;
    private Recart_AI_Cart_Tracker $cart_tracker;
    private Recart_AI_Webhook $webhook;
    private Recart_AI_Logger $logger;

    public function __construct(
        Recart_AI_Fingerprint $fingerprint,
        Recart_AI_Antiabuse $antiabuse,
        Recart_AI_Coupon $coupon,
        Recart_AI_Cart_Tracker $cart_tracker,
        Recart_AI_Webhook $webhook,
        Recart_AI_Logger $logger
    ) {
        $this->fingerprint  = $fingerprint;
        $this->antiabuse    = $antiabuse;
        $this->coupon       = $coupon;
        $this->cart_tracker = $cart_tracker;
        $this->webhook      = $webhook;
        $this->logger       = $logger;
    }

    /**
     * Initialize AJAX hooks.
     */
    public function init(): void {
        // Public AJAX actions (for both logged-in and guests)
        add_action( 'wp_ajax_recart_ai_submit_popup', array( $this, 'handle_popup_submit' ) );
        add_action( 'wp_ajax_nopriv_recart_ai_submit_popup', array( $this, 'handle_popup_submit' ) );

        add_action( 'wp_ajax_recart_ai_register_visit', array( $this, 'handle_register_visit' ) );
        add_action( 'wp_ajax_nopriv_recart_ai_register_visit', array( $this, 'handle_register_visit' ) );

        add_action( 'wp_ajax_recart_ai_update_session', array( $this, 'handle_update_session' ) );
        add_action( 'wp_ajax_nopriv_recart_ai_update_session', array( $this, 'handle_update_session' ) );

        add_action( 'wp_ajax_recart_ai_track_event', array( $this, 'handle_track_event' ) );
        add_action( 'wp_ajax_nopriv_recart_ai_track_event', array( $this, 'handle_track_event' ) );
    }

    /**
     * Handle popup form submission.
     */
    public function handle_popup_submit(): void {
        // Verify nonce
        if ( ! check_ajax_referer( 'recart_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        // Sanitize inputs
        $fingerprint_id = sanitize_text_field( wp_unslash( $_POST['fingerprint_id'] ?? '' ) );
        $email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $phone          = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $cart_total     = (float) ( $_POST['cart_total'] ?? 0 );
        $session_id     = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

        // Validate required fields
        if ( empty( $fingerprint_id ) || empty( $email ) ) {
            wp_send_json_error( array( 'message' => 'Missing required fields' ), 400 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Invalid email' ), 400 );
        }

        // Check email blacklist
        if ( $this->antiabuse->is_email_blacklisted( $email ) ) {
            $this->logger->log( 'blocked', 'Blacklisted email attempted popup submit', $fingerprint_id, $email );
            wp_send_json_success( array( 'message' => __( 'Dziękujemy! Sprawdź swoją skrzynkę.', 'recart-ai' ) ) );
        }

        // Register/update fingerprint
        $this->fingerprint->get_or_create( $fingerprint_id, $email, $phone );
        $this->fingerprint->update_cart_total( $fingerprint_id, $cart_total );

        // Get cart data
        $cart_data = $this->cart_tracker->get_cart_data();
        $cart_items = $cart_data['items'] ?? array();

        // Record abandonment
        $this->cart_tracker->record_abandonment(
            $fingerprint_id,
            $session_id,
            $email,
            $phone,
            $cart_items,
            $cart_total,
            get_woocommerce_currency()
        );

        // Send email captured webhook
        $this->webhook->send_email_captured( $fingerprint_id, $email, $phone, $cart_total, $cart_items );

        // Check eligibility for discount code
        $eligibility = $this->antiabuse->check_eligibility( $fingerprint_id, $cart_total );

        if ( $eligibility['eligible'] ) {
            // Generate coupon code
            $coupon_result = $this->coupon->generate_code( $fingerprint_id, $cart_total, $email );

            if ( $coupon_result['success'] ) {
                // Send coupon webhook
                $this->webhook->send_coupon_generated(
                    $fingerprint_id,
                    $email,
                    $coupon_result['code'],
                    $coupon_result['discount_type'],
                    $coupon_result['discount_value']
                );

                wp_send_json_success( array(
                    'message'        => __( 'Mamy coś dla Ciebie!', 'recart-ai' ),
                    'code'           => $coupon_result['code'],
                    'discount_type'  => $coupon_result['discount_type'],
                    'discount_value' => $coupon_result['discount_value'],
                    'expires_hours'  => $coupon_result['expires_hours'],
                    'min_cart_total'  => $coupon_result['min_cart_total'],
                ) );
            }
        }

        // Not eligible for code - determine response
        $response = array(
            'message' => __( 'Dziękujemy! Sprawdź swoją skrzynkę.', 'recart-ai' ),
            'action'  => $eligibility['action'] ?? 'collect_email_only',
        );

        if ( $eligibility['action'] === 'show_contact_message' ) {
            $response['message'] = __( 'Masz pytania? Napisz do nas, chętnie pomożemy!', 'recart-ai' );
        }

        wp_send_json_success( $response );
    }

    /**
     * Handle visit registration (fingerprint + cart total).
     */
    public function handle_register_visit(): void {
        if ( ! check_ajax_referer( 'recart_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        $fingerprint_id = sanitize_text_field( wp_unslash( $_POST['fingerprint_id'] ?? '' ) );
        $cart_total     = (float) ( $_POST['cart_total'] ?? 0 );

        if ( empty( $fingerprint_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing fingerprint' ), 400 );
        }

        // Register fingerprint (without email yet)
        $this->fingerprint->get_or_create( $fingerprint_id );

        if ( $cart_total > 0 ) {
            $this->fingerprint->update_cart_total( $fingerprint_id, $cart_total );
        }

        wp_send_json_success( array( 'registered' => true ) );
    }

    /**
     * Handle session time update.
     */
    public function handle_update_session(): void {
        if ( ! check_ajax_referer( 'recart_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        $fingerprint_id = sanitize_text_field( wp_unslash( $_POST['fingerprint_id'] ?? '' ) );
        $seconds        = (int) ( $_POST['seconds'] ?? 0 );

        if ( empty( $fingerprint_id ) || $seconds <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid data' ), 400 );
        }

        // Cap at reasonable value (prevent abuse)
        $seconds = min( $seconds, 60 );

        $this->fingerprint->update_session_time( $fingerprint_id, $seconds );

        wp_send_json_success( array( 'updated' => true ) );
    }

    /**
     * Handle event tracking.
     */
    public function handle_track_event(): void {
        if ( ! check_ajax_referer( 'recart_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        $fingerprint_id = sanitize_text_field( wp_unslash( $_POST['fingerprint_id'] ?? '' ) );
        $event_type     = sanitize_text_field( wp_unslash( $_POST['event_type'] ?? '' ) );
        $event_data     = sanitize_text_field( wp_unslash( $_POST['event_data'] ?? '{}' ) );

        if ( empty( $event_type ) ) {
            wp_send_json_error( array( 'message' => 'Missing event type' ), 400 );
        }

        $data = json_decode( $event_data, true );

        $this->logger->log(
            $event_type,
            sprintf( 'Frontend event: %s', $event_type ),
            $fingerprint_id ?: null,
            null,
            $data
        );

        wp_send_json_success( array( 'tracked' => true ) );
    }
}

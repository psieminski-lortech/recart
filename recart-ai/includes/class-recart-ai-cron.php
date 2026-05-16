<?php
/**
 * Cron tasks class.
 *
 * Handles scheduled tasks: expired coupon cleanup,
 * abandoned cart webhook sending, and log cleanup.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Cron {

    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Initialize cron hooks.
     */
    public function init(): void {
        add_action( 'recart_ai_cleanup_expired_coupons', array( $this, 'cleanup_expired_coupons' ) );
        add_action( 'recart_ai_check_abandoned_carts', array( $this, 'process_abandoned_carts' ) );
        add_action( 'recart_ai_cleanup_old_logs', array( $this, 'cleanup_old_logs' ) );
    }

    /**
     * Cleanup expired coupons.
     */
    public function cleanup_expired_coupons(): void {
        $coupon_handler = new Recart_AI_Coupon(
            new Recart_AI_Antiabuse( new Recart_AI_Fingerprint(), $this->logger ),
            $this->logger
        );
        $coupon_handler->cleanup_expired();
    }

    /**
     * Process abandoned carts and send webhooks.
     */
    public function process_abandoned_carts(): void {
        if ( get_option( 'recart_ai_webhook_enabled' ) !== '1' ) {
            return;
        }

        $fingerprint_handler = new Recart_AI_Fingerprint();
        $webhook_handler     = new Recart_AI_Webhook( $this->logger );
        $cart_tracker        = new Recart_AI_Cart_Tracker( $fingerprint_handler, $webhook_handler, $this->logger );

        $timeout = (int) get_option( 'recart_ai_abandonment_timeout', 30 );
        $pending_carts = $cart_tracker->get_pending_carts( $timeout );

        foreach ( $pending_carts as $cart ) {
            $sent = $webhook_handler->send_cart_abandoned( $cart );

            if ( $sent ) {
                $cart_tracker->mark_webhook_sent( (int) $cart->id );
            }
        }
    }

    /**
     * Cleanup old logs.
     */
    public function cleanup_old_logs(): void {
        $deleted = $this->logger->cleanup_old_logs();

        if ( $deleted > 0 ) {
            $this->logger->log( 'maintenance', sprintf( 'Cleaned up %d old log entries', $deleted ) );
        }
    }
}

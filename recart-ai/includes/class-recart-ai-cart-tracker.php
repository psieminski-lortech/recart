<?php
/**
 * Cart Abandonment Tracker class.
 *
 * Detects abandoned carts for both guests and logged-in users
 * using WooCommerce hooks.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Cart_Tracker {

    private Recart_AI_Fingerprint $fingerprint;
    private Recart_AI_Webhook $webhook;
    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Fingerprint $fingerprint, Recart_AI_Webhook $webhook, Recart_AI_Logger $logger ) {
        $this->fingerprint = $fingerprint;
        $this->webhook     = $webhook;
        $this->logger      = $logger;
    }

    /**
     * Initialize cart tracking hooks.
     */
    public function init(): void {
        // Track cart changes
        add_action( 'woocommerce_add_to_cart', array( $this, 'on_cart_updated' ), 10, 0 );
        add_action( 'woocommerce_cart_item_removed', array( $this, 'on_cart_updated' ), 10, 0 );
        add_action( 'woocommerce_cart_item_restored', array( $this, 'on_cart_updated' ), 10, 0 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_cart_updated' ), 10, 0 );

        // Track cart emptied
        add_action( 'woocommerce_cart_emptied', array( $this, 'on_cart_emptied' ) );

        // Track successful checkout (cart recovered)
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'on_checkout_complete' ), 10, 2 );
        add_action( 'woocommerce_thankyou', array( $this, 'on_order_complete' ) );
    }

    /**
     * Handle cart update - save cart state.
     */
    public function on_cart_updated(): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        $cart = WC()->cart;

        if ( $cart->is_empty() ) {
            return;
        }

        // Store cart data in session for later abandonment detection
        $cart_data = $this->get_cart_data();

        if ( WC()->session ) {
            WC()->session->set( 'recart_ai_cart_data', $cart_data );
            WC()->session->set( 'recart_ai_cart_updated', time() );
        }
    }

    /**
     * Handle cart emptied.
     */
    public function on_cart_emptied(): void {
        if ( WC()->session ) {
            WC()->session->set( 'recart_ai_cart_data', null );
            WC()->session->set( 'recart_ai_cart_updated', null );
        }
    }

    /**
     * Handle successful checkout - mark cart as recovered.
     */
    public function on_checkout_complete( int $order_id, array $data ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $email = $order->get_billing_email();

        if ( $email ) {
            $this->mark_cart_recovered( $email );
        }

        // Clear session data
        if ( WC()->session ) {
            WC()->session->set( 'recart_ai_cart_data', null );
            WC()->session->set( 'recart_ai_cart_updated', null );
        }
    }

    /**
     * Handle order complete page.
     */
    public function on_order_complete( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if a ReCart coupon was used
        $coupons = $order->get_coupon_codes();
        foreach ( $coupons as $code ) {
            $coupon = new \WC_Coupon( $code );
            if ( $coupon->get_meta( '_recart_ai_coupon' ) === '1' ) {
                $this->logger->log(
                    'cart_recovered',
                    sprintf( 'Cart recovered with coupon %s. Order #%d, Total: %s', $code, $order_id, $order->get_total() ),
                    null,
                    $order->get_billing_email(),
                    array(
                        'order_id'    => $order_id,
                        'order_total' => $order->get_total(),
                        'coupon_code' => $code,
                    )
                );
            }
        }
    }

    /**
     * Record an abandoned cart.
     */
    public function record_abandonment( string $fingerprint_id, string $session_id, ?string $email, ?string $phone, array $cart_items, float $cart_total, string $currency = 'PLN' ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_abandoned_carts';

        // Check if this is first contact
        $is_first_contact = $this->fingerprint->is_first_contact( $fingerprint_id ) ? 1 : 0;

        $wpdb->insert(
            $table,
            array(
                'fingerprint_id' => sanitize_text_field( $fingerprint_id ),
                'session_id'     => sanitize_text_field( $session_id ),
                'email'          => $email ? sanitize_email( $email ) : null,
                'phone'          => $phone ? sanitize_text_field( $phone ) : null,
                'cart_items'     => wp_json_encode( $cart_items ),
                'cart_total'     => $cart_total,
                'currency'       => sanitize_text_field( $currency ),
                'is_first_contact' => $is_first_contact,
                'webhook_sent'   => 0,
                'recovered'      => 0,
                'abandon_time'   => current_time( 'mysql' ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%s' )
        );

        $cart_id = $wpdb->insert_id;

        // Increment abandonment count on fingerprint
        $this->fingerprint->increment_abandonment( $fingerprint_id );

        $this->logger->log(
            'cart_abandoned',
            sprintf( 'Cart abandoned. Total: %s %s', $cart_total, $currency ),
            $fingerprint_id,
            $email,
            array(
                'cart_id'    => $cart_id,
                'cart_total' => $cart_total,
                'items'      => count( $cart_items ),
            )
        );

        return $cart_id;
    }

    /**
     * Get current cart data.
     */
    public function get_cart_data(): array {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return array();
        }

        $cart  = WC()->cart;
        $items = array();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );

            $items[] = array(
                'product_id'   => $product->get_id(),
                'name'         => $product->get_name(),
                'price'        => (float) $product->get_price(),
                'quantity'     => $cart_item['quantity'],
                'subtotal'     => (float) $cart_item['line_subtotal'],
                'image'        => $image_url,
                'url'          => get_permalink( $product->get_id() ),
                'sku'          => $product->get_sku(),
            );
        }

        return array(
            'items'    => $items,
            'total'    => (float) $cart->get_total( 'edit' ),
            'currency' => get_woocommerce_currency(),
        );
    }

    /**
     * Mark carts as recovered by email.
     */
    private function mark_cart_recovered( string $email ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_abandoned_carts';

        $wpdb->update(
            $table,
            array( 'recovered' => 1 ),
            array( 'email' => $email, 'recovered' => 0 ),
            array( '%d' ),
            array( '%s', '%d' )
        );
    }

    /**
     * Get pending abandoned carts (not yet webhook-sent).
     */
    public function get_pending_carts( int $timeout_minutes = 30 ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_abandoned_carts';

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$timeout_minutes} minutes" ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE webhook_sent = 0 AND recovered = 0 AND abandon_time <= %s AND email IS NOT NULL ORDER BY abandon_time ASC LIMIT 50",
                $cutoff
            )
        );
    }

    /**
     * Mark cart webhook as sent.
     */
    public function mark_webhook_sent( int $cart_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_abandoned_carts';

        $wpdb->update(
            $table,
            array( 'webhook_sent' => 1 ),
            array( 'id' => $cart_id ),
            array( '%d' ),
            array( '%d' )
        );
    }
}

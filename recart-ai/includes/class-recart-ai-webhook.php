<?php
/**
 * Webhook class for sending cart abandonment data.
 *
 * Sends JSON payloads to configurable endpoints (n8n, Langflow, ReCart AI backend).
 * Also supports Formspree integration for email notifications.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Webhook {

    private Recart_AI_Logger $logger;

    public function __construct( Recart_AI_Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Send abandoned cart webhook.
     */
    public function send_cart_abandoned( object $cart ): bool {
        if ( get_option( 'recart_ai_webhook_enabled' ) !== '1' ) {
            return false;
        }

        $webhook_url = get_option( 'recart_ai_webhook_url' );
        if ( empty( $webhook_url ) ) {
            return false;
        }

        $cart_items = json_decode( $cart->cart_items, true );

        $payload = array(
            'event'          => 'cart_abandoned',
            'fingerprint_id' => $cart->fingerprint_id,
            'email'          => $cart->email,
            'phone'          => $cart->phone ?? null,
            'cart_items'     => $cart_items,
            'cart_total'     => (float) $cart->cart_total,
            'currency'       => $cart->currency,
            'session_id'     => $cart->session_id,
            'abandon_time'   => $cart->abandon_time,
            'is_first_contact' => (bool) $cart->is_first_contact,
            'store_url'      => home_url(),
            'store_name'     => get_bloginfo( 'name' ),
            'timestamp'      => current_time( 'c' ),
        );

        $result = $this->send( $webhook_url, $payload );

        if ( $result ) {
            $this->logger->log(
                'webhook_sent',
                sprintf( 'Cart abandoned webhook sent for %s', $cart->email ),
                $cart->fingerprint_id,
                $cart->email
            );
        }

        return $result;
    }

    /**
     * Send email captured webhook.
     */
    public function send_email_captured( string $fingerprint_id, string $email, ?string $phone, float $cart_total, array $cart_items ): bool {
        if ( get_option( 'recart_ai_webhook_enabled' ) !== '1' ) {
            // Still try Formspree if enabled
            $this->send_formspree( $fingerprint_id, $email, $phone, $cart_total, $cart_items );
            return false;
        }

        $webhook_url = get_option( 'recart_ai_webhook_url' );
        if ( empty( $webhook_url ) ) {
            $this->send_formspree( $fingerprint_id, $email, $phone, $cart_total, $cart_items );
            return false;
        }

        $payload = array(
            'event'          => 'email_captured',
            'fingerprint_id' => $fingerprint_id,
            'email'          => $email,
            'phone'          => $phone,
            'cart_items'     => $cart_items,
            'cart_total'     => $cart_total,
            'currency'       => get_woocommerce_currency(),
            'store_url'      => home_url(),
            'store_name'     => get_bloginfo( 'name' ),
            'timestamp'      => current_time( 'c' ),
        );

        $result = $this->send( $webhook_url, $payload );

        // Also send to Formspree if configured
        $this->send_formspree( $fingerprint_id, $email, $phone, $cart_total, $cart_items );

        return $result;
    }

    /**
     * Send coupon generated webhook.
     */
    public function send_coupon_generated( string $fingerprint_id, string $email, string $code, string $discount_type, float $discount_value ): bool {
        if ( get_option( 'recart_ai_webhook_enabled' ) !== '1' ) {
            return false;
        }

        $webhook_url = get_option( 'recart_ai_webhook_url' );
        if ( empty( $webhook_url ) ) {
            return false;
        }

        $payload = array(
            'event'          => 'coupon_generated',
            'fingerprint_id' => $fingerprint_id,
            'email'          => $email,
            'coupon_code'    => $code,
            'discount_type'  => $discount_type,
            'discount_value' => $discount_value,
            'store_url'      => home_url(),
            'store_name'     => get_bloginfo( 'name' ),
            'timestamp'      => current_time( 'c' ),
        );

        return $this->send( $webhook_url, $payload );
    }

    /**
     * Send data to Formspree endpoint.
     */
    private function send_formspree( string $fingerprint_id, string $email, ?string $phone, float $cart_total, array $cart_items ): bool {
        if ( get_option( 'recart_ai_formspree_enabled' ) !== '1' ) {
            return false;
        }

        $formspree_endpoint = get_option( 'recart_ai_formspree_endpoint' );
        if ( empty( $formspree_endpoint ) ) {
            return false;
        }

        $item_names = array_map( function ( $item ) {
            return $item['name'] . ' (x' . $item['quantity'] . ')';
        }, $cart_items );

        $payload = array(
            'email'          => $email,
            'phone'          => $phone ?? '',
            'cart_total'     => $cart_total . ' ' . get_woocommerce_currency(),
            'cart_items'     => implode( ', ', $item_names ),
            'store'          => get_bloginfo( 'name' ),
            'time'           => current_time( 'Y-m-d H:i:s' ),
            '_subject'       => sprintf( 'Porzucony koszyk: %s (%s %s)', $email, $cart_total, get_woocommerce_currency() ),
        );

        $response = wp_remote_post( $formspree_endpoint, array(
            'body'    => wp_json_encode( $payload ),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->logger->log( 'formspree_error', 'Formspree send failed: ' . $response->get_error_message(), $fingerprint_id, $email );
            return false;
        }

        $this->logger->log( 'formspree_sent', 'Formspree notification sent', $fingerprint_id, $email );
        return true;
    }

    /**
     * Send webhook payload.
     */
    private function send( string $url, array $payload ): bool {
        $secret = get_option( 'recart_ai_webhook_secret', '' );

        $body = wp_json_encode( $payload );

        $headers = array(
            'Content-Type'       => 'application/json',
            'X-ReCart-Signature' => hash_hmac( 'sha256', $body, $secret ),
            'X-ReCart-Version'   => RECART_AI_VERSION,
            'User-Agent'        => 'ReCart-AI/' . RECART_AI_VERSION,
        );

        $response = wp_remote_post( $url, array(
            'body'      => $body,
            'headers'   => $headers,
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->logger->log(
                'webhook_error',
                'Webhook failed: ' . $response->get_error_message(),
                $payload['fingerprint_id'] ?? null,
                $payload['email'] ?? null,
                array( 'url' => $url, 'error' => $response->get_error_message() )
            );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            $this->logger->log(
                'webhook_error',
                sprintf( 'Webhook returned HTTP %d', $code ),
                $payload['fingerprint_id'] ?? null,
                $payload['email'] ?? null,
                array( 'url' => $url, 'http_code' => $code )
            );
            return false;
        }

        return true;
    }
}

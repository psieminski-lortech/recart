<?php
/**
 * Exit-Intent Popup class.
 *
 * Handles frontend popup display with exit-intent detection
 * for both desktop and mobile devices.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Popup {

    /**
     * Initialize popup hooks.
     */
    public function init(): void {
        if ( ! $this->should_show_popup() ) {
            return;
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_popup_template' ) );
    }

    /**
     * Check if popup should be shown on current page.
     */
    private function should_show_popup(): bool {
        // Don't show in admin
        if ( is_admin() ) {
            return false;
        }

        // Check if popup is enabled
        if ( get_option( 'recart_ai_popup_enabled' ) !== '1' ) {
            return false;
        }

        // Check if plugin is enabled
        if ( get_option( 'recart_ai_enabled' ) !== '1' ) {
            return false;
        }

        return true;
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets(): void {
        // Don't load on checkout or thank you pages
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        // FingerprintJS (open-source version from CDN)
        $gdpr_mode = get_option( 'recart_ai_gdpr_mode' ) === '1';

        if ( ! $gdpr_mode ) {
            wp_enqueue_script(
                'fingerprintjs',
                'https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@4/dist/fp.min.js',
                array(),
                '4.0.0',
                true
            );
        }

        // Main ReCart AI script
        wp_enqueue_script(
            'recart-ai-popup',
            RECART_AI_PLUGIN_URL . 'assets/js/recart-ai-popup.js',
            $gdpr_mode ? array() : array( 'fingerprintjs' ),
            RECART_AI_VERSION,
            true
        );

        // Popup styles
        wp_enqueue_style(
            'recart-ai-popup',
            RECART_AI_PLUGIN_URL . 'assets/css/recart-ai-popup.css',
            array(),
            RECART_AI_VERSION
        );

        // Localize script with settings
        wp_localize_script( 'recart-ai-popup', 'recartAiConfig', $this->get_frontend_config() );
    }

    /**
     * Get frontend configuration.
     */
    private function get_frontend_config(): array {
        $cart_items = array();
        $cart_total = 0;

        if ( function_exists( 'WC' ) && WC()->cart ) {
            $cart_total = (float) WC()->cart->get_total( 'edit' );

            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product = $cart_item['data'];
                $image   = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );

                $cart_items[] = array(
                    'id'       => $product->get_id(),
                    'name'     => $product->get_name(),
                    'price'    => (float) $product->get_price(),
                    'quantity' => $cart_item['quantity'],
                    'image'    => $image ?: wc_placeholder_img_src( 'thumbnail' ),
                );
            }
        }

        return array(
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'recart_ai_nonce' ),
            'popupEnabled'    => get_option( 'recart_ai_popup_enabled' ) === '1',
            'mobileEnabled'   => get_option( 'recart_ai_popup_mobile_enabled' ) === '1',
            'showProducts'    => get_option( 'recart_ai_popup_show_products' ) === '1',
            'showPhone'       => get_option( 'recart_ai_popup_show_phone' ) === '1',
            'popupDelay'      => (int) get_option( 'recart_ai_popup_delay', 0 ),
            'gdprMode'        => get_option( 'recart_ai_gdpr_mode' ) === '1',
            'minSessionTime'  => (int) get_option( 'recart_ai_min_session_time', 30 ),
            'cartItems'       => $cart_items,
            'cartTotal'       => $cart_total,
            'currency'        => get_woocommerce_currency_symbol(),
            'isCheckout'      => function_exists( 'is_checkout' ) && is_checkout(),
            'isThankYou'      => function_exists( 'is_order_received_page' ) && is_order_received_page(),
            'texts'           => array(
                'title'       => get_option( 'recart_ai_popup_title', __( 'Nie odchodź z pustymi rękami!', 'recart-ai' ) ),
                'message'     => get_option( 'recart_ai_popup_message', __( 'Widzimy, że masz produkty w koszyku. Zostaw nam swój email, a pomożemy Ci dokończyć zakupy.', 'recart-ai' ) ),
                'button'      => get_option( 'recart_ai_popup_button_text', __( 'Zapisz mój koszyk', 'recart-ai' ) ),
                'emailLabel'  => __( 'Twój email', 'recart-ai' ),
                'phoneLabel'  => __( 'Telefon (opcjonalnie)', 'recart-ai' ),
                'success'     => __( 'Dziękujemy! Sprawdź swoją skrzynkę.', 'recart-ai' ),
                'codeSuccess' => __( 'Mamy coś dla Ciebie! Użyj kodu:', 'recart-ai' ),
                'freeDelivery' => __( 'Darmowa dostawa z kodem:', 'recart-ai' ),
                'contactUs'   => __( 'Masz pytania? Napisz do nas!', 'recart-ai' ),
                'close'       => __( 'Nie, dziękuję', 'recart-ai' ),
                'privacy'     => __( 'Twoje dane są bezpieczne. Nie wysyłamy spamu.', 'recart-ai' ),
            ),
            'colors'          => array(
                'primary' => get_option( 'recart_ai_popup_color_primary', '#4F46E5' ),
                'bg'      => get_option( 'recart_ai_popup_color_bg', '#ffffff' ),
                'text'    => get_option( 'recart_ai_popup_color_text', '#1f2937' ),
            ),
        );
    }

    /**
     * Render popup HTML template in footer.
     */
    public function render_popup_template(): void {
        // Don't render on checkout or thank you pages
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        ?>
        <div id="recart-ai-popup-overlay" class="recart-ai-overlay" style="display:none;" aria-hidden="true">
            <div id="recart-ai-popup" class="recart-ai-popup" role="dialog" aria-modal="true" aria-labelledby="recart-ai-title">
                <button type="button" id="recart-ai-close" class="recart-ai-close" aria-label="<?php esc_attr_e( 'Zamknij', 'recart-ai' ); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <div id="recart-ai-content" class="recart-ai-content">
                    <!-- Form state -->
                    <div id="recart-ai-form-state" class="recart-ai-state">
                        <h2 id="recart-ai-title" class="recart-ai-title"></h2>
                        <p id="recart-ai-message" class="recart-ai-message"></p>

                        <div id="recart-ai-products" class="recart-ai-products"></div>

                        <form id="recart-ai-form" class="recart-ai-form" novalidate>
                            <div class="recart-ai-field">
                                <input type="email" id="recart-ai-email" name="email" required
                                       placeholder="<?php esc_attr_e( 'Twój email', 'recart-ai' ); ?>"
                                       class="recart-ai-input" autocomplete="email" />
                            </div>
                            <div id="recart-ai-phone-field" class="recart-ai-field" style="display:none;">
                                <input type="tel" id="recart-ai-phone" name="phone"
                                       placeholder="<?php esc_attr_e( 'Telefon (opcjonalnie)', 'recart-ai' ); ?>"
                                       class="recart-ai-input" autocomplete="tel" />
                            </div>
                            <button type="submit" id="recart-ai-submit" class="recart-ai-button">
                                <span id="recart-ai-button-text"></span>
                                <span id="recart-ai-spinner" class="recart-ai-spinner" style="display:none;"></span>
                            </button>
                        </form>

                        <p class="recart-ai-privacy">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span id="recart-ai-privacy-text"></span>
                        </p>

                        <button type="button" id="recart-ai-dismiss" class="recart-ai-dismiss"></button>
                    </div>

                    <!-- Success state -->
                    <div id="recart-ai-success-state" class="recart-ai-state" style="display:none;">
                        <div class="recart-ai-success-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <p id="recart-ai-success-message" class="recart-ai-success-text"></p>
                        <div id="recart-ai-code-display" class="recart-ai-code-display" style="display:none;">
                            <span id="recart-ai-code" class="recart-ai-code"></span>
                            <button type="button" id="recart-ai-copy-code" class="recart-ai-copy-btn" title="<?php esc_attr_e( 'Kopiuj kod', 'recart-ai' ); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

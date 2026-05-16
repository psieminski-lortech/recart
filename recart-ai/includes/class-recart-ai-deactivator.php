<?php
/**
 * Fired during plugin deactivation.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Deactivator {

    /**
     * Run deactivation routines.
     */
    public static function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'recart_ai_cleanup_expired_coupons' );
        wp_clear_scheduled_hook( 'recart_ai_check_abandoned_carts' );
        wp_clear_scheduled_hook( 'recart_ai_cleanup_old_logs' );
        wp_clear_scheduled_hook( 'recart_ai_license_heartbeat' );

        flush_rewrite_rules();
    }
}

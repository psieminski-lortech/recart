<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up all plugin data including database tables and options.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove custom tables
$tables = array(
    $wpdb->prefix . 'recart_fingerprints',
    $wpdb->prefix . 'recart_abandoned_carts',
    $wpdb->prefix . 'recart_logs',
    $wpdb->prefix . 'recart_blacklist',
    $wpdb->prefix . 'recart_coupons',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove all plugin options
$options = array(
    'recart_ai_enabled',
    'recart_ai_gdpr_mode',
    'recart_ai_popup_enabled',
    'recart_ai_popup_title',
    'recart_ai_popup_message',
    'recart_ai_popup_button_text',
    'recart_ai_popup_show_products',
    'recart_ai_popup_show_phone',
    'recart_ai_popup_delay',
    'recart_ai_popup_mobile_enabled',
    'recart_ai_popup_color_primary',
    'recart_ai_popup_color_bg',
    'recart_ai_popup_color_text',
    'recart_ai_antiabuse_enabled',
    'recart_ai_min_cart_total',
    'recart_ai_discount_type',
    'recart_ai_discount_value',
    'recart_ai_coupon_expiry_hours',
    'recart_ai_max_codes_30days',
    'recart_ai_anomaly_threshold',
    'recart_ai_anomaly_days',
    'recart_ai_min_session_time',
    'recart_ai_free_delivery_enabled',
    'recart_ai_free_delivery_threshold',
    'recart_ai_webhook_enabled',
    'recart_ai_webhook_url',
    'recart_ai_webhook_secret',
    'recart_ai_webhook_events',
    'recart_ai_abandonment_timeout',
    'recart_ai_formspree_enabled',
    'recart_ai_formspree_endpoint',
    'recart_ai_db_version',
    'recart_ai_activated',
    'recart_ai_license_key',
    'recart_ai_license_server_url',
    'recart_ai_license_valid',
    'recart_ai_license_plan',
    'recart_ai_license_plan_name',
    'recart_ai_license_popup_limit',
    'recart_ai_license_features',
    'recart_ai_license_expires',
    'recart_ai_license_last_check',
    'recart_ai_license_warning',
    'recart_ai_popups_remaining',
    'recart_ai_popups_this_period',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove WooCommerce coupons created by the plugin
$coupon_ids = $wpdb->get_col(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_recart_ai_coupon' AND meta_value = '1'"
);

foreach ( $coupon_ids as $coupon_id ) {
    wp_delete_post( (int) $coupon_id, true );
}

// Clear scheduled events
wp_clear_scheduled_hook( 'recart_ai_cleanup_expired_coupons' );
wp_clear_scheduled_hook( 'recart_ai_check_abandoned_carts' );
wp_clear_scheduled_hook( 'recart_ai_cleanup_old_logs' );
wp_clear_scheduled_hook( 'recart_ai_license_heartbeat' );

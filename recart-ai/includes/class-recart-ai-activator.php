<?php
/**
 * Fired during plugin activation.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Activator {

    /**
     * Run activation routines.
     */
    public static function activate(): void {
        self::create_tables();
        self::set_default_options();
        self::schedule_events();

        update_option( 'recart_ai_db_version', RECART_AI_DB_VERSION );
        update_option( 'recart_ai_activated', time() );

        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Fingerprints table
        $table_fingerprints = $wpdb->prefix . 'recart_fingerprints';
        $sql_fingerprints = "CREATE TABLE {$table_fingerprints} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            fingerprint_id varchar(64) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            cart_total decimal(10,2) DEFAULT 0.00,
            last_code_date datetime DEFAULT NULL,
            code_count int(11) DEFAULT 0,
            ip_hash varchar(64) NOT NULL,
            abandonment_count int(11) DEFAULT 0,
            last_abandonment datetime DEFAULT NULL,
            first_contact_at datetime DEFAULT NULL,
            session_time int(11) DEFAULT 0,
            is_blocked tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY fingerprint_id (fingerprint_id),
            KEY email (email),
            KEY ip_hash (ip_hash),
            KEY is_blocked (is_blocked)
        ) {$charset_collate};";

        // Abandoned carts table
        $table_carts = $wpdb->prefix . 'recart_abandoned_carts';
        $sql_carts = "CREATE TABLE {$table_carts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            fingerprint_id varchar(64) NOT NULL,
            session_id varchar(64) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            cart_items longtext NOT NULL,
            cart_total decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'PLN',
            is_first_contact tinyint(1) DEFAULT 1,
            webhook_sent tinyint(1) DEFAULT 0,
            recovered tinyint(1) DEFAULT 0,
            abandon_time datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY fingerprint_id (fingerprint_id),
            KEY session_id (session_id),
            KEY abandon_time (abandon_time)
        ) {$charset_collate};";

        // Event logs table
        $table_logs = $wpdb->prefix . 'recart_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            fingerprint_id varchar(64) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            message text NOT NULL,
            data longtext DEFAULT NULL,
            ip_hash varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY fingerprint_id (fingerprint_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Blacklist table
        $table_blacklist = $wpdb->prefix . 'recart_blacklist';
        $sql_blacklist = "CREATE TABLE {$table_blacklist} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type enum('fingerprint','ip','email') NOT NULL,
            value varchar(255) NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY type_value (type, value)
        ) {$charset_collate};";

        // Coupons tracking table
        $table_coupons = $wpdb->prefix . 'recart_coupons';
        $sql_coupons = "CREATE TABLE {$table_coupons} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) unsigned NOT NULL,
            coupon_code varchar(50) NOT NULL,
            fingerprint_id varchar(64) NOT NULL,
            email varchar(255) DEFAULT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percent',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            min_cart_total decimal(10,2) NOT NULL DEFAULT 0.00,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            used_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY fingerprint_id (fingerprint_id),
            KEY coupon_code (coupon_code),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_fingerprints );
        dbDelta( $sql_carts );
        dbDelta( $sql_logs );
        dbDelta( $sql_blacklist );
        dbDelta( $sql_coupons );
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        $defaults = array(
            // General
            'recart_ai_enabled'                => '1',
            'recart_ai_gdpr_mode'              => '0',

            // Popup settings
            'recart_ai_popup_enabled'          => '1',
            'recart_ai_popup_title'            => __( 'Nie odchodź z pustymi rękami!', 'recart-ai' ),
            'recart_ai_popup_message'          => __( 'Widzimy, że masz produkty w koszyku. Zostaw nam swój email, a pomożemy Ci dokończyć zakupy.', 'recart-ai' ),
            'recart_ai_popup_button_text'      => __( 'Zapisz mój koszyk', 'recart-ai' ),
            'recart_ai_popup_show_products'    => '1',
            'recart_ai_popup_show_phone'       => '1',
            'recart_ai_popup_delay'            => '0',
            'recart_ai_popup_mobile_enabled'   => '1',
            'recart_ai_popup_color_primary'    => '#4F46E5',
            'recart_ai_popup_color_bg'         => '#ffffff',
            'recart_ai_popup_color_text'       => '#1f2937',

            // Anti-abuse settings
            'recart_ai_antiabuse_enabled'      => '1',
            'recart_ai_min_cart_total'         => '150',
            'recart_ai_discount_type'          => 'percent',
            'recart_ai_discount_value'         => '10',
            'recart_ai_coupon_expiry_hours'    => '48',
            'recart_ai_max_codes_30days'       => '1',
            'recart_ai_anomaly_threshold'      => '3',
            'recart_ai_anomaly_days'           => '7',
            'recart_ai_min_session_time'       => '30',
            'recart_ai_free_delivery_enabled'  => '1',
            'recart_ai_free_delivery_threshold' => '200',

            // Webhook settings
            'recart_ai_webhook_enabled'        => '0',
            'recart_ai_webhook_url'            => '',
            'recart_ai_webhook_secret'         => wp_generate_password( 32, false ),
            'recart_ai_webhook_events'         => array( 'cart_abandoned', 'email_captured', 'coupon_generated' ),
            'recart_ai_abandonment_timeout'    => '30',

            // Formspree integration
            'recart_ai_formspree_enabled'      => '0',
            'recart_ai_formspree_endpoint'     => '',
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Schedule cron events.
     */
    private static function schedule_events(): void {
        if ( ! wp_next_scheduled( 'recart_ai_cleanup_expired_coupons' ) ) {
            wp_schedule_event( time(), 'hourly', 'recart_ai_cleanup_expired_coupons' );
        }

        if ( ! wp_next_scheduled( 'recart_ai_check_abandoned_carts' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'recart_ai_check_abandoned_carts' );
        }

        if ( ! wp_next_scheduled( 'recart_ai_cleanup_old_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'recart_ai_cleanup_old_logs' );
        }
    }
}

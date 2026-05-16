<?php
/**
 * The core plugin class.
 *
 * All plugin functionality is gated behind a valid license check.
 * Admin panel and license settings are always available so the user
 * can enter/manage their license key.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI {

    /**
     * Plugin instance.
     */
    private static ?Recart_AI $instance = null;

    /**
     * License manager instance.
     */
    private Recart_AI_License $license;

    /**
     * Get singleton instance.
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies(): void {
        // License (always loaded)
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-license.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-logger.php';

        // Admin (always loaded so license settings are accessible)
        if ( is_admin() ) {
            require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-admin.php';
        }

        // Core functionality (loaded only when license is potentially valid)
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-fingerprint.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-antiabuse.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-coupon.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-cart-tracker.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-webhook.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-popup.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-ajax.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-cron.php';
    }

    /**
     * Run the plugin - register all hooks.
     */
    public function run(): void {
        // Register custom cron interval
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

        // License is always initialized (for settings, heartbeat, notices)
        $this->license = new Recart_AI_License();
        $this->license->init();

        $logger = new Recart_AI_Logger();

        // Admin panel is always available (so user can manage license)
        if ( is_admin() ) {
            $admin = new Recart_AI_Admin( $logger, $this->license );
            $admin->init();
        }

        // =====================================================
        // FIREWALL: All functionality below requires valid license
        // =====================================================
        if ( ! $this->license->is_valid() ) {
            return; // Plugin stops here without a license
        }

        // Check if popup limit is reached
        $popup_limit_reached = $this->license->is_popup_limit_reached();

        // Initialize core components
        $fingerprint  = new Recart_AI_Fingerprint();
        $antiabuse    = new Recart_AI_Antiabuse( $fingerprint, $logger );
        $coupon       = new Recart_AI_Coupon( $antiabuse, $logger );
        $webhook      = new Recart_AI_Webhook( $logger );
        $cart_tracker = new Recart_AI_Cart_Tracker( $fingerprint, $webhook, $logger );
        $ajax         = new Recart_AI_Ajax( $fingerprint, $antiabuse, $coupon, $cart_tracker, $webhook, $logger );
        $cron         = new Recart_AI_Cron( $logger );

        // Popup only if limit not reached
        if ( ! $popup_limit_reached ) {
            $popup = new Recart_AI_Popup();
            $popup->init();
        }

        // AJAX, cart tracking, and cron always active with valid license
        $ajax->init();
        $cart_tracker->init();
        $cron->init();
    }

    /**
     * Get the license instance.
     */
    public function get_license(): Recart_AI_License {
        return $this->license;
    }

    /**
     * Add custom cron intervals.
     */
    public function add_cron_intervals( array $schedules ): array {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'recart-ai' ),
        );
        return $schedules;
    }
}

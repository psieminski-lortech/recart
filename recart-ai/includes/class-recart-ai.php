<?php
/**
 * The core plugin class.
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
        // Core
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-logger.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-fingerprint.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-antiabuse.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-coupon.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-cart-tracker.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-webhook.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-popup.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-ajax.php';
        require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-cron.php';

        // Admin
        if ( is_admin() ) {
            require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-admin.php';
        }
    }

    /**
     * Run the plugin - register all hooks.
     */
    public function run(): void {
        // Register custom cron interval
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

        // Initialize components
        $logger       = new Recart_AI_Logger();
        $fingerprint  = new Recart_AI_Fingerprint();
        $antiabuse    = new Recart_AI_Antiabuse( $fingerprint, $logger );
        $coupon       = new Recart_AI_Coupon( $antiabuse, $logger );
        $webhook      = new Recart_AI_Webhook( $logger );
        $cart_tracker = new Recart_AI_Cart_Tracker( $fingerprint, $webhook, $logger );
        $popup        = new Recart_AI_Popup();
        $ajax         = new Recart_AI_Ajax( $fingerprint, $antiabuse, $coupon, $cart_tracker, $webhook, $logger );
        $cron         = new Recart_AI_Cron( $logger );

        // Admin
        if ( is_admin() ) {
            $admin = new Recart_AI_Admin( $logger );
            $admin->init();
        }

        // Frontend hooks
        $popup->init();
        $ajax->init();
        $cart_tracker->init();
        $cron->init();
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

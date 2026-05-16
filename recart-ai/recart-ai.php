<?php
/**
 * Plugin Name: ReCart AI - Smart Cart Recovery with Anti-Abuse
 * Plugin URI: https://asystent.io
 * Description: AI-powered abandoned cart recovery with exit-intent popups, fingerprint-based anti-abuse protection, and webhook integration. Recovers up to 15% of abandoned carts without cookies or login requirements.
 * Version: 1.0.0
 * Author: Asystent.io / DATA SERVICES PIOTR SIEMIŃSKI
 * Author URI: https://asystent.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: recart-ai
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * WC requires at least: 9.0
 * WC tested up to: 9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'RECART_AI_VERSION', '1.0.0' );
define( 'RECART_AI_PLUGIN_FILE', __FILE__ );
define( 'RECART_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RECART_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RECART_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RECART_AI_DB_VERSION', '1.0.0' );

/**
 * Check requirements before loading the plugin.
 */
function recart_ai_check_requirements(): bool {
    if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'ReCart AI requires PHP 8.1 or higher.', 'recart-ai' );
            echo '</p></div>';
        } );
        return false;
    }

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'ReCart AI requires WooCommerce 9.0 or higher to be installed and active.', 'recart-ai' );
            echo '</p></div>';
        } );
        return false;
    }

    return true;
}

/**
 * Plugin activation hook.
 */
function recart_ai_activate(): void {
    require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-activator.php';
    Recart_AI_Activator::activate();
}
register_activation_hook( __FILE__, 'recart_ai_activate' );

/**
 * Plugin deactivation hook.
 */
function recart_ai_deactivate(): void {
    require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai-deactivator.php';
    Recart_AI_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'recart_ai_deactivate' );

/**
 * Initialize the plugin after all plugins are loaded.
 */
function recart_ai_init(): void {
    if ( ! recart_ai_check_requirements() ) {
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'recart-ai', false, dirname( RECART_AI_PLUGIN_BASENAME ) . '/languages' );

    // Load core classes
    require_once RECART_AI_PLUGIN_DIR . 'includes/class-recart-ai.php';

    // Initialize main plugin class
    $plugin = new Recart_AI();
    $plugin->run();
}
add_action( 'plugins_loaded', 'recart_ai_init' );

/**
 * Declare WooCommerce HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

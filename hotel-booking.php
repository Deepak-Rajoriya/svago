<?php
/**
 * Plugin Name:       Hotel Booking (svago)
 * Plugin URI:        https://example.com/hotel-booking
 * Description:       Hotel booking plugin with OTA integration, WooCommerce, and Elementor support.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            svago
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       svago
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version
 */
define( 'SVAGO_VERSION', '1.0.0' );

/**
 * Plugin directory path
 */
define( 'SVAGO_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL
 */
define( 'SVAGO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename
 */
define( 'SVAGO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load textdomain for translations
 */
function svago_load_textdomain(): void {
    load_plugin_textdomain( 'svago', false, dirname( SVAGO_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'svago_load_textdomain' );

/**
 * Main plugin bootstrap class
 */
require_once SVAGO_PATH . 'includes/class-plugin.php';

/**
 * Initialize the plugin
 */
function svago_init(): void {
    SVAGO\Plugin::get_instance()->init();
}
add_action( 'plugins_loaded', 'svago_init', 10 );

/**
 * Activation hook
 */
function svago_activate(): void {
    SVAGO\Plugin::activate();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'svago_activate' );

/**
 * Deactivation hook
 */
function svago_deactivate(): void {
    SVAGO\Plugin::deactivate();
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'svago_deactivate' );

/**
 * Uninstall hook
 */
function svago_uninstall(): void {
    // Clean up options, tables, etc. if needed
    // delete_option( 'svago_settings' );
    // global $wpdb;
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}svago_logs" );
}
register_uninstall_hook( __FILE__, 'svago_uninstall' );
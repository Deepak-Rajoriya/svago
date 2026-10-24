<?php
/**
 * Core plugin bootstrap class
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class - Singleton
 */
final class Plugin {

    /**
     * Singleton instance
     *
     * @var Plugin|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Prevent direct instantiation
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Initialize the plugin
     */
    public function init(): void {
        $this->register_autoloader();
        $this->load_dependencies();
        $this->register_aliases();
        $this->init_hooks();
    }

    /**
     * Register autoloader for SVAGO namespace supporting WordPress file naming
     */
    private function register_autoloader(): void {
        spl_autoload_register( [ $this, 'autoload' ], true, true );
    }

    /**
     * Autoloader callback supporting WordPress naming conventions and class map
     *
     * @param string $class Fully qualified class name
     */
    public function autoload( string $class ): void {
        $prefix = 'SVAGO\\';
        $base_dir = SVAGO_PATH . 'includes/';

        $len = strlen( $prefix );
        if ( 0 !== strncmp( $prefix, $class, $len ) ) {
            return;
        }

        $relative_class = substr( $class, $len );

        // Direct classmap for existing plugin classes
        $class_map = [
            'Plugin'                 => 'class-plugin.php',
            'OTA_Provider_Interface' => 'interface-ota-provider.php',
            'Provider_Factory'       => 'class-provider-factory.php',
            'Mock_Provider'          => 'class-mock-provider.php',
            'Real_Provider'          => 'class-real-provider.php',
            'Fallback_Loader'        => 'class-fallback-loader.php',
            'Logger'                 => 'class-logger.php',
            'Settings'               => 'class-settings.php',
            'Room_Meta'              => 'class-room-meta.php',
            'Shortcodes'             => 'class-shortcodes.php',
            'Booking_Flow'           => 'class-booking-flow.php',
            'Order_Manager'          => 'class-order-manager.php',
            'Notifications'          => 'class-notifications.php',
            'Availability'           => 'class-availability.php',
            'Onsite_Booking'         => 'class-onsite-booking.php',
            'Product_Seeder'         => 'class-product-seeder.php',
        ];

        if ( isset( $class_map[ $relative_class ] ) ) {
            $file = $base_dir . $class_map[ $relative_class ];
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }

        // Standard WordPress class/interface naming fallback
        $slug = strtolower( str_replace( '_', '-', $relative_class ) );
        $candidates = [
            $base_dir . 'class-' . $slug . '.php',
            $base_dir . 'interface-' . $slug . '.php',
            $base_dir . str_replace( '\\', '/', $relative_class ) . '.php',
        ];

        foreach ( $candidates as $candidate ) {
            if ( file_exists( $candidate ) ) {
                require_once $candidate;
                return;
            }
        }
    }

    /**
     * Register global class aliases for convenience and requirements spec
     */
    private function register_aliases(): void {
        if ( ! class_exists( 'OTA_Provider_Factory', false ) && class_exists( 'SVAGO\Provider_Factory' ) ) {
            class_alias( 'SVAGO\Provider_Factory', 'OTA_Provider_Factory' );
        }
        if ( ! interface_exists( 'OTA_Provider_Interface', false ) && interface_exists( 'SVAGO\OTA_Provider_Interface' ) ) {
            class_alias( 'SVAGO\OTA_Provider_Interface', 'OTA_Provider_Interface' );
        }
    }

    /**
     * Load all core dependency classes safely
     */
    private function load_dependencies(): void {
        // Initialize Logger singleton
        if ( class_exists( 'SVAGO\Logger' ) ) {
            Logger::get_instance();
        }

        // Initialize Settings
        if ( class_exists( 'SVAGO\Settings' ) ) {
            if ( method_exists( 'SVAGO\Settings', 'get_instance' ) ) {
                Settings::get_instance();
            } else {
                new Settings();
            }
        }

        // Initialize Room Meta
        if ( class_exists( 'SVAGO\Room_Meta' ) ) {
            if ( method_exists( 'SVAGO\Room_Meta', 'get_instance' ) ) {
                Room_Meta::get_instance();
            } else {
                new Room_Meta();
            }
        }

        // Initialize Shortcodes & AJAX Handlers
        if ( class_exists( 'SVAGO\Shortcodes' ) ) {
            Shortcodes::get_instance();
        }

        // Initialize Booking Flow & Session Manager
        if ( class_exists( 'SVAGO\Booking_Flow' ) ) {
            Booking_Flow::get_instance();
        }

        // Initialize Order & Payment Manager (Phase 4)
        if ( class_exists( 'SVAGO\Order_Manager' ) ) {
            Order_Manager::get_instance();
        }

        // Initialize Notifications Manager (Phase 5)
        if ( class_exists( 'SVAGO\Notifications' ) ) {
            Notifications::get_instance();
        }

        // Initialize Availability Manager & Mapping (Phase 6)
        if ( class_exists( 'SVAGO\Availability' ) ) {
            Availability::get_instance();
        }

        // Initialize Onsite Booking Screen (Phase 6)
        if ( class_exists( 'SVAGO\Onsite_Booking' ) ) {
            Onsite_Booking::get_instance();
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Refresh provider factory when mode changes
        add_action( 'update_option_svago_provider_mode', [ 'SVAGO\Provider_Factory', 'refresh' ] );
    }

    /**
     * Plugin activation tasks
     */
    public static function activate(): void {
        // Create logger table
        if ( file_exists( SVAGO_PATH . 'includes/class-logger.php' ) ) {
            require_once SVAGO_PATH . 'includes/class-logger.php';
            Logger::activate();
        }

        // Auto-seed the 4 hotel rooms in WooCommerce
        if ( file_exists( SVAGO_PATH . 'includes/class-product-seeder.php' ) ) {
            require_once SVAGO_PATH . 'includes/class-product-seeder.php';
            Product_Seeder::seed_rooms();
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Schedule daily pre-arrival reminder cron if not already scheduled
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'svago_daily_pre_arrival_reminder' ) ) {
            wp_schedule_event( time(), 'daily', 'svago_daily_pre_arrival_reminder' );
        }

        // Set default options
        $default_options = [
            'provider_mode'          => 'mock',
            'ota_api_url'            => '',
            'ota_api_key'            => '',
            'ota_api_secret'         => '',
            'hotel_phone'            => '',
            'twilio_account_sid'     => '',
            'twilio_auth_token'      => '',
            'twilio_whatsapp_number' => '',
            'enable_whatsapp'        => true,
            'enable_email'           => true,
        ];

        foreach ( $default_options as $key => $value ) {
            add_option( "svago_{$key}", $value, '', 'no' );
        }
    }

    /**
     * Plugin deactivation tasks
     */
    public static function deactivate(): void {
        flush_rewrite_rules();

        // Clear scheduled pre-arrival reminder cron
        if ( function_exists( 'wp_next_scheduled' ) ) {
            $timestamp = wp_next_scheduled( 'svago_daily_pre_arrival_reminder' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'svago_daily_pre_arrival_reminder' );
            }
        }
    }
}
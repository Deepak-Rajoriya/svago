<?php
/**
 * OTA Provider Factory
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Factory for creating OTA provider instances
 * Returns Mock or Real provider based on settings
 */
final class Provider_Factory {

    /**
     * Cached provider instance
     *
     * @var OTA_Provider_Interface|null
     */
    private static ?OTA_Provider_Interface $instance = null;

    /**
     * Fallback loader instance
     *
     * @var Fallback_Loader|null
     */
    private static ?Fallback_Loader $fallback_loader = null;

    /**
     * Get provider instance (singleton)
     *
     * @return OTA_Provider_Interface
     */
    public static function get(): OTA_Provider_Interface {
        if ( null === self::$instance ) {
            self::$instance = self::create_provider();
        }
        return self::$instance;
    }

    /**
     * Force refresh provider instance (e.g., after settings change)
     */
    public static function refresh(): void {
        self::$instance = null;
        self::$fallback_loader = null;
    }

    /**
     * Create provider based on settings
     *
     * @return OTA_Provider_Interface
     */
    private static function create_provider(): OTA_Provider_Interface {
        $mode = get_option( 'svago_provider_mode', 'mock' );

        if ( 'live' === $mode ) {
            // Check if real provider is properly configured
            $url  = get_option( 'svago_ota_api_url', '' );
            $key  = get_option( 'svago_ota_api_key', '' );
            $secret = get_option( 'svago_ota_api_secret', '' );

            if ( ! empty( $url ) && ! empty( $key ) && ! empty( $secret ) ) {
                try {
                    return new Real_Provider();
                } catch ( \Throwable $e ) {
                    // Fall back to mock provider on error
                    Logger::get_instance()->log(
                        'error',
                        'Failed to initialize Real Provider, falling back to Mock',
                        [ 'error' => $e->getMessage() ]
                    );
                }
            } else {
                Logger::get_instance()->log(
                    'warning',
                    'Live mode selected but API credentials incomplete, using Mock Provider'
                );
            }
        }

        return new Mock_Provider();
    }

    /**
     * Get fallback loader instance
     *
     * @return Fallback_Loader
     */
    public static function get_fallback_loader(): Fallback_Loader {
        if ( null === self::$fallback_loader ) {
            self::$fallback_loader = new Fallback_Loader();
        }
        return self::$fallback_loader;
    }

    /**
     * Execute provider method with fallback handling
     *
     * @param callable $callback
     * @param string   $method_name
     * @param array    $fallback_data
     * @return array
     */
    public static function execute_with_fallback( callable $callback, string $method_name, array $fallback_data = [] ): array {
        try {
            $result = $callback();
            return $result;
        } catch ( \Throwable $e ) {
            Logger::get_instance()->log(
                'error',
                "Provider method {$method_name} failed, attempting fallback",
                [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ]
            );

            // Try to load fallback data
            $fallback_loader = self::get_fallback_loader();
            $fallback_result = $fallback_loader->load_fallback_for_method( $method_name );

            if ( ! empty( $fallback_result ) ) {
                // Mark as cached/fallback data
                if ( is_array( $fallback_result ) ) {
                    $fallback_result['_fallback'] = true;
                    $fallback_result['_fallback_message'] = 'Live availability temporarily unavailable — showing cached rates';
                }
                return $fallback_result;
            }

            // No fallback available
            $hotel_phone = get_option( 'svago_hotel_phone', '' );
            $message = 'Unable to retrieve data';
            if ( $hotel_phone ) {
                $message .= ". Please call {$hotel_phone} to book.";
            }

            Logger::get_instance()->log(
                'error',
                "No fallback available for {$method_name}",
                [ 'error' => $e->getMessage() ]
            );

            return [
                'success' => false,
                'error'   => $message,
                '_fallback_failed' => true,
            ];
        }
    }
}
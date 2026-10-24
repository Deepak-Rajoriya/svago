<?php
/**
 * Fallback Data Loader
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Loads fallback JSON data when OTA API is unavailable
 */
final class Fallback_Loader {

    /**
     * Fallback data directory path
     *
     * @var string
     */
    private string $data_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->data_dir = SVAGO_PATH . 'data/fallback/';
    }

    /**
     * Load rooms data
     *
     * @return array
     */
    public function load_rooms(): array {
        return $this->load_json( 'rooms.json' );
    }

    /**
     * Load availability data
     *
     * @return array
     */
    public function load_availability(): array {
        return $this->load_json( 'availability.json' );
    }

    /**
     * Load rates data
     *
     * @return array
     */
    public function load_rates(): array {
        return $this->load_json( 'rates.json' );
    }

    /**
     * Load config data
     *
     * @return array
     */
    public function load_config(): array {
        return $this->load_json( 'config.json' );
    }

    /**
     * Save rooms data
     *
     * @param array $data
     * @return bool
     */
    public function save_rooms( array $data ): bool {
        return $this->save_json( 'rooms.json', $data );
    }

    /**
     * Save availability data
     *
     * @param array $data
     * @return bool
     */
    public function save_availability( array $data ): bool {
        return $this->save_json( 'availability.json', $data );
    }

    /**
     * Save rates data
     *
     * @param array $data
     * @return bool
     */
    public function save_rates( array $data ): bool {
        return $this->save_json( 'rates.json', $data );
    }

    /**
     * Save config data
     *
     * @param array $config
     * @return bool
     */
    public function save_config( array $config ): bool {
        return $this->save_json( 'config.json', $config );
    }

    /**
     * Load fallback data for a specific provider method
     *
     * @param string $method_name
     * @return array
     */
    public function load_fallback_for_method( string $method_name ): array {
        return match ( $method_name ) {
            'get_rooms'       => ( $this->load_rooms()['rooms'] ?? [] ),
            'get_availability'=> $this->load_availability(),
            'get_rates'       => $this->load_rates(),
            'check_health'    => [ 'ok' => false, 'latency' => 0, 'message' => 'Fallback mode' ],
            default           => [],
        };
    }

    /**
     * Load JSON file
     *
     * @param string $filename
     * @return array
     */
    private function load_json( string $filename ): array {
        $filepath = $this->data_dir . $filename;

        if ( ! file_exists( $filepath ) ) {
            Logger::get_instance()->log(
                'warning',
                "Fallback file not found: {$filename}",
                [ 'path' => $filepath ]
            );
            return [];
        }

        $content = file_get_contents( $filepath );
        if ( false === $content ) {
            Logger::get_instance()->log(
                'error',
                "Failed to read fallback file: {$filename}",
                [ 'path' => $filepath ]
            );
            return [];
        }

        $data = json_decode( $content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            Logger::get_instance()->log(
                'error',
                "Invalid JSON in fallback file: {$filename}",
                [ 'error' => json_last_error_msg(), 'path' => $filepath ]
            );
            return [];
        }

        return $data ?? [];
    }

    /**
     * Save JSON file
     *
     * @param string $filename
     * @param array  $data
     * @return bool
     */
    private function save_json( string $filename, array $data ): bool {
        $filepath = $this->data_dir . $filename;

        // Ensure directory exists
        if ( ! is_dir( $this->data_dir ) ) {
            wp_mkdir_p( $this->data_dir );
        }

        $json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            Logger::get_instance()->log(
                'error',
                "Failed to encode JSON for: {$filename}",
                [ 'error' => json_last_error_msg() ]
            );
            return false;
        }

        $result = file_put_contents( $filepath, $json, LOCK_EX );
        if ( false === $result ) {
            Logger::get_instance()->log(
                'error',
                "Failed to write fallback file: {$filename}",
                [ 'path' => $filepath ]
            );
            return false;
        }

        return true;
    }
}
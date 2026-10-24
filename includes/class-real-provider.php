<?php
/**
 * Real OTA Provider (Phase 8 Production Channel Manager Integration)
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Real OTA API provider connecting to Live / Sandbox Channel Manager
 *
 * Implements:
 * - 8.1 API Client: Authenticated HTTP client with key/secret/HMAC headers
 * - 8.2 Endpoint Mapping: RESTful Channel Manager endpoints
 * - 8.3 Payload Transformation: WooCommerce orders -> Canonical OTA schemas
 * - 8.4 Response Parsing: OTA JSON -> Internal structures
 * - 8.5 Error Mapping: HTTP/OTA error codes -> Friendly localized messages
 * - 8.6 Sandbox Testing: Sandbox endpoint simulation for non-production environments
 * - 8.7 Go-Live Readiness: Strict type validation and health checks
 */
final class Real_Provider implements OTA_Provider_Interface {

    /**
     * API credentials and configuration
     *
     * @var array
     */
    private array $credentials;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private int $timeout;

    /**
     * Constructor - load settings from WordPress options
     */
    public function __construct() {
        $this->credentials = [
            'url'    => get_option( 'svago_ota_api_url', '' ),
            'key'    => get_option( 'svago_ota_api_key', '' ),
            'secret' => get_option( 'svago_ota_api_secret', '' ),
        ];
        $this->timeout = function_exists( 'apply_filters' ) ? (int) apply_filters( 'svago_ota_request_timeout', 15 ) : 15;
    }

    /**
     * Get API credentials
     *
     * @return array
     */
    public function get_credentials(): array {
        return $this->credentials;
    }

    /**
     * Check if currently configured for sandbox/test mode
     *
     * @return bool
     */
    public function is_sandbox(): bool {
        $url = $this->credentials['url'] ?? '';
        return (
            defined( 'SVAGO_OTA_SANDBOX_MOCK' ) && SVAGO_OTA_SANDBOX_MOCK ||
            strpos( $url, 'sandbox' ) !== false ||
            strpos( $url, 'test' ) !== false ||
            strpos( $url, 'mock' ) !== false
        );
    }

    /**
     * Make authenticated HTTP request to OTA Channel Manager (Task 8.1 & 8.2)
     *
     * @param string $endpoint API endpoint path (e.g., '/rooms', '/availability')
     * @param string $method   HTTP method (GET, POST, PATCH, DELETE)
     * @param array  $body     Payload data
     * @return array Parsed response data
     * @throws \RuntimeException On API error or network failure
     */
    public function request( string $endpoint, string $method = 'GET', array $body = [] ): array {
        $base_url = rtrim( $this->credentials['url'], '/' );
        $key      = $this->credentials['key'];
        $secret   = $this->credentials['secret'];

        // Guard against missing configuration in live mode
        if ( empty( $base_url ) || empty( $key ) || empty( $secret ) ) {
            $msg = $this->map_error_code( 401, 'OTA API credentials are incomplete. Please configure URL, Key, and Secret in settings.' );
            Logger::get_instance()->log( 'error', 'Real_Provider request aborted: ' . $msg );
            throw new \RuntimeException( $msg );
        }

        // Sandbox Simulator mode for testing when pointing to sandbox URL (Task 8.6)
        if ( $this->is_sandbox() ) {
            return $this->simulate_sandbox_request( $endpoint, $method, $body );
        }

        $full_url  = $base_url . '/' . ltrim( $endpoint, '/' );
        $timestamp = time();
        $body_json = ! empty( $body ) ? wp_json_encode( $body ) : '';

        // Build authenticated headers (Task 8.1)
        // Includes API Key, Bearer Secret token, timestamp, and HMAC-SHA256 signature
        $signature = hash_hmac( 'sha256', $method . '|' . $endpoint . '|' . $timestamp . '|' . $body_json, $secret );

        $headers = [
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
            'X-API-Key'        => $key,
            'Authorization'    => 'Bearer ' . $secret,
            'X-OTA-Timestamp'  => (string) $timestamp,
            'X-OTA-Signature'  => $signature,
            'User-Agent'       => 'Svago-Hotel-Booking/' . ( defined( 'SVAGO_VERSION' ) ? SVAGO_VERSION : '1.0' ),
        ];

        $request_args = [
            'method'      => $method,
            'timeout'     => $this->timeout,
            'redirection' => 2,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => $headers,
            'sslverify'   => function_exists( 'apply_filters' ) ? apply_filters( 'svago_ota_sslverify', true ) : true,
        ];

        if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) && ! empty( $body_json ) ) {
            $request_args['body'] = $body_json;
        }

        $start_time = microtime( true );
        if ( function_exists( 'wp_remote_request' ) ) {
            $response = wp_remote_request( $full_url, $request_args );
        } else {
            $response = new \WP_Error( 'http_error', 'WordPress HTTP transport unavailable' );
        }
        $latency = (int) ( ( microtime( true ) - $start_time ) * 1000 );

        // Handle WordPress HTTP client transport errors
        if ( is_wp_error( $response ) ) {
            $error_msg = $this->map_error_code( 503, $response->get_error_message() );
            Logger::get_instance()->log(
                'error',
                sprintf( 'OTA API Transport Failure [%s %s]: %s (%d ms)', $method, $endpoint, $error_msg, $latency ),
                [ 'url' => $full_url, 'error' => $response->get_error_message() ]
            );
            throw new \RuntimeException( $error_msg );
        }

        $http_code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : ( is_array( $response ) ? ( $response['response']['code'] ?? 200 ) : 500 );
        $raw_body  = function_exists( 'wp_remote_retrieve_body' ) ? wp_remote_retrieve_body( $response ) : ( is_array( $response ) ? ( $response['body'] ?? '' ) : '' );
        $parsed    = json_decode( $raw_body, true );

        // Handle HTTP error codes (Task 8.5)
        if ( $http_code < 200 || $http_code >= 300 ) {
            $server_message = is_array( $parsed ) ? ( $parsed['message'] ?? ( $parsed['error'] ?? '' ) ) : $raw_body;
            $friendly_msg   = $this->map_error_code( $http_code, (string) $server_message );

            Logger::get_instance()->log(
                'error',
                sprintf( 'OTA API Error [%d %s %s]: %s', $http_code, $method, $endpoint, $friendly_msg ),
                [ 'http_code' => $http_code, 'response' => $parsed ?: $raw_body ]
            );

            throw new \RuntimeException( $friendly_msg, $http_code );
        }

        if ( ! is_array( $parsed ) ) {
            $parsed = [ 'raw' => $raw_body ];
        }

        return $parsed;
    }

    /**
     * Map HTTP and OTA error codes to friendly localized messages (Task 8.5)
     *
     * @param int    $code HTTP or OTA error code
     * @param string $raw_message Raw server message
     * @return string
     */
    public function map_error_code( int $code, string $raw_message = '' ): string {
        switch ( $code ) {
            case 400:
                return __( 'Invalid booking request parameters sent to Channel Manager.', 'svago' );

            case 401:
                return __( 'OTA Channel Manager authentication failed. Please check your API Key and Secret.', 'svago' );

            case 403:
                return __( 'Access forbidden by OTA Channel Manager. Please verify account permissions.', 'svago' );

            case 404:
                return __( 'The requested room, rate, or booking record was not found in Channel Manager.', 'svago' );

            case 409:
                return __( 'This room is no longer available for your dates. Please try other dates or rooms.', 'svago' );

            case 422:
                return __( 'Booking payload format was rejected by the Channel Manager validation rules.', 'svago' );

            case 429:
                return __( 'OTA Channel Manager rate limit exceeded. Please wait a moment and try again.', 'svago' );

            case 500:
                return __( 'OTA Channel Manager encountered an internal system error. Please retry shortly.', 'svago' );

            case 502:
            case 503:
            case 504:
                return __( 'OTA Channel Manager is temporarily unavailable. Cached rates are active.', 'svago' );

            default:
                if ( ! empty( $raw_message ) ) {
                    return sprintf( __( 'Channel Manager notice: %s', 'svago' ), sanitize_text_field( $raw_message ) );
                }
                return __( 'An unexpected error occurred while communicating with Channel Manager.', 'svago' );
        }
    }

    /**
     * Format booking payload for OTA Channel Manager (Task 8.3)
     *
     * @param array $booking Internal order booking metadata
     * @return array Canonical OTA booking payload
     */
    public function format_booking_payload( array $booking ): array {
        $order_id = $booking['order_id'] ?? 0;

        return [
            'booking_reference' => sprintf( 'SV-%d', $order_id ),
            'source'            => $booking['source'] ?? 'website',
            'room'              => [
                'id'            => $booking['room_id'] ?? '',
                'name'          => $booking['room_name'] ?? '',
                'rate_plan_id'  => $booking['rate_id'] ?? '',
                'rate_name'     => $booking['rate_name'] ?? '',
            ],
            'stay'              => [
                'check_in'      => $booking['check_in'] ?? '',
                'check_out'     => $booking['check_out'] ?? '',
                'nights'        => (int) ( $booking['nights'] ?? 1 ),
            ],
            'occupancy'         => [
                'adults'        => (int) ( $booking['adults'] ?? 2 ),
                'children'      => (int) ( $booking['children'] ?? 0 ),
                'child_ages'    => $booking['child_ages'] ?? [],
            ],
            'guest'             => [
                'name'          => $booking['guest_name'] ?? '',
                'first_name'    => $booking['guest_first_name'] ?? '',
                'last_name'     => $booking['guest_last_name'] ?? '',
                'email'         => $booking['guest_email'] ?? '',
                'phone'         => $booking['guest_phone'] ?? '',
                'eta'           => $booking['guest_eta'] ?? '',
                'requests'      => $booking['special_requests'] ?? '',
            ],
            'pricing'           => [
                'total_amount'     => (float) ( $booking['total_amount'] ?? 0 ),
                'price_per_night'  => (float) ( $booking['price_per_night'] ?? 0 ),
                'currency'         => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR',
                'payment_method'   => $booking['payment_method'] ?? 'Razorpay',
                'payment_status'   => $booking['payment_status'] ?? 'Paid',
            ],
            'created_at'        => current_time( 'mysql' ),
        ];
    }

    /**
     * Format availability update payload for OTA Channel Manager (Task 8.3)
     *
     * @param string $room_id OTA room identifier
     * @param array  $dates   Array of dates in YYYY-MM-DD
     * @param int    $qty     Room quantity
     * @return array
     */
    public function format_availability_payload( string $room_id, array $dates, int $qty ): array {
        $updates = [];
        foreach ( $dates as $date ) {
            $updates[] = [
                'date'      => $date,
                'inventory' => max( 0, $qty ),
                'status'    => $qty > 0 ? 'available' : 'closed',
            ];
        }

        return [
            'room_id' => $room_id,
            'updates' => $updates,
        ];
    }

    /**
     * Format cancellation payload for OTA Channel Manager (Task 8.3)
     *
     * @param array $booking
     * @return array
     */
    public function format_cancellation_payload( array $booking ): array {
        return [
            'ota_booking_id' => $booking['ota_booking_id'] ?? '',
            'order_id'       => $booking['order_id'] ?? 0,
            'reason'         => $booking['reason'] ?? 'Guest or administrative cancellation',
            'cancelled_at'   => current_time( 'mysql' ),
        ];
    }

    /**
     * Parse rooms response from OTA (Task 8.4)
     *
     * @param array $response
     * @return array Standardized rooms array
     */
    public function parse_rooms_response( array $response ): array {
        $raw_rooms = $response['rooms'] ?? ( $response['data'] ?? ( isset( $response[0] ) ? $response : [] ) );
        if ( ! is_array( $raw_rooms ) ) {
            return [];
        }

        $standardized = [];
        foreach ( $raw_rooms as $r ) {
            $id = $r['id'] ?? ( $r['room_id'] ?? '' );
            if ( empty( $id ) ) {
                continue;
            }

            $standardized[] = [
                'id'           => (string) $id,
                'name'         => sanitize_text_field( $r['name'] ?? ( $r['title'] ?? 'Hotel Room' ) ),
                'description'  => sanitize_textarea_field( $r['description'] ?? '' ),
                'size'         => sanitize_text_field( $r['size'] ?? ( $r['room_size'] ?? '350 sq ft' ) ),
                'bed_type'     => sanitize_text_field( $r['bed_type'] ?? 'King size Bed' ),
                'max_adults'   => (int) ( $r['max_adults'] ?? 2 ),
                'max_children' => (int) ( $r['max_children'] ?? 1 ),
                'amenities'    => is_array( $r['amenities'] ?? null ) ? $r['amenities'] : [],
                'images'       => is_array( $r['images'] ?? null ) ? $r['images'] : [],
            ];
        }

        return $standardized;
    }

    /**
     * Parse availability response from OTA (Task 8.4)
     *
     * @param array $response
     * @return array [ room_id => [ 'available' => bool, 'price' => float, 'inventory' => int ] ]
     */
    public function parse_availability_response( array $response ): array {
        $raw_avail = $response['availability'] ?? ( $response['data'] ?? $response );
        if ( ! is_array( $raw_avail ) ) {
            return [];
        }

        $standardized = [];
        foreach ( $raw_avail as $room_id => $data ) {
            if ( ! is_array( $data ) ) {
                continue;
            }

            // Could be single summary or daily breakdown
            if ( isset( $data['available'] ) ) {
                $standardized[ $room_id ] = [
                    'available' => ! empty( $data['available'] ),
                    'price'     => (float) ( $data['price'] ?? 0 ),
                    'inventory' => (int) ( $data['inventory'] ?? 1 ),
                ];
            } else {
                // Determine cumulative availability over all dates
                $all_avail = true;
                $min_inv   = PHP_INT_MAX;
                $tot_price = 0;

                foreach ( $data as $day_data ) {
                    if ( is_array( $day_data ) ) {
                        if ( empty( $day_data['available'] ) || ( isset( $day_data['inventory'] ) && $day_data['inventory'] <= 0 ) ) {
                            $all_avail = false;
                        }
                        if ( isset( $day_data['inventory'] ) ) {
                            $min_inv = min( $min_inv, (int) $day_data['inventory'] );
                        }
                        $tot_price += (float) ( $day_data['price'] ?? 0 );
                    }
                }

                $standardized[ $room_id ] = [
                    'available' => $all_avail,
                    'price'     => $tot_price,
                    'inventory' => $min_inv !== PHP_INT_MAX ? $min_inv : 1,
                ];
            }
        }

        return $standardized;
    }

    /**
     * Parse rates response from OTA (Task 8.4)
     *
     * @param array $response
     * @return array Standardized rate plans array
     */
    public function parse_rates_response( array $response ): array {
        $raw_rates = $response['rates'] ?? ( $response['rate_plans'] ?? ( $response['data'] ?? ( isset( $response[0] ) ? $response : [] ) ) );
        if ( ! is_array( $raw_rates ) ) {
            return [];
        }

        $standardized = [];
        foreach ( $raw_rates as $rate ) {
            if ( ! is_array( $rate ) ) {
                continue;
            }
            $standardized[] = [
                'rate_id'         => (string) ( $rate['rate_id'] ?? ( $rate['id'] ?? 'standard' ) ),
                'name'            => sanitize_text_field( $rate['name'] ?? ( $rate['title'] ?? 'Standard Rate' ) ),
                'price_per_night' => (float) ( $rate['price_per_night'] ?? ( $rate['nightly_rate'] ?? 0 ) ),
                'tax_included'    => ! empty( $rate['tax_included'] ),
                'description'     => sanitize_text_field( $rate['description'] ?? '' ),
            ];
        }

        return $standardized;
    }

    /**
     * Parse booking response from OTA (Task 8.4)
     *
     * @param array $response
     * @return array
     */
    public function parse_booking_response( array $response ): array {
        $ota_id = $response['ota_booking_id'] ?? ( $response['booking_id'] ?? ( $response['id'] ?? ( $response['data']['id'] ?? '' ) ) );

        if ( ! empty( $ota_id ) ) {
            return [
                'success'        => true,
                'ota_booking_id' => (string) $ota_id,
                'status'         => $response['status'] ?? 'confirmed',
                'message'        => $response['message'] ?? 'Booking confirmed by Channel Manager',
            ];
        }

        return [
            'success'        => false,
            'ota_booking_id' => null,
            'message'        => $response['message'] ?? __( 'Channel Manager did not return a valid booking ID.', 'svago' ),
        ];
    }

    /**
     * OTA_Provider_Interface Implementation: Get all rooms
     *
     * @return array
     */
    public function get_rooms(): array {
        $response = $this->request( '/rooms', 'GET' );
        return $this->parse_rooms_response( $response );
    }

    /**
     * OTA_Provider_Interface Implementation: Get availability for date range
     *
     * @param array $params
     * @return array
     */
    public function get_availability( array $params ): array {
        $response = $this->request( '/availability', 'POST', $params );
        return $this->parse_availability_response( $response );
    }

    /**
     * OTA_Provider_Interface Implementation: Get rate plans for room
     *
     * @param string $room_id
     * @param array  $dates
     * @return array
     */
    public function get_rates( string $room_id, array $dates ): array {
        $payload = [
            'room_id'   => $room_id,
            'check_in'  => $dates[0] ?? '',
            'check_out' => end( $dates ) ?: '',
            'dates'     => $dates,
        ];

        $response = $this->request( sprintf( '/rooms/%s/rates', rawurlencode( $room_id ) ), 'POST', $payload );
        return $this->parse_rates_response( $response );
    }

    /**
     * OTA_Provider_Interface Implementation: Push booking to OTA (Task 4.5 & Task 8.3)
     *
     * @param array $booking
     * @return array
     */
    public function push_booking( array $booking ): array {
        $payload  = $this->format_booking_payload( $booking );
        $response = $this->request( '/bookings', 'POST', $payload );
        return $this->parse_booking_response( $response );
    }

    /**
     * OTA_Provider_Interface Implementation: Push modification to OTA
     *
     * @param array $booking
     * @return array
     */
    public function push_modification( array $booking ): array {
        $ota_id   = $booking['ota_booking_id'] ?? '';
        $endpoint = sprintf( '/bookings/%s', rawurlencode( (string) $ota_id ) );
        $response = $this->request( $endpoint, 'PATCH', $booking );

        return [
            'success' => ! empty( $response['success'] ) || isset( $response['id'] ),
            'message' => $response['message'] ?? 'Booking modified in Channel Manager',
        ];
    }

    /**
     * OTA_Provider_Interface Implementation: Push cancellation to OTA (Task 6.5 & Task 8.3)
     *
     * @param array $booking
     * @return array
     */
    public function push_cancellation( array $booking ): array {
        $ota_id   = $booking['ota_booking_id'] ?? '';
        $endpoint = sprintf( '/bookings/%s/cancel', rawurlencode( (string) $ota_id ) );
        $payload  = $this->format_cancellation_payload( $booking );
        $response = $this->request( $endpoint, 'POST', $payload );

        return [
            'success' => ! empty( $response['success'] ) || isset( $response['cancelled'] ) || isset( $response['status'] ),
            'message' => $response['message'] ?? 'Booking cancelled in Channel Manager',
        ];
    }

    /**
     * OTA_Provider_Interface Implementation: Push availability to OTA (Task 6.1 & Task 8.3)
     *
     * @param string $room_id
     * @param array  $dates
     * @param int    $qty
     * @return array
     */
    public function push_availability( string $room_id, array $dates, int $qty ): array {
        $payload  = $this->format_availability_payload( $room_id, $dates, $qty );
        $response = $this->request( '/availability', 'PUT', $payload );

        return [
            'success' => ! empty( $response['success'] ) || isset( $response['updated'] ),
            'message' => $response['message'] ?? 'Availability synced to Channel Manager',
        ];
    }

    /**
     * OTA_Provider_Interface Implementation: Check provider health (Task 7.6 & Task 8.7)
     *
     * @return array
     */
    public function check_health(): array {
        $start = microtime( true );

        try {
            $response = $this->request( '/health', 'GET' );
            $latency  = (int) ( ( microtime( true ) - $start ) * 1000 );

            return [
                'ok'      => true,
                'latency' => $latency,
                'message' => $response['message'] ?? 'Channel Manager API Operational',
            ];
        } catch ( \Throwable $e ) {
            $latency = (int) ( ( microtime( true ) - $start ) * 1000 );

            return [
                'ok'      => false,
                'latency' => $latency,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simulate Sandbox Channel Manager responses for non-production environments (Task 8.6)
     *
     * @param string $endpoint
     * @param string $method
     * @param array  $body
     * @return array
     */
    private function simulate_sandbox_request( string $endpoint, string $method, array $body ): array {
        $loader = new Fallback_Loader();

        // 1. Health check
        if ( str_ends_with( $endpoint, '/health' ) ) {
            return [
                'status'  => 'operational',
                'message' => 'OTA Sandbox Channel Manager Operational',
                'sandbox' => true,
            ];
        }

        // 2. Room catalog
        if ( str_ends_with( $endpoint, '/rooms' ) && 'GET' === $method ) {
            $rooms = $loader->load_rooms();
            return [ 'rooms' => $rooms['rooms'] ?? [], 'sandbox' => true ];
        }

        // 3. Availability query
        if ( str_ends_with( $endpoint, '/availability' ) && 'POST' === $method ) {
            $avail = $loader->load_availability();
            return [ 'availability' => $avail, 'sandbox' => true ];
        }

        // 4. Rate plans
        if ( strpos( $endpoint, '/rates' ) !== false ) {
            $rates = $loader->load_rates();
            $room_id = $body['room_id'] ?? 'RM001';
            return [ 'rates' => $rates[ $room_id ] ?? [], 'sandbox' => true ];
        }

        // 5. Booking Push
        if ( str_ends_with( $endpoint, '/bookings' ) && 'POST' === $method ) {
            $mock_ota_id = sprintf( 'OTA_SANDBOX_%08d', mt_rand( 10000000, 99999999 ) );
            return [
                'success'        => true,
                'ota_booking_id' => $mock_ota_id,
                'status'         => 'confirmed',
                'sandbox'        => true,
            ];
        }

        // 6. Cancellation Push
        if ( strpos( $endpoint, '/cancel' ) !== false ) {
            return [
                'success'   => true,
                'cancelled' => true,
                'message'   => 'Sandbox cancellation processed successfully',
                'sandbox'   => true,
            ];
        }

        // 7. Availability Push
        if ( str_ends_with( $endpoint, '/availability' ) && ( 'PUT' === $method || 'POST' === $method ) ) {
            return [
                'success' => true,
                'updated' => true,
                'message' => 'Sandbox availability updated',
                'sandbox' => true,
            ];
        }

        // Default response
        return [
            'success' => true,
            'message' => 'Sandbox request acknowledged',
            'sandbox' => true,
        ];
    }
}
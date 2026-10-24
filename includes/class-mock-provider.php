<?php
/**
 * Mock OTA Provider
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mock provider implementation - reads from JSON fallback files
 */
final class Mock_Provider implements OTA_Provider_Interface {

    /**
     * Fallback loader instance
     *
     * @var Fallback_Loader
     */
    private Fallback_Loader $fallback_loader;

    /**
     * Cached data
     *
     * @var array
     */
    private array $cached_data = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->fallback_loader = new Fallback_Loader();
        $this->load_all_data();
    }

    /**
     * Load all fallback data into memory
     */
    private function load_all_data(): void {
        $this->cached_data['rooms']       = $this->fallback_loader->load_rooms();
        $this->cached_data['availability'] = $this->fallback_loader->load_availability();
        $this->cached_data['rates']       = $this->fallback_loader->load_rates();
        $this->cached_data['config']      = $this->fallback_loader->load_config();
    }

    /**
     * Get all rooms
     *
     * @return array
     */
    public function get_rooms(): array {
        return $this->cached_data['rooms']['rooms'] ?? [];
    }

    /**
     * Get availability for date range
     *
     * @param array $params
     * @return array
     */
    /**
     * Get availability for date range
     *
     * @param array $params
     * @return array
     */
    public function get_availability( array $params ): array {
        $check_in  = $params['check_in']  ?? '';
        $check_out = $params['check_out'] ?? '';
        $adults    = (int) ( $params['adults'] ?? 1 );
        $children  = (int) ( $params['children'] ?? 0 );

        if ( empty( $check_in ) || empty( $check_out ) ) {
            return [];
        }

        $dates = $this->get_date_range( $check_in, $check_out );
        if ( empty( $dates ) ) {
            return [];
        }

        $availability = $this->cached_data['availability'] ?? [];
        $rooms        = $this->get_rooms();
        $result       = [];

        foreach ( $rooms as $room ) {
            $room_id = $room['id'] ?? '';
            if ( empty( $room_id ) || ! isset( $availability[ $room_id ] ) ) {
                continue;
            }

            // Check occupancy constraints
            $max_adults   = (int) ( $room['max_adults'] ?? 2 );
            $max_children = (int) ( $room['max_children'] ?? 0 );
            if ( $adults > $max_adults || $children > $max_children ) {
                continue;
            }

            $room_availability = $availability[ $room_id ];
            $room_dates        = [];
            $all_nights_available = true;
            $min_inventory     = PHP_INT_MAX;
            $total_base_price  = 0;

            foreach ( $dates as $date ) {
                $day_data = $room_availability[ $date ] ?? [ 'available' => false, 'price' => 0, 'inventory' => 0 ];
                $room_dates[ $date ] = $day_data;

                $day_inventory = (int) ( $day_data['inventory'] ?? 0 );
                $day_available = (bool) ( $day_data['available'] ?? false ) && ( $day_inventory > 0 );

                if ( ! $day_available ) {
                    $all_nights_available = false;
                }

                $min_inventory    = min( $min_inventory, $day_inventory );
                $total_base_price += (float) ( $day_data['price'] ?? 0 );
            }

            if ( $min_inventory === PHP_INT_MAX ) {
                $min_inventory = 0;
            }

            // Only return room if available for the full requested stay
            if ( $all_nights_available && $min_inventory > 0 ) {
                $result[ $room_id ] = [
                    'room_id'       => $room_id,
                    'room_name'     => $room['name'] ?? '',
                    'available'     => true,
                    'inventory'     => $min_inventory,
                    'total_price'   => $total_base_price,
                    'nights'        => count( $dates ),
                    'dates'         => $room_dates,
                    'adults'        => $adults,
                    'children'      => $children,
                ];
            }
        }

        return $result;
    }

    /**
     * Get rate plans for a room
     *
     * @param string $room_id
     * @param array  $dates
     * @return array
     */
    public function get_rates( string $room_id, array $dates ): array {
        $rates = $this->cached_data['rates'][ $room_id ] ?? [];

        // Calculate total for each rate
        $check_in  = $dates['check_in']  ?? '';
        $check_out = $dates['check_out'] ?? '';
        $nights    = $this->calculate_nights( $check_in, $check_out );

        foreach ( $rates as &$rate ) {
            $price_per_night     = $rate['price_per_night'] ?? 0;
            $rate['nights']      = max( 1, $nights );
            $rate['total_price'] = $price_per_night * max( 1, $nights );
        }
        unset( $rate );

        return $rates;
    }

    /**
     * Push booking to OTA (mock)
     *
     * @param array $booking
     * @return array
     */
    public function push_booking( array $booking ): array {
        // Simulate API call delay
        usleep( 50000 ); // 50ms

        // Generate mock OTA booking ID
        $ota_booking_id = 'OTA-' . strtoupper( substr( md5( microtime( true ) . rand() ), 0, 10 ) );

        // Decrease inventory for the booked dates in mock availability
        $check_in  = $booking['check_in'] ?? '';
        $check_out = $booking['check_out'] ?? '';
        $room_id   = $booking['ota_room_id'] ?? '';

        if ( ! empty( $check_in ) && ! empty( $check_out ) && ! empty( $room_id ) ) {
            $dates = $this->get_date_range( $check_in, $check_out );
            $availability = $this->cached_data['availability'];

            if ( isset( $availability[ $room_id ] ) ) {
                foreach ( $dates as $date ) {
                    if ( isset( $availability[ $room_id ][ $date ] ) ) {
                        $current_inv = (int) ( $availability[ $room_id ][ $date ]['inventory'] ?? 1 );
                        $new_inv     = max( 0, $current_inv - 1 );
                        $availability[ $room_id ][ $date ]['inventory'] = $new_inv;
                        $availability[ $room_id ][ $date ]['available'] = ( $new_inv > 0 );
                    }
                }
                $this->cached_data['availability'] = $availability;
                $this->fallback_loader->save_availability( $availability );
            }
        }

        // Log the booking
        Logger::get_instance()->log(
            'info',
            'Mock booking pushed to OTA',
            [ 'ota_booking_id' => $ota_booking_id, 'booking' => $booking ]
        );

        return [
            'success'        => true,
            'ota_booking_id' => $ota_booking_id,
            'message'        => 'Booking created successfully (mock)',
        ];
    }

    /**
     * Push modification to OTA (mock)
     *
     * @param array $booking
     * @return array
     */
    public function push_modification( array $booking ): array {
        usleep( 30000 );

        Logger::get_instance()->log(
            'info',
            'Mock booking modification pushed to OTA',
            $booking
        );

        return [
            'success' => true,
            'message' => 'Booking modified successfully (mock)',
        ];
    }

    /**
     * Push cancellation to OTA (mock)
     *
     * @param array $booking
     * @return array
     */
    public function push_cancellation( array $booking ): array {
        usleep( 30000 );

        // Restore inventory for cancelled booking if dates provided
        $check_in  = $booking['check_in'] ?? '';
        $check_out = $booking['check_out'] ?? '';
        $room_id   = $booking['ota_room_id'] ?? '';

        if ( ! empty( $check_in ) && ! empty( $check_out ) && ! empty( $room_id ) ) {
            $dates = $this->get_date_range( $check_in, $check_out );
            $availability = $this->cached_data['availability'];

            if ( isset( $availability[ $room_id ] ) ) {
                foreach ( $dates as $date ) {
                    if ( isset( $availability[ $room_id ][ $date ] ) ) {
                        $current_inv = (int) ( $availability[ $room_id ][ $date ]['inventory'] ?? 0 );
                        $new_inv     = $current_inv + 1;
                        $availability[ $room_id ][ $date ]['inventory'] = $new_inv;
                        $availability[ $room_id ][ $date ]['available'] = true;
                    }
                }
                $this->cached_data['availability'] = $availability;
                $this->fallback_loader->save_availability( $availability );
            }
        }

        Logger::get_instance()->log(
            'info',
            'Mock booking cancellation pushed to OTA',
            $booking
        );

        return [
            'success' => true,
            'message' => 'Booking cancelled successfully (mock)',
        ];
    }

    /**
     * Push availability to OTA (mock)
     *
     * @param string $room_id
     * @param array  $dates
     * @param int    $qty
     * @return array
     */
    public function push_availability( string $room_id, array $dates, int $qty ): array {
        usleep( 30000 );

        // Update local fallback data
        $availability = $this->cached_data['availability'];
        if ( ! isset( $availability[ $room_id ] ) ) {
            $availability[ $room_id ] = [];
        }

        foreach ( $dates as $date_item ) {
            if ( is_array( $date_item ) ) {
                $date_str = $date_item['date'] ?? '';
                $item_qty = isset( $date_item['qty'] ) ? (int) $date_item['qty'] : $qty;
                $price    = isset( $date_item['price'] ) ? (float) $date_item['price'] : null;
                $blocked  = isset( $date_item['blocked'] ) ? (bool) $date_item['blocked'] : null;
            } else {
                $date_str = (string) $date_item;
                $item_qty = $qty;
                $price    = null;
                $blocked  = null;
            }

            if ( empty( $date_str ) ) {
                continue;
            }

            if ( ! isset( $availability[ $room_id ][ $date_str ] ) ) {
                $availability[ $room_id ][ $date_str ] = [ 'available' => true, 'price' => 0, 'inventory' => 0 ];
            }
            $availability[ $room_id ][ $date_str ]['inventory'] = $item_qty;
            $availability[ $room_id ][ $date_str ]['available'] = ( $item_qty > 0 && ! $blocked );

            if ( null !== $price ) {
                $availability[ $room_id ][ $date_str ]['price'] = $price;
            }
            if ( null !== $blocked ) {
                $availability[ $room_id ][ $date_str ]['blocked'] = $blocked;
            }
        }

        $this->cached_data['availability'] = $availability;

        // Save to file
        $this->fallback_loader->save_availability( $availability );

        Logger::get_instance()->log(
            'info',
            'Mock availability pushed to OTA',
            [ 'room_id' => $room_id, 'dates' => $dates, 'qty' => $qty ]
        );

        return [
            'success' => true,
            'message' => 'Availability updated successfully (mock)',
        ];
    }

    /**
     * Check provider health (mock)
     *
     * @return array
     */
    public function check_health(): array {
        $start = microtime( true );

        // Verify fallback files exist and are readable
        $rooms = $this->fallback_loader->load_rooms();
        $avail = $this->fallback_loader->load_availability();
        $rates = $this->fallback_loader->load_rates();

        $latency = (int) ( ( microtime( true ) - $start ) * 1000 );

        if ( empty( $rooms ) || empty( $avail ) || empty( $rates ) ) {
            return [
                'ok'      => false,
                'latency' => $latency,
                'message' => 'Mock provider warning: Fallback JSON files missing or empty',
            ];
        }

        return [
            'ok'      => true,
            'latency' => $latency,
            'message' => 'Mock provider healthy (local fallback data verified)',
        ];
    }

    /**
     * Get date range between check-in and check-out
     *
     * @param string $check_in
     * @param string $check_out
     * @return array
     */
    private function get_date_range( string $check_in, string $check_out ): array {
        try {
            $start = new \DateTime( $check_in );
            $end   = new \DateTime( $check_out );
        } catch ( \Exception $e ) {
            return [];
        }

        if ( $start >= $end ) {
            return [];
        }

        $dates    = [];
        $interval = new \DateInterval( 'P1D' );
        $period   = new \DatePeriod( $start, $interval, $end );

        foreach ( $period as $date ) {
            $dates[] = $date->format( 'Y-m-d' );
        }

        return $dates;
    }

    /**
     * Calculate nights between dates
     *
     * @param string $check_in
     * @param string $check_out
     * @return int
     */
    private function calculate_nights( string $check_in, string $check_out ): int {
        try {
            $start = new \DateTime( $check_in );
            $end   = new \DateTime( $check_out );
            $diff  = $start->diff( $end );
            return max( 1, (int) $diff->days );
        } catch ( \Exception $e ) {
            return 1;
        }
    }
}
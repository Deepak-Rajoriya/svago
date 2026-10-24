<?php
/**
 * OTA Provider Interface
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for OTA (Online Travel Agency) providers
 * Allows swapping between mock and real API implementations
 */
interface OTA_Provider_Interface {

    /**
     * Get all rooms
     *
     * @return array Array of room objects with id, name, description, size, bed_type, max_adults, max_children, amenities, images
     */
    public function get_rooms(): array;

    /**
     * Get availability for rooms within date range
     *
     * @param array $params {
     *     @type string $check_in    Check-in date (Y-m-d)
     *     @type string $check_out   Check-out date (Y-m-d)
     *     @type int    $adults      Number of adults
     *     @type int    $children    Number of children
     *     @type array  $child_ages  Array of child ages
     * }
     * @return array Room availability keyed by room_id, each containing dates with available, price, inventory
     */
    public function get_availability( array $params ): array;

    /**
     * Get rate plans for a specific room and date range
     *
     * @param string $room_id    OTA room ID
     * @param array  $dates      ['check_in' => 'Y-m-d', 'check_out' => 'Y-m-d']
     * @return array Array of rate plan objects with rate_id, name, price_per_night, tax_included
     */
    public function get_rates( string $room_id, array $dates ): array;

    /**
     * Push a new booking to OTA
     *
     * @param array $booking {
     *     @type string $ota_room_id       OTA room ID
     *     @type string $rate_id           Selected rate plan ID
     *     @type string $check_in          Check-in date (Y-m-d)
     *     @type string $check_out         Check-out date (Y-m-d)
     *     @type int    $adults            Number of adults
     *     @type int    $children          Number of children
     *     @type array  $child_ages        Child ages
     *     @type string $guest_name        Guest full name
     *     @type string $guest_email       Guest email
     *     @type string $guest_phone       Guest phone with country code
     *     @type string $special_requests  Special requests
     *     @type string $eta               Estimated time of arrival
     *     @type float  $total_amount      Total booking amount
     *     @type string $currency          Currency code (e.g., INR)
     *     @type string $payment_status    Payment status (paid/pending)
     *     @type string $source            Booking source (website/onsite_admin)
     *     @type bool   $consent           WhatsApp/email consent
     * }
     * @return array { success: bool, ota_booking_id: string|null, message: string }
     */
    public function push_booking( array $booking ): array;

    /**
     * Push booking modification to OTA
     *
     * @param array $booking {
     *     @type string $ota_booking_id  OTA booking ID
     *     @type array  $changes         Changed fields with new values
     * }
     * @return array { success: bool, message: string }
     */
    public function push_modification( array $booking ): array;

    /**
     * Push booking cancellation to OTA
     *
     * @param array $booking {
     *     @type string $ota_booking_id  OTA booking ID
     *     @type string $reason          Cancellation reason
     * }
     * @return array { success: bool, message: string }
     */
    public function push_cancellation( array $booking ): array;

    /**
     * Push availability update to OTA
     *
     * @param string $room_id  OTA room ID
     * @param array  $dates    Array of ['date' => 'Y-m-d', 'qty' => int]
     * @param int    $qty      Quantity (if single value for all dates)
     * @return array { success: bool, message: string }
     */
    public function push_availability( string $room_id, array $dates, int $qty ): array;

    /**
     * Check provider health
     *
     * @return array { ok: bool, latency: int, message: string }
     */
    public function check_health(): array;
}
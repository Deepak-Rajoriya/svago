<?php
/**
 * Shortcodes & AJAX Handlers for Hotel Search and Room Results
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles [hotel_search] and [hotel_rooms] shortcodes, frontend asset loading, and AJAX endpoints
 */
final class Shortcodes {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register shortcodes and hooks
     */
    public function __construct() {
        // Register Shortcodes
        add_shortcode( 'hotel_search', [ $this, 'render_search_widget' ] );
        add_shortcode( 'hotel_rooms', [ $this, 'render_room_results' ] );
        add_shortcode( 'hotel_booking', [ $this, 'render_booking_flow' ] );
        add_shortcode( 'hotel_guest_details', [ $this, 'render_guest_details' ] );
        add_shortcode( 'hotel_review', [ $this, 'render_review' ] );
        add_shortcode( 'hotel_confirmation', [ $this, 'render_confirmation' ] );

        // Enqueue frontend scripts & styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // AJAX endpoints for availability & rates
        add_action( 'wp_ajax_svago_get_availability', [ $this, 'ajax_get_availability' ] );
        add_action( 'wp_ajax_nopriv_svago_get_availability', [ $this, 'ajax_get_availability' ] );

        add_action( 'wp_ajax_svago_get_rates', [ $this, 'ajax_get_rates' ] );
        add_action( 'wp_ajax_nopriv_svago_get_rates', [ $this, 'ajax_get_rates' ] );

        add_action( 'wp_ajax_svago_get_blocked_dates', [ $this, 'ajax_get_blocked_dates' ] );
        add_action( 'wp_ajax_nopriv_svago_get_blocked_dates', [ $this, 'ajax_get_blocked_dates' ] );
    }

    /**
     * Enqueue styles and scripts
     */
    public function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'svago-hotel-booking',
            SVAGO_URL . 'assets/css/hotel-booking.css',
            [],
            SVAGO_VERSION
        );

        wp_enqueue_script(
            'svago-hotel-search',
            SVAGO_URL . 'assets/js/hotel-search.js',
            [ 'jquery' ],
            SVAGO_VERSION,
            true
        );

        wp_enqueue_script(
            'svago-hotel-rooms',
            SVAGO_URL . 'assets/js/hotel-rooms.js',
            [ 'jquery' ],
            SVAGO_VERSION,
            true
        );

        wp_enqueue_script(
            'svago-hotel-booking',
            SVAGO_URL . 'assets/js/hotel-booking.js',
            [ 'jquery', 'svago-hotel-rooms' ],
            SVAGO_VERSION,
            true
        );

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₹';

        wp_localize_script(
            'svago-hotel-search',
            'svago_booking_vars',
            [
                'ajax_url'           => admin_url( 'admin-ajax.php' ),
                'nonce'              => wp_create_nonce( 'svago_booking_nonce' ),
                'currency'           => $currency_symbol,
                'razorpay_key'       => get_option( 'svago_razorpay_key_id', '' ),
                'booking_url'        => '',
                'booking_page_url'   => get_option( 'svago_booking_page_url', '' ),
                'current_check_in'   => isset( $_GET['check_in'] ) ? sanitize_text_field( $_GET['check_in'] ) : date( 'Y-m-d', strtotime( '+1 day' ) ),
                'current_check_out'  => isset( $_GET['check_out'] ) ? sanitize_text_field( $_GET['check_out'] ) : date( 'Y-m-d', strtotime( '+3 days' ) ),
                'current_adults'     => isset( $_GET['adults'] ) ? intval( $_GET['adults'] ) : 2,
                'current_children'   => isset( $_GET['children'] ) ? intval( $_GET['children'] ) : 0,
                'i18n'               => [
                    'searching'   => __( 'Searching availability...', 'svago' ),
                    'processing'  => __( 'Processing...', 'svago' ),
                    'selected'    => __( 'Selected', 'svago' ),
                    'book_now'    => __( 'Book Now', 'svago' ),
                    'error'       => __( 'Unable to load room availability. Please try again.', 'svago' ),
                ],
            ]
        );
    }

    /**
     * Render [hotel_search] shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render_search_widget( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'action_url' => '',
                'check_in'   => '',
                'check_out'  => '',
                'adults'     => 2,
                'children'   => 0,
                'promo_code' => '',
            ],
            $atts,
            'hotel_search'
        );

        // Populate from $_GET parameters if present
        $check_in   = ! empty( $_GET['check_in'] ) ? sanitize_text_field( $_GET['check_in'] ) : $atts['check_in'];
        $check_out  = ! empty( $_GET['check_out'] ) ? sanitize_text_field( $_GET['check_out'] ) : $atts['check_out'];
        $adults     = isset( $_GET['adults'] ) ? max( 1, intval( $_GET['adults'] ) ) : intval( $atts['adults'] );
        $children   = isset( $_GET['children'] ) ? max( 0, intval( $_GET['children'] ) ) : intval( $atts['children'] );
        $promo_code = ! empty( $_GET['promo_code'] ) ? sanitize_text_field( $_GET['promo_code'] ) : $atts['promo_code'];
        $child_ages = [];

        if ( isset( $_GET['child_ages'] ) && is_array( $_GET['child_ages'] ) ) {
            $child_ages = array_map( 'intval', $_GET['child_ages'] );
        }

        $action_url = ! empty( $atts['action_url'] ) ? esc_url( $atts['action_url'] ) : '';

        ob_start();
        $template_file = SVAGO_PATH . 'templates/search-widget.php';
        if ( file_exists( $template_file ) ) {
            include $template_file;
        }
        return ob_get_clean();
    }

    /**
     * Render [hotel_rooms] shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render_room_results( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'check_in'  => '',
                'check_out' => '',
                'adults'    => 2,
                'children'  => 0,
            ],
            $atts,
            'hotel_rooms'
        );

        // Read query params or fall back to defaults
        $check_in  = ! empty( $_GET['check_in'] ) ? sanitize_text_field( $_GET['check_in'] ) : ( ! empty( $atts['check_in'] ) ? $atts['check_in'] : date( 'Y-m-d', strtotime( '+1 day' ) ) );
        $check_out = ! empty( $_GET['check_out'] ) ? sanitize_text_field( $_GET['check_out'] ) : ( ! empty( $atts['check_out'] ) ? $atts['check_out'] : date( 'Y-m-d', strtotime( '+3 days' ) ) );
        $adults    = isset( $_GET['adults'] ) ? max( 1, intval( $_GET['adults'] ) ) : max( 1, intval( $atts['adults'] ) );
        $children  = isset( $_GET['children'] ) ? max( 0, intval( $_GET['children'] ) ) : max( 0, intval( $atts['children'] ) );
        $child_ages = ( isset( $_GET['child_ages'] ) && is_array( $_GET['child_ages'] ) ) ? array_map( 'intval', $_GET['child_ages'] ) : [];

        // Validate date sanity
        try {
            $d_start = new \DateTime( $check_in );
            $d_end   = new \DateTime( $check_out );
            if ( $d_start >= $d_end ) {
                $d_end = clone $d_start;
                $d_end->modify( '+1 day' );
                $check_out = $d_end->format( 'Y-m-d' );
            }
            $nights = max( 1, (int) $d_start->diff( $d_end )->days );
        } catch ( \Exception $e ) {
            $check_in  = date( 'Y-m-d', strtotime( '+1 day' ) );
            $check_out = date( 'Y-m-d', strtotime( '+3 days' ) );
            $nights    = 2;
        }

        $provider = Provider_Factory::get();

        // 1. Fetch Rooms via fallback wrapper
        $rooms_result = Provider_Factory::execute_with_fallback(
            function() use ( $provider ) {
                return $provider->get_rooms();
            },
            'get_rooms'
        );
        $is_fallback = ! empty( $rooms_result['_fallback'] );
        $fallback_message = $rooms_result['_fallback_message'] ?? '';
        unset( $rooms_result['_fallback'], $rooms_result['_fallback_message'] );
        $rooms = is_array( $rooms_result ) ? array_values( array_filter( $rooms_result, 'is_array' ) ) : [];

        // 2. Fetch Availability via fallback wrapper
        $avail_params = [
            'check_in'   => $check_in,
            'check_out'  => $check_out,
            'adults'     => $adults,
            'children'   => $children,
            'child_ages' => $child_ages,
        ];
        $availability_map = Provider_Factory::execute_with_fallback(
            function() use ( $provider, $avail_params ) {
                return $provider->get_availability( $avail_params );
            },
            'get_availability',
            $avail_params
        );
        if ( ! empty( $availability_map['_fallback'] ) ) {
            $is_fallback = true;
            $fallback_message = $availability_map['_fallback_message'] ?? $fallback_message;
            unset( $availability_map['_fallback'], $availability_map['_fallback_message'] );
        }

        // 3. Fetch Rates per room via fallback wrapper
        $rates_map = [];
        $date_range = [ 'check_in' => $check_in, 'check_out' => $check_out ];
        foreach ( $rooms as $room_item ) {
            $r_id = $room_item['id'] ?? '';
            if ( empty( $r_id ) ) {
                continue;
            }
            $rates_result = Provider_Factory::execute_with_fallback(
                function() use ( $provider, $r_id, $date_range ) {
                    return $provider->get_rates( $r_id, $date_range );
                },
                'get_rates'
            );
            unset( $rates_result['_fallback'], $rates_result['_fallback_message'] );
            $rates_map[ $r_id ] = is_array( $rates_result ) ? $rates_result : [];
        }

        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₹';

        ob_start();
        $template_file = SVAGO_PATH . 'templates/room-results.php';
        if ( file_exists( $template_file ) ) {
            include $template_file;
        }
        return ob_get_clean();
    }

    /**
     * AJAX endpoint: Get room availability & rendered results HTML
     */
    public function ajax_get_availability(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $check_in  = isset( $_POST['check_in'] ) ? sanitize_text_field( $_POST['check_in'] ) : '';
        $check_out = isset( $_POST['check_out'] ) ? sanitize_text_field( $_POST['check_out'] ) : '';
        $adults    = isset( $_POST['adults'] ) ? max( 1, intval( $_POST['adults'] ) ) : 2;
        $children  = isset( $_POST['children'] ) ? max( 0, intval( $_POST['children'] ) ) : 0;
        $child_ages = ( isset( $_POST['child_ages'] ) && is_array( $_POST['child_ages'] ) ) ? array_map( 'intval', $_POST['child_ages'] ) : [];

        if ( empty( $check_in ) || empty( $check_out ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select check-in and check-out dates.', 'svago' ) ] );
        }

        // Render HTML with updated parameters
        $_GET['check_in']   = $check_in;
        $_GET['check_out']  = $check_out;
        $_GET['adults']     = $adults;
        $_GET['children']   = $children;
        $_GET['child_ages'] = $child_ages;

        $html = $this->render_room_results();

        wp_send_json_success( [
            'html'      => $html,
            'check_in'  => $check_in,
            'check_out' => $check_out,
            'adults'    => $adults,
            'children'  => $children,
        ] );
    }

    /**
     * AJAX endpoint: Get rate plans for a specific room and dates
     */
    public function ajax_get_rates(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $room_id   = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
        $check_in  = isset( $_POST['check_in'] ) ? sanitize_text_field( $_POST['check_in'] ) : '';
        $check_out = isset( $_POST['check_out'] ) ? sanitize_text_field( $_POST['check_out'] ) : '';

        if ( empty( $room_id ) || empty( $check_in ) || empty( $check_out ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'svago' ) ] );
        }

        $provider = Provider_Factory::get();
        $date_range = [ 'check_in' => $check_in, 'check_out' => $check_out ];

        $rates = Provider_Factory::execute_with_fallback(
            function() use ( $provider, $room_id, $date_range ) {
                return $provider->get_rates( $room_id, $date_range );
            },
            'get_rates'
        );

        wp_send_json_success( [
            'room_id' => $room_id,
            'rates'   => $rates,
        ] );
    }

    /**
     * AJAX endpoint: Get blocked dates where all rooms have 0 inventory
     */
    public function ajax_get_blocked_dates(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $loader = new Fallback_Loader();
        $availability = $loader->load_availability();
        $blocked_dates = [];

        // Check if all rooms have 0 inventory for a date
        if ( is_array( $availability ) && ! empty( $availability ) ) {
            $all_dates = [];
            foreach ( $availability as $room_id => $dates ) {
                if ( is_array( $dates ) ) {
                    foreach ( $dates as $date => $info ) {
                        $all_dates[ $date ][ $room_id ] = (int) ( $info['inventory'] ?? 0 );
                    }
                }
            }

            foreach ( $all_dates as $date => $room_inventories ) {
                $total_inv = array_sum( $room_inventories );
                if ( $total_inv <= 0 ) {
                    $blocked_dates[] = $date;
                }
            }
        }

        wp_send_json_success( [
            'blocked_dates' => $blocked_dates,
        ] );
    }

    /**
     * Render unified [hotel_booking] shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render_booking_flow( $atts = [] ): string {
        $session_data = Booking_Flow::get_session_data();
        $step = isset( $_GET['step'] ) ? sanitize_text_field( $_GET['step'] ) : ( $session_data['step'] ?? '' );

        // If on confirmation step (session already cleared after payment), render confirmation directly
        if ( 'confirmation' === $step ) {
            return $this->render_confirmation();
        }

        // If no booking session exists, show room search / selection
        if ( empty( $session_data ) ) {
            return $this->render_room_results();
        }

        if ( 'review' === $step ) {
            return $this->render_review();
        }

        return $this->render_guest_details();
    }

    /**
     * Render [hotel_guest_details] shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render_guest_details( $atts = [] ): string {
        $booking_data = Booking_Flow::get_session_data();
        if ( empty( $booking_data ) ) {
            return $this->render_room_results();
        }

        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₹';

        ob_start();
        $template = SVAGO_PATH . 'templates/guest-details.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Render [hotel_review] shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render_review( $atts = [] ): string {
        $booking_data = Booking_Flow::get_session_data();
        if ( empty( $booking_data ) ) {
            return $this->render_room_results();
        }

        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₹';

        ob_start();
        $template = SVAGO_PATH . 'templates/review.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Render [hotel_confirmation] shortcode (Task 4.4)
     *
     * @param array|string $atts
     * @return string
     */
    public function render_confirmation( $atts = [] ): string {
        $order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
        $booking_data = [];

        if ( $order_id && class_exists( 'SVAGO\Order_Manager' ) ) {
            $booking_data = Order_Manager::get_instance()->get_order_booking_data( $order_id );
        }

        if ( empty( $booking_data ) ) {
            $session = Booking_Flow::get_session_data() ?: [];
            $guest   = $session['guest'] ?? [];
            $booking_data = [
                'order_id'        => $order_id ?: 1001,
                'room_id'         => $session['room_id'] ?? 'RM001',
                'room_name'       => $session['room_name'] ?? __( 'Deluxe Room', 'svago' ),
                'room_image'      => $session['room_image'] ?? '',
                'rate_name'       => $session['rate_name'] ?? __( 'Standard Rate', 'svago' ),
                'check_in'        => $session['check_in'] ?? date( 'Y-m-d', strtotime( '+1 day' ) ),
                'check_out'       => $session['check_out'] ?? date( 'Y-m-d', strtotime( '+3 days' ) ),
                'nights'          => $session['nights'] ?? 2,
                'adults'          => $session['adults'] ?? 2,
                'children'        => $session['children'] ?? 0,
                'total_amount'    => $session['total_amount'] ?? 189510,
                'price_per_night' => $session['price_per_night'] ?? 94755,
                'guest_name'      => $guest['full_name'] ?? __( 'Valued Guest', 'svago' ),
                'guest_email'     => $guest['email'] ?? 'guest@example.com',
                'guest_phone'     => $guest['phone'] ?? '+91 98765 43210',
                'guest_eta'       => $guest['eta'] ?? '14:00 - 16:00',
                'special_requests'=> $guest['special_requests'] ?? '',
                'ota_booking_id'  => 'OTA-MOCK-' . ( $order_id ?: 1001 ),
                'payment_status'  => __( 'Paid via Razorpay', 'svago' ),
            ];
        }

        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₹';

        ob_start();
        $template = SVAGO_PATH . 'templates/confirmation.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }
}

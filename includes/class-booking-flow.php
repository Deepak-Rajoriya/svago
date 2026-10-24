<?php
/**
 * Booking Flow & Session Manager
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles booking session data, guest details, and pre-payment availability re-checks
 */
final class Booking_Flow {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Session key name
     */
    public const SESSION_KEY = 'svago_booking_data';

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
     * Constructor - register hooks
     */
    public function __construct() {
        // AJAX: Select room and start booking flow
        add_action( 'wp_ajax_svago_select_room', [ $this, 'ajax_select_room' ] );
        add_action( 'wp_ajax_nopriv_svago_select_room', [ $this, 'ajax_select_room' ] );

        // AJAX: Save guest details
        add_action( 'wp_ajax_svago_save_guest_details', [ $this, 'ajax_save_guest_details' ] );
        add_action( 'wp_ajax_nopriv_svago_save_guest_details', [ $this, 'ajax_save_guest_details' ] );

        // AJAX: Pre-payment availability re-check (Task 3.5 & 3.6)
        add_action( 'wp_ajax_svago_prepayment_check', [ $this, 'ajax_prepayment_check' ] );
        add_action( 'wp_ajax_nopriv_svago_prepayment_check', [ $this, 'ajax_prepayment_check' ] );

        // AJAX: Clear booking session
        add_action( 'wp_ajax_svago_clear_booking', [ $this, 'ajax_clear_booking' ] );
        add_action( 'wp_ajax_nopriv_svago_clear_booking', [ $this, 'ajax_clear_booking' ] );
    }

    /**
     * Store booking data into session (WooCommerce session with PHP session fallback)
     *
     * @param array $data
     * @return bool
     */
    public static function set_session_data( array $data ): bool {
        $saved = false;

        // 1. Try WooCommerce session
        if ( function_exists( 'WC' ) && WC() && WC()->session ) {
            if ( ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }
            WC()->session->set( self::SESSION_KEY, $data );
            if ( method_exists( WC()->session, 'save_data' ) ) {
                WC()->session->save_data();
            }
            $saved = true;
        }

        // 2. Multi-layer fallback to PHP native session
        if ( ! session_id() && ! headers_sent() ) {
            @session_start();
        }

        if ( session_id() || isset( $_SESSION ) ) {
            $_SESSION[ self::SESSION_KEY ] = $data;
            $saved = true;
        }

        // 3. Multi-layer fallback to transient via cookie identifier
        $token = self::get_cookie_token();
        if ( $token ) {
            set_transient( 'svago_bk_' . $token, $data, HOUR_IN_SECONDS * 6 );
            $saved = true;
        }

        return $saved;
    }

    /**
     * Retrieve booking data from session
     *
     * @return array|null
     */
    public static function get_session_data(): ?array {
        // 1. Try WooCommerce session
        if ( function_exists( 'WC' ) && WC() && WC()->session ) {
            $data = WC()->session->get( self::SESSION_KEY );
            if ( ! empty( $data ) && is_array( $data ) ) {
                return $data;
            }
        }

        // 2. Try PHP session
        if ( ! session_id() && ! headers_sent() ) {
            @session_start();
        }

        if ( isset( $_SESSION[ self::SESSION_KEY ] ) && is_array( $_SESSION[ self::SESSION_KEY ] ) ) {
            return $_SESSION[ self::SESSION_KEY ];
        }

        // 3. Try Transient fallback
        $token = self::get_cookie_token( false );
        if ( $token ) {
            $transient = get_transient( 'svago_bk_' . $token );
            if ( $transient && is_array( $transient ) ) {
                return $transient;
            }
        }

        return null;
    }

    /**
     * Clear booking session data
     */
    public static function clear_session_data(): void {
        if ( function_exists( 'WC' ) && WC() && WC()->session ) {
            WC()->session->__unset( self::SESSION_KEY );
        }

        if ( ! session_id() && ! headers_sent() ) {
            @session_start();
        }

        if ( isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            unset( $_SESSION[ self::SESSION_KEY ] );
        }

        $token = self::get_cookie_token( false );
        if ( $token ) {
            delete_transient( 'svago_bk_' . $token );
        }
    }

    /**
     * Generate or retrieve transient cookie token
     *
     * @param bool $create
     * @return string|null
     */
    private static function get_cookie_token( bool $create = true ): ?string {
        $cookie_name = 'svago_booking_token';
        if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
            return sanitize_text_field( $_COOKIE[ $cookie_name ] );
        }

        if ( ! $create || ( headers_sent() && php_sapi_name() !== 'cli' ) ) {
            return null;
        }

        $token = wp_generate_uuid4();
        if ( ! headers_sent() ) {
            @setcookie( $cookie_name, $token, time() + ( HOUR_IN_SECONDS * 6 ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        $_COOKIE[ $cookie_name ] = $token;
        return $token;
    }

    /**
     * AJAX endpoint: Select room and initiate booking flow (Task 3.1)
     */
    public function ajax_select_room(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $room_id   = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
        if ( empty( $room_id ) ) {
            $room_id = 'RM001';
        }
        $room_type = isset( $_POST['room_type'] ) ? sanitize_text_field( $_POST['room_type'] ) : 'Balcony';
        $rate_id   = isset( $_POST['rate_id'] ) ? sanitize_text_field( $_POST['rate_id'] ) : '';
        $check_in  = isset( $_POST['check_in'] ) ? sanitize_text_field( $_POST['check_in'] ) : '';
        $check_out = isset( $_POST['check_out'] ) ? sanitize_text_field( $_POST['check_out'] ) : '';
        $adults    = isset( $_POST['adults'] ) ? max( 1, intval( $_POST['adults'] ) ) : 2;
        $children  = isset( $_POST['children'] ) ? max( 0, intval( $_POST['children'] ) ) : 0;
        $child_ages = ( isset( $_POST['child_ages'] ) && is_array( $_POST['child_ages'] ) ) ? array_map( 'intval', $_POST['child_ages'] ) : [];

        $today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : date( 'Y-m-d' );
        if ( empty( $check_in ) ) {
            $check_in = $today;
        }
        if ( empty( $check_out ) ) {
            $check_out = date( 'Y-m-d', strtotime( $check_in . ' +1 day' ) );
        }

        // Calculate nights defensively
        try {
            $d_start = new \DateTime( $check_in );
            $d_end   = new \DateTime( $check_out );
        } catch ( \Throwable $e ) {
            $d_start = new \DateTime( $today );
            $d_end   = ( clone $d_start )->modify( '+1 day' );
            $check_in  = $d_start->format( 'Y-m-d' );
            $check_out = $d_end->format( 'Y-m-d' );
        }

        if ( $d_start >= $d_end ) {
            $d_end = ( clone $d_start )->modify( '+1 day' );
            $check_out = $d_end->format( 'Y-m-d' );
        }
        $nights = max( 1, (int) $d_start->diff( $d_end )->days );

        $provider = Provider_Factory::get();

        // 1. Find Room details
        $rooms = Provider_Factory::execute_with_fallback(
            function() use ( $provider ) {
                return $provider->get_rooms();
            },
            'get_rooms'
        );
        unset( $rooms['_fallback'], $rooms['_fallback_message'] );

        $selected_room = null;
        if ( is_array( $rooms ) ) {
            foreach ( $rooms as $r ) {
                if ( ( $r['id'] ?? '' ) === $room_id ) {
                    $selected_room = $r;
                    break;
                }
            }
            if ( ! $selected_room && ! empty( $rooms[0] ) ) {
                $selected_room = $rooms[0];
                $room_id = $selected_room['id'] ?? $room_id;
            }
        }

        if ( ! $selected_room ) {
            wp_send_json_error( [ 'message' => __( 'Selected room could not be found.', 'svago' ) ] );
        }

        // 2. Find Rate plan details
        $rates = Provider_Factory::execute_with_fallback(
            function() use ( $provider, $room_id, $check_in, $check_out ) {
                return $provider->get_rates( $room_id, [ 'check_in' => $check_in, 'check_out' => $check_out ] );
            },
            'get_rates'
        );
        unset( $rates['_fallback'], $rates['_fallback_message'] );

        $selected_rate = null;
        if ( is_array( $rates ) ) {
            foreach ( $rates as $rt ) {
                if ( ( $rt['rate_id'] ?? '' ) === $rate_id ) {
                    $selected_rate = $rt;
                    break;
                }
            }
            if ( ! $selected_rate && ! empty( $rates[0] ) ) {
                $selected_rate = $rates[0];
            }
        }

        $price_per_night = (float) ( $selected_rate['price_per_night'] ?? ( $selected_room['price'] ?? 0 ) );
        if ( ! empty( $selected_room['types'] ) && is_array( $selected_room['types'] ) ) {
            foreach ( $selected_room['types'] as $t_info ) {
                if ( ( $t_info['name'] ?? '' ) === $room_type && isset( $t_info['price'] ) ) {
                    $price_per_night = (float) $t_info['price'];
                    break;
                }
            }
        }
        $total_amount    = $price_per_night * $nights;

        // Room image with placeholder fallback
        $placeholder_url = defined( 'SVAGO_URL' ) ? SVAGO_URL . 'assets/images/placeholder.png' : '';
        $selected_img    = ! empty( $selected_room['images'][0] ) ? $selected_room['images'][0] : $placeholder_url;
        if ( ! empty( $selected_img ) && str_starts_with( $selected_img, '/' ) && ! str_starts_with( $selected_img, '//' ) ) {
            if ( defined( 'ABSPATH' ) && ! file_exists( ABSPATH . ltrim( $selected_img, '/' ) ) ) {
                $selected_img = $placeholder_url;
            }
        }

        // Build initial booking session payload
        $booking_data = [
            'room_id'         => $room_id,
            'room_type'       => $room_type,
            'room_name'       => $selected_room['name'] ?? __( 'Hotel Room', 'svago' ),
            'room_size'       => $selected_room['size'] ?? '',
            'bed_type'        => $selected_room['bed_type'] ?? '',
            'room_image'      => $selected_img,
            'rate_id'         => $selected_rate['rate_id'] ?? 'standard',
            'rate_name'       => $selected_rate['name'] ?? __( 'Standard Rate', 'svago' ),
            'rate_desc'       => $selected_rate['description'] ?? '',
            'tax_included'    => ! empty( $selected_rate['tax_included'] ),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'nights'          => $nights,
            'adults'          => $adults,
            'children'        => $children,
            'child_ages'      => $child_ages,
            'price_per_night' => $price_per_night,
            'total_amount'    => $total_amount,
            'guest'           => [],
            'step'            => 'details',
            'created_at'      => time(),
        ];

        self::set_session_data( $booking_data );

        // Determine destination URL
        $booking_page_url = get_option( 'svago_booking_page_url', '' );
        $url_args = [
            'step'      => 'details',
            'room_id'   => $room_id,
            'room_type' => $room_type,
            'check_in'  => $check_in,
            'check_out' => $check_out,
            'adults'    => $adults,
            'children'  => $children,
        ];
        $dest_redirect = $booking_page_url ? add_query_arg( $url_args, $booking_page_url ) : '';

        wp_send_json_success( [
            'message'      => __( 'Room selected successfully.', 'svago' ),
            'booking_data' => $booking_data,
            'redirect_url' => $dest_redirect,
        ] );
    }

    /**
     * AJAX endpoint: Save guest details and advance to review (Task 3.2)
     */
    public function ajax_save_guest_details(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
        $last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
        $email            = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $phone_country    = isset( $_POST['phone_country'] ) ? sanitize_text_field( $_POST['phone_country'] ) : '+91';
        $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $eta              = isset( $_POST['eta'] ) ? sanitize_text_field( $_POST['eta'] ) : '';
        $special_requests = isset( $_POST['special_requests'] ) ? sanitize_textarea_field( $_POST['special_requests'] ) : '';
        $consent          = ! empty( $_POST['consent'] );

        // Detailed Field-Specific Server Validation
        $errors = [];

        if ( empty( $first_name ) ) {
            $errors['first_name'] = __( 'Please enter your first name.', 'svago' );
        } elseif ( mb_strlen( $first_name ) < 2 ) {
            $errors['first_name'] = __( 'First name must be at least 2 characters.', 'svago' );
        }

        if ( empty( $last_name ) ) {
            $errors['last_name'] = __( 'Please enter your last name.', 'svago' );
        }

        if ( empty( $email ) ) {
            $errors['email'] = __( 'Please enter your email address.', 'svago' );
        } elseif ( ! is_email( $email ) ) {
            $errors['email'] = __( 'Please enter a valid email address.', 'svago' );
        }

        $clean_phone = preg_replace( '/[\s\-()]/', '', $phone );
        if ( empty( $clean_phone ) ) {
            $errors['phone'] = __( 'Please enter your contact mobile number.', 'svago' );
        } elseif ( ! preg_match( '/^\d{7,15}$/', $clean_phone ) ) {
            $errors['phone'] = __( 'Please enter a valid 7 to 15 digit mobile number.', 'svago' );
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [
                'message' => __( 'Please correct the highlighted fields below.', 'svago' ),
                'errors'  => $errors,
            ] );
            return;
        }

        // Retrieve existing session or auto-hydrate from submitted form metadata
        $session_data = self::get_session_data();
        if ( empty( $session_data ) || empty( $session_data['room_id'] ) ) {
            $post_room_id   = ! empty( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : 'RM001';
            $post_room_type = ! empty( $_POST['room_type'] ) ? sanitize_text_field( $_POST['room_type'] ) : 'Balcony';
            $post_room_name = ! empty( $_POST['room_name'] ) ? sanitize_text_field( $_POST['room_name'] ) : __( 'Hotel Room', 'svago' );
            $post_check_in  = ! empty( $_POST['check_in'] ) ? sanitize_text_field( $_POST['check_in'] ) : ( function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : date( 'Y-m-d' ) );
            $post_check_out = ! empty( $_POST['check_out'] ) ? sanitize_text_field( $_POST['check_out'] ) : date( 'Y-m-d', strtotime( $post_check_in . ' +1 day' ) );
            $post_nights    = ! empty( $_POST['nights'] ) ? max( 1, intval( $_POST['nights'] ) ) : 1;
            $post_adults    = ! empty( $_POST['adults'] ) ? max( 1, intval( $_POST['adults'] ) ) : 2;
            $post_children  = isset( $_POST['children'] ) ? max( 0, intval( $_POST['children'] ) ) : 0;
            $post_price     = ! empty( $_POST['price_per_night'] ) ? floatval( $_POST['price_per_night'] ) : 0;
            $post_total     = ! empty( $_POST['total_amount'] ) ? floatval( $_POST['total_amount'] ) : ( $post_price * $post_nights );

            $session_data = [
                'room_id'         => $post_room_id,
                'room_type'       => $post_room_type,
                'room_name'       => $post_room_name,
                'room_image'      => ! empty( $_POST['room_image'] ) ? esc_url_raw( $_POST['room_image'] ) : '',
                'check_in'        => $post_check_in,
                'check_out'       => $post_check_out,
                'nights'          => $post_nights,
                'adults'          => $post_adults,
                'children'        => $post_children,
                'child_ages'      => [],
                'price_per_night' => $post_price,
                'total_amount'    => $post_total,
                'rate_id'         => 'standard',
                'rate_name'       => __( 'Standard Rate', 'svago' ),
                'created_at'      => time(),
            ];
        } else {
            // Keep room_id and room_type synchronized with the form submission
            if ( ! empty( $_POST['room_id'] ) ) {
                $session_data['room_id'] = sanitize_text_field( $_POST['room_id'] );
            }
            if ( ! empty( $_POST['room_type'] ) ) {
                $session_data['room_type'] = sanitize_text_field( $_POST['room_type'] );
            }
        }

        $full_phone = trim( $phone_country . ' ' . $clean_phone );

        $session_data['guest'] = [
            'first_name'       => $first_name,
            'last_name'        => $last_name,
            'full_name'        => trim( $first_name . ' ' . $last_name ),
            'email'            => $email,
            'phone_country'    => $phone_country,
            'phone_number'     => $clean_phone,
            'phone'            => $full_phone,
            'eta'              => $eta,
            'special_requests' => $special_requests,
            'consent'          => $consent,
        ];
        $session_data['step'] = 'checkout';

        self::set_session_data( $session_data );

        // Sync booking session to WooCommerce Cart and customer profile
        if ( class_exists( 'SVAGO\Order_Manager' ) ) {
            Order_Manager::get_instance()->sync_booking_to_cart( $session_data );
        }

        // Commit WooCommerce session and ensure cookies are sent
        if ( function_exists( 'WC' ) && WC() && WC()->session ) {
            if ( ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }
            if ( method_exists( WC()->session, 'save_data' ) ) {
                WC()->session->save_data();
            }
        }

        $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );

        wp_send_json_success( [
            'message'      => __( 'Guest details saved. Redirecting to checkout...', 'svago' ),
            'redirect_url' => $checkout_url,
            'checkout_url' => $checkout_url,
            'booking_data' => $session_data,
        ] );
    }

    /**
     * AJAX endpoint: Pre-payment availability re-check with friendly messaging (Task 3.5 & 3.6)
     */
    public function ajax_prepayment_check(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $policy_agreed = ! empty( $_POST['policy_agreed'] );
        if ( ! $policy_agreed ) {
            wp_send_json_error( [
                'type'    => 'policy_required',
                'message' => __( 'You must agree to the hotel cancellation policy and terms to proceed.', 'svago' ),
            ] );
        }

        $session_data = self::get_session_data();
        if ( ! $session_data ) {
            wp_send_json_error( [
                'type'    => 'session_expired',
                'message' => __( 'Your booking session has expired. Please select your room again.', 'svago' ),
            ] );
        }

        $room_id   = $session_data['room_id'] ?? '';
        $check_in  = $session_data['check_in'] ?? '';
        $check_out = $session_data['check_out'] ?? '';
        $adults    = (int) ( $session_data['adults'] ?? 2 );
        $children  = (int) ( $session_data['children'] ?? 0 );

        $provider = Provider_Factory::get();
        $is_available = false;
        $used_fallback = false;

        try {
            // Live/Mock provider check
            $avail = $provider->get_availability( [
                'check_in'   => $check_in,
                'check_out'  => $check_out,
                'adults'     => $adults,
                'children'   => $children,
                'child_ages' => $session_data['child_ages'] ?? [],
            ] );

            if ( isset( $avail[ $room_id ] ) && ! empty( $avail[ $room_id ]['available'] ) ) {
                $is_available = true;
            }
        } catch ( \Throwable $e ) {
            Logger::get_instance()->log(
                'warning',
                'Pre-payment availability check failed on provider, checking fallback JSON',
                [ 'error' => $e->getMessage(), 'room_id' => $room_id ]
            );

            // Fallback to local JSON (Task 3.6)
            $loader = new Fallback_Loader();
            $fallback_avail = $loader->load_availability();
            $used_fallback = true;

            if ( isset( $fallback_avail[ $room_id ] ) && is_array( $fallback_avail[ $room_id ] ) ) {
                $room_dates = $fallback_avail[ $room_id ];
                $all_avail  = true;
                try {
                    $curr = new \DateTime( $check_in );
                    $end  = new \DateTime( $check_out );
                    while ( $curr < $end ) {
                        $d_str = $curr->format( 'Y-m-d' );
                        if ( empty( $room_dates[ $d_str ]['available'] ) || (int) ( $room_dates[ $d_str ]['inventory'] ?? 0 ) <= 0 ) {
                            $all_avail = false;
                            break;
                        }
                        $curr->modify( '+1 day' );
                    }
                    if ( $all_avail ) {
                        $is_available = true;
                    }
                } catch ( \Throwable $te ) {
                    $is_available = false;
                }
            }
        }

        if ( $is_available ) {
            wp_send_json_success( [
                'available'     => true,
                'used_fallback' => $used_fallback,
                'message'       => __( 'Room availability confirmed.', 'svago' ),
                'booking_data'  => $session_data,
            ] );
        }

        // Room is unavailable: Task 3.5 mandates friendly message (NO raw errors)
        $hotel_phone = get_option( 'svago_hotel_phone', '' );
        $friendly_message = __( 'This room is no longer available for your dates. Please try other dates or rooms.', 'svago' );

        if ( $used_fallback && $hotel_phone ) {
            $friendly_message .= ' ' . sprintf( __( 'You may also call the hotel reservations at %s.', 'svago' ), $hotel_phone );
        }

        Logger::get_instance()->log(
            'notice',
            'Pre-payment check rejected: Room unavailable for dates',
            [ 'room_id' => $room_id, 'check_in' => $check_in, 'check_out' => $check_out ]
        );

        wp_send_json_error( [
            'type'        => 'unavailable',
            'message'     => $friendly_message,
            'hotel_phone' => $hotel_phone,
        ] );
    }

    /**
     * AJAX endpoint: Clear booking session
     */
    public function ajax_clear_booking(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );
        self::clear_session_data();
        wp_send_json_success( [ 'message' => __( 'Booking session cleared.', 'svago' ) ] );
    }
}

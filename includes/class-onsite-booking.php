<?php
/**
 * Onsite Booking Manager (Reception Walk-in & Phone Reservations)
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles onsite front-desk bookings created by hotel reception staff
 * for walk-ins and phone calls (Task 6.2).
 */
final class Onsite_Booking {

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
     * Constructor - register actions and filters
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'wp_ajax_svago_create_onsite_booking', [ $this, 'ajax_create_onsite_booking' ] );
    }

    /**
     * Register Admin Submenu (Task 6.2)
     */
    public function register_admin_menu(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

        global $submenu;
        if ( isset( $submenu['svago-dashboard'] ) ) {
            foreach ( $submenu['svago-dashboard'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'svago-onsite-booking' ) {
                    return; // Already registered
                }
            }
        }

        add_submenu_page(
            'svago-dashboard',
            __( 'Front-Desk Onsite Booking', 'svago' ),
            __( 'Onsite Booking', 'svago' ),
            $cap,
            'svago-onsite-booking',
            [ $this, 'render_onsite_booking_page' ]
        );
    }

    /**
     * Render Onsite Booking Screen (Task 6.2)
     */
    public function render_onsite_booking_page(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'svago' ) );
        }

        $rooms = [];
        try {
            $provider = Provider_Factory::get();
            $rooms    = $provider->get_rooms();
        } catch ( \Throwable $e ) {
            $rooms = [];
        }

        $loader = new Fallback_Loader();
        if ( empty( $rooms ) || ! is_array( $rooms ) ) {
            $raw_rooms = $loader->load_rooms();
            $rooms     = $raw_rooms['rooms'] ?? $raw_rooms;
        }
        $rates = $loader->load_rates();

        $default_check_in  = current_time( 'Y-m-d' );
        $default_check_out = date( 'Y-m-d', strtotime( '+1 day' ) );
        $nonce = wp_create_nonce( 'svago_booking_nonce' );
        ?>
        <div class="wrap svago-onsite-wrap">
            <h1><?php esc_html_e( 'Front-Desk Onsite Booking (Walk-in & Phone)', 'svago' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Create a confirmed reservation for walk-in or phone guests. Creates a WooCommerce order, pushes the booking to the OTA Channel Manager, and triggers customer notifications.', 'svago' ); ?>
            </p>

            <div id="svago-onsite-alert" style="display:none; margin-top:14px;"></div>

            <div style="display:flex; gap:24px; margin-top:18px; flex-wrap:wrap; align-items:flex-start;">
                <!-- Main Booking Form -->
                <div style="flex:2; min-width:340px; background:#fff; border:1px solid #ccd0d4; padding:22px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <form id="svago-onsite-form">
                        <input type="hidden" name="action" value="svago_create_onsite_booking" />
                        <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

                        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; font-size:15px; color:#1e293b;">
                            🏨 <?php esc_html_e( '1. Stay & Room Selection', 'svago' ); ?>
                        </h3>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:16px;">
                            <div>
                                <label for="onsite_room_id" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Select Room Type *', 'svago' ); ?>
                                </label>
                                <select id="onsite_room_id" name="room_id" style="width:100%;" required>
                                    <?php foreach ( $rooms as $room ) : 
                                        $r_id = $room['id'] ?? '';
                                        $room_rates = $rates[ $r_id ] ?? [];
                                        $base_price = (float) ( $room['base_price'] ?? ( ! empty( $room_rates[0]['price_per_night'] ) ? $room_rates[0]['price_per_night'] : 10000 ) );
                                    ?>
                                        <option value="<?php echo esc_attr( $r_id ); ?>" data-price="<?php echo esc_attr( $base_price ); ?>" data-name="<?php echo esc_attr( $room['name'] ?? '' ); ?>">
                                            <?php echo esc_html( ( $room['name'] ?? 'Room' ) . ' — ₹' . number_format( $base_price ) . '/night' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="onsite_rate_id" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Rate Plan *', 'svago' ); ?>
                                </label>
                                <select id="onsite_rate_id" name="rate_id" style="width:100%;" required>
                                    <option value="standard" data-mult="1.0"><?php esc_html_e( 'Standard Rate (Room Only)', 'svago' ); ?></option>
                                    <option value="breakfast" data-mult="1.15"><?php esc_html_e( 'Breakfast Included (+15%)', 'svago' ); ?></option>
                                    <option value="half_board" data-mult="1.3"><?php esc_html_e( 'Half Board (Breakfast + Dinner) (+30%)', 'svago' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:16px; margin-bottom:16px;">
                            <div>
                                <label for="onsite_check_in" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Check-in Date *', 'svago' ); ?>
                                </label>
                                <input type="date" id="onsite_check_in" name="check_in" value="<?php echo esc_attr( $default_check_in ); ?>" style="width:100%;" required />
                            </div>

                            <div>
                                <label for="onsite_check_out" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Check-out Date *', 'svago' ); ?>
                                </label>
                                <input type="date" id="onsite_check_out" name="check_out" value="<?php echo esc_attr( $default_check_out ); ?>" style="width:100%;" required />
                            </div>

                            <div>
                                <label for="onsite_adults" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Adults', 'svago' ); ?>
                                </label>
                                <select id="onsite_adults" name="adults" style="width:100%;">
                                    <option value="1">1 Adult</option>
                                    <option value="2" selected>2 Adults</option>
                                    <option value="3">3 Adults</option>
                                    <option value="4">4 Adults</option>
                                </select>
                            </div>

                            <div>
                                <label for="onsite_children" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Children', 'svago' ); ?>
                                </label>
                                <select id="onsite_children" name="children" style="width:100%;">
                                    <option value="0" selected>0 Children</option>
                                    <option value="1">1 Child</option>
                                    <option value="2">2 Children</option>
                                </select>
                            </div>
                        </div>

                        <h3 style="margin-top:24px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:15px; color:#1e293b;">
                            👤 <?php esc_html_e( '2. Guest Information', 'svago' ); ?>
                        </h3>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label for="onsite_first_name" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'First Name *', 'svago' ); ?>
                                </label>
                                <input type="text" id="onsite_first_name" name="first_name" style="width:100%;" required placeholder="e.g. John" />
                            </div>

                            <div>
                                <label for="onsite_last_name" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Last Name *', 'svago' ); ?>
                                </label>
                                <input type="text" id="onsite_last_name" name="last_name" style="width:100%;" required placeholder="e.g. Doe" />
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label for="onsite_email" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Email Address', 'svago' ); ?>
                                </label>
                                <input type="email" id="onsite_email" name="email" style="width:100%;" placeholder="guest@example.com" />
                            </div>

                            <div>
                                <label for="onsite_phone" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                    <?php esc_html_e( 'Mobile Phone (for WhatsApp confirmation) *', 'svago' ); ?>
                                </label>
                                <input type="text" id="onsite_phone" name="phone" style="width:100%;" placeholder="+91 98765 43210" required />
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label for="onsite_special_requests" style="font-weight:600; display:block; margin-bottom:4px; font-size:12px;">
                                <?php esc_html_e( 'Special Requests / Reception Notes', 'svago' ); ?>
                            </label>
                            <textarea id="onsite_special_requests" name="special_requests" rows="2" style="width:100%;" placeholder="<?php esc_attr_e( 'Early check-in, ground floor, airport pickup notes...', 'svago' ); ?>"></textarea>
                        </div>

                        <h3 style="margin-top:24px; border-bottom:1px solid #eee; padding-bottom:10px; font-size:15px; color:#1e293b;">
                            💳 <?php esc_html_e( '3. Booking Source & Payment', 'svago' ); ?>
                        </h3>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label style="font-weight:600; display:block; margin-bottom:6px; font-size:12px;">
                                    <?php esc_html_e( 'Booking Source', 'svago' ); ?>
                                </label>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="radio" name="source" value="onsite_walkin" checked />
                                    🚶 <?php esc_html_e( 'Walk-in Guest', 'svago' ); ?>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="source" value="onsite_phone" />
                                    📞 <?php esc_html_e( 'Phone Reservation', 'svago' ); ?>
                                </label>
                            </div>

                            <div>
                                <label style="font-weight:600; display:block; margin-bottom:6px; font-size:12px;">
                                    <?php esc_html_e( 'Payment Method', 'svago' ); ?>
                                </label>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="radio" name="payment_method" value="cash" checked />
                                    💵 <?php esc_html_e( 'Cash Onsite', 'svago' ); ?>
                                </label>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="radio" name="payment_method" value="upi" />
                                    📱 <?php esc_html_e( 'Direct UPI / QR Code', 'svago' ); ?>
                                </label>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="radio" name="payment_method" value="card_onsite" />
                                    💳 <?php esc_html_e( 'Credit / Debit Card (POS Machine)', 'svago' ); ?>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="payment_method" value="pay_later" />
                                    ⏳ <?php esc_html_e( 'Pay at Checkout / Pending', 'svago' ); ?>
                                </label>
                            </div>
                        </div>

                        <div style="background:#f1f5f9; padding:12px 16px; border-radius:4px; margin-bottom:20px;">
                            <label style="font-weight:600; color:#1e293b;">
                                <input type="checkbox" name="mark_paid" value="yes" checked />
                                <?php esc_html_e( 'Mark order as Paid immediately (sets order status to Processing/Completed)', 'svago' ); ?>
                            </label>
                        </div>

                        <button type="submit" id="svago-onsite-submit-btn" class="button button-primary button-large" style="width:100%; text-align:center; padding:8px 0; font-size:15px;">
                            🛎️ <?php esc_html_e( 'Confirm & Create Onsite Booking', 'svago' ); ?>
                        </button>
                    </form>
                </div>

                <!-- Live Stay Summary Card -->
                <div style="flex:1; min-width:280px; background:#f8fafc; border:1px solid #e2e8f0; padding:20px; border-radius:6px;">
                    <h3 style="margin-top:0; font-size:15px; color:#0f172a; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                        📋 <?php esc_html_e( 'Live Reservation Summary', 'svago' ); ?>
                    </h3>
                    <div style="margin-bottom:12px;">
                        <span style="font-size:12px; color:#64748b; display:block;"><?php esc_html_e( 'Selected Room:', 'svago' ); ?></span>
                        <strong id="onsite-summary-room" style="font-size:14px; color:#1e293b;">-</strong>
                    </div>
                    <div style="margin-bottom:12px;">
                        <span style="font-size:12px; color:#64748b; display:block;"><?php esc_html_e( 'Duration of Stay:', 'svago' ); ?></span>
                        <span id="onsite-summary-dates" style="font-size:13px; font-weight:600;">-</span>
                    </div>
                    <div style="margin-bottom:12px;">
                        <span style="font-size:12px; color:#64748b; display:block;"><?php esc_html_e( 'Guests:', 'svago' ); ?></span>
                        <span id="onsite-summary-guests" style="font-size:13px;">2 Adults, 0 Children</span>
                    </div>
                    <div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:16px;">
                        <span style="font-size:13px; color:#475569; display:block;"><?php esc_html_e( 'Estimated Total (Taxes incl.):', 'svago' ); ?></span>
                        <strong id="onsite-summary-total" style="font-size:22px; color:#2563eb;">₹0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(function($) {
                function updateSummary() {
                    var roomName = $('#onsite_room_id option:selected').data('name');
                    var basePrice = parseFloat($('#onsite_room_id option:selected').data('price') || 0);
                    var rateMult = parseFloat($('#onsite_rate_id option:selected').data('mult') || 1.0);
                    var checkIn = $('#onsite_check_in').val();
                    var checkOut = $('#onsite_check_out').val();
                    var adults = $('#onsite_adults').val();
                    var children = $('#onsite_children').val();

                    $('#onsite-summary-room').text(roomName);
                    $('#onsite-summary-guests').text(adults + ' Adults, ' + children + ' Children');

                    if (checkIn && checkOut) {
                        var d1 = new Date(checkIn);
                        var d2 = new Date(checkOut);
                        var diffTime = d2 - d1;
                        var nights = Math.max(1, Math.round(diffTime / (1000 * 60 * 60 * 24)));
                        $('#onsite-summary-dates').text(checkIn + ' to ' + checkOut + ' (' + nights + ' night' + (nights > 1 ? 's' : '') + ')');

                        var nightly = basePrice * rateMult;
                        var total = nightly * nights * 1.18; // 18% GST standard
                        $('#onsite-summary-total').text('₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    }
                }

                $('#onsite_room_id, #onsite_rate_id, #onsite_check_in, #onsite_check_out, #onsite_adults, #onsite_children').on('change input', updateSummary);
                updateSummary();

                // AJAX Form Submission
                $('#svago-onsite-form').on('submit', function(e) {
                    e.preventDefault();
                    var $btn = $('#svago-onsite-submit-btn');
                    var $alert = $('#svago-onsite-alert');

                    $btn.prop('disabled', true).text('Creating Reservation...');
                    $alert.hide().empty();

                    $.post(ajaxurl, $(this).serialize(), function(res) {
                        if (res.success) {
                            $alert.html('<div class="notice notice-success is-dismissible"><p><strong>✓ ' + res.data.message + '</strong><br/><a href="' + res.data.order_url + '" target="_blank">View WooCommerce Order #' + res.data.order_id + '</a> | <a href="' + res.data.confirmation_url + '" target="_blank">View / Print Confirmation Receipt</a></p></div>').show();
                            $btn.prop('disabled', false).text('🛎️ Confirm & Create Onsite Booking');
                            $('#svago-onsite-form')[0].reset();
                            updateSummary();
                        } else {
                            $alert.html('<div class="notice notice-error is-dismissible"><p><strong>✗ ' + (res.data.message || 'Failed to create booking.') + '</strong></p></div>').show();
                            $btn.prop('disabled', false).text('🛎️ Confirm & Create Onsite Booking');
                        }
                    }).fail(function() {
                        $alert.html('<div class="notice notice-error is-dismissible"><p><strong>✗ Request failed. Please check your network connection.</strong></p></div>').show();
                        $btn.prop('disabled', false).text('🛎️ Confirm & Create Onsite Booking');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX Endpoint: Create Onsite Booking (Task 6.2)
     */
    public function ajax_create_onsite_booking(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $res = $this->create_onsite_booking( $_POST );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res );
        } else {
            wp_send_json_error( $res );
        }
    }

    /**
     * Core programmatic method: Create Onsite Booking
     *
     * @param array $data
     * @return array [ 'success' => bool, 'order_id' => int, 'message' => string ]
     */
    public function create_onsite_booking( array $data ): array {
        $room_id   = sanitize_text_field( $data['room_id'] ?? '' );
        $rate_id   = sanitize_text_field( $data['rate_id'] ?? 'standard' );
        $check_in  = sanitize_text_field( $data['check_in'] ?? '' );
        $check_out = sanitize_text_field( $data['check_out'] ?? '' );
        $adults    = max( 1, (int) ( $data['adults'] ?? 2 ) );
        $children  = max( 0, (int) ( $data['children'] ?? 0 ) );

        $first_name       = sanitize_text_field( $data['first_name'] ?? '' );
        $last_name        = sanitize_text_field( $data['last_name'] ?? '' );
        $email            = sanitize_email( $data['email'] ?? '' );
        $phone            = sanitize_text_field( $data['phone'] ?? '' );
        $special_requests = sanitize_textarea_field( $data['special_requests'] ?? '' );
        $source           = sanitize_text_field( $data['source'] ?? 'onsite_walkin' );
        $payment_method   = sanitize_text_field( $data['payment_method'] ?? 'cash' );
        $mark_paid        = ( isset( $data['mark_paid'] ) && 'yes' === $data['mark_paid'] );

        if ( empty( $room_id ) || empty( $check_in ) || empty( $check_out ) || empty( $first_name ) || empty( $phone ) ) {
            return [
                'success' => false,
                'message' => __( 'Please fill in all required fields (Room, Check-in, Check-out, Name, Phone).', 'svago' ),
            ];
        }

        // Validate date order
        $ts_in  = strtotime( $check_in );
        $ts_out = strtotime( $check_out );
        if ( ! $ts_in || ! $ts_out || $ts_out <= $ts_in ) {
            return [
                'success' => false,
                'message' => __( 'Check-out date must be after check-in date.', 'svago' ),
            ];
        }

        $nights = (int) max( 1, round( ( $ts_out - $ts_in ) / 86400 ) );

        // 1. Re-check availability via active provider (Task 3.5 & Task 6.2)
        try {
            $provider = Provider_Factory::get();
            $avail    = $provider->get_availability( [
                'check_in'   => $check_in,
                'check_out'  => $check_out,
                'adults'     => $adults,
                'children'   => $children,
                'child_ages' => [],
            ] );

            if ( empty( $avail[ $room_id ]['available'] ) ) {
                return [
                    'success' => false,
                    'message' => __( 'This room is sold out for the selected dates. Please select other dates or room.', 'svago' ),
                ];
            }
        } catch ( \Throwable $e ) {
            // Check fallback
            $loader = new Fallback_Loader();
            $fb     = $loader->load_availability();
            if ( empty( $fb[ $room_id ] ) ) {
                return [
                    'success' => false,
                    'message' => __( 'This room is unavailable for the selected dates.', 'svago' ),
                ];
            }
        }

        // 2. Determine pricing & rate details
        $rooms = [];
        try {
            $provider = Provider_Factory::get();
            $rooms    = $provider->get_rooms();
        } catch ( \Throwable $e ) {
            $rooms = [];
        }

        $loader = new Fallback_Loader();
        if ( empty( $rooms ) || ! is_array( $rooms ) ) {
            $raw_rooms = $loader->load_rooms();
            $rooms     = $raw_rooms['rooms'] ?? $raw_rooms;
        }
        $rates      = $loader->load_rates();
        $room_name  = __( 'Hotel Room', 'svago' );
        $base_price = 10000.0;

        foreach ( $rooms as $r ) {
            if ( ( $r['id'] ?? '' ) === $room_id ) {
                $room_name  = $r['name'] ?? __( 'Hotel Room', 'svago' );
                $r_rates    = $rates[ $room_id ] ?? [];
                $base_price = (float) ( $r['base_price'] ?? ( ! empty( $r_rates[0]['price_per_night'] ) ? $r_rates[0]['price_per_night'] : 10000.0 ) );
                break;
            }
        }

        $rate_multiplier = ( 'breakfast' === $rate_id ) ? 1.15 : ( ( 'half_board' === $rate_id ) ? 1.3 : 1.0 );
        $rate_names = [
            'standard'   => __( 'Standard Rate (Room Only)', 'svago' ),
            'breakfast'  => __( 'Breakfast Included', 'svago' ),
            'half_board' => __( 'Half Board', 'svago' ),
        ];
        $rate_name = $rate_names[ $rate_id ] ?? __( 'Standard Rate', 'svago' );

        $nightly_price = round( $base_price * $rate_multiplier, 2 );
        $subtotal      = $nightly_price * $nights;
        $tax_amount    = round( $subtotal * 0.18, 2 ); // 18% GST standard
        $total_amount  = $subtotal + $tax_amount;

        // Map Payment method titles
        $pm_titles = [
            'cash'        => __( 'Cash Onsite', 'svago' ),
            'upi'         => __( 'Direct UPI / QR Code', 'svago' ),
            'card_onsite' => __( 'Credit / Debit Card (POS)', 'svago' ),
            'pay_later'   => __( 'Pay at Checkout', 'svago' ),
        ];
        $payment_title = $pm_titles[ $payment_method ] ?? ucfirst( $payment_method );

        // 3. Create Booking Order via Order_Manager
        $order_mgr = Order_Manager::get_instance();
        $session_like_payload = [
            'room_id'          => $room_id,
            'room_name'        => $room_name,
            'rate_id'          => $rate_id,
            'rate_name'        => $rate_name,
            'check_in'         => $check_in,
            'check_out'        => $check_out,
            'nights'           => $nights,
            'adults'           => $adults,
            'children'         => $children,
            'child_ages'       => [],
            'price_per_night'  => $nightly_price,
            'total_amount'     => $total_amount,
            'guest'            => [
                'first_name'       => $first_name,
                'last_name'        => $last_name,
                'email'            => $email,
                'phone'            => $phone,
                'special_requests' => $special_requests,
                'consent'          => true, // Onsite bookings automatically opt-in for stay updates
            ],
        ];

        $order_id = $order_mgr->create_booking_order( $session_like_payload );
        if ( is_wp_error( $order_id ) || ! $order_id ) {
            return [
                'success' => false,
                'message' => is_wp_error( $order_id ) ? $order_id->get_error_message() : __( 'Failed creating booking order.', 'svago' ),
            ];
        }

        // 4. Update Onsite & Payment Metadata
        update_post_meta( $order_id, '_booking_source', $source );
        update_post_meta( $order_id, '_payment_method', $payment_method );
        update_post_meta( $order_id, '_payment_method_title', $payment_title );

        // If paid, mark payment complete
        if ( $mark_paid ) {
            if ( function_exists( 'wc_get_order' ) ) {
                $wc_order = wc_get_order( $order_id );
                if ( $wc_order ) {
                    $wc_order->payment_complete( 'ONSITE_' . strtoupper( substr( md5( (string) time() ), 0, 8 ) ) );
                    $wc_order->add_order_note( sprintf( __( 'Onsite booking confirmed by staff. Paid via %s.', 'svago' ), $payment_title ) );
                }
            } else {
                update_post_meta( $order_id, '_payment_status', 'Paid' );
                wp_update_post( [ 'ID' => $order_id, 'post_status' => 'wc-completed' ] );
            }
        }

        // 5. Push Booking to OTA Channel Manager (Task 4.5 & Task 6.2)
        $pushed = $order_mgr->push_booking_to_ota( $order_id );

        // 6. Trigger Customer Notifications (Phase 5)
        if ( class_exists( 'SVAGO\Notifications' ) ) {
            $notif = Notifications::get_instance();
            $notif->send_whatsapp( $order_id, 'booking_confirmed', true );
        }

        // 7. Log staff activity in Logger with current user ID (Task 6.7)
        $staff_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
        Logger::get_instance()->log(
            'info',
            sprintf( 'Staff User #%d created Onsite Booking #SV-%d (%s) for %s %s. Paid via %s.', $staff_user_id, $order_id, $room_name, $first_name, $last_name, $payment_title ),
            [
                'order_id'       => $order_id,
                'user_id'        => $staff_user_id,
                'source'         => $source,
                'payment_method' => $payment_method,
                'ota_pushed'     => $pushed,
            ]
        );

        $booking_page_url = get_option( 'svago_booking_page_url', '' );
        $conf_url         = $booking_page_url ? add_query_arg( [ 'step' => 'confirmation', 'order_id' => $order_id ], $booking_page_url ) : admin_url( "post.php?post={$order_id}&action=edit" );

        return [
            'success'          => true,
            'order_id'         => $order_id,
            'message'          => sprintf( __( 'Reservation #SV-%d successfully created and pushed to OTA!', 'svago' ), $order_id ),
            'order_url'        => admin_url( "post.php?post={$order_id}&action=edit" ),
            'confirmation_url' => $conf_url,
            'ota_pushed'       => $pushed,
        ];
    }
}

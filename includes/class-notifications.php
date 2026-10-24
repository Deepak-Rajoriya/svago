<?php
/**
 * Notifications Manager (Twilio WhatsApp & Transactional Email)
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Twilio WhatsApp notifications, template mapping,
 * consent gating, pre-arrival reminders cron, and admin manual resends.
 */
final class Notifications {

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
        // Order Status Event Triggers (Tasks 5.3, 5.4, 5.6)
        add_action( 'woocommerce_order_status_completed', [ $this, 'on_booking_confirmed' ], 10, 1 );
        add_action( 'woocommerce_payment_complete', [ $this, 'on_booking_confirmed' ], 10, 1 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'on_booking_confirmed' ], 10, 1 );
        add_action( 'woocommerce_order_status_failed', [ $this, 'on_payment_failed' ], 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'on_booking_cancelled' ], 10, 1 );
        add_action( 'woocommerce_order_status_refunded', [ $this, 'on_booking_cancelled' ], 10, 1 );

        // Pre-Arrival Reminder Daily Cron (Task 5.5)
        add_action( 'svago_daily_pre_arrival_reminder', [ $this, 'cron_pre_arrival_reminders' ] );

        // Admin Metabox on Order Screen & Resend AJAX (Task 5.8)
        add_action( 'add_meta_boxes', [ $this, 'add_order_metabox' ] );
        add_action( 'wp_ajax_svago_resend_whatsapp', [ $this, 'ajax_resend_whatsapp' ] );
    }

    /**
     * Trigger: Booking Confirmed (Task 5.3)
     *
     * @param int $order_id
     */
    public function on_booking_confirmed( $order_id ): void {
        $order_id = (int) $order_id;
        if ( ! $order_id ) {
            return;
        }

        // Avoid duplicate confirmation message
        $already_sent = get_post_meta( $order_id, '_svago_whatsapp_confirmed_sent', true );
        if ( 'yes' === $already_sent ) {
            return;
        }

        $result = $this->send_whatsapp( $order_id, 'booking_confirmed' );
        if ( ! empty( $result['success'] ) ) {
            update_post_meta( $order_id, '_svago_whatsapp_confirmed_sent', 'yes' );
        }
    }

    /**
     * Trigger: Payment Failed (Task 5.4)
     *
     * @param int $order_id
     */
    public function on_payment_failed( $order_id ): void {
        $order_id = (int) $order_id;
        if ( ! $order_id ) {
            return;
        }

        $this->send_whatsapp( $order_id, 'payment_failed' );
    }

    /**
     * Trigger: Booking Cancelled (Task 5.6)
     *
     * @param int $order_id
     */
    public function on_booking_cancelled( $order_id ): void {
        $order_id = (int) $order_id;
        if ( ! $order_id ) {
            return;
        }

        $this->send_whatsapp( $order_id, 'cancellation' );
    }

    /**
     * Cron Handler: Send Pre-Arrival Reminders 1 day before check-in (Task 5.5)
     *
     * @param string|null $target_date Optional date in Y-m-d format (defaults to tomorrow)
     * @return int Number of reminders processed
     */
    public function cron_pre_arrival_reminders( ?string $target_date = null ): int {
        $date = $target_date ?: date( 'Y-m-d', strtotime( '+1 day' ) );
        $processed = 0;

        // Query active orders where check_in matches target date
        $order_ids = $this->get_orders_for_arrival_date( $date );

        Logger::get_instance()->log(
            'info',
            sprintf( 'Running pre-arrival reminder cron for date %s. Found %d matching bookings.', $date, count( $order_ids ) )
        );

        foreach ( $order_ids as $order_id ) {
            // Check if reminder was already sent
            $reminder_sent = get_post_meta( $order_id, '_booking_reminder_sent', true );
            if ( 'yes' === $reminder_sent ) {
                continue;
            }

            $res = $this->send_whatsapp( $order_id, 'pre_arrival' );
            if ( ! empty( $res['success'] ) ) {
                update_post_meta( $order_id, '_booking_reminder_sent', 'yes' );
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Dispatch WhatsApp message with template mapping and consent gating (Tasks 5.2, 5.7, 5.8)
     *
     * @param int    $order_id
     * @param string $template_type 'booking_confirmed' | 'payment_failed' | 'pre_arrival' | 'cancellation'
     * @param bool   $ignore_consent Force send (used for admin manual override if requested)
     * @return array Result array [ 'success' => bool, 'reason' => string, 'sid' => string ]
     */
    public function send_whatsapp( int $order_id, string $template_type, bool $ignore_consent = false ): array {
        // 1. Check if WhatsApp notifications are enabled in admin
        $enabled = (bool) get_option( 'svago_enable_whatsapp', true );
        if ( ! $enabled ) {
            Logger::get_instance()->log(
                'info',
                sprintf( 'WhatsApp skipped for Order #%d: WhatsApp notifications globally disabled.', $order_id )
            );
            return [ 'success' => false, 'reason' => 'whatsapp_disabled' ];
        }

        // 2. Consent Gating (Task 5.7): Strictly require guest consent
        $consent = get_post_meta( $order_id, '_booking_consent', true );
        $has_consent = ( 'yes' === $consent || true === $consent || '1' === $consent );

        if ( ! $has_consent && ! $ignore_consent ) {
            Logger::get_instance()->log(
                'notice',
                sprintf( 'WhatsApp skipped for Order #%d: Guest opted out / did not grant marketing & notification consent.', $order_id )
            );
            update_post_meta( $order_id, '_svago_whatsapp_status', 'not_consented' );
            return [ 'success' => false, 'reason' => 'consent_not_granted' ];
        }

        // 3. Extract Booking & Contact Data
        $order_mgr    = Order_Manager::get_instance();
        $booking_data = $order_mgr->get_order_booking_data( $order_id );

        if ( ! $booking_data ) {
            Logger::get_instance()->log(
                'error',
                sprintf( 'WhatsApp failed for Order #%d: Missing booking metadata.', $order_id )
            );
            return [ 'success' => false, 'reason' => 'missing_metadata' ];
        }

        $phone = $booking_data['guest_phone'] ?? '';
        if ( empty( $phone ) ) {
            Logger::get_instance()->log(
                'error',
                sprintf( 'WhatsApp failed for Order #%d: Guest phone number is empty.', $order_id )
            );
            update_post_meta( $order_id, '_svago_whatsapp_status', 'failed' );
            update_post_meta( $order_id, '_svago_whatsapp_error', 'Missing phone number' );
            return [ 'success' => false, 'reason' => 'missing_phone' ];
        }

        // 4. Compile Template Body (Task 5.2)
        $message_body = $this->build_template_message( $template_type, $booking_data );

        // 5. Dispatch via Twilio API Client
        $dispatch_res = $this->dispatch_twilio_message( $phone, $message_body );

        // 6. Record metadata & log status (Task 5.8)
        if ( ! empty( $dispatch_res['success'] ) ) {
            $sid = $dispatch_res['sid'] ?? '';
            update_post_meta( $order_id, '_svago_whatsapp_status', 'sent' );
            update_post_meta( $order_id, '_svago_whatsapp_sid', $sid );
            update_post_meta( $order_id, '_svago_whatsapp_last_template', $template_type );
            update_post_meta( $order_id, '_svago_whatsapp_last_sent', current_time( 'mysql' ) );

            Logger::get_instance()->log(
                'info',
                sprintf( 'WhatsApp [%s] sent for Order #%d to %s (SID: %s)', $template_type, $order_id, $phone, $sid ),
                [ 'order_id' => $order_id, 'template' => $template_type, 'sid' => $sid ]
            );

            return [
                'success'  => true,
                'sid'      => $sid,
                'template' => $template_type,
            ];
        } else {
            $err = $dispatch_res['error'] ?? __( 'Unknown Twilio error', 'svago' );
            update_post_meta( $order_id, '_svago_whatsapp_status', 'failed' );
            update_post_meta( $order_id, '_svago_whatsapp_error', $err );

            Logger::get_instance()->log(
                'error',
                sprintf( 'WhatsApp [%s] failed for Order #%d: %s', $template_type, $order_id, $err ),
                [ 'order_id' => $order_id, 'error' => $err ]
            );

            return [
                'success' => false,
                'error'   => $err,
                'reason'  => 'api_error',
            ];
        }
    }

    /**
     * Build WhatsApp message text mapped to event template (Task 5.2)
     *
     * @param string $template_type
     * @param array  $b
     * @return string
     */
    public function build_template_message( string $template_type, array $b ): string {
        $hotel_name    = get_bloginfo( 'name' );
        $hotel_phone   = get_option( 'svago_hotel_phone', '+91 98765 43210' );
        $hotel_email   = get_option( 'svago_hotel_email', 'reservations@hotel.com' );
        $hotel_address = get_option( 'svago_hotel_address', '123 Ocean Drive, Luxury Bay, Goa 403001, India' );
        $hotel_map_url = get_option( 'svago_hotel_map_url', 'https://maps.google.com/?q=Hotel+Luxury+Bay' );

        $order_id   = $b['order_id'] ?? '';
        $guest_name = $b['guest_name'] ?? 'Guest';
        $room_name  = $b['room_name'] ?? 'Hotel Room';
        $rate_name  = $b['rate_name'] ?? 'Standard Rate';
        $check_in   = $b['check_in'] ?? '';
        $check_out  = $b['check_out'] ?? '';
        $nights     = $b['nights'] ?? 1;
        $adults     = $b['adults'] ?? 2;
        $children   = $b['children'] ?? 0;
        $total      = number_format( (float) ( $b['total_amount'] ?? 0 ), 2 );

        $children_text = $children > 0 ? sprintf( ', %d Children', $children ) : '';

        switch ( $template_type ) {
            case 'booking_confirmed':
                return sprintf(
                    "🏨 *Reservation Confirmed — %s*\n" .
                    "Dear %s, thank you! Your room is guaranteed.\n\n" .
                    "• Booking Ref: #SV-%s\n" .
                    "• Room: %s (%s)\n" .
                    "• Check-in: %s (from 02:00 PM)\n" .
                    "• Check-out: %s (by 11:00 AM)\n" .
                    "• Stay: %d Night(s) | %d Adults%s\n" .
                    "• Total Paid: ₹%s\n\n" .
                    "📍 Location: %s\n" .
                    "🗺️ Map Directions: %s\n\n" .
                    "Need assistance? Call us at %s.",
                    $hotel_name,
                    $guest_name,
                    $order_id,
                    $room_name,
                    $rate_name,
                    $check_in,
                    $check_out,
                    $nights,
                    $adults,
                    $children_text,
                    $total,
                    $hotel_address,
                    $hotel_map_url,
                    $hotel_phone
                );

            case 'payment_failed':
                $booking_page_url = get_option( 'svago_booking_page_url', home_url( '/hotel-booking/' ) );
                $retry_url        = add_query_arg( [ 'step' => 'details', 'retry_order' => $order_id ], $booking_page_url );
                return sprintf(
                    "⚠️ *Payment Notice — %s*\n" .
                    "Dear %s, we were unable to process payment for your reservation (#SV-%s) for %s.\n\n" .
                    "Your dates (%s to %s) are held temporarily.\n" .
                    "Please retry your payment here:\n%s\n\n" .
                    "Or call our 24/7 reservations desk at %s for instant assistance.",
                    $hotel_name,
                    $guest_name,
                    $order_id,
                    $room_name,
                    $check_in,
                    $check_out,
                    $retry_url,
                    $hotel_phone
                );

            case 'pre_arrival':
                return sprintf(
                    "🌴 *See You Tomorrow! — %s*\n" .
                    "Dear %s, we look forward to welcoming you tomorrow, %s!\n\n" .
                    "• Room: %s\n" .
                    "• Check-in: From 02:00 PM\n" .
                    "• Important: Valid Government Photo ID required for all guests at check-in.\n\n" .
                    "📍 Address: %s\n" .
                    "🗺️ Google Maps Directions: %s\n\n" .
                    "Safe travels! If you require early check-in or airport transfer, call %s.",
                    $hotel_name,
                    $guest_name,
                    $check_in,
                    $room_name,
                    $hotel_address,
                    $hotel_map_url,
                    $hotel_phone
                );

            case 'cancellation':
                return sprintf(
                    "❌ *Reservation Cancelled — %s*\n" .
                    "Dear %s, your reservation #SV-%s for %s (%s to %s) has been cancelled.\n\n" .
                    "If eligible under the hotel cancellation policy, refunds will be processed via your original payment method.\n\n" .
                    "For any queries, please reach out to %s or email %s.",
                    $hotel_name,
                    $guest_name,
                    $order_id,
                    $room_name,
                    $check_in,
                    $check_out,
                    $hotel_phone,
                    $hotel_email
                );

            default:
                return sprintf(
                    "🏨 *%s Update*\nDear %s, your booking #SV-%s has been updated. Please contact %s for details.",
                    $hotel_name,
                    $guest_name,
                    $order_id,
                    $hotel_phone
                );
        }
    }

    /**
     * Dispatch message through Twilio REST API client or Mock simulator (Task 5.1 & 5.8)
     *
     * @param string $to_phone
     * @param string $message_body
     * @return array [ 'success' => bool, 'sid' => string, 'error' => string ]
     */
    public function dispatch_twilio_message( string $to_phone, string $message_body ): array {
        $sid        = trim( (string) get_option( 'svago_twilio_account_sid', '' ) );
        $auth_token = trim( (string) get_option( 'svago_twilio_auth_token', '' ) );
        $from_raw   = trim( (string) get_option( 'svago_twilio_whatsapp_number', '' ) );

        // Format recipient to WhatsApp E.164
        $clean_to   = preg_replace( '/[^0-9+]/', '', $to_phone );
        $to_whatsapp = ( str_starts_with( $clean_to, 'whatsapp:' ) ) ? $clean_to : ( 'whatsapp:' . $clean_to );

        // Format sender
        $clean_from   = preg_replace( '/[^0-9+]/', '', $from_raw );
        $from_whatsapp = ( str_starts_with( $clean_from, 'whatsapp:' ) ) ? $clean_from : ( 'whatsapp:' . ( $clean_from ?: '+14155238886' ) );

        // If credentials are empty or provider mode is mock, execute realistic mock simulation
        $mode = get_option( 'svago_provider_mode', 'mock' );
        if ( empty( $sid ) || empty( $auth_token ) || 'mock' === $mode ) {
            $uuid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
            $mock_sid = 'SM_MOCK_' . $uuid;

            Logger::get_instance()->log(
                'info',
                sprintf( 'Twilio WhatsApp (Simulation) dispatched to %s. Message: %s', $to_whatsapp, substr( $message_body, 0, 100 ) . '...' ),
                [ 'sid' => $mock_sid, 'to' => $to_whatsapp ]
            );

            return [
                'success' => true,
                'sid'     => $mock_sid,
                'mode'    => 'simulation',
            ];
        }

        // Live Twilio API Request
        $api_url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $response = wp_remote_post( $api_url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$auth_token}" ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => [
                'From' => $from_whatsapp,
                'To'   => $to_whatsapp,
                'Body' => $message_body,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code || 201 === $code ) {
            return [
                'success' => true,
                'sid'     => $body['sid'] ?? ( 'SM' . time() ),
                'mode'    => 'live',
            ];
        }

        $error_msg = $body['message'] ?? sprintf( 'Twilio API Error (HTTP %d)', $code );
        return [
            'success' => false,
            'error'   => $error_msg,
        ];
    }

    /**
     * AJAX endpoint: Admin manual WhatsApp resend from order edit screen (Task 5.8)
     */
    public function ajax_resend_whatsapp(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $order_id      = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
        $template_type = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : 'booking_confirmed';

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order ID.', 'svago' ) ] );
        }

        // Allow admin manual resend to bypass marketing consent check if explicitly prompted
        $res = $this->send_whatsapp( $order_id, $template_type, true );

        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( [
                'message'   => sprintf( __( 'WhatsApp message (%s) successfully resent.', 'svago' ), $template_type ),
                'sid'       => $res['sid'] ?? '',
                'timestamp' => current_time( 'mysql' ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => $res['error'] ?? __( 'Failed to dispatch WhatsApp message.', 'svago' ),
                'reason'  => $res['reason'] ?? 'unknown',
            ] );
        }
    }

    /**
     * Register admin metabox on WooCommerce Order screen (Task 5.8)
     */
    public function add_order_metabox(): void {
        $screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
        foreach ( $screens as $screen ) {
            add_meta_box(
                'svago_whatsapp_meta',
                __( 'Hotel WhatsApp Notifications (Svago)', 'svago' ),
                [ $this, 'render_order_metabox' ],
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render admin order metabox
     *
     * @param object $post_or_order
     */
    public function render_order_metabox( $post_or_order ): void {
        $order_id = is_numeric( $post_or_order ) ? (int) $post_or_order : ( method_exists( $post_or_order, 'get_id' ) ? $post_or_order->get_id() : ( $post_or_order->ID ?? 0 ) );
        if ( ! $order_id ) {
            return;
        }

        $status   = get_post_meta( $order_id, '_svago_whatsapp_status', true ) ?: 'pending';
        $template = get_post_meta( $order_id, '_svago_whatsapp_last_template', true ) ?: 'booking_confirmed';
        $last_sent = get_post_meta( $order_id, '_svago_whatsapp_last_sent', true );
        $sid      = get_post_meta( $order_id, '_svago_whatsapp_sid', true );
        $consent  = get_post_meta( $order_id, '_booking_consent', true );
        $error    = get_post_meta( $order_id, '_svago_whatsapp_error', true );

        $nonce = wp_create_nonce( 'svago_booking_nonce' );
        ?>
        <div class="svago-admin-whatsapp-box">
            <p>
                <strong><?php esc_html_e( 'Guest Consent:', 'svago' ); ?></strong>
                <?php if ( 'yes' === $consent || true === $consent || '1' === $consent ) : ?>
                    <span style="color:#2e7d32; font-weight:600;">✓ <?php esc_html_e( 'Granted', 'svago' ); ?></span>
                <?php else : ?>
                    <span style="color:#c62828; font-weight:600;">✗ <?php esc_html_e( 'Not Consented', 'svago' ); ?></span>
                <?php endif; ?>
            </p>

            <p>
                <strong><?php esc_html_e( 'Delivery Status:', 'svago' ); ?></strong>
                <?php if ( 'sent' === $status ) : ?>
                    <span style="color:#2e7d32; font-weight:600;">✓ <?php esc_html_e( 'Sent', 'svago' ); ?></span>
                <?php elseif ( 'failed' === $status ) : ?>
                    <span style="color:#c62828; font-weight:600;">✗ <?php esc_html_e( 'Failed', 'svago' ); ?></span>
                <?php elseif ( 'not_consented' === $status ) : ?>
                    <span style="color:#f57c00; font-weight:600;">⚠️ <?php esc_html_e( 'Skipped (Opt-out)', 'svago' ); ?></span>
                <?php else : ?>
                    <span style="color:#666;">⏳ <?php esc_html_e( 'Pending', 'svago' ); ?></span>
                <?php endif; ?>
            </p>

            <?php if ( ! empty( $last_sent ) ) : ?>
                <p style="font-size:12px; color:#666;">
                    <strong><?php esc_html_e( 'Last Sent:', 'svago' ); ?></strong> <?php echo esc_html( $last_sent ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $sid ) ) : ?>
                <p style="font-size:11px; color:#888;">
                    <strong><?php esc_html_e( 'Twilio SID:', 'svago' ); ?></strong> <code><?php echo esc_html( $sid ); ?></code>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $error ) ) : ?>
                <p style="font-size:12px; color:#c62828;">
                    <strong><?php esc_html_e( 'Error:', 'svago' ); ?></strong> <?php echo esc_html( $error ); ?>
                </p>
            <?php endif; ?>

            <div style="margin-top:14px; border-top:1px solid #eee; padding-top:10px;">
                <label for="svago-resend-template" style="display:block; font-size:12px; margin-bottom:4px;">
                    <?php esc_html_e( 'Template to send:', 'svago' ); ?>
                </label>
                <select id="svago-resend-template" style="width:100%; margin-bottom:10px;">
                    <option value="booking_confirmed" <?php selected( $template, 'booking_confirmed' ); ?>><?php esc_html_e( 'Booking Confirmed', 'svago' ); ?></option>
                    <option value="pre_arrival" <?php selected( $template, 'pre_arrival' ); ?>><?php esc_html_e( 'Pre-Arrival Reminder', 'svago' ); ?></option>
                    <option value="payment_failed" <?php selected( $template, 'payment_failed' ); ?>><?php esc_html_e( 'Payment Failed', 'svago' ); ?></option>
                    <option value="cancellation" <?php selected( $template, 'cancellation' ); ?>><?php esc_html_e( 'Cancellation', 'svago' ); ?></option>
                </select>

                <button 
                    type="button" 
                    id="svago-resend-whatsapp-btn" 
                    class="button button-secondary" 
                    data-order-id="<?php echo esc_attr( $order_id ); ?>" 
                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                    style="width:100%; text-align:center;"
                >
                    💬 <?php esc_html_e( 'Resend WhatsApp Message', 'svago' ); ?>
                </button>
                <div id="svago-resend-status" style="margin-top:8px; font-size:12px;"></div>
            </div>
        </div>

        <script>
            jQuery(function($) {
                $('#svago-resend-whatsapp-btn').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var orderId = $btn.data('order-id');
                    var nonce = $btn.data('nonce');
                    var template = $('#svago-resend-template').val();
                    var $status = $('#svago-resend-status');

                    $btn.prop('disabled', true).text('Sending...');
                    $status.text('');

                    $.post(ajaxurl, {
                        action: 'svago_resend_whatsapp',
                        nonce: nonce,
                        order_id: orderId,
                        template: template
                    }, function(res) {
                        if (res.success) {
                            $status.css('color', '#2e7d32').text('✓ ' + res.data.message);
                            setTimeout(function() { location.reload(); }, 1200);
                        } else {
                            $status.css('color', '#c62828').text('✗ ' + (res.data.message || 'Send failed'));
                            $btn.prop('disabled', false).text('💬 Resend WhatsApp Message');
                        }
                    }).fail(function() {
                        $status.css('color', '#c62828').text('✗ Request failed');
                        $btn.prop('disabled', false).text('💬 Resend WhatsApp Message');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Find WooCommerce orders where check-in matches a specific date
     *
     * @param string $check_in_date Y-m-d
     * @return array Array of order IDs
     */
    private function get_orders_for_arrival_date( string $check_in_date ): array {
        if ( function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( [
                'limit'        => 100,
                'status'       => [ 'processing', 'completed' ],
                'meta_key'     => '_booking_check_in',
                'meta_value'   => $check_in_date,
                'return'       => 'ids',
            ] );
            if ( ! empty( $orders ) ) {
                return array_map( 'intval', $orders );
            }
        }

        // Direct database query fallback for custom or test environments
        global $wpdb;
        $order_ids = [];
        if ( isset( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                '_booking_check_in',
                $check_in_date
            );
            $results = $wpdb->get_col( $query );
            if ( ! empty( $results ) ) {
                $order_ids = array_map( 'intval', $results );
            }
        }

        return $order_ids;
    }
}

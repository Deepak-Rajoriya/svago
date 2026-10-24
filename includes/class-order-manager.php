<?php
/**
 * Order & Payment Manager
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles WooCommerce order creation, Razorpay payment verification,
 * asynchronous OTA push with WP-Cron retries, and hotel email notifications.
 */
final class Order_Manager {

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
        // AJAX Order Creation (Task 4.1)
        add_action( 'wp_ajax_svago_create_order', [ $this, 'ajax_create_order' ] );
        add_action( 'wp_ajax_nopriv_svago_create_order', [ $this, 'ajax_create_order' ] );

        // AJAX Razorpay Payment Verification & Capture (Task 4.2 & 4.3)
        add_action( 'wp_ajax_svago_verify_payment', [ $this, 'ajax_verify_payment' ] );
        add_action( 'wp_ajax_nopriv_svago_verify_payment', [ $this, 'ajax_verify_payment' ] );

        // Razorpay Webhook Endpoint & Idempotency (Task 7.3)
        add_action( 'wp_ajax_nopriv_svago_razorpay_webhook', [ $this, 'handle_razorpay_webhook' ] );
        add_action( 'wp_ajax_svago_razorpay_webhook', [ $this, 'handle_razorpay_webhook' ] );

        // WooCommerce Payment Hooks (Tasks 4.3 & 4.5)
        add_action( 'woocommerce_payment_complete', [ $this, 'handle_payment_complete' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'handle_payment_complete' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_payment_complete' ] );

        // WP-Cron Asynchronous Retry Hook (Task 4.6)
        add_action( 'svago_retry_ota_push', [ $this, 'cron_retry_ota_push' ] );

        // Hotel Reservations Email Bcc/Copy (Task 4.7)
        add_filter( 'woocommerce_email_recipient_customer_processing_order', [ $this, 'add_hotel_email_recipient' ], 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_completed_order', [ $this, 'add_hotel_email_recipient' ], 10, 2 );

        // WooCommerce Checkout Integration: Pre-fill guest info, Order Summary booking metadata & hotel policies
        add_filter( 'woocommerce_checkout_get_value', [ $this, 'prefill_checkout_field' ], 10, 2 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'render_booking_item_data' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_booking_cart_item_price' ], 20, 1 );
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'render_checkout_hotel_policies' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_checkout_booking_meta' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_line_item_booking_meta' ], 10, 4 );

        // Direct Checkout Flow: Skip WooCommerce Cart page entirely (Requirement 3)
        add_action( 'template_redirect', [ $this, 'skip_cart_redirect_to_checkout' ] );
        add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'direct_checkout_redirect' ] );
        add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );

        // Phase 6: WooCommerce Orders list custom columns & filters (Task 6.3)
        add_filter( 'manage_shop_order_posts_columns', [ $this, 'register_order_columns' ] );
        add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'register_order_columns' ] );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_order_columns' ], 10, 2 );
        add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'render_order_columns_hpos' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'render_order_filters' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_orders_query' ] );

        // Phase 6: Order screen actions - Resend Confirmation (Task 6.4)
        add_filter( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
        add_action( 'woocommerce_order_action_svago_resend_confirmation', [ $this, 'action_resend_confirmation' ] );

        // Phase 6: Manual Cancel with OTA push & notification (Task 6.5)
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'handle_order_cancelled' ], 5, 1 );
    }

    /**
     * Create WooCommerce order from booking session data (Task 4.1)
     *
     * @param array $session_data
     * @return int|\WP_Error Order ID or error
     */
    public function create_booking_order( array $session_data ) {
        $room_id         = sanitize_text_field( $session_data['room_id'] ?? '' );
        $room_name       = sanitize_text_field( $session_data['room_name'] ?? __( 'Hotel Room', 'svago' ) );
        $room_type       = sanitize_text_field( $session_data['room_type'] ?? 'Balcony' );
        $rate_id         = sanitize_text_field( $session_data['rate_id'] ?? 'standard' );
        $rate_name       = sanitize_text_field( $session_data['rate_name'] ?? __( 'Standard Rate', 'svago' ) );
        $check_in        = sanitize_text_field( $session_data['check_in'] ?? '' );
        $check_out       = sanitize_text_field( $session_data['check_out'] ?? '' );
        $nights          = max( 1, (int) ( $session_data['nights'] ?? 1 ) );
        $adults          = max( 1, (int) ( $session_data['adults'] ?? 2 ) );
        $children        = max( 0, (int) ( $session_data['children'] ?? 0 ) );
        $child_ages      = isset( $session_data['child_ages'] ) && is_array( $session_data['child_ages'] ) ? $session_data['child_ages'] : [];
        $price_per_night = (float) ( $session_data['price_per_night'] ?? 0 );
        $total_amount    = (float) ( $session_data['total_amount'] ?? ( $price_per_night * $nights ) );
        $guest           = $session_data['guest'] ?? [];

        if ( empty( $room_id ) || empty( $check_in ) || empty( $check_out ) ) {
            return new \WP_Error( 'missing_data', __( 'Incomplete booking data provided for order creation.', 'svago' ) );
        }

        $order_id = 0;

        // 1. If WooCommerce is active, create formal WC_Order
        if ( function_exists( 'wc_create_order' ) ) {
            try {
                $order = wc_create_order();

                // Find WooCommerce product linked with OTA room ID
                $product_id = $this->find_product_by_room_id( $room_id );
                $product    = $product_id ? wc_get_product( $product_id ) : null;

                if ( $product ) {
                    $order->add_product( $product, 1, [
                        'subtotal' => $total_amount,
                        'total'    => $total_amount,
                    ] );
                } else {
                    // Create custom fee/line item if product does not exist yet
                    $item = new \WC_Order_Item_Fee();
                    $item->set_name( sprintf( '%s (%s × %d nts)', $room_name, $rate_name, $nights ) );
                    $item->set_amount( $total_amount );
                    $item->set_total( $total_amount );
                    $order->add_item( $item );
                }

                // Billing Details
                $first_name = sanitize_text_field( $guest['first_name'] ?? '' );
                $last_name  = sanitize_text_field( $guest['last_name'] ?? '' );
                $email      = sanitize_email( $guest['email'] ?? '' );
                $phone      = sanitize_text_field( $guest['phone'] ?? '' );

                $order->set_billing_first_name( $first_name );
                $order->set_billing_last_name( $last_name );
                $order->set_billing_email( $email );
                $order->set_billing_phone( $phone );

                // Store Custom Booking Meta
                $order->update_meta_data( '_booking_room_id', $room_id );
                $order->update_meta_data( '_booking_room_name', $room_name );
                $order->update_meta_data( '_booking_room_type', $room_type );
                $order->update_meta_data( '_booking_rate_id', $rate_id );
                $order->update_meta_data( '_booking_rate_name', $rate_name );
                $order->update_meta_data( '_booking_check_in', $check_in );
                $order->update_meta_data( '_booking_check_out', $check_out );
                $order->update_meta_data( '_booking_nights', $nights );
                $order->update_meta_data( '_booking_adults', $adults );
                $order->update_meta_data( '_booking_children', $children );
                $order->update_meta_data( '_booking_child_ages', wp_json_encode( $child_ages ) );
                $order->update_meta_data( '_booking_price_per_night', $price_per_night );
                $order->update_meta_data( '_booking_total_amount', $total_amount );
                $order->update_meta_data( '_booking_guest_eta', sanitize_text_field( $guest['eta'] ?? '' ) );
                $order->update_meta_data( '_booking_special_requests', sanitize_textarea_field( $guest['special_requests'] ?? '' ) );
                $order->update_meta_data( '_booking_consent', ! empty( $guest['consent'] ) ? 'yes' : 'no' );
                $order->update_meta_data( '_booking_source', 'online' );
                $order->update_meta_data( '_booking_policy_agreed', 'yes' );
                $order->update_meta_data( '_booking_ota_pushed', 'no' );
                $order->update_meta_data( '_booking_ota_id', '' );
                $order->update_meta_data( '_booking_ota_retry_count', 0 );

                // Set Payment Method (Razorpay if enabled)
                $order->set_payment_method( 'razorpay' );
                $order->set_payment_method_title( 'Razorpay (Cards, UPI, NetBanking)' );

                $order->calculate_totals();
                $order->set_status( 'pending', __( 'Awaiting Razorpay payment confirmation.', 'svago' ) );
                $order->save();

                $order_id = $order->get_id();
            } catch ( \Throwable $e ) {
                Logger::get_instance()->log(
                    'error',
                    'Failed creating WooCommerce order: ' . $e->getMessage(),
                    [ 'session_data' => $session_data ]
                );
                return new \WP_Error( 'order_creation_failed', $e->getMessage() );
            }
        } else {
            // Standalone Fallback Order Creation (in environments without WooCommerce active)
            $order_id = wp_insert_post( [
                'post_type'   => 'shop_order',
                'post_status' => 'wc-pending',
                'post_title'  => sprintf( 'Hotel Booking - %s (%s)', $room_name, $check_in ),
            ] );

            if ( is_wp_error( $order_id ) || ! $order_id ) {
                return new \WP_Error( 'order_creation_failed', __( 'Could not create booking order record.', 'svago' ) );
            }

            // Save meta via WordPress update_post_meta
            update_post_meta( $order_id, '_billing_first_name', sanitize_text_field( $guest['first_name'] ?? '' ) );
            update_post_meta( $order_id, '_billing_last_name', sanitize_text_field( $guest['last_name'] ?? '' ) );
            update_post_meta( $order_id, '_billing_email', sanitize_email( $guest['email'] ?? '' ) );
            update_post_meta( $order_id, '_billing_phone', sanitize_text_field( $guest['phone'] ?? '' ) );
            update_post_meta( $order_id, '_order_total', $total_amount );

            update_post_meta( $order_id, '_booking_room_id', $room_id );
            update_post_meta( $order_id, '_booking_room_name', $room_name );
            update_post_meta( $order_id, '_booking_room_type', $room_type );
            update_post_meta( $order_id, '_booking_rate_id', $rate_id );
            update_post_meta( $order_id, '_booking_rate_name', $rate_name );
            update_post_meta( $order_id, '_booking_check_in', $check_in );
            update_post_meta( $order_id, '_booking_check_out', $check_out );
            update_post_meta( $order_id, '_booking_nights', $nights );
            update_post_meta( $order_id, '_booking_adults', $adults );
            update_post_meta( $order_id, '_booking_children', $children );
            update_post_meta( $order_id, '_booking_child_ages', wp_json_encode( $child_ages ) );
            update_post_meta( $order_id, '_booking_price_per_night', $price_per_night );
            update_post_meta( $order_id, '_booking_total_amount', $total_amount );
            update_post_meta( $order_id, '_booking_guest_eta', sanitize_text_field( $guest['eta'] ?? '' ) );
            update_post_meta( $order_id, '_booking_special_requests', sanitize_textarea_field( $guest['special_requests'] ?? '' ) );
            update_post_meta( $order_id, '_booking_consent', ! empty( $guest['consent'] ) ? 'yes' : 'no' );
            update_post_meta( $order_id, '_booking_source', 'online' );
            update_post_meta( $order_id, '_booking_policy_agreed', 'yes' );
            update_post_meta( $order_id, '_booking_ota_pushed', 'no' );
            update_post_meta( $order_id, '_booking_ota_id', '' );
            update_post_meta( $order_id, '_booking_ota_retry_count', 0 );
        }

        Logger::get_instance()->log(
            'info',
            sprintf( 'Booking order #%d generated successfully for %s', $order_id, $room_name ),
            [ 'order_id' => $order_id, 'room_id' => $room_id, 'total' => $total_amount ]
        );

        return $order_id;
    }

    /**
     * AJAX endpoint: Create order and prepare Razorpay checkout params (Task 4.1 & 4.2)
     */
    public function ajax_create_order(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $policy_agreed = ! empty( $_POST['agree_policy'] ) || ! empty( $_POST['policy_agreed'] );
        if ( ! $policy_agreed ) {
            wp_send_json_error( [
                'type'    => 'policy_required',
                'message' => __( 'You must agree to the hotel cancellation policy and terms to proceed.', 'svago' ),
            ] );
            return;
        }

        $session_data = Booking_Flow::get_session_data();
        if ( empty( $session_data ) ) {
            wp_send_json_error( [
                'type'    => 'session_expired',
                'message' => __( 'Your booking session has expired. Please select your room again.', 'svago' ),
            ] );
            return;
        }

        // Re-check inventory availability prior to order generation (Task 3.5 & Task 4.1)
        $room_id   = $session_data['room_id'] ?? '';
        $check_in  = $session_data['check_in'] ?? '';
        $check_out = $session_data['check_out'] ?? '';

        try {
            $provider = Provider_Factory::get();
            $avail    = $provider->get_availability( [
                'check_in'   => $check_in,
                'check_out'  => $check_out,
                'adults'     => $session_data['adults'] ?? 2,
                'children'   => $session_data['children'] ?? 0,
                'child_ages' => $session_data['child_ages'] ?? [],
            ] );

            if ( empty( $avail[ $room_id ]['available'] ) ) {
                wp_send_json_error( [
                    'type'    => 'unavailable',
                    'message' => __( 'This room is no longer available for your dates. Please try other dates or rooms.', 'svago' ),
                ] );
                return;
            }
        } catch ( \Throwable $e ) {
            // Check fallback
            $loader = new Fallback_Loader();
            $fb     = $loader->load_availability();
            if ( empty( $fb[ $room_id ] ) ) {
                wp_send_json_error( [
                    'type'    => 'unavailable',
                    'message' => __( 'This room is no longer available for your dates. Please try other dates or rooms.', 'svago' ),
                ] );
                return;
            }
        }

        // Create Order
        $order_id = $this->create_booking_order( $session_data );
        if ( is_wp_error( $order_id ) ) {
            wp_send_json_error( [ 'message' => $order_id->get_error_message() ] );
            return;
        }

        $total_amount = (float) ( $session_data['total_amount'] ?? 0 );
        $guest        = $session_data['guest'] ?? [];
        $currency     = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';
        $hotel_name   = get_bloginfo( 'name' );

        // Determine destination WooCommerce checkout URL
        $checkout_url = '';
        if ( is_numeric( $order_id ) && $order_id > 0 ) {
            if ( function_exists( 'wc_get_order' ) ) {
                $wc_order = wc_get_order( $order_id );
                if ( $wc_order && method_exists( $wc_order, 'get_checkout_payment_url' ) ) {
                    $checkout_url = $wc_order->get_checkout_payment_url();
                }
            }
        }
        if ( empty( $checkout_url ) && function_exists( 'wc_get_checkout_url' ) ) {
            $checkout_url = wc_get_checkout_url();
        }
        if ( empty( $checkout_url ) ) {
            $checkout_url = home_url( '/checkout/' );
        }

        // If WooCommerce cart is active, synchronize booking item into cart as well
        if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
            try {
                WC()->cart->empty_cart();
                $product_id = $this->find_product_by_room_id( $room_id );
                if ( $product_id ) {
                    WC()->cart->add_to_cart( $product_id, 1, 0, [], [
                        'booking_room_id'   => $room_id,
                        'booking_room_name' => $session_data['room_name'] ?? '',
                        'booking_room_type' => $session_data['room_type'] ?? 'Balcony',
                        'booking_check_in'  => $check_in,
                        'booking_check_out' => $check_out,
                        'booking_nights'    => $session_data['nights'] ?? 1,
                        'booking_adults'    => $session_data['adults'] ?? 2,
                        'booking_children'  => $session_data['children'] ?? 0,
                    ] );
                }
            } catch ( \Throwable $e ) {
                // Ignore cart sync errors
            }
        }

        // Determine destination confirmation URL
        $booking_page_url = get_option( 'svago_booking_page_url', '' );
        $confirmation_url = $booking_page_url ? add_query_arg( [ 'step' => 'confirmation', 'order_id' => $order_id ], $booking_page_url ) : '';

        wp_send_json_success( [
            'order_id'         => $order_id,
            'checkout_url'     => $checkout_url,
            'redirect_url'     => $checkout_url,
            'amount_in_paise'  => (int) round( $total_amount * 100 ),
            'amount_formatted' => number_format( $total_amount, 2 ),
            'currency'         => $currency,
            'hotel_name'       => $hotel_name,
            'guest_name'       => $guest['full_name'] ?? ( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) ),
            'guest_email'      => $guest['email'] ?? '',
            'guest_phone'      => $guest['phone'] ?? '',
            'confirmation_url' => $confirmation_url,
        ] );
    }

    /**
     * AJAX endpoint: Verify Razorpay payment and confirm booking (Task 4.3)
     */
    public function ajax_verify_payment(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $order_id            = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
        $razorpay_payment_id = isset( $_POST['razorpay_payment_id'] ) ? sanitize_text_field( $_POST['razorpay_payment_id'] ) : '';
        $razorpay_order_id   = isset( $_POST['razorpay_order_id'] ) ? sanitize_text_field( $_POST['razorpay_order_id'] ) : '';
        $razorpay_signature  = isset( $_POST['razorpay_signature'] ) ? sanitize_text_field( $_POST['razorpay_signature'] ) : '';

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order reference.', 'svago' ) ] );
            return;
        }

        // Idempotency Check (Req #10 & Task 4.3): If already paid/processing, return success immediately
        $already_pushed = get_post_meta( $order_id, '_booking_ota_pushed', true );
        if ( 'yes' === $already_pushed ) {
            $booking_page_url = get_option( 'svago_booking_page_url', '' );
            $conf_url         = $booking_page_url ? add_query_arg( [ 'step' => 'confirmation', 'order_id' => $order_id ], $booking_page_url ) : '';
            wp_send_json_success( [
                'message'      => __( 'Payment already captured.', 'svago' ),
                'order_id'     => $order_id,
                'redirect_url' => $conf_url,
            ] );
            return;
        }

        // Signature verification (if secret configured)
        $key_secret = get_option( 'svago_razorpay_key_secret', '' );
        if ( ! empty( $key_secret ) && ! empty( $razorpay_order_id ) && ! empty( $razorpay_signature ) ) {
            $expected_signature = hash_hmac( 'sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret );
            if ( ! hash_equals( $expected_signature, $razorpay_signature ) ) {
                Logger::get_instance()->log(
                    'error',
                    sprintf( 'Razorpay signature mismatch for order #%d', $order_id ),
                    [ 'payment_id' => $razorpay_payment_id ]
                );
                wp_send_json_error( [ 'message' => __( 'Payment verification failed. Invalid transaction signature.', 'svago' ) ] );
                return;
            }
        }

        // Complete the payment on WooCommerce Order
        if ( function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->payment_complete( $razorpay_payment_id );
                $order->add_order_note( sprintf( __( 'Razorpay payment captured successfully. Payment ID: %s', 'svago' ), $razorpay_payment_id ) );
                $order->save();
            }
        } else {
            // Standalone fallback
            update_post_meta( $order_id, '_payment_method', 'razorpay' );
            update_post_meta( $order_id, '_transaction_id', $razorpay_payment_id );
            wp_update_post( [
                'ID'          => $order_id,
                'post_status' => 'wc-processing',
            ] );
        }

        // Push booking to OTA (Task 4.5)
        $this->push_booking_to_ota( $order_id );

        // Send confirmation email copy to hotel reservations (Task 4.7)
        $this->send_hotel_notification_email( $order_id );

        // Clear booking session data
        Booking_Flow::clear_session_data();

        // Build redirect URL
        $booking_page_url = get_option( 'svago_booking_page_url', '' );
        $redirect_url     = $booking_page_url ? add_query_arg( [ 'step' => 'confirmation', 'order_id' => $order_id ], $booking_page_url ) : '';

        wp_send_json_success( [
            'message'      => __( 'Payment confirmed successfully.', 'svago' ),
            'order_id'     => $order_id,
            'redirect_url' => $redirect_url,
        ] );
        return;
    }

    /**
     * Webhook Endpoint: Handle Razorpay Webhooks idempotently (Task 7.3)
     */
    public function handle_razorpay_webhook( ?string $raw_body_override = null ): void {
        $raw_body  = null !== $raw_body_override ? $raw_body_override : file_get_contents( 'php://input' );
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ( $_SERVER['HTTP_X_SIGNATURE'] ?? '' );
        $secret    = get_option( 'svago_razorpay_webhook_secret', '' );

        // If webhook secret configured, strictly verify signature
        if ( ! empty( $secret ) ) {
            if ( empty( $signature ) ) {
                Logger::get_instance()->log( 'error', 'Razorpay Webhook: Missing X-Razorpay-Signature header.' );
                wp_send_json_error( [ 'message' => 'Signature missing' ] );
                return;
            }
            $expected = hash_hmac( 'sha256', $raw_body, $secret );
            if ( ! hash_equals( $expected, $signature ) ) {
                Logger::get_instance()->log( 'error', 'Razorpay Webhook: Signature verification failed.' );
                wp_send_json_error( [ 'message' => 'Invalid signature' ] );
                return;
            }
        }

        $payload = json_decode( $raw_body, true );
        if ( empty( $payload ) || ! is_array( $payload ) ) {
            $payload = $_POST;
        }

        $event          = $payload['event'] ?? '';
        $payment_entity = $payload['payload']['payment']['entity'] ?? [];
        $order_id       = (int) ( $payment_entity['notes']['order_id'] ?? ( $payload['order_id'] ?? 0 ) );
        $payment_id     = sanitize_text_field( $payment_entity['id'] ?? ( $payload['payment_id'] ?? '' ) );

        if ( ! $order_id ) {
            wp_send_json_success( [ 'status' => 'ignored_no_order' ] );
            return;
        }

        // Idempotency check: Confirm only once (Task 7.3)
        $already_pushed = get_post_meta( $order_id, '_booking_ota_pushed', true );
        if ( 'yes' === $already_pushed ) {
            Logger::get_instance()->log(
                'info',
                sprintf( 'Razorpay Webhook: Duplicate callback ignored for Order #%d (already confirmed).', $order_id ),
                [ 'event' => $event, 'payment_id' => $payment_id ]
            );
            wp_send_json_success( [ 'status' => 'already_processed', 'order_id' => $order_id ] );
            return;
        }

        // Mark payment complete on order
        if ( function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->payment_complete( $payment_id );
                $order->add_order_note( sprintf( __( 'Payment confirmed via Razorpay Webhook (%s). Payment ID: %s', 'svago' ), $event, $payment_id ) );
            }
        } else {
            update_post_meta( $order_id, '_payment_status', 'Paid' );
            update_post_meta( $order_id, '_transaction_id', $payment_id );
            wp_update_post( [ 'ID' => $order_id, 'post_status' => 'wc-processing' ] );
        }

        // Push booking to OTA (Task 4.5 & Task 7.3)
        $this->push_booking_to_ota( $order_id );

        // Trigger Notifications
        if ( class_exists( 'SVAGO\Notifications' ) ) {
            Notifications::get_instance()->send_whatsapp( $order_id, 'booking_confirmed' );
        }
        $this->send_hotel_notification_email( $order_id );

        wp_send_json_success( [ 'status' => 'processed', 'order_id' => $order_id ] );
    }

    /**
     * Handle payment completed hook from WooCommerce (Task 4.3 & 4.5)
     *
     * @param int $order_id
     */
    public function handle_payment_complete( $order_id ): void {
        if ( ! $order_id ) {
            return;
        }

        // Push to OTA if not already pushed (idempotent)
        $this->push_booking_to_ota( (int) $order_id );

        // Send confirmation copy to hotel
        $this->send_hotel_notification_email( (int) $order_id );
    }

    /**
     * Push confirmed booking to OTA Channel Manager (Task 4.5 & Task 4.6)
     *
     * @param int $order_id
     * @return bool True if pushed successfully, false otherwise
     */
    public function push_booking_to_ota( int $order_id ): bool {
        // Idempotency check: Confirm only once
        $pushed = get_post_meta( $order_id, '_booking_ota_pushed', true );
        if ( 'yes' === $pushed ) {
            return true;
        }

        $booking_data = $this->get_order_booking_data( $order_id );
        if ( ! $booking_data ) {
            Logger::get_instance()->log(
                'error',
                sprintf( 'Cannot push order #%d to OTA: missing booking metadata', $order_id )
            );
            return false;
        }

        $payload = [
            'order_id'     => $order_id,
            'room_id'      => $booking_data['room_id'],
            'ota_room_id'  => $booking_data['room_id'],
            'rate_id'      => $booking_data['rate_id'],
            'check_in'     => $booking_data['check_in'],
            'check_out'    => $booking_data['check_out'],
            'nights'       => $booking_data['nights'],
            'adults'       => $booking_data['adults'],
            'children'     => $booking_data['children'],
            'guest_name'   => $booking_data['guest_name'],
            'guest_email'  => $booking_data['guest_email'],
            'guest_phone'  => $booking_data['guest_phone'],
            'total_amount' => $booking_data['total_amount'],
            'created_at'   => current_time( 'mysql' ),
        ];

        try {
            $provider = Provider_Factory::get();
            $result   = $provider->push_booking( $payload );

            if ( ! empty( $result['success'] ) ) {
                $ota_id = $result['ota_booking_id'] ?? ( 'OTA-' . $order_id );
                update_post_meta( $order_id, '_booking_ota_pushed', 'yes' );
                update_post_meta( $order_id, '_booking_ota_id', $ota_id );

                Logger::get_instance()->log(
                    'info',
                    sprintf( 'Booking order #%d successfully pushed to OTA. OTA Booking ID: %s', $order_id, $ota_id ),
                    [ 'order_id' => $order_id, 'ota_booking_id' => $ota_id ]
                );

                return true;
            } else {
                throw new \RuntimeException( $result['message'] ?? __( 'Unknown OTA provider rejection.', 'svago' ) );
            }
        } catch ( \Throwable $e ) {
            // Task 4.6: If push fails, log error and schedule retry via WP-Cron (3 attempts, 5 min apart)
            update_post_meta( $order_id, '_booking_ota_pushed', 'failed' );

            Logger::get_instance()->log(
                'warning',
                sprintf( 'OTA push failed for Order #%d: %s. Scheduling WP-Cron retry.', $order_id, $e->getMessage() ),
                [ 'order_id' => $order_id, 'error' => $e->getMessage() ]
            );

            // Schedule single retry event if not already queued
            if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( 'svago_retry_ota_push', [ $order_id ] ) ) {
                wp_schedule_single_event( time() + 300, 'svago_retry_ota_push', [ $order_id ] );
            }

            return false;
        }
    }

    /**
     * WP-Cron callback: Retry failed OTA push up to 3 times (Task 4.6)
     *
     * @param int $order_id
     */
    public function cron_retry_ota_push( int $order_id ): void {
        // If already succeeded, exit
        $pushed = get_post_meta( $order_id, '_booking_ota_pushed', true );
        if ( 'yes' === $pushed ) {
            return;
        }

        $attempts = (int) get_post_meta( $order_id, '_booking_ota_retry_count', true );
        $attempts++;
        update_post_meta( $order_id, '_booking_ota_retry_count', $attempts );

        Logger::get_instance()->log(
            'notice',
            sprintf( 'Executing WP-Cron OTA push retry attempt #%d for Order #%d', $attempts, $order_id )
        );

        $success = $this->push_booking_to_ota( $order_id );

        if ( ! $success ) {
            if ( $attempts < 3 ) {
                // Schedule next attempt 5 minutes later
                if ( function_exists( 'wp_schedule_single_event' ) ) {
                    wp_schedule_single_event( time() + 300, 'svago_retry_ota_push', [ $order_id ] );
                }
            } else {
                // All 3 attempts exhausted: Log critical alert for admin
                Logger::get_instance()->log(
                    'error',
                    sprintf( 'CRITICAL: OTA push for Order #%d permanently failed after 3 automated attempts. Manual sync required.', $order_id ),
                    [ 'order_id' => $order_id, 'attempts' => $attempts ]
                );
            }
        }
    }

    /**
     * Retrieve and consolidate booking metadata for an order
     *
     * @param int $order_id
     * @return array|null
     */
    public function get_order_booking_data( int $order_id ): ?array {
        $room_id = get_post_meta( $order_id, '_booking_room_id', true );
        if ( empty( $room_id ) ) {
            return null;
        }

        $first_name = get_post_meta( $order_id, '_billing_first_name', true );
        $last_name  = get_post_meta( $order_id, '_billing_last_name', true );
        $guest_name = trim( $first_name . ' ' . $last_name );
        if ( empty( $guest_name ) ) {
            $guest_name = get_post_meta( $order_id, '_booking_guest_name', true );
        }

        $guest_phone = get_post_meta( $order_id, '_billing_phone', true );
        if ( empty( $guest_phone ) ) {
            $guest_phone = get_post_meta( $order_id, '_booking_guest_phone', true );
        }

        $guest_email = get_post_meta( $order_id, '_billing_email', true );
        if ( empty( $guest_email ) ) {
            $guest_email = get_post_meta( $order_id, '_booking_guest_email', true );
        }

        $total = get_post_meta( $order_id, '_booking_total_amount', true );
        if ( empty( $total ) ) {
            $total = get_post_meta( $order_id, '_order_total', true );
        }

        return [
            'order_id'         => $order_id,
            'room_id'          => $room_id,
            'room_name'        => get_post_meta( $order_id, '_booking_room_name', true ) ?: __( 'Hotel Room', 'svago' ),
            'rate_id'          => get_post_meta( $order_id, '_booking_rate_id', true ) ?: 'standard',
            'rate_name'        => get_post_meta( $order_id, '_booking_rate_name', true ) ?: __( 'Standard Rate', 'svago' ),
            'check_in'         => get_post_meta( $order_id, '_booking_check_in', true ),
            'check_out'        => get_post_meta( $order_id, '_booking_check_out', true ),
            'nights'           => (int) get_post_meta( $order_id, '_booking_nights', true ) ?: 1,
            'adults'           => (int) get_post_meta( $order_id, '_booking_adults', true ) ?: 2,
            'children'         => (int) get_post_meta( $order_id, '_booking_children', true ) ?: 0,
            'guest_name'       => $guest_name ?: __( 'Guest', 'svago' ),
            'guest_first_name' => $first_name,
            'guest_last_name'  => $last_name,
            'guest_email'      => $guest_email,
            'guest_phone'      => $guest_phone,
            'guest_eta'        => get_post_meta( $order_id, '_booking_guest_eta', true ),
            'special_requests' => get_post_meta( $order_id, '_booking_special_requests', true ),
            'total_amount'     => (float) $total,
            'price_per_night'  => (float) get_post_meta( $order_id, '_booking_price_per_night', true ),
            'ota_pushed'       => get_post_meta( $order_id, '_booking_ota_pushed', true ),
            'ota_booking_id'   => get_post_meta( $order_id, '_booking_ota_id', true ),
            'payment_method'   => get_post_meta( $order_id, '_payment_method_title', true ) ?: 'Razorpay',
            'payment_status'   => 'Paid',
        ];
    }

    /**
     * Add hotel reservations email to WooCommerce customer receipt (Task 4.7)
     *
     * @param string $recipient
     * @param object $order
     * @return string
     */
    public function add_hotel_email_recipient( string $recipient, $order ): string {
        $hotel_email = get_option( 'svago_hotel_email', '' );
        if ( ! empty( $hotel_email ) && is_email( $hotel_email ) ) {
            $recipients = array_map( 'trim', explode( ',', $recipient ) );
            if ( ! in_array( $hotel_email, $recipients, true ) ) {
                $recipients[] = $hotel_email;
            }
            return implode( ', ', array_filter( $recipients ) );
        }
        return $recipient;
    }

    /**
     * Send direct confirmation copy to hotel reservations inbox (Task 4.7)
     *
     * @param int $order_id
     * @return bool
     */
    public function send_hotel_notification_email( int $order_id ): bool {
        $hotel_email = get_option( 'svago_hotel_email', '' );
        if ( empty( $hotel_email ) || ! is_email( $hotel_email ) ) {
            return false;
        }

        $booking = $this->get_order_booking_data( $order_id );
        if ( ! $booking ) {
            return false;
        }

        $subject = sprintf( '[%s] New Confirmed Booking #%d - %s (%s)', get_bloginfo( 'name' ), $order_id, $booking['guest_name'], $booking['room_name'] );

        $message  = "A new confirmed booking has been placed.\n\n";
        $message .= "Reservation Reference: #" . $order_id . "\n";
        if ( ! empty( $booking['ota_booking_id'] ) ) {
            $message .= "OTA Booking Reference: " . $booking['ota_booking_id'] . "\n";
        }
        $message .= "Guest Name: " . $booking['guest_name'] . "\n";
        $message .= "Email: " . $booking['guest_email'] . "\n";
        $message .= "Phone: " . $booking['guest_phone'] . "\n";
        $message .= "Room: " . $booking['room_name'] . " (" . $booking['rate_name'] . ")\n";
        $message .= "Stay: " . $booking['check_in'] . " to " . $booking['check_out'] . " (" . $booking['nights'] . " nights)\n";
        $message .= "Guests: " . $booking['adults'] . " Adults, " . $booking['children'] . " Children\n";
        if ( ! empty( $booking['guest_eta'] ) ) {
            $message .= "Estimated Arrival: " . $booking['guest_eta'] . "\n";
        }
        if ( ! empty( $booking['special_requests'] ) ) {
            $message .= "Special Requests: " . $booking['special_requests'] . "\n";
        }
        $message .= "Total Amount: ₹" . number_format( $booking['total_amount'], 2 ) . " (Paid via Razorpay)\n";

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        return wp_mail( $hotel_email, $subject, $message, $headers );
    }

    /**
     * Find WooCommerce product ID by OTA Room ID
     *
     * @param string $room_id
     * @return int|null
     */
    private function find_product_by_room_id( string $room_id ): ?int {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return null;
        }

        $products = wc_get_products( [
            'limit'      => 1,
            'meta_key'   => '_ota_room_id',
            'meta_value' => $room_id,
            'return'     => 'ids',
        ] );

        if ( empty( $products[0] ) ) {
            $products = wc_get_products( [
                'limit'      => 1,
                'meta_key'   => '_svago_ota_room_id',
                'meta_value' => $room_id,
                'return'     => 'ids',
            ] );
        }

        return ! empty( $products[0] ) ? (int) $products[0] : null;
    }

    /**
     * Register custom columns on WooCommerce Orders list (Task 6.3)
     *
     * @param array $columns
     * @return array
     */
    public function register_order_columns( array $columns ): array {
        $new_columns = [];

        foreach ( $columns as $key => $column ) {
            $new_columns[ $key ] = $column;
            if ( 'order_number' === $key ) {
                $new_columns['svago_stay']       = __( 'Stay Dates', 'svago' );
                $new_columns['svago_room']       = __( 'Room Booked', 'svago' );
                $new_columns['svago_source']     = __( 'Source', 'svago' );
                $new_columns['svago_ota_status'] = __( 'OTA Sync', 'svago' );
            }
        }

        if ( ! isset( $new_columns['svago_stay'] ) ) {
            $new_columns['svago_stay']       = __( 'Stay Dates', 'svago' );
            $new_columns['svago_room']       = __( 'Room Booked', 'svago' );
            $new_columns['svago_source']     = __( 'Source', 'svago' );
            $new_columns['svago_ota_status'] = __( 'OTA Sync', 'svago' );
        }

        return $new_columns;
    }

    /**
     * Render custom column content on CPT order list (Task 6.3)
     *
     * @param string $column
     * @param int    $post_id
     */
    public function render_order_columns( string $column, int $post_id ): void {
        $this->output_column_content( $column, $post_id );
    }

    /**
     * Render custom column content on HPOS order list (Task 6.3)
     *
     * @param string $column
     * @param object $order
     */
    public function render_order_columns_hpos( string $column, $order ): void {
        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : ( $order->ID ?? 0 );
        $this->output_column_content( $column, (int) $order_id );
    }

    /**
     * Render HTML content for custom order columns
     *
     * @param string $column
     * @param int    $order_id
     */
    private function output_column_content( string $column, int $order_id ): void {
        $room_id = get_post_meta( $order_id, '_booking_room_id', true );
        if ( empty( $room_id ) ) {
            echo '<span style="color:#999;">—</span>';
            return;
        }

        switch ( $column ) {
            case 'svago_stay':
                $in     = get_post_meta( $order_id, '_booking_check_in', true );
                $out    = get_post_meta( $order_id, '_booking_check_out', true );
                $nights = (int) get_post_meta( $order_id, '_booking_nights', true ) ?: 1;
                if ( $in && $out ) {
                    echo '<strong>' . esc_html( $in ) . '</strong> &rarr; <strong>' . esc_html( $out ) . '</strong>';
                    echo '<div style="font-size:11px; color:#64748b;">' . esc_html( sprintf( _n( '%d Night', '%d Nights', $nights, 'svago' ), $nights ) ) . '</div>';
                } else {
                    echo '—';
                }
                break;

            case 'svago_room':
                $room_name = get_post_meta( $order_id, '_booking_room_name', true ) ?: __( 'Hotel Room', 'svago' );
                $rate_name = get_post_meta( $order_id, '_booking_rate_name', true );
                echo '<strong>' . esc_html( $room_name ) . '</strong>';
                if ( $rate_name ) {
                    echo '<div style="font-size:11px; color:#64748b;">' . esc_html( $rate_name ) . '</div>';
                }
                break;

            case 'svago_source':
                $source = get_post_meta( $order_id, '_booking_source', true ) ?: 'online';
                if ( 'onsite_walkin' === $source ) {
                    echo '<span style="background:#e0f2fe; color:#0369a1; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">🚶 ' . esc_html__( 'Walk-in', 'svago' ) . '</span>';
                } elseif ( 'onsite_phone' === $source ) {
                    echo '<span style="background:#f3e8ff; color:#7e22ce; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">📞 ' . esc_html__( 'Phone', 'svago' ) . '</span>';
                } else {
                    echo '<span style="background:#f1f5f9; color:#334155; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">🌐 ' . esc_html__( 'Online', 'svago' ) . '</span>';
                }
                break;

            case 'svago_ota_status':
                $pushed = get_post_meta( $order_id, '_booking_ota_pushed', true );
                $ota_id = get_post_meta( $order_id, '_booking_ota_id', true );
                $cancelled = get_post_meta( $order_id, '_booking_ota_cancelled', true );

                if ( 'yes' === $cancelled ) {
                    echo '<span style="background:#fee2e2; color:#b91c1c; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">' . esc_html__( 'Cancelled', 'svago' ) . '</span>';
                } elseif ( 'yes' === $pushed ) {
                    echo '<span style="background:#dcfce7; color:#15803d; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;" title="' . esc_attr( $ota_id ) . '">✓ ' . esc_html__( 'Pushed', 'svago' ) . '</span>';
                    if ( $ota_id ) {
                        echo '<div style="font-size:10px; color:#64748b; font-family:monospace;">' . esc_html( $ota_id ) . '</div>';
                    }
                } elseif ( 'failed' === $pushed ) {
                    echo '<span style="background:#fef2f2; color:#dc2626; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">✗ ' . esc_html__( 'Push Failed', 'svago' ) . '</span>';
                } else {
                    echo '<span style="background:#fef3c7; color:#b45309; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">⏳ ' . esc_html__( 'Pending', 'svago' ) . '</span>';
                }
                break;
        }
    }

    /**
     * Add filter dropdowns on Orders screen (Task 6.3)
     *
     * @param string $post_type
     */
    public function render_order_filters( string $post_type ): void {
        if ( 'shop_order' !== $post_type ) {
            return;
        }

        $source_filter = isset( $_GET['svago_source_filter'] ) ? sanitize_text_field( $_GET['svago_source_filter'] ) : '';
        $ota_filter    = isset( $_GET['svago_ota_filter'] ) ? sanitize_text_field( $_GET['svago_ota_filter'] ) : '';
        ?>
        <select name="svago_source_filter" id="svago_source_filter">
            <option value=""><?php esc_html_e( 'All Booking Sources', 'svago' ); ?></option>
            <option value="online" <?php selected( $source_filter, 'online' ); ?>><?php esc_html_e( 'Online Bookings', 'svago' ); ?></option>
            <option value="onsite_walkin" <?php selected( $source_filter, 'onsite_walkin' ); ?>><?php esc_html_e( 'Walk-in Guests', 'svago' ); ?></option>
            <option value="onsite_phone" <?php selected( $source_filter, 'onsite_phone' ); ?>><?php esc_html_e( 'Phone Reservations', 'svago' ); ?></option>
        </select>

        <select name="svago_ota_filter" id="svago_ota_filter">
            <option value=""><?php esc_html_e( 'All OTA Sync States', 'svago' ); ?></option>
            <option value="yes" <?php selected( $ota_filter, 'yes' ); ?>><?php esc_html_e( 'Pushed to OTA', 'svago' ); ?></option>
            <option value="no" <?php selected( $ota_filter, 'no' ); ?>><?php esc_html_e( 'Push Pending', 'svago' ); ?></option>
            <option value="failed" <?php selected( $ota_filter, 'failed' ); ?>><?php esc_html_e( 'Push Failed', 'svago' ); ?></option>
        </select>
        <?php
    }

    /**
     * Modify WP_Query based on custom order filters (Task 6.3)
     *
     * @param \WP_Query $query
     */
    public function filter_orders_query( $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( 'shop_order' !== $post_type ) {
            return;
        }

        $meta_query = $query->get( 'meta_query' ) ?: [];

        if ( ! empty( $_GET['svago_source_filter'] ) ) {
            $meta_query[] = [
                'key'   => '_booking_source',
                'value' => sanitize_text_field( $_GET['svago_source_filter'] ),
            ];
        }

        if ( ! empty( $_GET['svago_ota_filter'] ) ) {
            $meta_query[] = [
                'key'   => '_booking_ota_pushed',
                'value' => sanitize_text_field( $_GET['svago_ota_filter'] ),
            ];
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Add custom order actions to Order edit screen (Task 6.4)
     *
     * @param array $actions
     * @return array
     */
    public function add_order_actions( array $actions ): array {
        $actions['svago_resend_confirmation'] = __( 'Resend Hotel Confirmation (WhatsApp & Email)', 'svago' );
        return $actions;
    }

    /**
     * Action handler: Resend confirmation (Task 6.4)
     *
     * @param object $order
     */
    public function action_resend_confirmation( $order ): void {
        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : ( $order->ID ?? 0 );
        if ( ! $order_id ) {
            return;
        }

        // Resend WhatsApp confirmation (ignore marketing consent restriction for explicit staff trigger)
        if ( class_exists( 'SVAGO\Notifications' ) ) {
            Notifications::get_instance()->send_whatsapp( (int) $order_id, 'booking_confirmed', true );
        }

        // Resend direct copy to hotel reservations inbox
        $this->send_hotel_notification_email( (int) $order_id );

        // Add note to order
        $staff_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
        if ( method_exists( $order, 'add_order_note' ) ) {
            $order->add_order_note( sprintf( __( 'Staff User #%d manually resent booking confirmation (WhatsApp & Email).', 'svago' ), $staff_user_id ) );
        }

        Logger::get_instance()->log(
            'info',
            sprintf( 'Staff User #%d manually resent booking confirmation for Order #%d.', $staff_user_id, $order_id ),
            [ 'order_id' => $order_id, 'user_id' => $staff_user_id ]
        );
    }

    /**
     * Handle Manual Cancellation with automated OTA Push (Task 6.5)
     *
     * @param int $order_id
     */
    public function handle_order_cancelled( $order_id ): void {
        $order_id = (int) $order_id;
        if ( ! $order_id ) {
            return;
        }

        $room_id = get_post_meta( $order_id, '_booking_room_id', true );
        if ( empty( $room_id ) ) {
            return;
        }

        // Idempotency: only push cancellation once
        $already_cancelled = get_post_meta( $order_id, '_booking_ota_cancelled', true );
        if ( 'yes' === $already_cancelled ) {
            return;
        }

        $booking_data = $this->get_order_booking_data( $order_id );
        if ( ! $booking_data ) {
            return;
        }

        $ota_booking_id = $booking_data['ota_booking_id'] ?? get_post_meta( $order_id, '_booking_ota_id', true );

        try {
            $provider = Provider_Factory::get();
            $cancel_payload = [
                'ota_booking_id' => $ota_booking_id ?: ( 'ORDER-' . $order_id ),
                'ota_room_id'    => $booking_data['room_id'],
                'check_in'       => $booking_data['check_in'],
                'check_out'      => $booking_data['check_out'],
                'order_id'       => $order_id,
                'reason'         => __( 'Cancelled by administrator', 'svago' ),
            ];

            $res = $provider->push_cancellation( $cancel_payload );
            update_post_meta( $order_id, '_booking_ota_cancelled', 'yes' );

            $staff_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
            Logger::get_instance()->log(
                'info',
                sprintf( 'Staff User #%d manually cancelled Order #%d. OTA cancellation pushed successfully.', $staff_user_id, $order_id ),
                [ 'order_id' => $order_id, 'ota_res' => $res, 'user_id' => $staff_user_id ]
            );
        } catch ( \Throwable $e ) {
            Logger::get_instance()->log(
                'error',
                sprintf( 'Failed to push cancellation for Order #%d to OTA: %s', $order_id, $e->getMessage() ),
                [ 'order_id' => $order_id, 'error' => $e->getMessage() ]
            );
        }
    }

    /**
     * Synchronize booking session data into WooCommerce Cart and Customer profile (Task Requirement 3 & 4)
     *
     * @param array $session_data
     * @return bool
     */
    public function sync_booking_to_cart( array $session_data ): bool {
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return false;
        }

        try {
            $room_id         = sanitize_text_field( $session_data['room_id'] ?? '' );
            $room_name       = sanitize_text_field( $session_data['room_name'] ?? __( 'Hotel Room', 'svago' ) );
            $room_type       = sanitize_text_field( $session_data['room_type'] ?? 'Balcony' );
            $check_in        = sanitize_text_field( $session_data['check_in'] ?? '' );
            $check_out       = sanitize_text_field( $session_data['check_out'] ?? '' );
            $nights          = max( 1, (int) ( $session_data['nights'] ?? 1 ) );
            $adults          = max( 1, (int) ( $session_data['adults'] ?? 2 ) );
            $children        = max( 0, (int) ( $session_data['children'] ?? 0 ) );
            $total_amount    = (float) ( $session_data['total_amount'] ?? 0 );
            $guest           = $session_data['guest'] ?? [];

            if ( WC()->cart ) {
                WC()->cart->empty_cart();

                $product_id = $this->find_product_by_room_id( $room_id );
                if ( ! $product_id && class_exists( 'SVAGO\Product_Seeder' ) ) {
                    $seeder = new Product_Seeder();
                    $seeder->seed_products();
                    $product_id = $this->find_product_by_room_id( $room_id );
                }
                if ( ! $product_id ) {
                    $product_id = (int) apply_filters( 'svago_default_booking_product_id', 1, $room_id );
                }

                if ( $product_id ) {
                    $cart_item_data = [
                        'booking_room_id'   => $room_id,
                        'booking_room_name' => $room_name,
                        'booking_room_type' => $room_type,
                        'booking_check_in'  => $check_in,
                        'booking_check_out' => $check_out,
                        'booking_nights'    => $nights,
                        'booking_adults'    => $adults,
                        'booking_children'  => $children,
                        'booking_guest'     => $guest,
                        'custom_price'      => $total_amount,
                    ];

                    WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );
                }
            }

            // Pre-fill WooCommerce Customer Profile in Session
            if ( WC()->customer ) {
                $first_name = $guest['first_name'] ?? '';
                $last_name  = $guest['last_name'] ?? '';
                $email      = $guest['email'] ?? '';
                $phone      = $guest['phone'] ?? ( ( $guest['phone_country'] ?? '' ) . ' ' . ( $guest['phone_number'] ?? '' ) );

                if ( ! empty( $first_name ) ) {
                    WC()->customer->set_billing_first_name( $first_name );
                    WC()->customer->set_shipping_first_name( $first_name );
                }
                if ( ! empty( $last_name ) ) {
                    WC()->customer->set_billing_last_name( $last_name );
                    WC()->customer->set_shipping_last_name( $last_name );
                }
                if ( ! empty( $email ) ) {
                    WC()->customer->set_billing_email( $email );
                }
                if ( ! empty( $phone ) ) {
                    WC()->customer->set_billing_phone( trim( (string) $phone ) );
                }
                WC()->customer->save();
            }

            return true;
        } catch ( \Throwable $e ) {
            Logger::get_instance()->log( 'warning', 'Failed syncing booking to cart: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Pre-populate WooCommerce Checkout billing and shipping fields with guest information (Task Requirement 4)
     *
     * @param mixed  $value Existing field value
     * @param string $input Field name
     * @return mixed
     */
    public function prefill_checkout_field( $value, $input ) {
        if ( ! empty( $value ) ) {
            return $value;
        }

        $session_data = Booking_Flow::get_session_data();
        $guest        = $session_data['guest'] ?? [];
        if ( empty( $guest ) ) {
            return $value;
        }

        switch ( $input ) {
            case 'billing_first_name':
            case 'shipping_first_name':
                return $guest['first_name'] ?? $value;

            case 'billing_last_name':
            case 'shipping_last_name':
                return $guest['last_name'] ?? $value;

            case 'billing_email':
                return $guest['email'] ?? $value;

            case 'billing_phone':
                $phone = $guest['phone'] ?? ( ( $guest['phone_country'] ?? '' ) . ' ' . ( $guest['phone_number'] ?? '' ) );
                return ! empty( $phone ) ? trim( (string) $phone ) : $value;

            case 'order_comments':
                $notes = [];
                if ( ! empty( $guest['eta'] ) ) {
                    $notes[] = sprintf( __( 'Estimated Arrival Time (ETA): %s', 'svago' ), $guest['eta'] );
                }
                if ( ! empty( $guest['special_requests'] ) ) {
                    $notes[] = sprintf( __( 'Special Requests: %s', 'svago' ), $guest['special_requests'] );
                }
                if ( ! empty( $session_data['room_type'] ) ) {
                    $notes[] = sprintf( __( 'Selected Room Type: %s', 'svago' ), $session_data['room_type'] );
                }
                return ! empty( $notes ) ? implode( "\n", $notes ) : $value;
        }

        return $value;
    }

    /**
     * Set dynamic calculated price on booking cart items in WooCommerce
     *
     * @param \WC_Cart $cart
     */
    public function set_booking_cart_item_price( $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }
        if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['custom_price'] ) && is_numeric( $cart_item['custom_price'] ) ) {
                $cart_item['data']->set_price( (float) $cart_item['custom_price'] );
            }
        }
    }

    /**
     * Display booking information (Room, Type, Dates, Guests, Nights) in WooCommerce Order Summary (Task Requirement 3)
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function render_booking_item_data( array $item_data, array $cart_item ): array {
        if ( ! empty( $cart_item['booking_room_type'] ) ) {
            $item_data[] = [
                'name'  => __( 'Room Type', 'svago' ),
                'value' => esc_html( $cart_item['booking_room_type'] ),
            ];
        }
        if ( ! empty( $cart_item['booking_check_in'] ) ) {
            $in_fmt = function_exists( 'date_i18n' ) ? date_i18n( 'D, M j, Y', strtotime( $cart_item['booking_check_in'] ) ) : date( 'D, M j, Y', strtotime( $cart_item['booking_check_in'] ) );
            $item_data[] = [
                'name'  => __( 'Check-in', 'svago' ),
                'value' => esc_html( $in_fmt . ' (From 2:00 PM)' ),
            ];
        }
        if ( ! empty( $cart_item['booking_check_out'] ) ) {
            $out_fmt = function_exists( 'date_i18n' ) ? date_i18n( 'D, M j, Y', strtotime( $cart_item['booking_check_out'] ) ) : date( 'D, M j, Y', strtotime( $cart_item['booking_check_out'] ) );
            $item_data[] = [
                'name'  => __( 'Check-out', 'svago' ),
                'value' => esc_html( $out_fmt . ' (Until 11:00 AM)' ),
            ];
        }
        if ( ! empty( $cart_item['booking_nights'] ) ) {
            $nights = (int) $cart_item['booking_nights'];
            $item_data[] = [
                'name'  => __( 'Duration', 'svago' ),
                'value' => esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'svago' ), $nights ) ),
            ];
        }
        if ( ! empty( $cart_item['booking_adults'] ) ) {
            $adults   = (int) $cart_item['booking_adults'];
            $children = (int) ( $cart_item['booking_children'] ?? 0 );
            $g_text   = sprintf( _n( '%d Adult', '%d Adults', $adults, 'svago' ), $adults );
            if ( $children > 0 ) {
                $g_text .= ', ' . sprintf( _n( '%d Child', '%d Children', $children, 'svago' ), $children );
            }
            $item_data[] = [
                'name'  => __( 'Guests', 'svago' ),
                'value' => esc_html( $g_text ),
            ];
        }

        return $item_data;
    }

    /**
     * Render Cancellation & Hotel Policies box on the WooCommerce Checkout page (Task Requirement 3)
     */
    public function render_checkout_hotel_policies(): void {
        ?>
        <div class="svago-wc-checkout-policies" style="margin: 24px 0 20px 0; padding: 20px 22px; background: #fbf9f4; border: 1px solid #e4e0d7; border-radius: 8px; font-size: 0.875rem; color: #18181b;">
            <h4 style="margin: 0 0 12px 0; font-size: 0.9375rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #18181b;">
                📋 <?php esc_html_e( 'Cancellation & Hotel Policies', 'svago' ); ?>
            </h4>
            <ul style="margin: 0; padding-left: 18px; line-height: 1.6; color: #4b5563;">
                <li><strong><?php esc_html_e( 'Free Cancellation:', 'svago' ); ?></strong> <?php esc_html_e( 'Cancel free of charge up to 48 hours prior to check-in (2:00 PM).', 'svago' ); ?></li>
                <li><strong><?php esc_html_e( 'Late Cancellation / No-Show:', 'svago' ); ?></strong> <?php esc_html_e( 'Cancellations within 48 hours or failure to arrive will be charged the first night.', 'svago' ); ?></li>
                <li><strong><?php esc_html_e( 'Check-in / Check-out:', 'svago' ); ?></strong> <?php esc_html_e( 'Check-in begins at 2:00 PM. Check-out time is 11:00 AM.', 'svago' ); ?></li>
                <li><strong><?php esc_html_e( 'Government Photo ID:', 'svago' ); ?></strong> <?php esc_html_e( 'Valid government-issued photo ID (Passport, Aadhar, Driving License) is required for all adult guests.', 'svago' ); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Save booking metadata to WooCommerce order upon checkout completion
     *
     * @param int   $order_id
     * @param array $data
     */
    public function save_checkout_booking_meta( $order_id, $data = null ): void {
        $session_data = Booking_Flow::get_session_data();
        if ( empty( $session_data ) ) {
            return;
        }

        $room_id         = sanitize_text_field( $session_data['room_id'] ?? '' );
        $room_name       = sanitize_text_field( $session_data['room_name'] ?? '' );
        $room_type       = sanitize_text_field( $session_data['room_type'] ?? 'Balcony' );
        $check_in        = sanitize_text_field( $session_data['check_in'] ?? '' );
        $check_out       = sanitize_text_field( $session_data['check_out'] ?? '' );
        $nights          = max( 1, (int) ( $session_data['nights'] ?? 1 ) );
        $adults          = max( 1, (int) ( $session_data['adults'] ?? 2 ) );
        $children        = max( 0, (int) ( $session_data['children'] ?? 0 ) );
        $total_amount    = (float) ( $session_data['total_amount'] ?? 0 );
        $guest           = $session_data['guest'] ?? [];

        update_post_meta( $order_id, '_booking_room_id', $room_id );
        update_post_meta( $order_id, '_booking_room_name', $room_name );
        update_post_meta( $order_id, '_booking_room_type', $room_type );
        update_post_meta( $order_id, '_booking_check_in', $check_in );
        update_post_meta( $order_id, '_booking_check_out', $check_out );
        update_post_meta( $order_id, '_booking_nights', $nights );
        update_post_meta( $order_id, '_booking_adults', $adults );
        update_post_meta( $order_id, '_booking_children', $children );
        update_post_meta( $order_id, '_booking_total_amount', $total_amount );
        update_post_meta( $order_id, '_booking_source', 'online' );
        update_post_meta( $order_id, '_booking_ota_pushed', 'no' );
        update_post_meta( $order_id, '_booking_policy_agreed', 'yes' );

        if ( ! empty( $guest['eta'] ) ) {
            update_post_meta( $order_id, '_booking_guest_eta', sanitize_text_field( $guest['eta'] ) );
        }
        if ( ! empty( $guest['special_requests'] ) ) {
            update_post_meta( $order_id, '_booking_special_requests', sanitize_textarea_field( $guest['special_requests'] ) );
        }
        if ( isset( $guest['consent'] ) ) {
            update_post_meta( $order_id, '_booking_consent', ! empty( $guest['consent'] ) ? 'yes' : 'no' );
        }
    }

    /**
     * Transfer custom cart item booking metadata onto WooCommerce Order Line Item
     *
     * @param \WC_Order_Item_Product $item
     * @param string                 $cart_item_key
     * @param array                  $values
     * @param \WC_Order              $order
     */
    public function save_line_item_booking_meta( $item, $cart_item_key, $values, $order ): void {
        if ( ! empty( $values['booking_room_type'] ) ) {
            $item->add_meta_data( __( 'Room Type', 'svago' ), $values['booking_room_type'], true );
        }
        if ( ! empty( $values['booking_check_in'] ) ) {
            $item->add_meta_data( __( 'Check-in', 'svago' ), $values['booking_check_in'], true );
        }
        if ( ! empty( $values['booking_check_out'] ) ) {
            $item->add_meta_data( __( 'Check-out', 'svago' ), $values['booking_check_out'], true );
        }
        if ( ! empty( $values['booking_nights'] ) ) {
            $item->add_meta_data( __( 'Nights', 'svago' ), $values['booking_nights'], true );
        }
        if ( ! empty( $values['booking_adults'] ) ) {
            $item->add_meta_data( __( 'Adults', 'svago' ), $values['booking_adults'], true );
        }
        if ( isset( $values['booking_children'] ) ) {
            $item->add_meta_data( __( 'Children', 'svago' ), $values['booking_children'], true );
        }
    }

    /**
     * Skip WooCommerce Cart page entirely and redirect directly to Checkout or Booking flow (Requirement 3)
     */
    public function skip_cart_redirect_to_checkout(): void {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return;
        }

        if ( function_exists( 'is_cart' ) && is_cart() ) {
            // If cart is empty, redirect back to booking page or homepage
            if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
                $booking_page = get_option( 'svago_booking_page_url', '' );
                $redirect_url = ! empty( $booking_page ) ? $booking_page : home_url( '/' );
                wp_safe_redirect( $redirect_url );
                if ( ! defined( 'SVAGO_TESTING' ) ) {
                    exit;
                }
                return;
            }

            // If cart has items, skip cart and redirect directly to checkout
            $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
            wp_safe_redirect( $checkout_url );
            if ( ! defined( 'SVAGO_TESTING' ) ) {
                exit;
            }
            return;
        }
    }

    /**
     * Force any WooCommerce add-to-cart redirect to go straight to Checkout (Requirement 3)
     *
     * @param string $url
     * @return string
     */
    public function direct_checkout_redirect( $url ): string {
        return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $url;
    }
}


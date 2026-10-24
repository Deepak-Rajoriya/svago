<?php
/**
 * Test Suite: Guest Info Redesign & WooCommerce Checkout Integration
 * Verifies:
 * 1. UI Redesign of Guest Info (matching Step 1 & 2 layout, no old stepper, 2-column grid, top bar)
 * 2. Elimination of Review & Policy step from the active flow sequence
 * 3. Save Guest Info AJAX handler syncs cart and returns WooCommerce checkout redirect URL
 * 4. WooCommerce Checkout pre-fills billing & shipping fields from guest session
 * 5. WooCommerce Order Summary includes Room Type, Dates, Nights, and Guests
 * 6. WooCommerce Checkout displays Cancellation & Hotel Policies
 */

// 1. Mock WordPress & WooCommerce environment
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../../' );
}
if ( ! defined( 'SVAGO_PATH' ) ) {
    define( 'SVAGO_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SVAGO_URL' ) ) {
    define( 'SVAGO_URL', 'http://localhost/wp-content/plugins/svago/' );
}
if ( ! defined( 'SVAGO_VERSION' ) ) {
    define( 'SVAGO_VERSION', '1.0.0' );
}
if ( ! defined( 'SVAGO_TESTING' ) ) {
    define( 'SVAGO_TESTING', true );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['test_transients'] = [];
$GLOBALS['test_post_meta']  = [];
$GLOBALS['test_filters']    = [];
$GLOBALS['test_actions']    = [];

function wp_generate_uuid4() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function get_transient( $key ) {
    return $GLOBALS['test_transients'][ $key ] ?? false;
}

function set_transient( $key, $val, $exp = 0 ) {
    $GLOBALS['test_transients'][ $key ] = $val;
    return true;
}

function delete_transient( $key ) {
    unset( $GLOBALS['test_transients'][ $key ] );
    return true;
}

function sanitize_text_field( $str ) {
    return is_string( $str ) ? trim( strip_tags( $str ) ) : $str;
}

function sanitize_textarea_field( $str ) {
    return is_string( $str ) ? trim( strip_tags( $str ) ) : $str;
}

function sanitize_email( $email ) {
    return is_string( $email ) ? filter_var( trim( $email ), FILTER_SANITIZE_EMAIL ) : $email;
}

function is_email( $email ) {
    return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
}

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
    return $url;
}

function esc_textarea( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain = 'default' ) {
    return $text;
}

function esc_attr__( $text, $domain = 'default' ) {
    return $text;
}

function __( $text, $domain = 'default' ) {
    return $text;
}

function _n( $single, $plural, $number, $domain = 'default' ) {
    return $number === 1 ? $single : $plural;
}

function esc_html_e( $text, $domain = 'default' ) {
    echo esc_html( $text );
}

function esc_attr_e( $text, $domain = 'default' ) {
    echo esc_attr( $text );
}

function date_i18n( $format, $timestamp = false ) {
    return date( $format, $timestamp ? $timestamp : time() );
}

function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
    return 1;
}

function selected( $selected, $current = true, $echo = true ) {
    $out = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
    if ( $echo ) echo $out;
    return $out;
}

function checked( $checked, $current = true, $echo = true ) {
    $out = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
    if ( $echo ) echo $out;
    return $out;
}

function add_query_arg( $args, $url = '' ) {
    $qs = http_build_query( $args );
    return $url . ( strpos( $url, '?' ) !== false ? '&' : '?' ) . $qs;
}

function current_time( $type ) {
    return date( 'Y-m-d' );
}

function add_shortcode( $tag, $callback ) {
    return true;
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['test_actions'][ $tag ][] = $callback;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['test_filters'][ $tag ][] = $callback;
}

function apply_filters( $tag, $value, ...$args ) {
    if ( ! empty( $GLOBALS['test_filters'][ $tag ] ) ) {
        foreach ( $GLOBALS['test_filters'][ $tag ] as $cb ) {
            $value = call_user_func( $cb, $value, ...$args );
        }
    }
    return $value;
}

function get_option( $name, $default = false ) {
    return $default;
}

function update_post_meta( $id, $key, $val ) {
    $GLOBALS['test_post_meta'][ $id ][ $key ] = $val;
    return true;
}

function get_woocommerce_currency_symbol() {
    return '₹';
}

function wc_get_checkout_url() {
    return 'https://example.com/checkout/';
}

function home_url( $path = '' ) {
    return 'https://example.com' . $path;
}

$GLOBALS['mock_is_cart'] = false;
$GLOBALS['mock_redirect_url'] = null;

function is_cart() {
    return ! empty( $GLOBALS['mock_is_cart'] );
}

function is_admin() {
    return false;
}

function wp_safe_redirect( $url ) {
    $GLOBALS['mock_redirect_url'] = $url;
    return true;
}

class TestWPCart {
    public $items = [];
    public function empty_cart() {
        $this->items = [];
    }
    public function is_empty() {
        return empty( $this->items );
    }
    public function add_to_cart( $product_id, $qty = 1, $var_id = 0, $vars = [], $item_data = [] ) {
        $this->items[] = [
            'product_id' => $product_id,
            'quantity'   => $qty,
            'data'       => $item_data,
        ] + $item_data;
        return true;
    }
    public function get_cart() {
        return $this->items;
    }
}

class TestWPCustomer {
    public $billing_first_name = '';
    public $billing_last_name = '';
    public $billing_email = '';
    public $billing_phone = '';
    public $shipping_first_name = '';
    public $shipping_last_name = '';

    public function set_billing_first_name( $v ) { $this->billing_first_name = $v; }
    public function set_billing_last_name( $v ) { $this->billing_last_name = $v; }
    public function set_billing_email( $v ) { $this->billing_email = $v; }
    public function set_billing_phone( $v ) { $this->billing_phone = $v; }
    public function set_shipping_first_name( $v ) { $this->shipping_first_name = $v; }
    public function set_shipping_last_name( $v ) { $this->shipping_last_name = $v; }
    public function save() {}
}

class TestWooCommerce {
    public $cart;
    public $customer;
    public $session = null;
    public function __construct() {
        $this->cart = new TestWPCart();
        $this->customer = new TestWPCustomer();
    }
}

function WC() {
    static $wc = null;
    if ( null === $wc ) {
        $wc = new TestWooCommerce();
    }
    return $wc;
}

class TestWPDB {
    public $prefix = 'wp_';
    public function insert( $table, $data ) {
        return 1;
    }
}
$GLOBALS['wpdb'] = new TestWPDB();

$ajax_json_output = null;
function wp_send_json_success( $data = null ) {
    $GLOBALS['ajax_json_output'] = [ 'success' => true, 'data' => $data ];
}
function wp_send_json_error( $data = null ) {
    $GLOBALS['ajax_json_output'] = [ 'success' => false, 'data' => $data ];
}

// Load plugin files
require_once SVAGO_PATH . 'includes/class-logger.php';
require_once SVAGO_PATH . 'includes/interface-ota-provider.php';
require_once SVAGO_PATH . 'includes/class-mock-provider.php';
require_once SVAGO_PATH . 'includes/class-provider-factory.php';
require_once SVAGO_PATH . 'includes/class-booking-flow.php';
require_once SVAGO_PATH . 'includes/class-order-manager.php';
require_once SVAGO_PATH . 'includes/class-shortcodes.php';

use SVAGO\Booking_Flow;
use SVAGO\Order_Manager;
use SVAGO\Shortcodes;

$passed = 0;
$failed = 0;

function assert_test( $condition, $name ) {
    global $passed, $failed;
    if ( $condition ) {
        echo "[PASS] " . $name . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $name . "\n";
        $failed++;
    }
}

echo "=== STARTING GUEST INFO & WC CHECKOUT INTEGRATION TESTS ===\n\n";

// TEST 1: Template Structure & Design Consistency of guest-details.php
$booking_data = [
    'room_id'         => 'RM001',
    'room_type'       => 'Balcony',
    'room_name'       => 'Jazz Room',
    'room_image'      => 'http://example.com/jazz.jpg',
    'check_in'        => '2026-11-01',
    'check_out'       => '2026-11-04',
    'nights'          => 3,
    'adults'          => 2,
    'children'        => 0,
    'price_per_night' => 15000,
    'total_amount'    => 45000,
    'guest'           => [
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'email'         => 'john@example.com',
        'phone_country' => '+91',
        'phone_number'  => '9876543210',
    ]
];
$currency = '₹';

ob_start();
include SVAGO_PATH . 'templates/guest-details.php';
$template_output = ob_get_clean();

assert_test( strpos( $template_output, 'svago-step2-page-wrapper svago-step3-page-wrapper' ) !== false, "Guest Details uses luxury step wrapper classes" );
assert_test( strpos( $template_output, 'svago-step3-grid' ) !== false, "Guest Details uses 2-column layout grid" );
assert_test( strpos( $template_output, 'svago-step3-main-col' ) !== false && strpos( $template_output, 'svago-step3-side-col' ) !== false, "Guest Details contains form main column and stay summary side column" );
assert_test( strpos( $template_output, 'svago-stepper' ) === false, "Old circular stepper is completely absent from Guest Details" );
assert_test( strpos( $template_output, 'CALENDAR' ) !== false && strpos( $template_output, 'ROOM AND RATE SELECTION' ) !== false && strpos( $template_output, 'svago-nav-active">GUEST INFO' ) !== false, "Top navigation bar displays breadcrumb with active GUEST INFO" );
assert_test( strpos( $template_output, 'CHECKOUT >' ) !== false, "Top navigation bar displays 'CHECKOUT >' on right edge" );
assert_test( strpos( $template_output, 'PROCEED TO CHECKOUT' ) !== false, "CTA submit button is 'PROCEED TO CHECKOUT'" );
assert_test( strpos( $template_output, 'Your Stay Summary' ) !== false, "Right sidebar includes 'Your Stay Summary'" );
assert_test( strpos( $template_output, 'Jazz Room' ) !== false, "Right sidebar includes room name" );
assert_test( strpos( $template_output, 'Balcony' ) !== false, "Right sidebar includes room type badge" );

// TEST 2: Flow Sequence & Removal of Review & Policy step
$_COOKIE[ Booking_Flow::SESSION_KEY ] = 'test-token-123';
Booking_Flow::set_session_data( $booking_data );

$shortcodes = Shortcodes::get_instance();

// Calling render_booking_flow with step=details should render guest details
$_GET['step'] = 'details';
$flow_details_output = $shortcodes->render_booking_flow();
assert_test( strpos( $flow_details_output, 'svago-step3-page-wrapper' ) !== false, "render_booking_flow renders Guest Details on step=details" );

// Calling render_booking_flow with legacy step=review MUST redirect or render Guest Details (no old review step)
$_GET['step'] = 'review';
$flow_review_output = $shortcodes->render_booking_flow();
assert_test( strpos( $flow_review_output, 'svago-step3-page-wrapper' ) !== false, "render_booking_flow maps legacy step=review directly to Guest Details (Review step completely removed)" );

// TEST 3: Submitting Guest Info syncs Cart and returns WooCommerce Checkout URL
$_POST = [
    'first_name'       => 'Jane',
    'last_name'        => 'Smith',
    'email'            => 'jane.smith@example.com',
    'phone_country'    => '+91',
    'phone'            => '9123456780',
    'eta'              => '14:00 - 16:00',
    'special_requests' => 'Sea facing room if possible',
    'consent'          => '1',
];

$booking_flow = Booking_Flow::get_instance();
$booking_flow->ajax_save_guest_details();

$res = $GLOBALS['ajax_json_output'];
assert_test( ! empty( $res['success'] ), "ajax_save_guest_details returned success" );
assert_test( isset( $res['data']['checkout_url'] ) && $res['data']['checkout_url'] === 'https://example.com/checkout/', "ajax_save_guest_details returns WooCommerce checkout URL" );
assert_test( isset( $res['data']['redirect_url'] ) && $res['data']['redirect_url'] === 'https://example.com/checkout/', "ajax_save_guest_details returns redirect_url matching checkout URL" );

// Verify cart was synced
$cart_items = WC()->cart->get_cart();
assert_test( ! empty( $cart_items ), "WooCommerce Cart contains synced booking item" );
$cart_item = $cart_items[0] ?? [];
assert_test( ( $cart_item['booking_room_type'] ?? '' ) === 'Balcony', "Cart item metadata contains selected Room Type" );
assert_test( ( $cart_item['booking_check_in'] ?? '' ) === '2026-11-01', "Cart item metadata contains Check-in date" );
assert_test( ( $cart_item['booking_check_out'] ?? '' ) === '2026-11-04', "Cart item metadata contains Check-out date" );
assert_test( ( $cart_item['booking_nights'] ?? 0 ) === 3, "Cart item metadata contains Nights count" );

// TEST 4: Pre-filling WooCommerce Checkout fields
$order_mgr = Order_Manager::get_instance();

// Verify WC Customer profile was pre-filled
assert_test( WC()->customer->billing_first_name === 'Jane', "WC()->customer billing_first_name set to Jane" );
assert_test( WC()->customer->billing_last_name === 'Smith', "WC()->customer billing_last_name set to Smith" );
assert_test( WC()->customer->billing_email === 'jane.smith@example.com', "WC()->customer billing_email set" );
assert_test( WC()->customer->billing_phone === '+91 9123456780', "WC()->customer billing_phone set" );

// Verify prefill_checkout_field filter for billing and shipping
assert_test( $order_mgr->prefill_checkout_field( '', 'billing_first_name' ) === 'Jane', "prefill_checkout_field populates billing_first_name" );
assert_test( $order_mgr->prefill_checkout_field( '', 'shipping_first_name' ) === 'Jane', "prefill_checkout_field populates shipping_first_name" );
assert_test( $order_mgr->prefill_checkout_field( '', 'billing_last_name' ) === 'Smith', "prefill_checkout_field populates billing_last_name" );
assert_test( $order_mgr->prefill_checkout_field( '', 'billing_email' ) === 'jane.smith@example.com', "prefill_checkout_field populates billing_email" );
assert_test( $order_mgr->prefill_checkout_field( '', 'billing_phone' ) === '+91 9123456780', "prefill_checkout_field populates billing_phone" );

$comments = $order_mgr->prefill_checkout_field( '', 'order_comments' );
assert_test( strpos( $comments, 'Estimated Arrival Time (ETA): 14:00 - 16:00' ) !== false, "order_comments pre-filled with ETA" );
assert_test( strpos( $comments, 'Special Requests: Sea facing room if possible' ) !== false, "order_comments pre-filled with Special Requests" );
assert_test( strpos( $comments, 'Selected Room Type: Balcony' ) !== false, "order_comments pre-filled with Room Type" );

// TEST 5: Order Summary Item Data Rendering
$item_data = $order_mgr->render_booking_item_data( [], $cart_item );
$names = array_column( $item_data, 'name' );
assert_test( in_array( 'Room Type', $names ), "Order Summary includes 'Room Type'" );
assert_test( in_array( 'Check-in', $names ), "Order Summary includes 'Check-in'" );
assert_test( in_array( 'Check-out', $names ), "Order Summary includes 'Check-out'" );
assert_test( in_array( 'Duration', $names ), "Order Summary includes 'Duration'" );
assert_test( in_array( 'Guests', $names ), "Order Summary includes 'Guests'" );

// TEST 6: WooCommerce Checkout Cancellation & Hotel Policies
ob_start();
$order_mgr->render_checkout_hotel_policies();
$policies_html = ob_get_clean();

assert_test( strpos( $policies_html, 'svago-wc-checkout-policies' ) !== false && strpos( $policies_html, 'Hotel Policies' ) !== false, "Checkout policies rendered with heading" );
assert_test( strpos( $policies_html, 'Free Cancellation' ) !== false, "Checkout policies contains Free Cancellation policy" );
assert_test( strpos( $policies_html, 'Check-in begins at 2:00 PM' ) !== false, "Checkout policies contains Check-in time" );
assert_test( strpos( $policies_html, 'Government Photo ID' ) !== false, "Checkout policies contains Photo ID requirement" );

// TEST 7: Form Validation Error Handling in ajax_save_guest_details
// 7a. Missing First & Last Name
$_POST = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => 'valid@example.com',
    'phone'      => '9876543210',
];
$booking_flow->ajax_save_guest_details();
$err_res = $GLOBALS['ajax_json_output'];
assert_test( empty( $err_res['success'] ), "ajax_save_guest_details fails when names are empty" );
assert_test( isset( $err_res['data']['errors']['first_name'] ), "Validation returns specific error for first_name" );
assert_test( isset( $err_res['data']['errors']['last_name'] ), "Validation returns specific error for last_name" );

// 7b. Invalid Email & Phone
$_POST = [
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'email'      => 'not-an-email',
    'phone'      => '123',
];
$booking_flow->ajax_save_guest_details();
$err_res2 = $GLOBALS['ajax_json_output'];
assert_test( empty( $err_res2['success'] ), "ajax_save_guest_details fails for invalid email and short phone" );
assert_test( isset( $err_res2['data']['errors']['email'] ), "Validation returns specific error for email" );
assert_test( isset( $err_res2['data']['errors']['phone'] ), "Validation returns specific error for phone" );

// TEST 8: Hidden Reservation Fields & Alert Banner in guest-details.php
assert_test( strpos( $template_output, 'id="svago-guest-form-alert"' ) !== false, "Guest Details includes #svago-guest-form-alert element" );
assert_test( strpos( $template_output, 'id="svago-guest-room-id"' ) !== false, "Guest Details includes hidden room_id input" );
assert_test( strpos( $template_output, 'id="svago-guest-room-type"' ) !== false, "Guest Details includes hidden room_type input" );
assert_test( strpos( $template_output, 'id="svago-guest-check-in"' ) !== false, "Guest Details includes hidden check_in input" );
assert_test( strpos( $template_output, 'id="svago-guest-check-out"' ) !== false, "Guest Details includes hidden check_out input" );

// TEST 9: Auto-hydration when Session is Empty
delete_transient( 'svago_bk_test-token-123' );
$_COOKIE[ Booking_Flow::SESSION_KEY ] = 'empty-session-token';
$_POST = [
    'first_name'       => 'Alice',
    'last_name'        => 'Wonder',
    'email'            => 'alice@example.com',
    'phone_country'    => '+91',
    'phone'            => '9876543219',
    'room_id'          => 'RM002',
    'room_type'        => 'Jacuzzi',
    'room_name'        => 'Literature Room',
    'check_in'         => '2026-12-01',
    'check_out'        => '2026-12-03',
    'nights'           => 2,
    'adults'           => 2,
    'children'         => 0,
    'price_per_night'  => 18000,
    'total_amount'     => 36000,
];
$booking_flow->ajax_save_guest_details();
$hydrated_res = $GLOBALS['ajax_json_output'];
assert_test( ! empty( $hydrated_res['success'] ), "ajax_save_guest_details auto-hydrates and succeeds when session was initially empty" );
assert_test( ( $hydrated_res['data']['booking_data']['room_type'] ?? '' ) === 'Jacuzzi', "Auto-hydrated booking has correct room_type" );

// TEST 10: Skip WooCommerce Cart Page (Direct Checkout Flow)
// 10a. direct_checkout_redirect filter always returns checkout URL
assert_test( $order_mgr->direct_checkout_redirect( 'https://example.com/cart/' ) === 'https://example.com/checkout/', "direct_checkout_redirect returns checkout URL" );

// 10b. When is_cart() is true and cart has items, skip_cart_redirect_to_checkout redirects to checkout
$GLOBALS['mock_is_cart'] = true;
$GLOBALS['mock_redirect_url'] = null;
// Cart currently has the item from earlier test
$order_mgr->skip_cart_redirect_to_checkout();
assert_test( $GLOBALS['mock_redirect_url'] === 'https://example.com/checkout/', "skip_cart_redirect_to_checkout redirects cart with items directly to checkout" );

// 10c. When is_cart() is true and cart is empty, redirect to home or booking page
WC()->cart->empty_cart();
$GLOBALS['mock_redirect_url'] = null;
$order_mgr->skip_cart_redirect_to_checkout();
assert_test( $GLOBALS['mock_redirect_url'] === 'https://example.com/', "skip_cart_redirect_to_checkout redirects empty cart to booking/home page" );
$GLOBALS['mock_is_cart'] = false;

// TEST 11: CSS and JS Integrity
$css_content = file_get_contents( SVAGO_PATH . 'assets/css/hotel-booking.css' );
assert_test( strpos( $css_content, '.svago-step3-input.svago-input-error' ) !== false, "CSS contains .svago-input-error styling" );
assert_test( strpos( $css_content, '.svago-form-alert-error' ) !== false, "CSS contains .svago-form-alert-error styling" );
assert_test( strpos( $css_content, '.svago-step3-field-group .svago-field-error' ) !== false, "CSS contains .svago-field-error styling" );

$js_content = file_get_contents( SVAGO_PATH . 'assets/js/hotel-booking.js' );
assert_test( strpos( $js_content, 'setFieldError' ) !== false, "JS contains setFieldError helper" );
assert_test( strpos( $js_content, 'svago-guest-form-alert' ) !== false, "JS displays errors in svago-guest-form-alert" );
assert_test( strpos( $js_content, 'window.location.href = checkoutUrl' ) !== false, "JS redirects to checkoutUrl on success" );

echo "\n=== TEST SUMMARY: {$passed} PASSED, {$failed} FAILED ===\n";

if ( $failed > 0 ) {
    exit(1);
}
exit(0);

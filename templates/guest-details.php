<?php
/**
 * Guest Details Screen Template - Step 3 of [hotel_booking]
 *
 * Implements the new luxury design matching Step 1 (Calendar) & Step 2 (Room Selection):
 * - Max-width 1200px container, centered
 * - Soft, warm cream/beige background (var(--primary-bg, #fbf9f4))
 * - Global top navigation bar: < BACK | CALENDAR > ROOM AND RATE SELECTION > GUEST INFO (active) | CHECKOUT >
 * - 2-Column layout:
 *     - Left Column (~65% width): Guest Information form with clean understated inputs
 *     - Right Column (~35% width): Booking Stay Summary with room image, dates, type, pricing
 * - Submitting form redirects directly to WooCommerce Checkout
 *
 * @package SVAGO
 *
 * Variables passed:
 * @var array  $booking_data
 * @var string $currency
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$room_name       = $booking_data['room_name'] ?? esc_html__( 'Selected Room', 'svago' );
$room_type       = $booking_data['room_type'] ?? 'Balcony';
$room_image      = $booking_data['room_image'] ?? '';
$rate_name       = $booking_data['rate_name'] ?? esc_html__( 'Standard Rate', 'svago' );
$check_in        = $booking_data['check_in'] ?? '';
$check_out       = $booking_data['check_out'] ?? '';
$nights          = (int) ( $booking_data['nights'] ?? 1 );
$adults          = (int) ( $booking_data['adults'] ?? 2 );
$children        = (int) ( $booking_data['children'] ?? 0 );
$total_amount    = (float) ( $booking_data['total_amount'] ?? 0 );
$price_per_night = (float) ( $booking_data['price_per_night'] ?? 0 );
$currency_sym    = $currency ?? '₹';

$guest            = $booking_data['guest'] ?? [];
$first_name       = $guest['first_name'] ?? '';
$last_name        = $guest['last_name'] ?? '';
$email            = $guest['email'] ?? '';
$phone_country    = $guest['phone_country'] ?? '+91';
$phone_number     = $guest['phone_number'] ?? '';
$eta              = $guest['eta'] ?? '';
$special_requests = $guest['special_requests'] ?? '';
$consent          = isset( $guest['consent'] ) ? (bool) $guest['consent'] : true;

$current_page_base = ! empty( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
$back_to_rooms_url = function_exists( 'add_query_arg' ) ? add_query_arg( [
    'step'      => 'rooms',
    'room_id'   => $booking_data['room_id'] ?? '',
    'room_type' => $room_type,
    'check_in'  => $check_in,
    'check_out' => $check_out,
    'adults'    => $adults,
    'children'  => $children,
], $current_page_base ) : '?step=rooms';

$placeholder_url = SVAGO_URL . 'assets/images/placeholder.png';
$stay_img        = ! empty( $room_image ) ? $room_image : $placeholder_url;
?>

<div class="svago-step2-page-wrapper svago-step3-page-wrapper" id="svago-guest-details-view">
    <div class="svago-step2-container svago-step3-container">

        <!-- ==================================================================
             TOP NAVIGATION BAR (Step 3: GUEST INFO active)
             Alignment: < BACK | CALENDAR > ROOM AND RATE SELECTION > GUEST INFO | CHECKOUT >
             ================================================================== -->
        <header class="svago-step2-top-bar svago-step3-top-bar" aria-label="<?php esc_attr_e( 'Booking navigation bar', 'svago' ); ?>">
            <div class="svago-top-bar-left">
                <a 
                    href="<?php echo esc_url( $back_to_rooms_url ); ?>" 
                    class="svago-top-back-btn" 
                    id="svago-step3-global-back"
                >
                    &lt; <?php esc_html_e( 'BACK', 'svago' ); ?>
                </a>
            </div>

            <nav class="svago-top-bar-center" aria-label="<?php esc_attr_e( 'Step progress', 'svago' ); ?>">
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'CALENDAR', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'ROOM AND RATE SELECTION', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-active"><?php esc_html_e( 'GUEST INFO', 'svago' ); ?></span>
            </nav>

            <div class="svago-top-bar-right">
                <span class="svago-next-steps-text"><?php esc_html_e( 'CHECKOUT >', 'svago' ); ?></span>
            </div>
        </header>

        <!-- Direct Booking Guarantee Banner -->
        <div class="svago-best-rate-banner" style="margin-bottom: 28px;">
            <span class="svago-banner-icon">✨</span>
            <span class="svago-banner-text">
                <strong><?php esc_html_e( 'Direct Booking Guarantee:', 'svago' ); ?></strong> 
                <?php esc_html_e( 'Best rates guaranteed with complimentary breakfast and flexible cancellation.', 'svago' ); ?>
            </span>
        </div>

        <!-- 2-Column Layout Grid -->
        <div class="svago-step3-grid">

            <!-- ==============================================================
                 LEFT COLUMN: Guest Information Form (~65% width)
                 ============================================================== -->
            <main class="svago-step3-main-col">
                <div class="svago-step3-card svago-step3-form-card">
                    
                    <header class="svago-step3-card-header">
                        <h2 class="svago-step3-title"><?php esc_html_e( 'Guest Information', 'svago' ); ?></h2>
                        <p class="svago-step3-subtitle">
                            <?php esc_html_e( 'Please ensure details match the government-issued photo ID presented at check-in.', 'svago' ); ?>
                        </p>
                    </header>

                    <form id="svago-guest-form" class="svago-step3-form" novalidate>
                        
                        <!-- Top Form Error / Notification Alert -->
                        <div id="svago-guest-form-alert" class="svago-form-alert" style="display:none;" role="alert" aria-live="assertive"></div>

                        <!-- Hidden Reservation Metadata for Bulletproof Session Hydration -->
                        <input type="hidden" name="room_id" id="svago-guest-room-id" value="<?php echo esc_attr( $booking_data['room_id'] ?? '' ); ?>" />
                        <input type="hidden" name="room_type" id="svago-guest-room-type" value="<?php echo esc_attr( $room_type ); ?>" />
                        <input type="hidden" name="room_name" id="svago-guest-room-name" value="<?php echo esc_attr( $room_name ); ?>" />
                        <input type="hidden" name="room_image" id="svago-guest-room-image" value="<?php echo esc_attr( $stay_img ); ?>" />
                        <input type="hidden" name="check_in" id="svago-guest-check-in" value="<?php echo esc_attr( $check_in ); ?>" />
                        <input type="hidden" name="check_out" id="svago-guest-check-out" value="<?php echo esc_attr( $check_out ); ?>" />
                        <input type="hidden" name="nights" id="svago-guest-nights" value="<?php echo esc_attr( $nights ); ?>" />
                        <input type="hidden" name="adults" id="svago-guest-adults" value="<?php echo esc_attr( $adults ); ?>" />
                        <input type="hidden" name="children" id="svago-guest-children" value="<?php echo esc_attr( $children ); ?>" />
                        <input type="hidden" name="price_per_night" id="svago-guest-price" value="<?php echo esc_attr( $price_per_night ); ?>" />
                        <input type="hidden" name="total_amount" id="svago-guest-total" value="<?php echo esc_attr( $total_amount ); ?>" />
                        
                        <!-- 2-Column: First Name & Last Name -->
                        <div class="svago-step3-form-row svago-step3-row-2col">
                            <div class="svago-step3-field-group">
                                <label for="svago-guest-first-name" class="svago-step3-label">
                                    <?php esc_html_e( 'First Name', 'svago' ); ?> <span class="svago-step3-req">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="svago-guest-first-name" 
                                    name="first_name" 
                                    class="svago-step3-input" 
                                    placeholder="<?php esc_attr_e( 'e.g. John', 'svago' ); ?>" 
                                    value="<?php echo esc_attr( $first_name ); ?>" 
                                    required 
                                />
                                <span class="svago-field-error" aria-live="polite"></span>
                            </div>

                            <div class="svago-step3-field-group">
                                <label for="svago-guest-last-name" class="svago-step3-label">
                                    <?php esc_html_e( 'Last Name', 'svago' ); ?> <span class="svago-step3-req">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="svago-guest-last-name" 
                                    name="last_name" 
                                    class="svago-step3-input" 
                                    placeholder="<?php esc_attr_e( 'e.g. Doe', 'svago' ); ?>" 
                                    value="<?php echo esc_attr( $last_name ); ?>" 
                                    required 
                                />
                                <span class="svago-field-error" aria-live="polite"></span>
                            </div>
                        </div>

                        <!-- 2-Column: Email & Mobile Number -->
                        <div class="svago-step3-form-row svago-step3-row-2col">
                            <div class="svago-step3-field-group">
                                <label for="svago-guest-email" class="svago-step3-label">
                                    <?php esc_html_e( 'Email Address', 'svago' ); ?> <span class="svago-step3-req">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="svago-guest-email" 
                                    name="email" 
                                    class="svago-step3-input" 
                                    placeholder="<?php esc_attr_e( 'john.doe@example.com', 'svago' ); ?>" 
                                    value="<?php echo esc_attr( $email ); ?>" 
                                    required 
                                />
                                <span class="svago-field-error" aria-live="polite"></span>
                            </div>

                            <div class="svago-step3-field-group">
                                <label for="svago-guest-phone" class="svago-step3-label">
                                    <?php esc_html_e( 'Mobile Number', 'svago' ); ?> <span class="svago-step3-req">*</span>
                                </label>
                                <div class="svago-step3-phone-wrapper">
                                    <select id="svago-phone-country" name="phone_country" class="svago-step3-select svago-step3-phone-country">
                                        <option value="+91" <?php selected( $phone_country, '+91' ); ?>>🇮🇳 +91 (IN)</option>
                                        <option value="+1" <?php selected( $phone_country, '+1' ); ?>>🇺🇸 +1 (US)</option>
                                        <option value="+44" <?php selected( $phone_country, '+44' ); ?>>🇬🇧 +44 (UK)</option>
                                        <option value="+971" <?php selected( $phone_country, '+971' ); ?>>🇦🇪 +971 (UAE)</option>
                                        <option value="+61" <?php selected( $phone_country, '+61' ); ?>>🇦🇺 +61 (AU)</option>
                                        <option value="+65" <?php selected( $phone_country, '+65' ); ?>>🇸🇬 +65 (SG)</option>
                                        <option value="+49" <?php selected( $phone_country, '+49' ); ?>>🇩🇪 +49 (DE)</option>
                                    </select>
                                    <input 
                                        type="tel" 
                                        id="svago-guest-phone" 
                                        name="phone" 
                                        class="svago-step3-input svago-step3-phone-number" 
                                        placeholder="<?php esc_attr_e( '9876543210', 'svago' ); ?>" 
                                        value="<?php echo esc_attr( $phone_number ); ?>" 
                                        required 
                                    />
                                </div>
                                <span class="svago-field-error" aria-live="polite"></span>
                            </div>
                        </div>

                        <!-- Estimated Arrival Time (ETA) -->
                        <div class="svago-step3-form-row">
                            <div class="svago-step3-field-group">
                                <label for="svago-guest-eta" class="svago-step3-label">
                                    <?php esc_html_e( 'Estimated Arrival Time (ETA)', 'svago' ); ?>
                                </label>
                                <select id="svago-guest-eta" name="eta" class="svago-step3-select">
                                    <option value=""><?php esc_html_e( 'I do not know yet', 'svago' ); ?></option>
                                    <option value="12:00 - 14:00" <?php selected( $eta, '12:00 - 14:00' ); ?>>12:00 PM – 02:00 PM</option>
                                    <option value="14:00 - 16:00" <?php selected( $eta, '14:00 - 16:00' ); ?>>02:00 PM – 04:00 PM (Standard Check-in)</option>
                                    <option value="16:00 - 18:00" <?php selected( $eta, '16:00 - 18:00' ); ?>>04:00 PM – 06:00 PM</option>
                                    <option value="18:00 - 20:00" <?php selected( $eta, '18:00 - 20:00' ); ?>>06:00 PM – 08:00 PM</option>
                                    <option value="After 20:00" <?php selected( $eta, 'After 20:00' ); ?>>After 08:00 PM (Late Arrival)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Special Requests -->
                        <div class="svago-step3-form-row">
                            <div class="svago-step3-field-group">
                                <label for="svago-special-requests" class="svago-step3-label">
                                    <?php esc_html_e( 'Special Requests (Optional)', 'svago' ); ?>
                                </label>
                                <textarea 
                                    id="svago-special-requests" 
                                    name="special_requests" 
                                    class="svago-step3-textarea" 
                                    rows="3" 
                                    placeholder="<?php esc_attr_e( 'e.g., quiet room, high floor, non-smoking, early check-in (subject to availability)', 'svago' ); ?>"
                                ><?php echo esc_textarea( $special_requests ); ?></textarea>
                            </div>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="svago-step3-consent-row">
                            <label class="svago-step3-checkbox-label">
                                <input 
                                    type="checkbox" 
                                    id="svago-guest-consent" 
                                    name="consent" 
                                    value="1" 
                                    <?php checked( $consent, true ); ?> 
                                />
                                <span>
                                    <?php esc_html_e( 'I agree to receive booking confirmation, reminders, and updates via WhatsApp and Email.', 'svago' ); ?>
                                </span>
                            </label>
                        </div>

                        <!-- Submit CTA Section (Dark Pill Button matching Step 1 & 2) -->
                        <div class="svago-step3-cta-section">
                            <button 
                                type="submit" 
                                class="svago-pill-btn svago-step3-submit-btn svago-proceed-review" 
                                id="svago-proceed-checkout-btn"
                            >
                                <span class="svago-btn-text">
                                    🔒 <?php esc_html_e( 'PROCEED TO CHECKOUT', 'svago' ); ?> &rarr;
                                </span>
                                <span class="svago-spinner" aria-hidden="true"></span>
                            </button>
                        </div>

                    </form>
                </div>
            </main>

            <!-- ==============================================================
                 RIGHT COLUMN: Booking Summary Sidebar (~35% width)
                 ============================================================== -->
            <aside class="svago-step3-side-col" aria-label="<?php esc_attr_e( 'Your Reservation Summary', 'svago' ); ?>">
                <div class="svago-step3-card svago-step3-summary-card">
                    
                    <h3 class="svago-step3-summary-title">
                        <?php esc_html_e( 'Your Stay Summary', 'svago' ); ?>
                    </h3>

                    <!-- Room Image -->
                    <div class="svago-step3-room-img-wrapper">
                        <img 
                            src="<?php echo esc_url( $stay_img ); ?>" 
                            alt="<?php echo esc_attr( $room_name ); ?>" 
                            class="svago-step3-room-img" 
                            onerror="this.onerror=null;this.src='<?php echo esc_url( $placeholder_url ); ?>';" 
                        />
                    </div>

                    <!-- Room Title & Type -->
                    <div class="svago-step3-room-details">
                        <h4 class="svago-step3-room-name"><?php echo esc_html( $room_name ); ?></h4>
                        <?php if ( ! empty( $room_type ) ) : ?>
                            <span class="svago-step3-type-tag">
                                <?php echo esc_html( sprintf( __( 'Type: %s', 'svago' ), $room_type ) ); ?>
                            </span>
                        <?php endif; ?>
                        <span class="svago-step3-rate-badge"><?php echo esc_html( $rate_name ); ?></span>
                    </div>

                    <!-- Dates Box -->
                    <div class="svago-step3-dates-panel">
                        <div class="svago-step3-date-col">
                            <span class="svago-step3-date-sub"><?php esc_html_e( 'Check-in', 'svago' ); ?></span>
                            <span class="svago-step3-date-val"><?php echo esc_html( function_exists( 'date_i18n' ) ? date_i18n( 'D, M j, Y', strtotime( $check_in ) ) : date( 'D, M j, Y', strtotime( $check_in ) ) ); ?></span>
                            <span class="svago-step3-date-time"><?php esc_html_e( 'From 2:00 PM', 'svago' ); ?></span>
                        </div>
                        <div class="svago-step3-date-sep">&rarr;</div>
                        <div class="svago-step3-date-col">
                            <span class="svago-step3-date-sub"><?php esc_html_e( 'Check-out', 'svago' ); ?></span>
                            <span class="svago-step3-date-val"><?php echo esc_html( function_exists( 'date_i18n' ) ? date_i18n( 'D, M j, Y', strtotime( $check_out ) ) : date( 'D, M j, Y', strtotime( $check_out ) ) ); ?></span>
                            <span class="svago-step3-date-time"><?php esc_html_e( 'Until 11:00 AM', 'svago' ); ?></span>
                        </div>
                    </div>

                    <!-- Duration & Guests Meta -->
                    <div class="svago-step3-meta-rows">
                        <div class="svago-step3-meta-row">
                            <span class="svago-step3-meta-label"><?php esc_html_e( 'Length of stay:', 'svago' ); ?></span>
                            <strong class="svago-step3-meta-val"><?php echo esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'svago' ), $nights ) ); ?></strong>
                        </div>
                        <div class="svago-step3-meta-row">
                            <span class="svago-step3-meta-label"><?php esc_html_e( 'Guests:', 'svago' ); ?></span>
                            <strong class="svago-step3-meta-val">
                                <?php 
                                $g_text = sprintf( _n( '%d Adult', '%d Adults', $adults, 'svago' ), $adults );
                                if ( $children > 0 ) {
                                    $g_text .= ', ' . sprintf( _n( '%d Child', '%d Children', $children, 'svago' ), $children );
                                }
                                echo esc_html( $g_text );
                                ?>
                            </strong>
                        </div>
                    </div>

                    <!-- Price Table -->
                    <div class="svago-step3-pricing-box">
                        <div class="svago-step3-price-line">
                            <span><?php echo esc_html( sprintf( __( '%s × %d nts', 'svago' ), $currency_sym . number_format( $price_per_night ), $nights ) ); ?></span>
                            <span><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                        </div>
                        <div class="svago-step3-price-line">
                            <span><?php esc_html_e( 'Taxes & Fees', 'svago' ); ?></span>
                            <span class="svago-step3-tag-incl"><?php esc_html_e( 'Included', 'svago' ); ?></span>
                        </div>
                        <div class="svago-step3-price-div"></div>
                        <div class="svago-step3-price-line svago-step3-total-line">
                            <span><?php esc_html_e( 'Total Amount', 'svago' ); ?></span>
                            <span class="svago-step3-total-val"><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="svago-step3-guarantee-note">
                        🔒 <?php esc_html_e( 'Secure reservation. Pre-filled in WooCommerce Checkout.', 'svago' ); ?>
                    </div>

                </div>
            </aside>

        </div>

    </div>
</div>

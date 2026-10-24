<?php
/**
 * Guest Details Form Template
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

$guest = $booking_data['guest'] ?? [];
$first_name       = $guest['first_name'] ?? '';
$last_name        = $guest['last_name'] ?? '';
$email            = $guest['email'] ?? '';
$phone_country    = $guest['phone_country'] ?? '+91';
$phone_number     = $guest['phone_number'] ?? '';
$eta              = $guest['eta'] ?? '';
$special_requests = $guest['special_requests'] ?? '';
$consent          = isset( $guest['consent'] ) ? (bool) $guest['consent'] : true;
?>

<div class="svago-booking-wrapper svago-guest-details-container" id="svago-guest-details-view">
    
    <!-- Progress Stepper -->
    <div class="svago-stepper">
        <div class="svago-step svago-step-completed">
            <span class="svago-step-num">✓</span>
            <span class="svago-step-label"><?php esc_html_e( 'Select Room', 'svago' ); ?></span>
        </div>
        <div class="svago-step-line svago-step-line-active"></div>
        <div class="svago-step svago-step-active">
            <span class="svago-step-num">2</span>
            <span class="svago-step-label"><?php esc_html_e( 'Guest Details', 'svago' ); ?></span>
        </div>
        <div class="svago-step-line"></div>
        <div class="svago-step">
            <span class="svago-step-num">3</span>
            <span class="svago-step-label"><?php esc_html_e( 'Review & Policy', 'svago' ); ?></span>
        </div>
        <div class="svago-step-line"></div>
        <div class="svago-step">
            <span class="svago-step-num">4</span>
            <span class="svago-step-label"><?php esc_html_e( 'Payment', 'svago' ); ?></span>
        </div>
    </div>

    <div class="svago-booking-layout">

        <!-- Left Form Column -->
        <div class="svago-booking-main-col">
            <div class="svago-card svago-form-card">
                <div class="svago-card-header">
                    <h2 class="svago-card-title"><?php esc_html_e( 'Primary Guest Information', 'svago' ); ?></h2>
                    <p class="svago-card-subtitle"><?php esc_html_e( 'Please ensure details match the ID presented at check-in.', 'svago' ); ?></p>
                </div>

                <form id="svago-guest-form" class="svago-booking-form" novalidate>
                    <div class="svago-form-row svago-row-2col">
                        <div class="svago-form-group">
                            <label for="svago-guest-first-name">
                                <?php esc_html_e( 'First Name', 'svago' ); ?> <span class="svago-required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="svago-guest-first-name" 
                                name="first_name" 
                                class="svago-input" 
                                placeholder="<?php esc_attr_e( 'e.g. John', 'svago' ); ?>" 
                                value="<?php echo esc_attr( $first_name ); ?>" 
                                required 
                            />
                            <span class="svago-field-error" aria-live="polite"></span>
                        </div>

                        <div class="svago-form-group">
                            <label for="svago-guest-last-name">
                                <?php esc_html_e( 'Last Name', 'svago' ); ?> <span class="svago-required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="svago-guest-last-name" 
                                name="last_name" 
                                class="svago-input" 
                                placeholder="<?php esc_attr_e( 'e.g. Doe', 'svago' ); ?>" 
                                value="<?php echo esc_attr( $last_name ); ?>" 
                                required 
                            />
                            <span class="svago-field-error" aria-live="polite"></span>
                        </div>
                    </div>

                    <div class="svago-form-row svago-row-2col">
                        <div class="svago-form-group">
                            <label for="svago-guest-email">
                                <?php esc_html_e( 'Email Address', 'svago' ); ?> <span class="svago-required">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="svago-guest-email" 
                                name="email" 
                                class="svago-input" 
                                placeholder="<?php esc_attr_e( 'john.doe@example.com', 'svago' ); ?>" 
                                value="<?php echo esc_attr( $email ); ?>" 
                                required 
                            />
                            <span class="svago-field-error" aria-live="polite"></span>
                        </div>

                        <div class="svago-form-group">
                            <label for="svago-guest-phone">
                                <?php esc_html_e( 'Mobile Number', 'svago' ); ?> <span class="svago-required">*</span>
                            </label>
                            <div class="svago-phone-input-group">
                                <select id="svago-phone-country" name="phone_country" class="svago-select svago-country-code-select">
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
                                    class="svago-input svago-phone-input" 
                                    placeholder="<?php esc_attr_e( '9876543210', 'svago' ); ?>" 
                                    value="<?php echo esc_attr( $phone_number ); ?>" 
                                    required 
                                />
                            </div>
                            <span class="svago-field-error" aria-live="polite"></span>
                        </div>
                    </div>

                    <div class="svago-form-group">
                        <label for="svago-guest-eta">
                            <?php esc_html_e( 'Estimated Arrival Time (ETA)', 'svago' ); ?>
                        </label>
                        <select id="svago-guest-eta" name="eta" class="svago-select">
                            <option value=""><?php esc_html_e( 'I do not know yet', 'svago' ); ?></option>
                            <option value="12:00 - 14:00" <?php selected( $eta, '12:00 - 14:00' ); ?>>12:00 PM – 02:00 PM</option>
                            <option value="14:00 - 16:00" <?php selected( $eta, '14:00 - 16:00' ); ?>>02:00 PM – 04:00 PM (Standard Check-in)</option>
                            <option value="16:00 - 18:00" <?php selected( $eta, '16:00 - 18:00' ); ?>>04:00 PM – 06:00 PM</option>
                            <option value="18:00 - 20:00" <?php selected( $eta, '18:00 - 20:00' ); ?>>06:00 PM – 08:00 PM</option>
                            <option value="After 20:00" <?php selected( $eta, 'After 20:00' ); ?>>After 08:00 PM (Late Arrival)</option>
                        </select>
                    </div>

                    <div class="svago-form-group">
                        <label for="svago-special-requests">
                            <?php esc_html_e( 'Special Requests (Optional)', 'svago' ); ?>
                        </label>
                        <textarea 
                            id="svago-special-requests" 
                            name="special_requests" 
                            class="svago-textarea" 
                            rows="3" 
                            placeholder="<?php esc_attr_e( 'e.g., quiet room, high floor, non-smoking, early check-in (subject to availability)', 'svago' ); ?>"
                        ><?php echo esc_textarea( $special_requests ); ?></textarea>
                    </div>

                    <!-- WhatsApp and Email Notification Consent Checkbox (Task 3.2 & 5.7) -->
                    <div class="svago-form-group svago-checkbox-group">
                        <label class="svago-checkbox-label">
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

                    <!-- Action Buttons -->
                    <div class="svago-form-actions">
                        <button type="button" class="svago-btn svago-btn-secondary svago-back-to-rooms">
                            &larr; <?php esc_html_e( 'Back to Rooms', 'svago' ); ?>
                        </button>
                        <button type="submit" class="svago-btn svago-btn-primary svago-proceed-review">
                            <span class="svago-btn-text"><?php esc_html_e( 'Continue to Review', 'svago' ); ?> &rarr;</span>
                            <span class="svago-spinner" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Summary Sidebar -->
        <div class="svago-booking-side-col">
            <div class="svago-card svago-summary-card">
                <h3 class="svago-summary-heading"><?php esc_html_e( 'Your Stay Summary', 'svago' ); ?></h3>

                <?php if ( ! empty( $room_image ) ) : ?>
                    <div class="svago-summary-room-img">
                        <img src="<?php echo esc_url( $room_image ); ?>" alt="<?php echo esc_attr( $room_name ); ?>" />
                    </div>
                <?php endif; ?>

                <div class="svago-summary-room-info">
                    <h4 class="svago-summary-room-name"><?php echo esc_html( $room_name ); ?></h4>
                    <span class="svago-summary-rate-badge"><?php echo esc_html( $rate_name ); ?></span>
                </div>

                <div class="svago-summary-dates-box">
                    <div class="svago-summary-date-col">
                        <span class="svago-summary-date-title"><?php esc_html_e( 'Check-in', 'svago' ); ?></span>
                        <span class="svago-summary-date-val"><?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_in ) ) ); ?></span>
                        <span class="svago-summary-date-time"><?php esc_html_e( 'From 2:00 PM', 'svago' ); ?></span>
                    </div>
                    <div class="svago-summary-arrow">&rarr;</div>
                    <div class="svago-summary-date-col">
                        <span class="svago-summary-date-title"><?php esc_html_e( 'Check-out', 'svago' ); ?></span>
                        <span class="svago-summary-date-val"><?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_out ) ) ); ?></span>
                        <span class="svago-summary-date-time"><?php esc_html_e( 'Until 11:00 AM', 'svago' ); ?></span>
                    </div>
                </div>

                <div class="svago-summary-meta-line">
                    <span><?php esc_html_e( 'Length of stay:', 'svago' ); ?></span>
                    <strong><?php echo esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'svago' ), $nights ) ); ?></strong>
                </div>

                <div class="svago-summary-meta-line">
                    <span><?php esc_html_e( 'Guests:', 'svago' ); ?></span>
                    <strong>
                        <?php 
                        $g_text = sprintf( _n( '%d Adult', '%d Adults', $adults, 'svago' ), $adults );
                        if ( $children > 0 ) {
                            $g_text .= ', ' . sprintf( _n( '%d Child', '%d Children', $children, 'svago' ), $children );
                        }
                        echo esc_html( $g_text );
                        ?>
                    </strong>
                </div>

                <div class="svago-summary-pricing-table">
                    <div class="svago-price-row">
                        <span><?php echo esc_html( sprintf( __( '%s × %d nights', 'svago' ), $currency_sym . number_format( $price_per_night ), $nights ) ); ?></span>
                        <span><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>
                    <div class="svago-price-row svago-price-row-total">
                        <span><?php esc_html_e( 'Total Amount', 'svago' ); ?></span>
                        <span class="svago-total-price-val"><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>
                    <div class="svago-tax-note">
                        ✓ <?php esc_html_e( 'Includes all applicable taxes and service fees', 'svago' ); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

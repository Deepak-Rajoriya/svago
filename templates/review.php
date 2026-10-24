<?php
/**
 * Review & Policy Acceptance Template
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
$room_type       = $booking_data['room_type'] ?? '';
$room_image      = $booking_data['room_image'] ?? '';
$room_size       = $booking_data['room_size'] ?? '';
$bed_type        = $booking_data['bed_type'] ?? '';
$rate_name       = $booking_data['rate_name'] ?? esc_html__( 'Standard Rate', 'svago' );
$rate_desc       = $booking_data['rate_desc'] ?? '';
$check_in        = $booking_data['check_in'] ?? '';
$check_out       = $booking_data['check_out'] ?? '';
$nights          = (int) ( $booking_data['nights'] ?? 1 );
$adults          = (int) ( $booking_data['adults'] ?? 2 );
$children        = (int) ( $booking_data['children'] ?? 0 );
$total_amount    = (float) ( $booking_data['total_amount'] ?? 0 );
$price_per_night = (float) ( $booking_data['price_per_night'] ?? 0 );
$currency_sym    = $currency ?? '₹';

$guest            = $booking_data['guest'] ?? [];
$guest_name       = $guest['full_name'] ?? ( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) );
$guest_email      = $guest['email'] ?? '';
$guest_phone      = $guest['phone'] ?? ( ( $guest['phone_country'] ?? '' ) . ' ' . ( $guest['phone_number'] ?? '' ) );
$eta              = $guest['eta'] ?? '';
$special_requests = $guest['special_requests'] ?? '';
?>

<div class="svago-booking-wrapper svago-review-container" id="svago-review-view">

    <!-- Progress Stepper -->
    <div class="svago-stepper">
        <div class="svago-step svago-step-completed">
            <span class="svago-step-num">✓</span>
            <span class="svago-step-label"><?php esc_html_e( 'Select Room', 'svago' ); ?></span>
        </div>
        <div class="svago-step-line svago-step-line-active"></div>
        <div class="svago-step svago-step-completed">
            <span class="svago-step-num">✓</span>
            <span class="svago-step-label"><?php esc_html_e( 'Guest Details', 'svago' ); ?></span>
        </div>
        <div class="svago-step-line svago-step-line-active"></div>
        <div class="svago-step svago-step-active">
            <span class="svago-step-num">3</span>
            <span class="svago-step-label"><?php esc_html_e( 'Review & Policy', 'svago' ); ?></span>
        </div>
    </div>

    <!-- Pre-payment Availability Alert Box (Task 3.5 & 3.6) -->
    <div id="svago-availability-alert" class="svago-alert svago-alert-danger" style="display:none;" role="alert">
        <span class="svago-alert-icon">⚠️</span>
        <div class="svago-alert-content">
            <strong class="svago-alert-title"><?php esc_html_e( 'Availability Notice', 'svago' ); ?></strong>
            <p id="svago-availability-alert-msg" class="svago-alert-msg"></p>
            <div class="svago-alert-actions">
                <a href="#svago-room-results" class="svago-btn svago-btn-secondary svago-btn-sm svago-change-dates-btn">
                    <?php esc_html_e( 'Choose Different Room or Dates', 'svago' ); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="svago-booking-layout">

        <!-- Left Column: Details & Policies -->
        <div class="svago-booking-main-col">

            <!-- Reservation Overview Card -->
            <div class="svago-card svago-review-card">
                <div class="svago-card-header">
                    <h2 class="svago-card-title"><?php esc_html_e( 'Review Your Reservation', 'svago' ); ?></h2>
                    <p class="svago-card-subtitle"><?php esc_html_e( 'Please check your reservation details before proceeding to checkout.', 'svago' ); ?></p>
                </div>

                <div class="svago-review-room-box">
                    <?php 
                    $placeholder_url = SVAGO_URL . 'assets/images/placeholder.png';
                    $review_img      = ! empty( $room_image ) ? $room_image : $placeholder_url;
                    ?>
                    <div class="svago-review-room-thumb">
                        <img 
                            src="<?php echo esc_url( $review_img ); ?>" 
                            alt="<?php echo esc_attr( $room_name ); ?>" 
                            onerror="this.onerror=null;this.src='<?php echo esc_url( $placeholder_url ); ?>';" 
                        />
                    </div>
                    <div class="svago-review-room-text">
                        <h3 class="svago-review-room-title"><?php echo esc_html( $room_name ); ?></h3>
                        <div class="svago-review-room-specs">
                            <?php if ( ! empty( $room_type ) ) : ?>
                                <span class="svago-review-room-type" style="font-weight:600; color:#1e3a8a;">🌿 <?php echo esc_html( sprintf( __( 'Type: %s', 'svago' ), $room_type ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $bed_type ) ) : ?>
                                <span>🛏️ <?php echo esc_html( $bed_type ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $room_size ) ) : ?>
                                <span>📐 <?php echo esc_html( $room_size ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="svago-review-rate-tag">
                            <strong><?php esc_html_e( 'Plan:', 'svago' ); ?></strong> <?php echo esc_html( $rate_name ); ?>
                            <?php if ( ! empty( $rate_desc ) ) : ?>
                                <span class="svago-review-rate-desc">(<?php echo esc_html( $rate_desc ); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Guest Summary Box -->
                <div class="svago-review-guest-box">
                    <div class="svago-review-section-title">
                        <span><?php esc_html_e( 'Guest Contact Details', 'svago' ); ?></span>
                        <button type="button" class="svago-edit-link svago-edit-guest-btn">✏️ <?php esc_html_e( 'Edit', 'svago' ); ?></button>
                    </div>
                    <div class="svago-guest-info-grid">
                        <div class="svago-info-item">
                            <span class="svago-info-label"><?php esc_html_e( 'Lead Guest:', 'svago' ); ?></span>
                            <span class="svago-info-value"><strong><?php echo esc_html( $guest_name ); ?></strong></span>
                        </div>
                        <div class="svago-info-item">
                            <span class="svago-info-label"><?php esc_html_e( 'Email:', 'svago' ); ?></span>
                            <span class="svago-info-value"><?php echo esc_html( $guest_email ); ?></span>
                        </div>
                        <div class="svago-info-item">
                            <span class="svago-info-label"><?php esc_html_e( 'Phone:', 'svago' ); ?></span>
                            <span class="svago-info-value"><?php echo esc_html( $guest_phone ); ?></span>
                        </div>
                        <?php if ( ! empty( $eta ) ) : ?>
                            <div class="svago-info-item">
                                <span class="svago-info-label"><?php esc_html_e( 'Estimated Arrival:', 'svago' ); ?></span>
                                <span class="svago-info-value"><?php echo esc_html( $eta ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $special_requests ) ) : ?>
                            <div class="svago-info-item svago-info-full">
                                <span class="svago-info-label"><?php esc_html_e( 'Special Requests:', 'svago' ); ?></span>
                                <span class="svago-info-value"><?php echo esc_html( $special_requests ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Cancellation Policy & Terms Card (Task 3.3) -->
            <div class="svago-card svago-policy-card">
                <div class="svago-card-header">
                    <h3 class="svago-card-title"><?php esc_html_e( 'Cancellation & Hotel Policies', 'svago' ); ?></h3>
                </div>

                <div class="svago-policy-content">
                    <ul class="svago-policy-list">
                        <li>
                            <strong><?php esc_html_e( 'Free Cancellation:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'Cancel free of charge up to 48 hours prior to 2:00 PM on the check-in date.', 'svago' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Late Cancellation / No-Show:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'Cancellations within 48 hours of check-in, or failure to arrive, will be charged the first night room rate including taxes.', 'svago' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Check-in / Check-out:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'Standard check-in begins at 2:00 PM. Check-out time is 11:00 AM.', 'svago' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Government ID Requirement:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'Valid government-issued photo ID (Passport, Aadhar, Driving License) is mandatory for all adult guests upon arrival.', 'svago' ); ?>
                        </li>
                    </ul>
                </div>

                <!-- Mandatory Policy Acceptance Checkbox (Task 3.4) -->
                <div class="svago-mandatory-agreement">
                    <label class="svago-checkbox-label svago-checkbox-mandatory">
                        <input type="checkbox" id="svago-policy-checkbox" name="policy_agreed" value="1" />
                        <span>
                            <strong><?php esc_html_e( 'I agree to the cancellation policy, hotel rules, and booking terms & conditions.', 'svago' ); ?></strong>
                            <span class="svago-required">*</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="svago-form-actions svago-review-actions">
                <button type="button" class="svago-btn svago-btn-secondary svago-back-to-details">
                    &larr; <?php esc_html_e( 'Back to Guest Details', 'svago' ); ?>
                </button>
                
                <!-- Payment button is DISABLED until mandatory checkbox is ticked (Task 3.4) -->
                <button 
                    type="button" 
                    id="svago-proceed-payment-btn" 
                    class="svago-btn svago-btn-primary svago-proceed-payment" 
                    disabled
                >
                    <span class="svago-btn-text">
                        🔒 <?php esc_html_e( 'Proceed to Checkout', 'svago' ); ?> (<?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?>)
                    </span>
                    <span class="svago-spinner" aria-hidden="true"></span>
                </button>
            </div>

        </div>

        <!-- Right Column: Stay & Price Breakdown -->
        <div class="svago-booking-side-col">
            <div class="svago-card svago-summary-card">
                <h3 class="svago-summary-heading"><?php esc_html_e( 'Price Breakdown', 'svago' ); ?></h3>

                <div class="svago-summary-dates-box">
                    <div class="svago-summary-date-col">
                        <span class="svago-summary-date-title"><?php esc_html_e( 'Check-in', 'svago' ); ?></span>
                        <span class="svago-summary-date-val"><?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_in ) ) ); ?></span>
                    </div>
                    <div class="svago-summary-arrow">&rarr;</div>
                    <div class="svago-summary-date-col">
                        <span class="svago-summary-date-title"><?php esc_html_e( 'Check-out', 'svago' ); ?></span>
                        <span class="svago-summary-date-val"><?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_out ) ) ); ?></span>
                    </div>
                </div>

                <div class="svago-summary-pricing-table">
                    <div class="svago-price-row">
                        <span><?php echo esc_html( sprintf( __( 'Room Rate (%s × %d nts)', 'svago' ), $currency_sym . number_format( $price_per_night ), $nights ) ); ?></span>
                        <span><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>

                    <div class="svago-price-row">
                        <span><?php esc_html_e( 'Taxes & Hotel Fees', 'svago' ); ?></span>
                        <span class="svago-included-tag"><?php esc_html_e( 'Included', 'svago' ); ?></span>
                    </div>

                    <div class="svago-price-divider"></div>

                    <div class="svago-price-row svago-price-row-total">
                        <span><?php esc_html_e( 'Total Amount', 'svago' ); ?></span>
                        <span class="svago-total-price-val"><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>

                    <div class="svago-secure-notice">
                        🔒 <?php esc_html_e( 'Safe & Secure 256-bit Encrypted Checkout', 'svago' ); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
/**
 * Booking Confirmation Template
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

$order_id        = (int) ( $booking_data['order_id'] ?? 0 );
$room_name       = $booking_data['room_name'] ?? esc_html__( 'Deluxe Room', 'svago' );
$room_type       = $booking_data['room_type'] ?? '';
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

$guest_name       = $booking_data['guest_name'] ?? esc_html__( 'Valued Guest', 'svago' );
$guest_email      = $booking_data['guest_email'] ?? '';
$guest_phone      = $booking_data['guest_phone'] ?? '';
$guest_eta        = $booking_data['guest_eta'] ?? '';
$special_requests = $booking_data['special_requests'] ?? '';
$ota_booking_id   = $booking_data['ota_booking_id'] ?? '';
$payment_status   = $booking_data['payment_status'] ?? esc_html__( 'Paid', 'svago' );

$hotel_phone   = get_option( 'svago_hotel_phone', '+91 98765 43210' );
$hotel_email   = get_option( 'svago_hotel_email', 'reservations@hotel.com' );
$hotel_address = get_option( 'svago_hotel_address', '123 Ocean Drive, Luxury Bay, Goa 403001, India' );
$hotel_map_url = get_option( 'svago_hotel_map_url', 'https://maps.google.com/?q=Hotel+Luxury+Bay' );
?>

<div class="svago-step2-page-wrapper svago-confirmation-container" id="svago-confirmation-view">
    <div class="svago-step2-container">

        <!-- Top Navigation Bar matching new design -->
        <header class="svago-step2-top-bar" aria-label="<?php esc_attr_e( 'Booking navigation bar', 'svago' ); ?>" style="margin-bottom: 24px;">
            <div class="svago-top-bar-left">
                <span style="font-size: 0.8125rem; font-weight: 600; color: #15803d;">✓ <?php esc_html_e( 'COMPLETED', 'svago' ); ?></span>
            </div>

            <nav class="svago-top-bar-center" aria-label="<?php esc_attr_e( 'Step progress', 'svago' ); ?>">
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'CALENDAR', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'ROOM AND RATE SELECTION', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'GUEST INFO', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-active"><?php esc_html_e( 'CONFIRMED', 'svago' ); ?></span>
            </nav>

            <div class="svago-top-bar-right">
                <span class="svago-next-steps-text" style="color: #15803d;"><?php esc_html_e( 'DONE ✓', 'svago' ); ?></span>
            </div>
        </header>

    <!-- Success Banner -->
    <div class="svago-confirmation-hero">
        <div class="svago-hero-icon-wrap">
            <div class="svago-hero-check">✓</div>
        </div>
        <h1 class="svago-confirmation-title"><?php esc_html_e( 'Reservation Confirmed!', 'svago' ); ?></h1>
        <p class="svago-confirmation-subtitle">
            <?php echo esc_html( sprintf( __( 'Thank you, %s! We look forward to welcoming you.', 'svago' ), $guest_name ) ); ?>
        </p>
        <p class="svago-confirmation-email-notice">
            <?php echo esc_html( sprintf( __( 'A confirmation receipt has been sent to %s.', 'svago' ), $guest_email ) ); ?>
        </p>

        <div class="svago-reference-badges">
            <div class="svago-ref-badge">
                <span class="svago-ref-label"><?php esc_html_e( 'Booking ID', 'svago' ); ?></span>
                <span class="svago-ref-value">#SV-<?php echo esc_html( $order_id ); ?></span>
            </div>
            <?php if ( ! empty( $ota_booking_id ) ) : ?>
                <div class="svago-ref-badge">
                    <span class="svago-ref-label"><?php esc_html_e( 'OTA Confirmation', 'svago' ); ?></span>
                    <span class="svago-ref-value"><?php echo esc_html( $ota_booking_id ); ?></span>
                </div>
            <?php endif; ?>
            <div class="svago-ref-badge svago-badge-paid">
                <span class="svago-ref-label"><?php esc_html_e( 'Payment Status', 'svago' ); ?></span>
                <span class="svago-ref-value">✓ <?php echo esc_html( $payment_status ); ?></span>
            </div>
        </div>
    </div>

    <!-- 2-Column Details Layout -->
    <div class="svago-booking-layout">

        <!-- Left Column: Reservation Summary & Policies -->
        <div class="svago-booking-main-col">

            <!-- Stay Summary Card -->
            <div class="svago-card svago-confirmation-card">
                <div class="svago-card-header">
                    <h2 class="svago-card-title"><?php esc_html_e( 'Reservation Details', 'svago' ); ?></h2>
                </div>

                <div class="svago-review-room-box">
                    <?php 
                    $placeholder_url = SVAGO_URL . 'assets/images/placeholder.png';
                    $conf_img        = ! empty( $room_image ) ? $room_image : $placeholder_url;
                    ?>
                    <div class="svago-review-room-thumb">
                        <img 
                            src="<?php echo esc_url( $conf_img ); ?>" 
                            alt="<?php echo esc_attr( $room_name ); ?>" 
                            onerror="this.onerror=null;this.src='<?php echo esc_url( $placeholder_url ); ?>';" 
                        />
                    </div>
                    <div class="svago-review-room-text">
                        <h3 class="svago-review-room-title"><?php echo esc_html( $room_name ); ?></h3>
                        <?php if ( ! empty( $room_type ) ) : ?>
                            <span class="svago-confirmation-room-type" style="display:inline-block; font-size:0.8125rem; font-weight:600; color:#1e3a8a; background:#eff6ff; padding:2px 8px; border-radius:4px; margin-bottom:4px;">
                                <?php echo esc_html( sprintf( __( 'Type: %s', 'svago' ), $room_type ) ); ?>
                            </span>
                        <?php endif; ?>
                        <div class="svago-review-rate-tag">
                            <strong><?php esc_html_e( 'Selected Plan:', 'svago' ); ?></strong> <?php echo esc_html( $rate_name ); ?>
                        </div>
                    </div>
                </div>

                <!-- Stay Details Grid -->
                <div class="svago-confirmation-grid">
                    <div class="svago-grid-item">
                        <span class="svago-grid-label"><?php esc_html_e( 'Check-in Date', 'svago' ); ?></span>
                        <span class="svago-grid-value">
                            <strong><?php echo esc_html( $check_in ? date_i18n( 'D, M j, Y', strtotime( $check_in ) ) : '—' ); ?></strong>
                        </span>
                        <span class="svago-grid-sub"><?php esc_html_e( 'From 02:00 PM', 'svago' ); ?></span>
                    </div>

                    <div class="svago-grid-item">
                        <span class="svago-grid-label"><?php esc_html_e( 'Check-out Date', 'svago' ); ?></span>
                        <span class="svago-grid-value">
                            <strong><?php echo esc_html( $check_out ? date_i18n( 'D, M j, Y', strtotime( $check_out ) ) : '—' ); ?></strong>
                        </span>
                        <span class="svago-grid-sub"><?php esc_html_e( 'Until 11:00 AM', 'svago' ); ?></span>
                    </div>

                    <div class="svago-grid-item">
                        <span class="svago-grid-label"><?php esc_html_e( 'Length of Stay', 'svago' ); ?></span>
                        <span class="svago-grid-value">
                            <strong><?php echo esc_html( sprintf( _n( '%d Night', '%d Nights', $nights, 'svago' ), $nights ) ); ?></strong>
                        </span>
                    </div>

                    <div class="svago-grid-item">
                        <span class="svago-grid-label"><?php esc_html_e( 'Guests', 'svago' ); ?></span>
                        <span class="svago-grid-value">
                            <strong>
                                <?php 
                                echo esc_html( sprintf( __( '%d Adults', 'svago' ), $adults ) );
                                if ( $children > 0 ) {
                                    echo esc_html( sprintf( __( ', %d Children', 'svago' ), $children ) );
                                }
                                ?>
                            </strong>
                        </span>
                    </div>
                </div>

                <!-- Guest Info Recap -->
                <div class="svago-confirmation-section-title">
                    <span><?php esc_html_e( 'Lead Guest Information', 'svago' ); ?></span>
                </div>

                <div class="svago-guest-info-grid">
                    <div class="svago-info-item">
                        <span class="svago-info-label"><?php esc_html_e( 'Guest Name:', 'svago' ); ?></span>
                        <span class="svago-info-value"><strong><?php echo esc_html( $guest_name ); ?></strong></span>
                    </div>
                    <div class="svago-info-item">
                        <span class="svago-info-label"><?php esc_html_e( 'Email:', 'svago' ); ?></span>
                        <span class="svago-info-value"><?php echo esc_html( $guest_email ); ?></span>
                    </div>
                    <div class="svago-info-item">
                        <span class="svago-info-label"><?php esc_html_e( 'Mobile:', 'svago' ); ?></span>
                        <span class="svago-info-value"><?php echo esc_html( $guest_phone ); ?></span>
                    </div>
                    <?php if ( ! empty( $guest_eta ) ) : ?>
                        <div class="svago-info-item">
                            <span class="svago-info-label"><?php esc_html_e( 'Estimated Arrival:', 'svago' ); ?></span>
                            <span class="svago-info-value"><?php echo esc_html( $guest_eta ); ?></span>
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

            <!-- Important Information Card -->
            <div class="svago-card svago-policy-card">
                <div class="svago-card-header">
                    <h3 class="svago-card-title"><?php esc_html_e( 'Check-in & Hotel Policies', 'svago' ); ?></h3>
                </div>
                <div class="svago-policy-content">
                    <ul class="svago-policy-list">
                        <li>
                            <strong><?php esc_html_e( 'Government ID Required:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'All staying guests must present valid government-issued photo identification (Aadhaar, Passport, Driving License) at check-in.', 'svago' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Cancellation Window:', 'svago' ); ?></strong>
                            <?php esc_html_e( 'Free cancellation available up to 48 hours prior to 02:00 PM on your arrival date. Cancellations made after this period are subject to a 1-night retention fee.', 'svago' ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Need Assistance?', 'svago' ); ?></strong>
                            <?php echo esc_html( sprintf( __( 'Call our 24/7 Front Desk at %s or email %s.', 'svago' ), $hotel_phone, $hotel_email ) ); ?>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Right Column: Price Recap, Location & Actions -->
        <div class="svago-booking-side-col">

            <!-- Price Summary -->
            <div class="svago-card svago-summary-card">
                <h3 class="svago-summary-heading"><?php esc_html_e( 'Payment Breakdown', 'svago' ); ?></h3>

                <div class="svago-summary-pricing-table">
                    <div class="svago-price-row">
                        <span><?php echo esc_html( sprintf( __( 'Room Rate (%d nts)', 'svago' ), $nights ) ); ?></span>
                        <span><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>

                    <div class="svago-price-row">
                        <span><?php esc_html_e( 'Taxes & Service Fees', 'svago' ); ?></span>
                        <span class="svago-included-tag"><?php esc_html_e( 'Included', 'svago' ); ?></span>
                    </div>

                    <div class="svago-price-divider"></div>

                    <div class="svago-price-row svago-price-row-total">
                        <span><?php esc_html_e( 'Amount Paid', 'svago' ); ?></span>
                        <span class="svago-total-price-val"><?php echo esc_html( $currency_sym . number_format( $total_amount ) ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Hotel Location & Directions Card (Task 4.4) -->
            <div class="svago-card svago-location-card">
                <h3 class="svago-summary-heading"><?php esc_html_e( 'Hotel Location & Contact', 'svago' ); ?></h3>

                <p class="svago-hotel-address">
                    📍 <?php echo esc_html( $hotel_address ); ?>
                </p>

                <?php if ( ! empty( $hotel_map_url ) ) : ?>
                    <a href="<?php echo esc_url( $hotel_map_url ); ?>" target="_blank" rel="noopener noreferrer" class="svago-btn svago-btn-secondary svago-btn-block svago-map-btn">
                        🗺️ <?php esc_html_e( 'Open in Google Maps', 'svago' ); ?> &rarr;
                    </a>
                <?php endif; ?>

                <div class="svago-contact-details">
                    <div class="svago-contact-row">
                        <span>📞 <?php esc_html_e( 'Phone:', 'svago' ); ?></span>
                        <a href="tel:<?php echo esc_attr( $hotel_phone ); ?>"><?php echo esc_html( $hotel_phone ); ?></a>
                    </div>
                    <div class="svago-contact-row">
                        <span>✉️ <?php esc_html_e( 'Email:', 'svago' ); ?></span>
                        <a href="mailto:<?php echo esc_attr( $hotel_email ); ?>"><?php echo esc_html( $hotel_email ); ?></a>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (Print / Download Invoice) (Task 4.4) -->
            <div class="svago-confirmation-actions">
                <button type="button" class="svago-btn svago-btn-primary svago-btn-block svago-print-btn" onclick="window.print();">
                    🖨️ <?php esc_html_e( 'Print / Download Receipt', 'svago' ); ?>
                </button>
                <a href="<?php echo esc_url( get_option( 'svago_booking_page_url', '?' ) ); ?>" class="svago-btn svago-btn-secondary svago-btn-block svago-new-booking-btn">
                    🏨 <?php esc_html_e( 'Book Another Stay', 'svago' ); ?>
                </a>
            </div>

        </div>

    </div>

</div>
</div>

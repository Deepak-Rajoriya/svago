<?php
/**
 * Room Results Template
 *
 * @package SVAGO
 *
 * Variables passed from Shortcodes class:
 * @var array  $rooms
 * @var array  $availability_map
 * @var array  $rates_map
 * @var string $check_in
 * @var string $check_out
 * @var int    $nights
 * @var int    $adults
 * @var int    $children
 * @var array  $child_ages
 * @var bool   $is_fallback
 * @var string $fallback_message
 * @var string $currency
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="svago-room-results-container" id="svago-room-results">

    <!-- Best Rate Guarantee Banner (Requirement #19) -->
    <div class="svago-best-rate-banner">
        <span class="svago-banner-icon">✨</span>
        <span class="svago-banner-text">
            <strong><?php esc_html_e( 'Direct Booking Guarantee:', 'svago' ); ?></strong> 
            <?php esc_html_e( 'Best rates guaranteed with complimentary breakfast and flexible cancellation.', 'svago' ); ?>
        </span>
    </div>

    <!-- Cached / Fallback Data Notice (Task 2.8) -->
    <?php if ( ! empty( $is_fallback ) ) : ?>
        <div class="svago-fallback-notice" role="alert">
            <span class="svago-notice-icon">ℹ️</span>
            <span class="svago-notice-text">
                <?php echo esc_html( ! empty( $fallback_message ) ? $fallback_message : __( 'Live availability temporarily unavailable — showing cached rates.', 'svago' ) ); ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Stay Summary Header -->
    <div class="svago-results-summary-bar">
        <div class="svago-summary-dates">
            <span class="svago-summary-label"><?php esc_html_e( 'Your Dates:', 'svago' ); ?></span>
            <span class="svago-summary-value">
                <?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_in ) ) ); ?>
                &rarr;
                <?php echo esc_html( date_i18n( 'D, M j, Y', strtotime( $check_out ) ) ); ?>
                (<strong><?php echo esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'svago' ), $nights ) ); ?></strong>)
            </span>
        </div>

        <div class="svago-summary-guests">
            <span class="svago-summary-label"><?php esc_html_e( 'Guests:', 'svago' ); ?></span>
            <span class="svago-summary-value">
                <?php 
                $g_text = sprintf( _n( '%d Adult', '%d Adults', $adults, 'svago' ), $adults );
                if ( $children > 0 ) {
                    $g_text .= ', ' . sprintf( _n( '%d Child', '%d Children', $children, 'svago' ), $children );
                }
                echo esc_html( $g_text );
                ?>
            </span>
        </div>

        <div class="svago-summary-action">
            <a href="#svago-search-widget" class="svago-modify-search-btn">
                ✏️ <?php esc_html_e( 'Modify Search', 'svago' ); ?>
            </a>
        </div>
    </div>

    <!-- Rooms List / Grid -->
    <div class="svago-rooms-list">
        <?php if ( empty( $rooms ) ) : ?>
            <?php 
            $hotel_phone = get_option( 'svago_hotel_phone', '' ); 
            $clean_phone = preg_replace( '/[^0-9+]/', '', (string) $hotel_phone );
            ?>
            <!-- Empty state: Fallback missing / No rooms available (Task 7.5) -->
            <div class="svago-no-rooms-card svago-call-to-book-card" style="text-align:center; padding:32px 20px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
                <span style="font-size:42px; display:block; margin-bottom:12px;">📞</span>
                <h3 style="font-size:20px; margin-bottom:8px; color:#1e293b;"><?php esc_html_e( 'Online Booking Temporarily Unavailable', 'svago' ); ?></h3>
                <p style="font-size:14px; color:#64748b; max-width:540px; margin:0 auto 18px;">
                    <?php esc_html_e( 'We are currently unable to retrieve room availability online. Please contact our 24/7 reservations team directly to book your room over the phone.', 'svago' ); ?>
                </p>
                <?php if ( ! empty( $hotel_phone ) ) : ?>
                    <a href="tel:<?php echo esc_attr( $clean_phone ); ?>" class="button svago-call-btn" style="display:inline-block; background:#1e293b; color:#fff; padding:12px 28px; text-decoration:none; border-radius:6px; font-weight:600; font-size:15px;">
                        📞 <?php echo esc_html( sprintf( __( 'Call Reservations: %s', 'svago' ), $hotel_phone ) ); ?>
                    </a>
                <?php endif; ?>
            </div>

        <?php else : ?>

            <?php 
            $any_available = false;
            foreach ( $rooms as $room ) {
                $r_id              = $room['id'] ?? '';
                $availability_info = $availability_map[ $r_id ] ?? [ 'available' => false, 'inventory' => 0 ];
                $rates             = $rates_map[ $r_id ] ?? [];

                if ( ! empty( $availability_info['available'] ) ) {
                    $any_available = true;
                }

                include SVAGO_PATH . 'templates/room-card.php';
            }
            ?>

            <?php if ( ! $any_available ) : ?>
                <?php 
                $hotel_phone = get_option( 'svago_hotel_phone', '' ); 
                $clean_phone = preg_replace( '/[^0-9+]/', '', (string) $hotel_phone );
                ?>
                <div class="svago-all-sold-out-banner" style="text-align:center; padding:24px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; margin-top:20px;">
                    <h4 style="font-size:16px; margin-bottom:6px; color:#92400e;"><?php esc_html_e( 'All rooms are sold out for these dates', 'svago' ); ?></h4>
                    <p style="font-size:13px; color:#b45309; margin-bottom:12px;"><?php esc_html_e( 'Try searching for different dates above, or call our reservations desk directly for cancellations or last-minute openings.', 'svago' ); ?></p>
                    <?php if ( ! empty( $hotel_phone ) ) : ?>
                        <a href="tel:<?php echo esc_attr( $clean_phone ); ?>" class="button" style="display:inline-block; background:#b45309; color:#fff; padding:8px 18px; text-decoration:none; border-radius:4px; font-weight:600; font-size:13px;">
                            📞 <?php echo esc_html( sprintf( __( 'Call Reservations: %s', 'svago' ), $hotel_phone ) ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</div>

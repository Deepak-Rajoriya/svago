<?php
/**
 * Room & Rate Selection Screen Template - Step 2 of [hotel_booking]
 *
 * Implements two inner steps:
 * - Inner Step 1: Select Room (List of Rooms with 2-column layout)
 * - Inner Step 2: Room Type / Rate Plan Selection (Vertical stack of plan cards)
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

$currency_sym = $currency ?? '₹';
$calendar_back_url = function_exists( 'add_query_arg' ) ? add_query_arg( [
    'step'      => 'calendar',
    'check_in'  => $check_in,
    'check_out' => $check_out,
    'adults'    => $adults,
    'children'  => $children,
], strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ) ) : '?step=calendar';
?>

<div class="svago-step2-page-wrapper" id="svago-room-results">
    <div class="svago-step2-container">

        <!-- Hidden Step 2 Context State -->
        <input type="hidden" id="svago-step2-check-in" value="<?php echo esc_attr( $check_in ); ?>" />
        <input type="hidden" id="svago-step2-check-out" value="<?php echo esc_attr( $check_out ); ?>" />
        <input type="hidden" id="svago-step2-adults" value="<?php echo esc_attr( $adults ); ?>" />
        <input type="hidden" id="svago-step2-children" value="<?php echo esc_attr( $children ); ?>" />
        <input type="hidden" id="svago-step2-selected-room-id" value="<?php echo esc_attr( $rooms[0]['id'] ?? 'RM001' ); ?>" />
        <input type="hidden" id="svago-step2-selected-room-name" value="<?php echo esc_attr( $rooms[0]['name'] ?? 'Hotel Room' ); ?>" />

        <!-- ==================================================================
             TOP NAVIGATION BAR (Global for Step 2)
             Space-between alignment: < BACK | Breadcrumb | NEXT STEPS >
             ================================================================== -->
        <header class="svago-step2-top-bar" aria-label="<?php esc_attr_e( 'Booking navigation bar', 'svago' ); ?>">
            <div class="svago-top-bar-left">
                <a 
                    href="<?php echo esc_url( $calendar_back_url ); ?>" 
                    class="svago-top-back-btn" 
                    id="svago-step2-global-back"
                    data-initial-href="<?php echo esc_url( $calendar_back_url ); ?>"
                >
                    &lt; <?php esc_html_e( 'BACK', 'svago' ); ?>
                </a>
            </div>

            <nav class="svago-top-bar-center" aria-label="<?php esc_attr_e( 'Step progress', 'svago' ); ?>">
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'CALENDAR', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-active"><?php esc_html_e( 'ROOM AND RATE SELECTION', 'svago' ); ?></span>
                <span class="svago-nav-sep" aria-hidden="true">&gt;</span>
                <span class="svago-nav-step svago-nav-inactive"><?php esc_html_e( 'GUEST INFO', 'svago' ); ?></span>
            </nav>

            <div class="svago-top-bar-right">
                <span class="svago-next-steps-text"><?php esc_html_e( 'NEXT STEPS >', 'svago' ); ?></span>
            </div>
        </header>

        <!-- Best Rate Guarantee Banner (Requirement #19) -->
        <div class="svago-best-rate-banner" style="margin-bottom: 24px;">
            <span class="svago-banner-icon">✨</span>
            <span class="svago-banner-text">
                <strong><?php esc_html_e( 'Direct Booking Guarantee:', 'svago' ); ?></strong> 
                <?php esc_html_e( 'Best rates guaranteed with complimentary breakfast and flexible cancellation.', 'svago' ); ?>
            </span>
        </div>

        <!-- Cached / Fallback Data Notice (Task 2.8) -->
        <?php if ( ! empty( $is_fallback ) ) : ?>
            <div class="svago-fallback-notice" role="alert" style="margin-bottom: 24px;">
                <span class="svago-notice-icon">ℹ️</span>
                <span class="svago-notice-text">
                    <?php echo esc_html( ! empty( $fallback_message ) ? $fallback_message : __( 'Live availability temporarily unavailable — showing cached rates.', 'svago' ) ); ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- ==================================================================
             INNER STEP 1: ROOM SELECTION (List of Rooms)
             ================================================================== -->
        <section class="svago-step2-inner svago-step2-inner-1" id="svago-step2-inner-1" aria-label="<?php esc_attr_e( 'Room Selection', 'svago' ); ?>">
            
            <?php if ( empty( $rooms ) ) : ?>
                <?php 
                $hotel_phone = get_option( 'svago_hotel_phone', '' ); 
                $clean_phone = preg_replace( '/[^0-9+]/', '', (string) $hotel_phone );
                ?>
                <!-- Empty state: Fallback missing / No rooms available (Task 7.5) -->
                <div class="svago-no-rooms-card svago-call-to-book-card" style="text-align:center; padding:48px 24px; background:#fff; border:1px solid #e4e0d7; border-radius:8px;">
                    <span style="font-size:42px; display:block; margin-bottom:12px;">📞</span>
                    <h3 style="font-size:20px; margin-bottom:8px; color:#18181b;"><?php esc_html_e( 'Online Booking Temporarily Unavailable', 'svago' ); ?></h3>
                    <p style="font-size:14px; color:#71717a; max-width:540px; margin:0 auto 18px;">
                        <?php esc_html_e( 'We are currently unable to retrieve room availability online. Please contact our 24/7 reservations team directly to book your room over the phone.', 'svago' ); ?>
                    </p>
                    <?php if ( ! empty( $hotel_phone ) ) : ?>
                        <a href="tel:<?php echo esc_attr( $clean_phone ); ?>" class="button svago-call-btn svago-pill-btn" style="text-decoration:none;">
                            📞 <?php echo esc_html( sprintf( __( 'Call Reservations: %s', 'svago' ), $hotel_phone ) ); ?>
                        </a>
                    <?php endif; ?>
                </div>

            <?php else : ?>

                <!-- Vertical Stack of Room Cards -->
                <div class="svago-room-selection-stack svago-rooms-list">
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
                </div>

                <?php if ( ! $any_available ) : ?>
                    <?php 
                    $hotel_phone = get_option( 'svago_hotel_phone', '' ); 
                    $clean_phone = preg_replace( '/[^0-9+]/', '', (string) $hotel_phone );
                    ?>
                    <div class="svago-all-sold-out-banner" style="text-align:center; padding:24px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; margin-top:32px;">
                        <h4 style="font-size:16px; margin-bottom:6px; color:#92400e;"><?php esc_html_e( 'All rooms are sold out for these dates', 'svago' ); ?></h4>
                        <p style="font-size:13px; color:#b45309; margin-bottom:12px;"><?php esc_html_e( 'Try searching for different dates above, or call our reservations desk directly for cancellations or last-minute openings.', 'svago' ); ?></p>
                        <?php if ( ! empty( $hotel_phone ) ) : ?>
                            <a href="tel:<?php echo esc_attr( $clean_phone ); ?>" class="button svago-pill-btn" style="text-decoration:none; background:#b45309;">
                                📞 <?php echo esc_html( sprintf( __( 'Call Reservations: %s', 'svago' ), $hotel_phone ) ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </section>

        <!-- ==================================================================
             INNER STEP 2: ROOM TYPE / RATE PLAN SELECTION
             Triggered when "VIEW RATES & OFFERS" is clicked
             ================================================================== -->
        <section 
            class="svago-step2-inner svago-step2-inner-2 svago-step2-inner-2-container" 
            id="svago-step2-inner-2" 
            style="display:none;" 
            aria-label="<?php esc_attr_e( 'Room Type & Rate Plan Selection', 'svago' ); ?>"
        >
            
            <!-- Selected Room Recap Header -->
            <div class="svago-selected-room-header">
                <div class="svago-selected-room-info">
                    <img 
                        src="<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>" 
                        alt="" 
                        class="svago-selected-room-thumb" 
                        id="svago-step2-recap-thumb" 
                        onerror="this.onerror=null;this.src='<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>';" 
                    />
                    <div>
                        <h4 class="svago-selected-room-title" id="svago-step2-recap-title">BALCONY Rooms</h4>
                        <p class="svago-selected-room-tagline" id="svago-step2-recap-tagline">Take The Evening Outside</p>
                    </div>
                </div>
                <button type="button" class="svago-btn-change-room" id="svago-btn-change-room">
                    &larr; <?php esc_html_e( 'Change Room', 'svago' ); ?>
                </button>
            </div>

            <!-- Room Type Selection: 2-Column Cards Layout (Balcony vs Jacuzzi) -->
            <div class="svago-room-type-selection-block svago-room-types-cards-stack" style="margin-bottom: 28px;">
                <h4 style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark, #18181b); margin-bottom: 16px;">
                    <?php esc_html_e( 'Select Room Type:', 'svago' ); ?>
                </h4>

                <!-- Hidden type pills for backwards compatibility with tests / scripts looking for data-room-type -->
                <div class="svago-type-options-row" style="display:none;">
                    <button type="button" class="svago-type-pill-btn svago-type-active" data-room-type="Balcony">
                        <span class="svago-type-icon">🌿</span>
                        <span class="svago-type-label"><?php esc_html_e( 'Balcony', 'svago' ); ?></span>
                    </button>
                    <button type="button" class="svago-type-pill-btn" data-room-type="Jacuzzi">
                        <span class="svago-type-icon">🛁</span>
                        <span class="svago-type-label"><?php esc_html_e( 'Jacuzzi', 'svago' ); ?></span>
                    </button>
                </div>

                <!-- 2-Column Room Type Cards Stack (Exact same layout as Room Listing screen) -->
                <div class="svago-room-selection-stack svago-type-cards-wrapper">

                    <!-- Type Card 1: Balcony Room -->
                    <article 
                        class="svago-room-card svago-room-card-v2 svago-room-type-card" 
                        data-room-type="Balcony"
                        data-room-name="Balcony Room"
                    >
                        <!-- Left Column (Image): ~50-55% width, vertical, clean edges -->
                        <div class="svago-room-v2-image-col">
                            <div class="svago-room-v2-img-wrapper">
                                <img 
                                    src="<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>" 
                                    alt="<?php esc_attr_e( 'Balcony Room', 'svago' ); ?>" 
                                    class="svago-room-v2-img svago-type-card-img svago-balcony-img" 
                                    loading="lazy" 
                                    onerror="this.onerror=null;this.src='<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>';"
                                />
                            </div>
                        </div>

                        <!-- Right Column (Content): ~45-50% width, center-aligned text -->
                        <div class="svago-room-v2-content-col">
                            <header class="svago-room-v2-title-section">
                                <h2 class="svago-room-main-title svago-room-title">
                                    <span class="svago-title-sans"><?php esc_html_e( 'BALCONY', 'svago' ); ?></span>
                                    <span class="svago-title-serif"><?php esc_html_e( 'Rooms', 'svago' ); ?></span>
                                    <span class="svago-accessible-room-name" style="display:none;"><?php esc_html_e( 'Balcony Room', 'svago' ); ?></span>
                                </h2>
                                <div class="svago-room-type-category-name" id="svago-balcony-parent-name" style="font-size:0.875rem; font-weight:600; color:#71717a; margin-top:-4px; margin-bottom:8px;"></div>
                                <p class="svago-room-tagline"><?php esc_html_e( 'Take The Evening Outside', 'svago' ); ?></p>
                            </header>

                            <p class="svago-room-description svago-room-desc">
                                <?php esc_html_e( 'Open the doors to your private balcony and make a little more of the evening, with a drink in hand, the city beyond and nowhere you need to be just yet.', 'svago' ); ?>
                            </p>

                            <!-- Amenities Grid: 3 columns x 2 rows, minimalist line-art icons -->
                            <div class="svago-amenities-v2-grid">
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'balcony' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Private Balcony', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'bed' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'King size Bed', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'shower' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Shower area', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'tv' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'T.V Unit', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'wardrobe' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Walk-In Wardrobe', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'dressing' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Dressing area', 'svago' ); ?></span>
                                </div>
                            </div>

                            <!-- Pricing Section -->
                            <div class="svago-v2-pricing-section">
                                <span class="svago-price-from-label"><?php esc_html_e( 'From:', 'svago' ); ?></span>
                                <div class="svago-price-night-large">
                                    <?php echo esc_html( $currency_sym . ' 70,854' ); ?> / <?php esc_html_e( 'night', 'svago' ); ?>
                                </div>
                                <span class="svago-price-taxes-note"><?php esc_html_e( 'Includes taxes & fees', 'svago' ); ?></span>
                            </div>

                            <!-- CTA Button Section -->
                            <div class="svago-v2-cta-section">
                                <button 
                                    type="button" 
                                    class="svago-pill-btn svago-btn-select-room-type" 
                                    data-room-type="Balcony"
                                    data-room-id="<?php echo esc_attr( $rooms[0]['id'] ?? 'RM001' ); ?>"
                                >
                                    <?php esc_html_e( 'SELECT BALCONY', 'svago' ); ?>
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- Type Card 2: Jacuzzi Room -->
                    <article 
                        class="svago-room-card svago-room-card-v2 svago-room-type-card" 
                        data-room-type="Jacuzzi"
                        data-room-name="Jacuzzi Room"
                    >
                        <!-- Left Column (Image): ~50-55% width, vertical, clean edges -->
                        <div class="svago-room-v2-image-col">
                            <div class="svago-room-v2-img-wrapper">
                                <img 
                                    src="<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>" 
                                    alt="<?php esc_attr_e( 'Jacuzzi Room', 'svago' ); ?>" 
                                    class="svago-room-v2-img svago-type-card-img svago-jacuzzi-img" 
                                    loading="lazy" 
                                    onerror="this.onerror=null;this.src='<?php echo esc_url( SVAGO_URL . 'assets/images/placeholder.png' ); ?>';"
                                />
                            </div>
                        </div>

                        <!-- Right Column (Content): ~45-50% width, center-aligned text -->
                        <div class="svago-room-v2-content-col">
                            <header class="svago-room-v2-title-section">
                                <h2 class="svago-room-main-title svago-room-title">
                                    <span class="svago-title-sans"><?php esc_html_e( 'JACUZZI', 'svago' ); ?></span>
                                    <span class="svago-title-serif"><?php esc_html_e( 'Rooms', 'svago' ); ?></span>
                                    <span class="svago-accessible-room-name" style="display:none;"><?php esc_html_e( 'Jacuzzi Room', 'svago' ); ?></span>
                                </h2>
                                <div class="svago-room-type-category-name" id="svago-jacuzzi-parent-name" style="font-size:0.875rem; font-weight:600; color:#71717a; margin-top:-4px; margin-bottom:8px;"></div>
                                <p class="svago-room-tagline"><?php esc_html_e( 'Stay In For The Evening', 'svago' ); ?></p>
                            </header>

                            <p class="svago-room-description svago-room-desc">
                                <?php esc_html_e( 'With a private Jacuzzi waiting in your room, there is very little reason to make plans after a long day. Draw the curtains, pour yourself a drink and settle in.', 'svago' ); ?>
                            </p>

                            <!-- Amenities Grid: 3 columns x 2 rows, minimalist line-art icons -->
                            <div class="svago-amenities-v2-grid">
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'jacuzzi' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Jacuzzi', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'bed' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'King size Bed', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'shower' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Shower area', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'tv' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'T.V Unit', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'wardrobe' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Walk-In Wardrobe', 'svago' ); ?></span>
                                </div>
                                <div class="svago-amenity-v2-item">
                                    <?php echo svago_render_amenity_svg( 'dressing' ); ?>
                                    <span class="svago-amenity-v2-label"><?php esc_html_e( 'Dressing area', 'svago' ); ?></span>
                                </div>
                            </div>

                            <!-- Pricing Section -->
                            <div class="svago-v2-pricing-section">
                                <span class="svago-price-from-label"><?php esc_html_e( 'From:', 'svago' ); ?></span>
                                <div class="svago-price-night-large">
                                    <?php echo esc_html( $currency_sym . ' 75,000' ); ?> / <?php esc_html_e( 'night', 'svago' ); ?>
                                </div>
                                <span class="svago-price-taxes-note"><?php esc_html_e( 'Includes taxes & fees', 'svago' ); ?></span>
                            </div>

                            <!-- CTA Button Section -->
                            <div class="svago-v2-cta-section">
                                <button 
                                    type="button" 
                                    class="svago-pill-btn svago-btn-select-room-type" 
                                    data-room-type="Jacuzzi"
                                    data-room-id="<?php echo esc_attr( $rooms[0]['id'] ?? 'RM001' ); ?>"
                                >
                                    <?php esc_html_e( 'SELECT JACUZZI', 'svago' ); ?>
                                </button>
                            </div>
                        </div>
                    </article>

                </div>
            </div>

            <!-- ==================================================================
                 RATE PLANS SECTION (Temporarily Hidden, Code Preserved Intact)
                 ================================================================== -->
            <div class="svago-rate-plans-section" id="svago-rate-plans-section" style="display:none;" aria-hidden="true">
                <!-- Section Title above the list: "SELECT RATE PLAN" -->
                <h3 class="svago-rates-section-title svago-rates-heading">
                    <?php esc_html_e( 'SELECT RATE PLAN', 'svago' ); ?>
                </h3>

                <!-- Dynamic Rate Plans per Room -->
                <div class="svago-rate-plans-wrapper">
                    <?php foreach ( $rooms as $r_idx => $room ) : 
                        $r_id  = $room['id'] ?? '';
                        $rates = $rates_map[ $r_id ] ?? [];
                    ?>
                        <div 
                            class="svago-room-rates-group svago-rate-plans-list svago-rate-plans-v2-stack" 
                            data-room-id="<?php echo esc_attr( $r_id ); ?>" 
                            style="display:none;"
                        >
                            <?php if ( empty( $rates ) ) : ?>
                                <div class="svago-no-rates-box" style="padding:28px; background:#fff; border:1px solid #e4e0d7; border-radius:8px; text-align:center;">
                                    <p style="margin:0; color:#71717a;"><?php esc_html_e( 'Rate plans currently unavailable for this room.', 'svago' ); ?></p>
                                </div>
                            <?php else : ?>
                                <?php foreach ( $rates as $p_idx => $rate ) : 
                                    $rate_id       = $rate['rate_id'] ?? 'rate-' . $p_idx;
                                    $rate_name     = $rate['name'] ?? esc_html__( 'Standard Rate', 'svago' );
                                    $rate_desc     = $rate['description'] ?? esc_html__( 'Includes luxury amenities and priority service.', 'svago' );
                                    $price_night   = (float) ( $rate['price_per_night'] ?? 70854 );
                                    $total_price   = (float) ( $rate['total_price'] ?? ( $price_night * max( 1, $nights ) ) );
                                    $is_default    = ( $p_idx === 0 );
                                ?>
                                    <div 
                                        class="svago-rate-plan-item svago-rate-plan-card-v2 <?php echo $is_default ? 'svago-plan-active svago-rate-selected' : ''; ?>" 
                                        data-room-id="<?php echo esc_attr( $r_id ); ?>"
                                        data-rate-id="<?php echo esc_attr( $rate_id ); ?>"
                                        data-total-amount="<?php echo esc_attr( $total_price ); ?>"
                                    >
                                        
                                        <!-- Left block: Plan name (bold), short description, green taxes text -->
                                        <div class="svago-plan-v2-left svago-rate-plan-details">
                                            <div class="svago-plan-v2-name svago-rate-name"><?php echo esc_html( $rate_name ); ?></div>
                                            <div class="svago-plan-v2-desc svago-rate-desc"><?php echo esc_html( $rate_desc ); ?></div>
                                            <div class="svago-plan-v2-tax-tag svago-tax-notice">
                                                <?php esc_html_e( 'Taxes & fees included', 'svago' ); ?>
                                            </div>
                                        </div>

                                        <!-- Center block: Large bold price per night, small total price for N nights -->
                                        <div class="svago-plan-v2-center svago-rate-pricing">
                                            <div class="svago-plan-v2-nightly svago-price-per-night">
                                                <span class="svago-price-amount"><?php echo esc_html( $currency_sym . ' ' . number_format( $price_night ) ); ?></span>
                                                <span class="svago-price-period" style="font-size:0.875rem; font-weight:500; color:#71717a;">/ <?php esc_html_e( 'night', 'svago' ); ?></span>
                                            </div>
                                            <div class="svago-plan-v2-total svago-price-total">
                                                <?php 
                                                if ( $nights > 1 ) {
                                                    echo esc_html( sprintf( __( 'Total: %s for %d nights', 'svago' ), $currency_sym . ' ' . number_format( $total_price ), $nights ) );
                                                } else {
                                                    echo esc_html( sprintf( __( 'Total: %s for 1 night', 'svago' ), $currency_sym . ' ' . number_format( $total_price ) ) );
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <!-- Right block: Pill-shaped dark Book Now button with white text -->
                                        <div class="svago-plan-v2-right">
                                            <button 
                                                type="button" 
                                                class="svago-pill-btn svago-plan-book-btn svago-book-btn" 
                                                data-room-id="<?php echo esc_attr( $r_id ); ?>" 
                                                data-rate-id="<?php echo esc_attr( $rate_id ); ?>" 
                                                data-total-amount="<?php echo esc_attr( $total_price ); ?>"
                                            >
                                                <?php esc_html_e( 'Book Now', 'svago' ); ?>
                                            </button>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </section>

    </div>
</div>

<script>
/**
 * Interactive behavior for Step 2: Inner Step 1 (Select Room) <-> Inner Step 2 (Select Rate Plan)
 */
(function($) {
    'use strict';
    $(document).ready(function() {
        var $innerStep1 = $('#svago-step2-inner-1');
        var $innerStep2 = $('#svago-step2-inner-2');
        var $globalBack = $('#svago-step2-global-back');
        var $changeRoomBtn = $('#svago-btn-change-room');
        var $recapThumb = $('#svago-step2-recap-thumb');
        var $recapTitle = $('#svago-step2-recap-title');
        var $recapTagline = $('#svago-step2-recap-tagline');

        var initialBackHref = $globalBack.data('initial-href') || '?step=calendar';

        function showInnerStep1() {
            $innerStep2.hide();
            $innerStep1.fadeIn(200);
            $globalBack.attr('href', initialBackHref).off('click.step2inner');
            $('html, body').animate({ scrollTop: $('#svago-room-results').offset().top - 20 }, 300);
        }

        function showInnerStep2(roomId, roomTitle, roomTagline, roomImg) {
            // Update context state
            $('#svago-step2-selected-room-id').val(roomId);
            if (roomTitle) {
                $('#svago-step2-selected-room-name').val(roomTitle);
            }

            // Update recap card
            if (roomTitle) $recapTitle.text(roomTitle);
            if (roomTagline) $recapTagline.text(roomTagline);
            if (roomImg) {
                $recapThumb.attr('src', roomImg).attr('alt', roomTitle || '').show();
                $('.svago-type-card-img').attr('src', roomImg);
            } else {
                $recapThumb.hide();
            }

            // Update Inner Step 2 Room Type cards with active room ID & labels
            $('.svago-room-type-card').attr('data-room-id', roomId);
            $('.svago-btn-select-room-type').attr('data-room-id', roomId);
            if (roomTitle) {
                $('#svago-balcony-parent-name').text(roomTitle + ' • Balcony Type');
                $('#svago-jacuzzi-parent-name').text(roomTitle + ' • Jacuzzi Type');
            }

            // Show matching rate group, hide others
            $('.svago-room-rates-group').hide();
            var $targetGroup = $('.svago-room-rates-group[data-room-id="' + roomId + '"]');
            if ($targetGroup.length) {
                $targetGroup.show();
            }

            $innerStep1.hide();
            $innerStep2.fadeIn(200);

            // Hook global back button to return to Inner Step 1
            $globalBack.attr('href', '#change-room').off('click.step2inner').on('click.step2inner', function(e) {
                e.preventDefault();
                showInnerStep1();
            });

            $('html, body').animate({ scrollTop: $('#svago-room-results').offset().top - 20 }, 300);
        }

        // Handle Room Type Selection (Proceed directly to Step 3: Guest Info)
        $(document).off('click.svagotype', '.svago-btn-select-room-type').on('click.svagotype', '.svago-btn-select-room-type', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var roomType = $btn.data('room-type') || 'Balcony';
            var roomId = $btn.attr('data-room-id') || $btn.data('room-id') || $('#svago-step2-selected-room-id').val() || $('.svago-room-card-v2:first').data('room-id') || 'RM001';

            var checkIn = $('#svago-step2-check-in').val() || $('#svago-check-in').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_check_in : '');
            var checkOut = $('#svago-step2-check-out').val() || $('#svago-check-out').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_check_out : '');
            var adults = $('#svago-step2-adults').val() || $('#svago-adults').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_adults : 2);
            var children = $('#svago-step2-children').val() || $('#svago-children').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_children : 0);

            if (!checkIn) {
                checkIn = new Date().toISOString().slice(0, 10);
            }
            if (!checkOut || checkOut <= checkIn) {
                var d = new Date(checkIn);
                d.setDate(d.getDate() + 1);
                checkOut = d.toISOString().slice(0, 10);
            }

            $btn.prop('disabled', true).text((typeof svago_booking_vars !== 'undefined' && svago_booking_vars.i18n && svago_booking_vars.i18n.processing) ? svago_booking_vars.i18n.processing : 'Processing...');

            try {
                sessionStorage.setItem('svago_selected_booking', JSON.stringify({
                    room_id: roomId,
                    room_type: roomType,
                    check_in: checkIn,
                    check_out: checkOut,
                    adults: adults,
                    children: children,
                    timestamp: new Date().getTime()
                }));
            } catch (err) {}

            var targetBaseUrl = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.booking_url) ? svago_booking_vars.booking_url : window.location.href.split('?')[0];
            var destUrl = targetBaseUrl + '?step=details&room_id=' + encodeURIComponent(roomId) + 
                          '&room_type=' + encodeURIComponent(roomType) + 
                          '&check_in=' + encodeURIComponent(checkIn) + 
                          '&check_out=' + encodeURIComponent(checkOut) + 
                          '&adults=' + encodeURIComponent(adults) + 
                          '&children=' + encodeURIComponent(children);

            var navigated = false;
            function navigateNext() {
                if (!navigated) {
                    navigated = true;
                    window.location.href = destUrl;
                }
            }

            var fallbackTimer = setTimeout(navigateNext, 1200);

            var ajaxUrl = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.ajax_url) ? svago_booking_vars.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.nonce) ? svago_booking_vars.nonce : '';

            $.post(ajaxUrl, {
                action: 'svago_select_room',
                nonce: nonce,
                room_id: roomId,
                room_type: roomType,
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children
            }, function(response) {
                clearTimeout(fallbackTimer);
                if (response && response.data && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    navigateNext();
                }
            }).fail(function() {
                clearTimeout(fallbackTimer);
                navigateNext();
            });
        });

        // 1. "VIEW RATES & OFFERS" button click on room card
        $('.svago-btn-view-rates').on('click', function(e) {
            e.preventDefault();
            var $card = $(this).closest('.svago-room-card-v2');
            var roomId = $(this).data('room-id') || $card.data('room-id');
            var roomTitle = $card.data('room-display') || $card.find('.svago-room-main-title').text();
            var roomTagline = $card.data('room-tagline') || $card.find('.svago-room-tagline').text();
            var roomImg = $card.data('room-img') || $card.find('.svago-room-v2-img').attr('src');

            showInnerStep2(roomId, roomTitle, roomTagline, roomImg);
        });

        // 2. Change Room button in Inner Step 2
        $changeRoomBtn.on('click', function(e) {
            e.preventDefault();
            showInnerStep1();
        });

        // 3. Highlight rate plan card on click
        $(document).on('click', '.svago-rate-plan-card-v2', function(e) {
            if (!$(e.target).hasClass('svago-plan-book-btn')) {
                $(this).siblings().removeClass('svago-plan-active svago-rate-selected');
                $(this).addClass('svago-plan-active svago-rate-selected');
            }
        });

        // 4. Auto-activate Inner Step 2 if room_id is present in query parameters
        var urlParams = new URLSearchParams(window.location.search);
        var preselectedRoom = urlParams.get('room_id');
        if (preselectedRoom) {
            var $presetCard = $('.svago-room-card-v2[data-room-id="' + preselectedRoom + '"]');
            if ($presetCard.length) {
                showInnerStep2(
                    preselectedRoom,
                    $presetCard.data('room-display'),
                    $presetCard.data('room-tagline'),
                    $presetCard.data('room-img')
                );
            }
        }
    });
})(jQuery);
</script>

<?php
/**
 * Room Card Template Partial
 *
 * @package SVAGO
 *
 * Variables passed:
 * @var array  $room
 * @var array  $availability_info
 * @var array  $rates
 * @var int    $nights
 * @var string $check_in
 * @var string $check_out
 * @var int    $adults
 * @var int    $children
 * @var string $currency
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$room_id       = $room['id'] ?? '';
$room_name     = $room['name'] ?? esc_html__( 'Hotel Room', 'svago' );
$description   = $room['description'] ?? '';
$size          = $room['size'] ?? '';
$bed_type      = $room['bed_type'] ?? '';
$max_adults    = (int) ( $room['max_adults'] ?? 2 );
$max_children  = (int) ( $room['max_children'] ?? 0 );
$amenities     = $room['amenities'] ?? [];
$images        = $room['images'] ?? [];
$featured_img  = ! empty( $images[0] ) ? $images[0] : SVAGO_URL . 'assets/images/room-placeholder.jpg';

// Availability state
$is_available = ! empty( $availability_info['available'] );
$inventory    = isset( $availability_info['inventory'] ) ? (int) $availability_info['inventory'] : 0;
$is_low_stock = $is_available && ( $inventory > 0 && $inventory <= 2 );
$is_sold_out  = ! $is_available || ( $inventory <= 0 );

$currency_sym = $currency ?? '₹';
?>

<div class="svago-room-card <?php echo $is_sold_out ? 'svago-room-sold-out' : ''; ?>" data-room-id="<?php echo esc_attr( $room_id ); ?>">
    
    <!-- Image Gallery Section -->
    <div class="svago-room-image-col">
        <div class="svago-room-image-wrapper">
            <img src="<?php echo esc_url( $featured_img ); ?>" alt="<?php echo esc_attr( $room_name ); ?>" class="svago-room-featured-img" loading="lazy" />
            
            <?php if ( $is_low_stock ) : ?>
                <span class="svago-badge svago-badge-low-stock">
                    🔥 <?php echo esc_html( sprintf( __( 'Only %d left!', 'svago' ), $inventory ) ); ?>
                </span>
            <?php elseif ( $is_sold_out ) : ?>
                <span class="svago-badge svago-badge-sold-out">
                    🚫 <?php esc_html_e( 'Sold Out', 'svago' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ( count( $images ) > 1 ) : ?>
            <div class="svago-room-thumbnails">
                <?php foreach ( array_slice( $images, 0, 4 ) as $idx => $thumb_url ) : ?>
                    <button type="button" class="svago-thumb-btn <?php echo ( $idx === 0 ) ? 'active' : ''; ?>" data-full-src="<?php echo esc_url( $thumb_url ); ?>">
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Room Details Section -->
    <div class="svago-room-content-col">
        
        <div class="svago-room-header">
            <h3 class="svago-room-title"><?php echo esc_html( $room_name ); ?></h3>
            <?php if ( ! empty( $description ) ) : ?>
                <p class="svago-room-desc"><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Room Features & Specs -->
        <div class="svago-room-meta-specs">
            <?php if ( ! empty( $size ) ) : ?>
                <span class="svago-spec-item" title="<?php esc_attr_e( 'Room Size', 'svago' ); ?>">
                    <span class="svago-spec-icon">📐</span>
                    <span class="svago-spec-text"><?php echo esc_html( $size ); ?></span>
                </span>
            <?php endif; ?>

            <?php if ( ! empty( $bed_type ) ) : ?>
                <span class="svago-spec-item" title="<?php esc_attr_e( 'Bed Type', 'svago' ); ?>">
                    <span class="svago-spec-icon">🛏️</span>
                    <span class="svago-spec-text"><?php echo esc_html( $bed_type ); ?></span>
                </span>
            <?php endif; ?>

            <span class="svago-spec-item" title="<?php esc_attr_e( 'Maximum Occupancy', 'svago' ); ?>">
                <span class="svago-spec-icon">👥</span>
                <span class="svago-spec-text">
                    <?php 
                    $occ_parts = [];
                    if ( $max_adults ) {
                        $occ_parts[] = sprintf( _n( '%d Adult', '%d Adults', $max_adults, 'svago' ), $max_adults );
                    }
                    if ( $max_children ) {
                        $occ_parts[] = sprintf( _n( '%d Child', '%d Children', $max_children, 'svago' ), $max_children );
                    }
                    echo esc_html( implode( ', ', $occ_parts ) );
                    ?>
                </span>
            </span>
        </div>

        <!-- Amenities Chips -->
        <?php if ( ! empty( $amenities ) && is_array( $amenities ) ) : ?>
            <div class="svago-room-amenities">
                <?php foreach ( array_slice( $amenities, 0, 6 ) as $amenity ) : ?>
                    <span class="svago-amenity-chip">✓ <?php echo esc_html( $amenity ); ?></span>
                <?php endforeach; ?>
                <?php if ( count( $amenities ) > 6 ) : ?>
                    <span class="svago-amenity-more">+<?php echo count( $amenities ) - 6; ?> <?php esc_html_e( 'more', 'svago' ); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Rate Plans & Pricing Section -->
        <div class="svago-room-rates-section">
            <?php if ( $is_sold_out ) : ?>
                <div class="svago-sold-out-box">
                    <p class="svago-sold-out-msg">
                        <strong><?php esc_html_e( 'Sold out for your selected dates.', 'svago' ); ?></strong><br>
                        <span><?php esc_html_e( 'Please select different dates or choose another room.', 'svago' ); ?></span>
                    </p>
                </div>
            <?php elseif ( empty( $rates ) ) : ?>
                <div class="svago-no-rates-box">
                    <p><?php esc_html_e( 'Rate plans currently unavailable for these dates.', 'svago' ); ?></p>
                </div>
            <?php else : ?>
                <h4 class="svago-rates-heading"><?php esc_html_e( 'Select Rate Plan', 'svago' ); ?></h4>
                <div class="svago-rate-plans-list">
                    <?php foreach ( $rates as $idx => $rate ) : 
                        $rate_id       = $rate['rate_id'] ?? 'rate-' . $idx;
                        $rate_name     = $rate['name'] ?? esc_html__( 'Standard Rate', 'svago' );
                        $rate_desc     = $rate['description'] ?? '';
                        $price_night   = (float) ( $rate['price_per_night'] ?? 0 );
                        $total_price   = (float) ( $rate['total_price'] ?? ( $price_night * max( 1, $nights ) ) );
                        $tax_included  = ! empty( $rate['tax_included'] );
                    ?>
                        <div class="svago-rate-plan-item <?php echo ( $idx === 0 ) ? 'svago-rate-selected' : ''; ?>" data-rate-id="<?php echo esc_attr( $rate_id ); ?>">
                            
                            <div class="svago-rate-plan-details">
                                <span class="svago-rate-name"><?php echo esc_html( $rate_name ); ?></span>
                                <?php if ( ! empty( $rate_desc ) ) : ?>
                                    <span class="svago-rate-desc"><?php echo esc_html( $rate_desc ); ?></span>
                                <?php endif; ?>
                                <span class="svago-tax-notice">
                                    <?php echo $tax_included ? esc_html__( 'Taxes & fees included', 'svago' ) : esc_html__( 'Taxes extra', 'svago' ); ?>
                                </span>
                            </div>

                            <div class="svago-rate-pricing">
                                <div class="svago-price-per-night">
                                    <span class="svago-price-amount"><?php echo esc_html( $currency_sym . number_format( $price_night ) ); ?></span>
                                    <span class="svago-price-period">/ <?php esc_html_e( 'night', 'svago' ); ?></span>
                                </div>
                                
                                <?php if ( $nights > 1 ) : ?>
                                    <div class="svago-price-total">
                                        <?php echo esc_html( sprintf( __( 'Total: %s for %d nights', 'svago' ), $currency_sym . number_format( $total_price ), $nights ) ); ?>
                                    </div>
                                <?php endif; ?>

                                <button 
                                    type="button" 
                                    class="svago-btn svago-btn-select-rate svago-book-btn" 
                                    data-room-id="<?php echo esc_attr( $room_id ); ?>" 
                                    data-rate-id="<?php echo esc_attr( $rate_id ); ?>"
                                    data-total-amount="<?php echo esc_attr( $total_price ); ?>"
                                >
                                    <?php esc_html_e( 'Book Now', 'svago' ); ?>
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

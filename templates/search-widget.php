<?php
/**
 * Search Widget Template
 *
 * @package SVAGO
 *
 * Variables passed from Shortcodes class:
 * @var array  $atts
 * @var string $check_in
 * @var string $check_out
 * @var int    $adults
 * @var int    $children
 * @var array  $child_ages
 * @var string $promo_code
 * @var string $action_url
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$min_date = date( 'Y-m-d' );
$default_check_in = ! empty( $check_in ) ? $check_in : date( 'Y-m-d', strtotime( '+1 day' ) );
$default_check_out = ! empty( $check_out ) ? $check_out : date( 'Y-m-d', strtotime( '+3 days' ) );
?>

<div class="svago-search-widget-container" id="svago-search-widget">
    <form class="svago-search-form" method="GET" action="<?php echo esc_url( $action_url ); ?>" data-ajax-search="true">
        <div class="svago-search-grid">
            
            <!-- Check-in Date -->
            <div class="svago-form-group svago-date-group">
                <label for="svago-check-in">
                    <span class="svago-icon-calendar">📅</span>
                    <?php esc_html_e( 'Check-in', 'svago' ); ?>
                </label>
                <input 
                    type="date" 
                    id="svago-check-in" 
                    name="check_in" 
                    class="svago-input svago-datepicker" 
                    value="<?php echo esc_attr( $default_check_in ); ?>" 
                    min="<?php echo esc_attr( $min_date ); ?>" 
                    required 
                />
            </div>

            <!-- Check-out Date -->
            <div class="svago-form-group svago-date-group">
                <label for="svago-check-out">
                    <span class="svago-icon-calendar">📅</span>
                    <?php esc_html_e( 'Check-out', 'svago' ); ?>
                </label>
                <input 
                    type="date" 
                    id="svago-check-out" 
                    name="check_out" 
                    class="svago-input svago-datepicker" 
                    value="<?php echo esc_attr( $default_check_out ); ?>" 
                    min="<?php echo esc_attr( $default_check_in ); ?>" 
                    required 
                />
            </div>

            <!-- Adults -->
            <div class="svago-form-group svago-guests-group">
                <label for="svago-adults">
                    <span class="svago-icon-user">👤</span>
                    <?php esc_html_e( 'Adults', 'svago' ); ?>
                </label>
                <select id="svago-adults" name="adults" class="svago-select">
                    <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                        <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $adults, $i ); ?>>
                            <?php echo esc_html( sprintf( _n( '%d Adult', '%d Adults', $i, 'svago' ), $i ) ); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Children -->
            <div class="svago-form-group svago-children-group">
                <label for="svago-children">
                    <span class="svago-icon-child">🧒</span>
                    <?php esc_html_e( 'Children', 'svago' ); ?>
                </label>
                <select id="svago-children" name="children" class="svago-select svago-children-select">
                    <?php for ( $i = 0; $i <= 5; $i++ ) : ?>
                        <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $children, $i ); ?>>
                            <?php echo $i === 0 ? esc_html__( '0 Children', 'svago' ) : esc_html( sprintf( _n( '%d Child', '%d Children', $i, 'svago' ), $i ) ); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Promo Code (Optional) -->
            <div class="svago-form-group svago-promo-group">
                <label for="svago-promo-code">
                    <span class="svago-icon-promo">🏷️</span>
                    <?php esc_html_e( 'Promo Code', 'svago' ); ?>
                </label>
                <input 
                    type="text" 
                    id="svago-promo-code" 
                    name="promo_code" 
                    class="svago-input" 
                    placeholder="<?php esc_attr_e( 'Optional', 'svago' ); ?>" 
                    value="<?php echo esc_attr( $promo_code ); ?>" 
                />
            </div>

            <!-- Search Button -->
            <div class="svago-form-group svago-submit-group">
                <button type="submit" class="svago-btn svago-btn-primary svago-search-submit">
                    <span class="svago-btn-text"><?php esc_html_e( 'Check Availability', 'svago' ); ?></span>
                    <span class="svago-spinner" aria-hidden="true"></span>
                </button>
            </div>

        </div>

        <!-- Dynamic Child Ages Container (Visible only when children > 0) -->
        <div class="svago-child-ages-wrapper" id="svago-child-ages-container" style="<?php echo ( $children > 0 ) ? '' : 'display:none;'; ?>">
            <span class="svago-child-ages-title"><?php esc_html_e( 'Ages of children at check-in:', 'svago' ); ?></span>
            <div class="svago-child-ages-list">
                <?php for ( $c = 1; $c <= 5; $c++ ) : 
                    $curr_age = $child_ages[ $c - 1 ] ?? 5;
                    $visible = ( $c <= $children );
                ?>
                    <div class="svago-child-age-item svago-child-age-<?php echo esc_attr( $c ); ?>" style="<?php echo $visible ? '' : 'display:none;'; ?>">
                        <label for="svago-child-age-<?php echo esc_attr( $c ); ?>">
                            <?php echo esc_html( sprintf( __( 'Child %d Age', 'svago' ), $c ) ); ?>
                        </label>
                        <select 
                            id="svago-child-age-<?php echo esc_attr( $c ); ?>" 
                            name="child_ages[]" 
                            class="svago-select svago-child-age-select" 
                            <?php echo $visible ? '' : 'disabled'; ?>
                        >
                            <option value="0" <?php selected( $curr_age, 0 ); ?>><?php esc_html_e( '< 1 yr', 'svago' ); ?></option>
                            <?php for ( $a = 1; $a <= 17; $a++ ) : ?>
                                <option value="<?php echo esc_attr( $a ); ?>" <?php selected( $curr_age, $a ); ?>>
                                    <?php echo esc_html( sprintf( _n( '%d yr', '%d yrs', $a, 'svago' ), $a ) ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

    </form>
</div>

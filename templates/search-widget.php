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
        <input type="hidden" name="step" value="calendar" />
        <div class="svago-search-grid">
            
            <!-- Check-in Date -->
            <div class="svago-form-group svago-date-group">
                <label for="svago-check-in">
                    <span class="svago-icon-calendar">📅</span>
                    <?php esc_html_e( 'Check - In', 'svago' ); ?>
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
                    <?php esc_html_e( 'Check - out', 'svago' ); ?>
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

            <!-- Room & Guests (Adults) -->
            <div class="svago-form-group svago-guests-group">
                <label for="svago-adults">
                    <span class="svago-icon-user">👤</span>
                    <?php esc_html_e( 'Room & Guests', 'svago' ); ?>
                </label>
                <select id="svago-adults" name="adults" class="svago-select">
                    <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                        <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $adults, $i ); ?>>
                            <?php echo esc_html( sprintf( _n( '%d Adult', '%d Adults', $i, 'svago' ), $i ) ); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Search Button -->
            <div class="svago-form-group svago-submit-group">
                <button type="submit" class="svago-btn svago-btn-primary svago-search-submit">
                    <span class="svago-btn-text"><?php esc_html_e( 'SEARCH', 'svago' ); ?></span>
                    <span class="svago-spinner" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </form>
</div>

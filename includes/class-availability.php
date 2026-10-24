<?php
/**
 * Room Availability Manager & Room/Rate Mapping UI
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles room availability management, date-by-date inventory overrides,
 * OTA availability pushing, and room/rate mapping overview.
 */
final class Availability {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register actions and filters
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
        add_action( 'wp_ajax_svago_save_availability', [ $this, 'ajax_save_availability' ] );
        add_action( 'wp_ajax_svago_load_room_availability', [ $this, 'ajax_load_room_availability' ] );
        add_action( 'wp_ajax_svago_auto_map_sync', [ $this, 'ajax_auto_map_sync' ] );
        add_action( 'wp_ajax_svago_manual_map_room', [ $this, 'ajax_manual_map_room' ] );
    }

    /**
     * Register Admin Submenus (Task 6.1 & Task 6.6)
     */
    public function register_admin_menus(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

        global $submenu;
        $avail_registered   = false;
        $mapping_registered = false;

        if ( isset( $submenu['svago-dashboard'] ) ) {
            foreach ( $submenu['svago-dashboard'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'svago-availability' ) {
                    $avail_registered = true;
                }
                if ( isset( $item[2] ) && $item[2] === 'svago-room-mapping' ) {
                    $mapping_registered = true;
                }
            }
        }

        // 1. Availability Manager (Task 6.1)
        if ( ! $avail_registered ) {
            add_submenu_page(
                'svago-dashboard',
                __( 'Room Availability Manager', 'svago' ),
                __( 'Availability', 'svago' ),
                $cap,
                'svago-availability',
                [ $this, 'render_availability_page' ]
            );
        }

        // 2. Room & Rate Mapping UI (Task 6.6)
        if ( ! $mapping_registered ) {
            add_submenu_page(
                'svago-dashboard',
                __( 'Room & Rate Mapping', 'svago' ),
                __( 'Room Mapping', 'svago' ),
                $cap,
                'svago-room-mapping',
                [ $this, 'render_room_mapping_page' ]
            );
        }
    }

    /**
     * Render Availability Manager screen (Task 6.1)
     */
    public function render_availability_page(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'svago' ) );
        }

        // Get rooms catalog
        $rooms = $this->get_available_rooms();
        $selected_room = isset( $_GET['room_id'] ) ? sanitize_text_field( $_GET['room_id'] ) : ( ! empty( $rooms ) ? $rooms[0]['id'] : '' );

        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : current_time( 'Y-m-d' );
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-d', strtotime( $start_date . ' +13 days' ) );

        // Handle POST save
        $save_notice = '';
        if ( isset( $_POST['svago_save_availability_nonce'] ) && check_admin_referer( 'svago_save_availability_action', 'svago_save_availability_nonce' ) ) {
            $post_room_id = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
            $post_dates   = isset( $_POST['dates'] ) && is_array( $_POST['dates'] ) ? $_POST['dates'] : [];

            if ( $post_room_id && ! empty( $post_dates ) ) {
                $res = $this->update_room_availability( $post_room_id, $post_dates );
                if ( ! empty( $res['success'] ) ) {
                    $save_notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
                } else {
                    $save_notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
                }
            }
        }

        // Fetch availability data for selected room and range
        $calendar_days = $this->build_availability_calendar( $selected_room, $start_date, $end_date );
        $nonce = wp_create_nonce( 'svago_booking_nonce' );
        ?>
        <div class="wrap svago-availability-wrap">
            <h1><?php esc_html_e( 'Room Availability & Inventory Manager', 'svago' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Manage daily room inventory, nightly rates, and stop-sell blocks. Updates are automatically synced to WooCommerce product meta and pushed to the OTA Channel Manager.', 'svago' ); ?>
            </p>

            <?php echo $save_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <!-- Filter Controls Form -->
            <form method="get" action="" class="svago-availability-filter-bar" style="background:#fff; padding:16px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:20px; display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="svago-availability" />

                <div>
                    <label for="svago_room_select" style="font-weight:600; display:block; margin-bottom:4px;">
                        <?php esc_html_e( 'Select Room:', 'svago' ); ?>
                    </label>
                    <select id="svago_room_select" name="room_id" style="min-width:220px;">
                        <?php foreach ( $rooms as $r ) : ?>
                            <option value="<?php echo esc_attr( $r['id'] ); ?>" <?php selected( $selected_room, $r['id'] ); ?>>
                                <?php echo esc_html( $r['name'] . ' (' . $r['id'] . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="svago_start_date" style="font-weight:600; display:block; margin-bottom:4px;">
                        <?php esc_html_e( 'From Date:', 'svago' ); ?>
                    </label>
                    <input type="date" id="svago_start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" required />
                </div>

                <div>
                    <label for="svago_end_date" style="font-weight:600; display:block; margin-bottom:4px;">
                        <?php esc_html_e( 'To Date:', 'svago' ); ?>
                    </label>
                    <input type="date" id="svago_end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" required />
                </div>

                <div>
                    <button type="submit" class="button button-primary">
                        🔍 <?php esc_html_e( 'View Availability', 'svago' ); ?>
                    </button>
                </div>
            </form>

            <!-- Bulk Update Tool Card -->
            <div class="svago-bulk-box" style="background:#f9fafb; border:1px solid #e5e7eb; border-left:4px solid #2271b1; padding:14px 18px; margin-bottom:20px; border-radius:4px;">
                <h3 style="margin-top:0; margin-bottom:8px; font-size:14px; color:#1e293b;">⚡ <?php esc_html_e( 'Quick Bulk Edit for Range', 'svago' ); ?></h3>
                <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <div>
                        <label style="font-size:12px; display:block;"><?php esc_html_e( 'Set All Inventory to:', 'svago' ); ?></label>
                        <input type="number" id="svago-bulk-inv" min="0" max="100" placeholder="e.g. 5" style="width:90px;" />
                    </div>
                    <div>
                        <label style="font-size:12px; display:block;"><?php esc_html_e( 'Set All Rates (₹):', 'svago' ); ?></label>
                        <input type="number" id="svago-bulk-price" min="0" step="50" placeholder="e.g. 12500" style="width:110px;" />
                    </div>
                    <div style="padding-top:16px;">
                        <button type="button" id="svago-apply-bulk-btn" class="button button-secondary">
                            <?php esc_html_e( 'Apply to Table Below', 'svago' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Availability Editor Table Form -->
            <form method="post" action="">
                <?php wp_nonce_field( 'svago_save_availability_action', 'svago_save_availability_nonce' ); ?>
                <input type="hidden" name="room_id" value="<?php echo esc_attr( $selected_room ); ?>" />

                <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:130px;"><?php esc_html_e( 'Date', 'svago' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Day', 'svago' ); ?></th>
                            <th style="width:140px;"><?php esc_html_e( 'Inventory Available', 'svago' ); ?></th>
                            <th style="width:140px;"><?php esc_html_e( 'Nightly Rate (₹)', 'svago' ); ?></th>
                            <th style="width:130px;"><?php esc_html_e( 'Status', 'svago' ); ?></th>
                            <th><?php esc_html_e( 'Stop-Sell / Block', 'svago' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $calendar_days ) ) : ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e( 'No dates in selected range.', 'svago' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $calendar_days as $day ) : ?>
                                <?php
                                $d = $day['date'];
                                $is_sold_out = empty( $day['available'] ) || $day['inventory'] <= 0;
                                $day_class = $day['is_weekend'] ? 'background-color:#f8fafc;' : '';
                                ?>
                                <tr style="<?php echo esc_attr( $day_class ); ?>">
                                    <td>
                                        <strong><?php echo esc_html( $d ); ?></strong>
                                    </td>
                                    <td>
                                        <span style="<?php echo $day['is_weekend'] ? 'color:#2563eb; font-weight:600;' : ''; ?>">
                                            <?php echo esc_html( $day['day_name'] ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="dates[<?php echo esc_attr( $d ); ?>][inventory]" 
                                            class="svago-input-inv"
                                            value="<?php echo esc_attr( $day['inventory'] ); ?>" 
                                            min="0" 
                                            max="100" 
                                            style="width:80px;" 
                                        />
                                    </td>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="dates[<?php echo esc_attr( $d ); ?>][price]" 
                                            class="svago-input-price"
                                            value="<?php echo esc_attr( $day['price'] ); ?>" 
                                            min="0" 
                                            step="10" 
                                            style="width:110px;" 
                                        />
                                    </td>
                                    <td>
                                        <?php if ( $day['blocked'] ) : ?>
                                            <span style="color:#b91c1c; font-weight:600;">🚫 <?php esc_html_e( 'Blocked', 'svago' ); ?></span>
                                        <?php elseif ( $is_sold_out ) : ?>
                                            <span style="color:#c2410c; font-weight:600;">⚠️ <?php esc_html_e( 'Sold Out', 'svago' ); ?></span>
                                        <?php else : ?>
                                            <span style="color:#15803d; font-weight:600;">✓ <?php echo esc_html( sprintf( __( '%d Open', 'svago' ), $day['inventory'] ) ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <label>
                                            <input 
                                                type="checkbox" 
                                                name="dates[<?php echo esc_attr( $d ); ?>][blocked]" 
                                                value="1" 
                                                class="svago-input-blocked"
                                                <?php checked( ! empty( $day['blocked'] ) ); ?> 
                                            />
                                            <?php esc_html_e( 'Block this date', 'svago' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px; display:flex; align-items:center; gap:16px;">
                    <button type="submit" class="button button-primary button-large" style="padding:4px 20px;">
                        💾 <?php esc_html_e( 'Save & Push Availability Changes', 'svago' ); ?>
                    </button>
                    <span style="font-size:12px; color:#64748b;">
                        <?php esc_html_e( 'Changes will update local inventory and be dispatched to the OTA Channel Manager via push_availability().', 'svago' ); ?>
                    </span>
                </div>
            </form>
        </div>

        <script>
            jQuery(function($) {
                $('#svago-apply-bulk-btn').on('click', function(e) {
                    e.preventDefault();
                    var bulkInv = $('#svago-bulk-inv').val();
                    var bulkPrice = $('#svago-bulk-price').val();

                    if (bulkInv !== '') {
                        $('.svago-input-inv').val(bulkInv);
                    }
                    if (bulkPrice !== '') {
                        $('.svago-input-price').val(bulkPrice);
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Render Room & Rate Mapping screen (Task 6.6 & Auto-Map & Sync Workflow)
     */
    public function render_room_mapping_page(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'svago' ) );
        }

        $rooms           = $this->get_available_rooms();
        $mapped_products = $this->get_mapped_wc_products();
        $loader          = new Fallback_Loader();
        $rates_catalog   = $loader->load_rates();
        $nonce           = wp_create_nonce( 'svago_booking_nonce' );
        ?>
        <div class="wrap svago-mapping-wrap">
            <h1 style="display:flex; align-items:center; gap:8px;">
                <span>🏨</span> <?php esc_html_e( 'Room & Rate Mapping Overview', 'svago' ); ?>
            </h1>
            <p class="description" style="font-size:14px; margin-top:4px;">
                <?php esc_html_e( 'Connect and synchronize your WooCommerce Products with OTA Room Identifiers, Rate Plans, and live inventory.', 'svago' ); ?>
            </p>

            <!-- Auto-Map & Sync Hero Action Card -->
            <div style="background:#fff; border:1px solid #c3c4c7; border-left:4px solid #2563eb; padding:18px 24px; border-radius:6px; margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div style="max-width:760px;">
                    <h3 style="margin:0 0 6px 0; font-size:16px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                        <span>⚡</span> <?php esc_html_e( 'Auto-Map & Sync Rooms', 'svago' ); ?>
                    </h3>
                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                        <?php esc_html_e( 'One-click automated synchronization: fetches room definitions from the OTA API (or mock catalog), creates corresponding WooCommerce products (with descriptions, amenities, and room specifications), maps OTA Room IDs, and syncs initial availability and pricing. You can still manually adjust WooCommerce products at any time without breaking sync.', 'svago' ); ?>
                    </p>
                </div>
                <div>
                    <button type="button" class="button button-primary button-hero" id="svago-auto-map-btn" style="display:flex; align-items:center; gap:8px; font-weight:700;">
                        <span class="dashicons dashicons-update" id="svago-auto-map-icon"></span>
                        <span id="svago-auto-map-text"><?php esc_html_e( 'Auto-Map & Sync', 'svago' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- Dynamic Alert Container -->
            <div id="svago-mapping-alert" style="display:none; margin-top:16px;"></div>

            <!-- Rooms Mapping Table -->
            <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <thead>
                    <tr>
                        <th style="width:110px; font-weight:700;"><?php esc_html_e( 'OTA Room ID', 'svago' ); ?></th>
                        <th style="width:200px; font-weight:700;"><?php esc_html_e( 'Room Details', 'svago' ); ?></th>
                        <th style="width:220px; font-weight:700;"><?php esc_html_e( 'WooCommerce Product', 'svago' ); ?></th>
                        <th style="font-weight:700;"><?php esc_html_e( 'Rate Plans', 'svago' ); ?></th>
                        <th style="width:110px; font-weight:700;"><?php esc_html_e( 'Occupancy', 'svago' ); ?></th>
                        <th style="width:120px; font-weight:700;"><?php esc_html_e( 'Base Price', 'svago' ); ?></th>
                        <th style="width:110px; font-weight:700;"><?php esc_html_e( 'Status', 'svago' ); ?></th>
                        <th style="width:140px; font-weight:700;"><?php esc_html_e( 'Action', 'svago' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rooms ) ) : ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:30px;">
                                <div style="font-size:14px; color:#64748b;"><?php esc_html_e( 'No rooms found in active provider catalog.', 'svago' ); ?></div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rooms as $room ) : ?>
                            <?php
                            $room_id  = $room['id'] ?? '';
                            $product  = $mapped_products[ $room_id ] ?? null;
                            $has_prod = ! empty( $product );
                            $rates    = $rates_catalog[ $room_id ] ?? ( $room['rates'] ?? [] );
                            if ( empty( $rates ) ) {
                                $rates = [ [ 'rate_id' => 'RT001', 'name' => 'Standard Rate', 'price_per_night' => ( $room['price'] ?? 50000 ) ] ];
                            }

                            // Calculate display base price
                            $base_price = 0;
                            if ( ! empty( $product['price'] ) ) {
                                $base_price = (float) $product['price'];
                            } elseif ( ! empty( $rates[0]['price_per_night'] ) ) {
                                $base_price = (float) $rates[0]['price_per_night'];
                            } elseif ( ! empty( $room['price'] ) ) {
                                $base_price = (float) $room['price'];
                            } elseif ( ! empty( $room['base_price'] ) ) {
                                $base_price = (float) $room['base_price'];
                            }
                            ?>
                            <tr>
                                <td style="vertical-align:top;">
                                    <code style="font-size:13px; font-weight:700; padding:3px 6px; background:#f1f5f9; border-radius:4px; color:#0f172a;"><?php echo esc_html( $room_id ); ?></code>
                                </td>
                                <td style="vertical-align:top;">
                                    <strong style="font-size:14px; color:#1e293b;"><?php echo esc_html( $room['name'] ?? '' ); ?></strong>
                                    <?php if ( ! empty( $room['size'] ) || ! empty( $room['bed_type'] ) ) : ?>
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                            <?php echo esc_html( trim( ( $room['size'] ?? '' ) . ' • ' . ( $room['bed_type'] ?? '' ), ' •' ) ); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align:top;">
                                    <?php if ( $has_prod ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $product['id'] ) ); ?>" style="font-weight:700; font-size:13px; color:#2563eb; text-decoration:none;" target="_blank">
                                            <?php echo esc_html( $product['title'] ); ?> <span style="color:#64748b; font-weight:normal;">(#<?php echo esc_html( $product['id'] ); ?>)</span>
                                        </a>
                                        <div style="font-size:12px; color:#15803d; font-weight:600; margin-top:3px;">
                                            ₹<?php echo esc_html( number_format( (float) $product['price'], 2 ) ); ?> <span style="font-weight:normal; color:#64748b;">(WC Price)</span>
                                        </div>
                                    <?php else : ?>
                                        <span style="color:#b45309; font-weight:600; font-size:12px;">⚠️ <?php esc_html_e( 'No WC Product Linked', 'svago' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align:top;">
                                    <?php foreach ( $rates as $rate ) : ?>
                                        <?php
                                        $r_id    = $rate['rate_id'] ?? ( $rate['id'] ?? '' );
                                        $r_name  = $rate['name'] ?? '';
                                        $r_price = ! empty( $rate['price_per_night'] ) ? (float) $rate['price_per_night'] : 0;
                                        ?>
                                        <div style="font-size:12px; margin-bottom:3px; color:#334155;">
                                            <code style="font-size:11px;"><?php echo esc_html( $r_id ); ?></code>: 
                                            <strong><?php echo esc_html( $r_name ); ?></strong>
                                            <?php if ( $r_price > 0 ) : ?>
                                                <span style="color:#059669;">(₹<?php echo esc_html( number_format( $r_price, 0 ) ); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td style="vertical-align:top; font-size:13px;">
                                    <?php echo esc_html( sprintf( __( '%d Adults, %d Ch', 'svago' ), $room['max_adults'] ?? 2, $room['max_children'] ?? 0 ) ); ?>
                                </td>
                                <td style="vertical-align:top;">
                                    <strong style="font-size:13px; color:#0f172a;">₹<?php echo esc_html( number_format( (float) $base_price, 2 ) ); ?></strong>
                                </td>
                                <td style="vertical-align:top;">
                                    <?php if ( $has_prod ) : ?>
                                        <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; background:#dcfce7; color:#15803d;">
                                            ✓ <?php esc_html_e( 'Linked', 'svago' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; background:#fef3c7; color:#b45309;">
                                            ⚠️ <?php esc_html_e( 'Unlinked', 'svago' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align:top;">
                                    <?php if ( $has_prod ) : ?>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <a href="<?php echo esc_url( get_edit_post_link( $product['id'] ) ); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e( 'Edit WooCommerce Product', 'svago' ); ?>">
                                                ✏️ <?php esc_html_e( 'Edit Product', 'svago' ); ?>
                                            </a>
                                            <button type="button" class="button button-small svago-unlink-btn" data-room-id="<?php echo esc_attr( $room_id ); ?>" style="color:#b91c1c;" title="<?php esc_attr_e( 'Unlink this WooCommerce Product', 'svago' ); ?>">
                                                🔗 <?php esc_html_e( 'Unlink', 'svago' ); ?>
                                            </button>
                                        </div>
                                    <?php else : ?>
                                        <button type="button" class="button button-small button-primary svago-single-sync-btn" data-room-id="<?php echo esc_attr( $room_id ); ?>" title="<?php esc_attr_e( 'Auto-create WooCommerce product for this room', 'svago' ); ?>">
                                            ⚡ <?php esc_html_e( 'Auto-Map', 'svago' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Instructions Card -->
            <div style="margin-top:28px; padding:18px 20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h3 style="margin-top:0; font-size:15px; color:#0f172a;"><?php esc_html_e( '💡 How Room Mapping Works:', 'svago' ); ?></h3>
                <ol style="margin-bottom:0; font-size:13px; color:#475569; padding-left:20px; line-height:1.6;">
                    <li><?php esc_html_e( 'Every hotel room is represented by a native WooCommerce Product linked to an OTA Room Identifier (e.g., RM001).', 'svago' ); ?></li>
                    <li><?php esc_html_e( 'Clicking "Auto-Map & Sync" automatically creates all missing rooms, populates their specifications and amenities, and maps them instantly.', 'svago' ); ?></li>
                    <li><?php esc_html_e( 'You can click "Edit" on any linked product to freely customize the room description, featured gallery photos, or pricing in WooCommerce without breaking the OTA connection.', 'svago' ); ?></li>
                    <li><?php esc_html_e( 'Direct website bookings seamlessly generate native WooCommerce orders with room metadata and sync inventory in real time.', 'svago' ); ?></li>
                </ol>
            </div>
        </div>

        <script>
            jQuery(function($) {
                var nonce = '<?php echo esc_js( $nonce ); ?>';

                // Auto-Map & Sync Button
                $('#svago-auto-map-btn').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var originalHtml = btn.html();
                    btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span>' + '<?php echo esc_js( __( 'Synchronizing Rooms & Products...', 'svago' ) ); ?>');
                    $('#svago-mapping-alert').hide().removeClass('notice-success notice-error notice');

                    $.post(ajaxurl, {
                        action: 'svago_auto_map_sync',
                        nonce: nonce
                    }, function(resp) {
                        if (resp && resp.success) {
                            $('#svago-mapping-alert')
                                .addClass('notice notice-success')
                                .html('<p><strong>✓ ' + (resp.data.message || 'Rooms synchronized successfully!') + '</strong> Reloading...</p>')
                                .slideDown(200);
                            setTimeout(function() {
                                window.location.reload();
                            }, 1200);
                        } else {
                            var msg = resp && resp.data && resp.data.message ? resp.data.message : 'Error syncing rooms.';
                            $('#svago-mapping-alert')
                                .addClass('notice notice-error')
                                .html('<p><strong>❌ Sync Failed:</strong> ' + msg + '</p>')
                                .slideDown(200);
                            btn.prop('disabled', false).html(originalHtml);
                        }
                    }).fail(function() {
                        $('#svago-mapping-alert')
                            .addClass('notice notice-error')
                            .html('<p><strong>❌ Server Error:</strong> Failed to reach server endpoint.</p>')
                            .slideDown(200);
                        btn.prop('disabled', false).html(originalHtml);
                    });
                });

                // Single Auto-Map button
                $('.svago-single-sync-btn').on('click', function(e) {
                    e.preventDefault();
                    $('#svago-auto-map-btn').trigger('click');
                });

                // Unlink Product Button
                $('.svago-unlink-btn').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var roomId = btn.data('room-id');
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to unlink this WooCommerce product from OTA room ', 'svago' ) ); ?>' + roomId + '?')) {
                        return;
                    }
                    btn.prop('disabled', true).text('...');
                    $.post(ajaxurl, {
                        action: 'svago_manual_map_room',
                        nonce: nonce,
                        room_id: roomId,
                        unlink: 1
                    }, function(resp) {
                        if (resp && resp.success) {
                            window.location.reload();
                        } else {
                            alert(resp && resp.data && resp.data.message ? resp.data.message : 'Unlink failed.');
                            btn.prop('disabled', false).text('🔗 Unlink');
                        }
                    }).fail(function() {
                        alert('Server error while unlinking.');
                        btn.prop('disabled', false).text('🔗 Unlink');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Update room availability and push to OTA (Task 6.1)
     *
     * @param string $room_id
     * @param array  $dates_data Map of date => ['inventory' => int, 'price' => float, 'blocked' => bool]
     * @return array [ 'success' => bool, 'message' => string ]
     */
    public function update_room_availability( string $room_id, array $dates_data ): array {
        if ( empty( $room_id ) || empty( $dates_data ) ) {
            return [ 'success' => false, 'message' => __( 'Missing room ID or dates data.', 'svago' ) ];
        }

        $provider = Provider_Factory::get();
        $loader   = new Fallback_Loader();
        $avail    = $loader->load_availability();

        if ( ! isset( $avail[ $room_id ] ) ) {
            $avail[ $room_id ] = [];
        }

        $pushed_dates = [];
        $total_days = count( $dates_data );

        foreach ( $dates_data as $date => $item ) {
            $inv     = isset( $item['inventory'] ) ? max( 0, (int) $item['inventory'] ) : 0;
            $price   = isset( $item['price'] ) ? max( 0.0, (float) $item['price'] ) : 0.0;
            $blocked = ! empty( $item['blocked'] );

            $final_inv   = $blocked ? 0 : $inv;
            $is_avail    = ( $final_inv > 0 ) && ! $blocked;

            $avail[ $room_id ][ $date ] = [
                'available' => $is_avail,
                'inventory' => $final_inv,
                'price'     => $price,
                'blocked'   => $blocked,
            ];

            $pushed_dates[] = [
                'date'    => $date,
                'qty'     => $final_inv,
                'price'   => $price,
                'blocked' => $blocked,
            ];
        }

        // Save local JSON fallback
        $loader->save_availability( $avail );

        // Push to OTA Channel Manager via provider->push_availability()
        $ota_res = $provider->push_availability( $room_id, $pushed_dates, 0 );

        // If mapped to a WooCommerce product, sync stock
        $wc_product_id = $this->find_product_by_room_id( $room_id );
        if ( $wc_product_id ) {
            // Take the lowest or current inventory as product stock indicator
            $latest_date = array_key_first( $dates_data );
            $sample_inv  = $avail[ $room_id ][ $latest_date ]['inventory'] ?? 0;
            update_post_meta( $wc_product_id, '_stock', $sample_inv );
            update_post_meta( $wc_product_id, '_manage_stock', 'yes' );
            if ( $sample_inv <= 0 ) {
                update_post_meta( $wc_product_id, '_stock_status', 'outofstock' );
            } else {
                update_post_meta( $wc_product_id, '_stock_status', 'instock' );
            }
        }

        // Log staff action in Logger (Task 6.7)
        $user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
        Logger::get_instance()->log(
            'info',
            sprintf( 'Staff User #%d updated availability for Room %s across %d dates.', $user_id, $room_id, $total_days ),
            [
                'user_id'   => $user_id,
                'room_id'   => $room_id,
                'days'      => $total_days,
                'dates'     => array_keys( $dates_data ),
                'ota_sync'  => $ota_res['success'] ?? true,
            ]
        );

        return [
            'success'  => true,
            'message'  => sprintf( __( 'Availability for %s updated across %d days and pushed to OTA.', 'svago' ), $room_id, $total_days ),
            'ota_sync' => $ota_res,
        ];
    }

    /**
     * AJAX endpoint to save availability changes (Task 6.1)
     */
    public function ajax_save_availability(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $room_id    = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
        $dates_json = isset( $_POST['dates'] ) ? wp_unslash( $_POST['dates'] ) : '';
        $dates_data = is_array( $dates_json ) ? $dates_json : json_decode( $dates_json, true );

        if ( empty( $room_id ) || empty( $dates_data ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data submitted.', 'svago' ) ] );
        }

        $res = $this->update_room_availability( $room_id, $dates_data );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res );
        } else {
            wp_send_json_error( $res );
        }
    }

    /**
     * AJAX endpoint to load room availability matrix
     */
    public function ajax_load_room_availability(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $room_id    = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : current_time( 'Y-m-d' );
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : date( 'Y-m-d', strtotime( '+13 days' ) );

        $calendar = $this->build_availability_calendar( $room_id, $start_date, $end_date );
        wp_send_json_success( [ 'days' => $calendar ] );
    }

    /**
     * Build availability day matrix for a room within date range
     *
     * @param string $room_id
     * @param string $start_date Y-m-d
     * @param string $end_date Y-m-d
     * @return array
     */
    public function build_availability_calendar( string $room_id, string $start_date, string $end_date ): array {
        $loader    = new Fallback_Loader();
        $avail     = $loader->load_availability();
        $raw_rooms = $loader->load_rooms();
        $rooms     = $raw_rooms['rooms'] ?? $raw_rooms;
        $rates     = $loader->load_rates();

        $base_price = 0;
        foreach ( $rooms as $r ) {
            if ( ( $r['id'] ?? '' ) === $room_id ) {
                $r_rates    = $rates[ $room_id ] ?? [];
                $base_price = (float) ( $r['base_price'] ?? ( ! empty( $r_rates[0]['price_per_night'] ) ? $r_rates[0]['price_per_night'] : 10000 ) );
                break;
            }
        }

        $room_avail = $avail[ $room_id ] ?? [];
        $days = [];

        $start_ts = strtotime( $start_date );
        $end_ts   = strtotime( $end_date );

        if ( ! $start_ts || ! $end_ts || $end_ts < $start_ts ) {
            $end_ts = $start_ts + ( 13 * 86400 );
        }

        // Limit range to max 60 days to prevent browser lockup
        $diff_days = min( 60, (int) round( ( $end_ts - $start_ts ) / 86400 ) + 1 );

        for ( $i = 0; $i < $diff_days; $i++ ) {
            $current_ts  = $start_ts + ( $i * 86400 );
            $date_str    = date( 'Y-m-d', $current_ts );
            $day_name    = date( 'D', $current_ts );
            $is_weekend  = ( 'Sat' === $day_name || 'Sun' === $day_name );

            $inv_data  = $room_avail[ $date_str ] ?? [];
            $inventory = isset( $inv_data['inventory'] ) ? (int) $inv_data['inventory'] : 5;
            $price     = isset( $inv_data['price'] ) && $inv_data['price'] > 0 ? (float) $inv_data['price'] : $base_price;
            $blocked   = ! empty( $inv_data['blocked'] );
            $available = ! empty( $inv_data['available'] ) && $inventory > 0 && ! $blocked;

            $days[] = [
                'date'       => $date_str,
                'day_name'   => $day_name,
                'is_weekend' => $is_weekend,
                'inventory'  => $inventory,
                'price'      => $price,
                'available'  => $available,
                'blocked'    => $blocked,
            ];
        }

        return $days;
    }

    /**
     * Get list of all available rooms
     */
    private function get_available_rooms(): array {
        try {
            $provider = Provider_Factory::get();
            $rooms = $provider->get_rooms();
            if ( ! empty( $rooms ) && is_array( $rooms ) ) {
                return $rooms;
            }
        } catch ( \Throwable $e ) {
            // fallback below
        }

        $loader = new Fallback_Loader();
        $data   = $loader->load_rooms();
        return $data['rooms'] ?? $data;
    }

    /**
     * Map WooCommerce products linked to rooms
     * Checks both _ota_room_id and _svago_ota_room_id
     */
    public function get_mapped_wc_products(): array {
        $mapped = [];
        global $wpdb;

        if ( ! empty( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
            $rows = $wpdb->get_results(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                 WHERE meta_key IN ('_ota_room_id', '_svago_ota_room_id') 
                 AND meta_value != ''"
            );
            if ( ! empty( $rows ) ) {
                foreach ( $rows as $row ) {
                    $post_id = (int) $row->post_id;
                    $room_id = trim( (string) $row->meta_value );
                    if ( empty( $room_id ) || isset( $mapped[ $room_id ] ) ) {
                        continue;
                    }
                    $post = get_post( $post_id );
                    if ( ! $post || 'trash' === $post->post_status ) {
                        continue;
                    }
                    $price = get_post_meta( $post_id, '_price', true );
                    if ( function_exists( 'wc_get_product' ) ) {
                        $prod = wc_get_product( $post_id );
                        if ( $prod ) {
                            $price = $prod->get_price();
                        }
                    }
                    $mapped[ $room_id ] = [
                        'id'    => $post_id,
                        'title' => get_the_title( $post_id ),
                        'price' => $price,
                    ];
                }
            }
        }

        // Additional fallback using WooCommerce functions if available
        if ( empty( $mapped ) && function_exists( 'wc_get_products' ) ) {
            foreach ( [ '_ota_room_id', '_svago_ota_room_id' ] as $mkey ) {
                $products = wc_get_products( [
                    'limit'    => -1,
                    'meta_key' => $mkey,
                ] );
                foreach ( $products as $prod ) {
                    $ota_id = $prod->get_meta( $mkey );
                    if ( $ota_id && ! isset( $mapped[ $ota_id ] ) ) {
                        $mapped[ $ota_id ] = [
                            'id'    => $prod->get_id(),
                            'title' => $prod->get_name(),
                            'price' => $prod->get_price(),
                        ];
                    }
                }
            }
        }

        return $mapped;
    }

    /**
     * Find WooCommerce product by OTA Room ID
     */
    public function find_product_by_room_id( string $room_id ): ?int {
        global $wpdb;
        if ( ! empty( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
            $pid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key IN ('_ota_room_id', '_svago_ota_room_id') 
                     AND meta_value = %s 
                     ORDER BY (meta_key = '_ota_room_id') DESC LIMIT 1",
                    $room_id
                )
            );
            if ( ! empty( $pid ) ) {
                return (int) $pid;
            }
        }

        if ( function_exists( 'wc_get_products' ) ) {
            foreach ( [ '_ota_room_id', '_svago_ota_room_id' ] as $mkey ) {
                $products = wc_get_products( [
                    'limit'      => 1,
                    'meta_key'   => $mkey,
                    'meta_value' => $room_id,
                    'return'     => 'ids',
                ] );
                if ( ! empty( $products[0] ) ) {
                    return (int) $products[0];
                }
            }
        }

        return null;
    }

    /**
     * AJAX endpoint to trigger Auto-Map & Sync (Task requirement)
     */
    public function ajax_auto_map_sync(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $res = Product_Seeder::auto_map_and_sync();
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res );
        } else {
            wp_send_json_error( $res );
        }
    }

    /**
     * AJAX endpoint to manually link or unlink a WooCommerce product to an OTA room
     */
    public function ajax_manual_map_room(): void {
        check_ajax_referer( 'svago_booking_nonce', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $room_id    = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
        $product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
        $unlink     = ! empty( $_POST['unlink'] );

        if ( empty( $room_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Room ID.', 'svago' ) ] );
        }

        if ( $unlink || 0 === $product_id ) {
            $existing_pid = $this->find_product_by_room_id( $room_id );
            if ( $existing_pid ) {
                delete_post_meta( $existing_pid, '_ota_room_id' );
                delete_post_meta( $existing_pid, '_svago_ota_room_id' );
                if ( function_exists( 'wc_delete_product_transients' ) ) {
                    wc_delete_product_transients( $existing_pid );
                }
            }
            if ( class_exists( 'SVAGO\Logger' ) ) {
                Logger::get_instance()->info(
                    sprintf( 'Unlinked WooCommerce product #%d from OTA Room ID %s', $existing_pid, $room_id )
                );
            }
            wp_send_json_success( [
                'message' => sprintf( __( 'Unlinked OTA Room %s from WooCommerce product.', 'svago' ), $room_id ),
                'room_id' => $room_id,
            ] );
        } else {
            // Remove previous mapping on other products if any
            global $wpdb;
            if ( ! empty( $wpdb ) ) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_ota_room_id', '_svago_ota_room_id') AND meta_value = %s",
                        $room_id
                    )
                );
            }

            update_post_meta( $product_id, '_ota_room_id', $room_id );
            update_post_meta( $product_id, '_svago_ota_room_id', $room_id );
            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients( $product_id );
            }

            if ( class_exists( 'SVAGO\Logger' ) ) {
                Logger::get_instance()->info(
                    sprintf( 'Manually mapped WooCommerce product #%d to OTA Room ID %s', $product_id, $room_id )
                );
            }

            wp_send_json_success( [
                'message'    => sprintf( __( 'Mapped WooCommerce product #%d to OTA Room %s.', 'svago' ), $product_id, $room_id ),
                'room_id'    => $room_id,
                'product_id' => $product_id,
            ] );
        }
    }
}

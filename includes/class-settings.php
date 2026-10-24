<?php
/**
 * Admin Settings
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings page for Hotel Booking plugin
 */
final class Settings {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Option name
     */
    private const OPTION_NAME = 'svago_settings';

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
     * Constructor - register hooks
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 9 );
        add_action( 'wp_ajax_svago_health_check', [ $this, 'ajax_health_check' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
    }

    /**
     * Register settings sections and fields
     */
    public function register_settings(): void {
        // Register settings
        register_setting( 'svago_settings_group', 'svago_provider_mode', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_provider_mode' ],
            'default'           => 'mock',
        ] );

        register_setting( 'svago_settings_group', 'svago_ota_api_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_ota_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_ota_api_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_hotel_phone', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_twilio_account_sid', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_twilio_auth_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_twilio_whatsapp_number', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_enable_whatsapp', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ] );

        register_setting( 'svago_settings_group', 'svago_enable_email', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ] );

        // Payment & Razorpay Settings (Task 4.2)
        register_setting( 'svago_settings_group', 'svago_razorpay_key_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_razorpay_key_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_razorpay_webhook_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        // Hotel Information Settings (Task 4.4 & 4.7)
        register_setting( 'svago_settings_group', 'svago_hotel_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_hotel_address', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'svago_settings_group', 'svago_hotel_map_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );

        // Add settings sections
        add_settings_section(
            'svago_section_ota',
            __( 'OTA Integration', 'svago' ),
            [ $this, 'render_section_ota' ],
            'svago_settings'
        );

        add_settings_section(
            'svago_section_payment',
            __( 'Payment & Razorpay Gateway', 'svago' ),
            [ $this, 'render_section_payment' ],
            'svago_settings'
        );

        add_settings_section(
            'svago_section_hotel_info',
            __( 'Hotel Information & Contact', 'svago' ),
            [ $this, 'render_section_hotel_info' ],
            'svago_settings'
        );

        add_settings_section(
            'svago_section_notifications',
            __( 'Notifications', 'svago' ),
            [ $this, 'render_section_notifications' ],
            'svago_settings'
        );

        add_settings_section(
            'svago_section_fallback',
            __( 'Fallback & Health', 'svago' ),
            [ $this, 'render_section_fallback' ],
            'svago_settings'
        );

        // OTA fields
        add_settings_field(
            'svago_provider_mode',
            __( 'Provider Mode', 'svago' ),
            [ $this, 'render_field_provider_mode' ],
            'svago_settings',
            'svago_section_ota'
        );

        add_settings_field(
            'svago_ota_api_url',
            __( 'OTA API URL', 'svago' ),
            [ $this, 'render_field_ota_api_url' ],
            'svago_settings',
            'svago_section_ota'
        );

        add_settings_field(
            'svago_ota_api_key',
            __( 'OTA API Key', 'svago' ),
            [ $this, 'render_field_ota_api_key' ],
            'svago_settings',
            'svago_section_ota'
        );

        add_settings_field(
            'svago_ota_api_secret',
            __( 'OTA API Secret', 'svago' ),
            [ $this, 'render_field_ota_api_secret' ],
            'svago_settings',
            'svago_section_ota'
        );

        add_settings_field(
            'svago_hotel_phone',
            __( 'Hotel Phone (for "Call to Book" fallback)', 'svago' ),
            [ $this, 'render_field_hotel_phone' ],
            'svago_settings',
            'svago_section_fallback'
        );

        // Notification fields
        add_settings_field(
            'svago_enable_whatsapp',
            __( 'Enable WhatsApp Notifications', 'svago' ),
            [ $this, 'render_field_enable_whatsapp' ],
            'svago_settings',
            'svago_section_notifications'
        );

        add_settings_field(
            'svago_twilio_account_sid',
            __( 'Twilio Account SID', 'svago' ),
            [ $this, 'render_field_twilio_account_sid' ],
            'svago_settings',
            'svago_section_notifications'
        );

        add_settings_field(
            'svago_twilio_auth_token',
            __( 'Twilio Auth Token', 'svago' ),
            [ $this, 'render_field_twilio_auth_token' ],
            'svago_settings',
            'svago_section_notifications'
        );

        add_settings_field(
            'svago_twilio_whatsapp_number',
            __( 'Twilio WhatsApp Number', 'svago' ),
            [ $this, 'render_field_twilio_whatsapp_number' ],
            'svago_settings',
            'svago_section_notifications'
        );

        add_settings_field(
            'svago_enable_email',
            __( 'Enable Email Notifications', 'svago' ),
            [ $this, 'render_field_enable_email' ],
            'svago_settings',
            'svago_section_notifications'
        );

        // Payment & Razorpay fields (Task 4.2)
        add_settings_field(
            'svago_razorpay_key_id',
            __( 'Razorpay Key ID', 'svago' ),
            [ $this, 'render_field_razorpay_key_id' ],
            'svago_settings',
            'svago_section_payment'
        );

        add_settings_field(
            'svago_razorpay_key_secret',
            __( 'Razorpay Key Secret', 'svago' ),
            [ $this, 'render_field_razorpay_key_secret' ],
            'svago_settings',
            'svago_section_payment'
        );

        add_settings_field(
            'svago_razorpay_webhook_secret',
            __( 'Razorpay Webhook Secret', 'svago' ),
            [ $this, 'render_field_razorpay_webhook_secret' ],
            'svago_settings',
            'svago_section_payment'
        );

        // Hotel Information fields (Task 4.4 & 4.7)
        add_settings_field(
            'svago_hotel_email',
            __( 'Hotel Reservations Email', 'svago' ),
            [ $this, 'render_field_hotel_email' ],
            'svago_settings',
            'svago_section_hotel_info'
        );

        add_settings_field(
            'svago_hotel_address',
            __( 'Hotel Physical Address', 'svago' ),
            [ $this, 'render_field_hotel_address' ],
            'svago_settings',
            'svago_section_hotel_info'
        );

        add_settings_field(
            'svago_hotel_map_url',
            __( 'Google Maps URL', 'svago' ),
            [ $this, 'render_field_hotel_map_url' ],
            'svago_settings',
            'svago_section_hotel_info'
        );

        // Health check button
        add_settings_field(
            'svago_health_check',
            __( 'Health Check', 'svago' ),
            [ $this, 'render_field_health_check' ],
            'svago_settings',
            'svago_section_fallback'
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

        // Main top-level menu: Hotel Booking -> points to Dashboard
        add_menu_page(
            __( 'Hotel Booking', 'svago' ),
            __( 'Hotel Booking', 'svago' ),
            $cap,
            'svago-dashboard',
            [ $this, 'render_dashboard_page' ],
            'dashicons-building',
            56
        );

        // 1. Dashboard (matches parent slug to rename the first submenu)
        add_submenu_page(
            'svago-dashboard',
            __( 'Hotel Booking Dashboard', 'svago' ),
            __( 'Dashboard', 'svago' ),
            $cap,
            'svago-dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        // 2. Availability
        add_submenu_page(
            'svago-dashboard',
            __( 'Room Availability Manager', 'svago' ),
            __( 'Availability', 'svago' ),
            $cap,
            'svago-availability',
            [ \SVAGO\Availability::get_instance(), 'render_availability_page' ]
        );

        // 3. Onsite Booking
        add_submenu_page(
            'svago-dashboard',
            __( 'Front-Desk Onsite Booking', 'svago' ),
            __( 'Onsite Booking', 'svago' ),
            $cap,
            'svago-onsite-booking',
            [ \SVAGO\Onsite_Booking::get_instance(), 'render_onsite_booking_page' ]
        );

        // 4. Bookings
        add_submenu_page(
            'svago-dashboard',
            __( 'Hotel Bookings & Reservations', 'svago' ),
            __( 'Bookings', 'svago' ),
            $cap,
            'svago-bookings',
            [ $this, 'render_bookings_page' ]
        );

        // 5. Room Mapping
        add_submenu_page(
            'svago-dashboard',
            __( 'Room & Rate Mapping', 'svago' ),
            __( 'Room Mapping', 'svago' ),
            $cap,
            'svago-room-mapping',
            [ \SVAGO\Availability::get_instance(), 'render_room_mapping_page' ]
        );

        // 6. Shortcodes
        add_submenu_page(
            'svago-dashboard',
            __( 'Hotel Booking Shortcodes', 'svago' ),
            __( 'Shortcodes', 'svago' ),
            $cap,
            'svago-shortcodes',
            [ $this, 'render_shortcodes_page' ]
        );

        // 7. Logs
        add_submenu_page(
            'svago-dashboard',
            __( 'Hotel Booking Logs', 'svago' ),
            __( 'Logs', 'svago' ),
            $cap,
            'svago-logs',
            [ \SVAGO\Logger::get_instance(), 'render_logs_page' ]
        );

        // 8. Settings
        add_submenu_page(
            'svago-dashboard',
            __( 'Hotel Booking Settings', 'svago' ),
            __( 'Settings', 'svago' ),
            $cap,
            'svago-settings',
            [ $this, 'render_settings_page' ]
        );

        // Also add shortcut under WooCommerce menu if available
        add_submenu_page(
            'woocommerce',
            __( 'Hotel Booking Settings', 'svago' ),
            __( 'Hotel Booking', 'svago' ),
            $cap,
            'svago-settings-wc',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Render Hotel Booking Dashboard Screen
     */
    public function render_dashboard_page(): void {
        $mode      = get_option( 'svago_provider_mode', 'mock' );
        $provider  = \SVAGO\Provider_Factory::get();
        $health    = $provider->check_health();
        $is_ok     = ! empty( $health['ok'] );
        $latency   = $health['latency'] ?? 0;
        $message   = $health['message'] ?? '';
        $sync_time = get_option( 'svago_last_sync_time', current_time( 'mysql' ) );

        $loader     = new \SVAGO\Fallback_Loader();
        $rooms_data = $loader->load_rooms();
        $rooms      = $rooms_data['rooms'] ?? [];

        $logger    = \SVAGO\Logger::get_instance();
        $logs      = $logger->get_logs( 5 );

        // Count hotel orders if available
        $total_bookings = 0;
        if ( function_exists( 'wc_get_orders' ) ) {
            $total_bookings = count( wc_get_orders( [ 'limit' => -1, 'return' => 'ids' ] ) );
        }
        ?>
        <div class="wrap svago-dashboard-wrap">
            <h1 style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                <span>🏨 <?php esc_html_e( 'Hotel Booking Dashboard', 'svago' ); ?></span>
                <span style="font-size:12px; font-weight:600; padding:4px 10px; border-radius:12px; background:<?php echo 'live' === $mode ? '#e0f2fe; color:#0369a1;' : '#fef3c7; color:#b45309;'; ?>">
                    <?php echo 'live' === $mode ? esc_html__( 'Live Mode', 'svago' ) : esc_html__( 'Mock Mode', 'svago' ); ?>
                </span>
            </h1>

            <!-- Health Status Banner -->
            <div style="background:#fff; border-left:4px solid <?php echo $is_ok ? '#16a34a' : '#dc2626'; ?>; padding:16px 20px; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:<?php echo $is_ok ? '#16a34a' : '#dc2626'; ?>;"></span>
                        <strong style="font-size:15px; color:<?php echo $is_ok ? '#15803d' : '#b91c1c'; ?>;">
                            <?php echo $is_ok ? esc_html__( 'OTA Channel Manager: Operational', 'svago' ) : esc_html__( 'OTA Channel Manager: Outage / Fallback Active', 'svago' ); ?>
                        </strong>
                    </div>
                    <div style="font-size:12px; color:#64748b; margin-top:4px;">
                        <?php echo esc_html( $message ?: 'All hotel reservation systems and local fallbacks are fully operational.' ); ?>
                        &bull; <?php esc_html_e( 'Latency:', 'svago' ); ?> <strong><?php echo esc_html( $latency ); ?>ms</strong>
                        &bull; <?php esc_html_e( 'Last Sync:', 'svago' ); ?> <code><?php echo esc_html( $sync_time ); ?></code>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-onsite-booking' ) ); ?>" class="button button-primary">
                        🛎️ <?php esc_html_e( 'New Onsite Booking', 'svago' ); ?>
                    </a>
                    <button type="button" class="button button-secondary" id="svago-refresh-health-btn" onclick="location.reload();">
                        🔄 <?php esc_html_e( 'Refresh Status', 'svago' ); ?>
                    </button>
                </div>
            </div>

            <!-- 4 Quick Stats -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px;">
                    <span style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600;"><?php esc_html_e( 'Themed Rooms', 'svago' ); ?></span>
                    <div style="font-size:28px; font-weight:700; color:#1e293b; margin-top:4px;"><?php echo count( $rooms ); ?></div>
                    <div style="font-size:12px; color:#16a34a; margin-top:4px;">✓ Jazz, Literature, Love, Theatre</div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px;">
                    <span style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600;"><?php esc_html_e( 'Operational Mode', 'svago' ); ?></span>
                    <div style="font-size:24px; font-weight:700; color:#2563eb; margin-top:6px;"><?php echo ucfirst( $mode ); ?></div>
                    <div style="font-size:12px; color:#64748b; margin-top:4px;"><?php echo 'live' === $mode ? 'Connected to OTA Channel Manager' : 'Local Sandbox & Fallback Engine'; ?></div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px;">
                    <span style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600;"><?php esc_html_e( 'Payments & Checkout', 'svago' ); ?></span>
                    <div style="font-size:20px; font-weight:700; color:#0f172a; margin-top:8px;">Razorpay Gateway</div>
                    <div style="font-size:12px; color:#16a34a; margin-top:4px;">✓ Direct UPI, Cards, Cash Onsite</div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px;">
                    <span style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600;"><?php esc_html_e( 'Guest Notifications', 'svago' ); ?></span>
                    <div style="font-size:20px; font-weight:700; color:#0f172a; margin-top:8px;">WhatsApp & Email</div>
                    <div style="font-size:12px; color:#16a34a; margin-top:4px;">✓ Twilio & WP Mail SMTP ready</div>
                </div>
            </div>

            <!-- Quick Navigation Hub -->
            <h2 style="font-size:16px; margin:28px 0 14px; color:#1e293b;"><?php esc_html_e( 'Management Hub', 'svago' ); ?></h2>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:28px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-availability' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px; transition:border-color 0.2s;">
                    <span style="font-size:32px;">📅</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'Availability & Inventory', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Manage daily room inventory, price matrix overrides, and block dates.', 'svago' ); ?></span>
                    </div>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-onsite-booking' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px;">
                    <span style="font-size:32px;">🛎️</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'New Onsite Booking', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Front-desk walk-in and phone booking terminal with immediate order creation.', 'svago' ); ?></span>
                    </div>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-bookings' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px;">
                    <span style="font-size:32px;">📋</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'Bookings & Orders', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Review all website and front-desk reservations with OTA push status.', 'svago' ); ?></span>
                    </div>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-room-mapping' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px;">
                    <span style="font-size:32px;">🗺️</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'Room & Rate Mapping', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Link WooCommerce products to OTA room IDs and rate plan codes.', 'svago' ); ?></span>
                    </div>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-logs' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px;">
                    <span style="font-size:32px;">📜</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'System & Activity Logs', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Audit trails, staff overrides, OTA synchronization, and error traces.', 'svago' ); ?></span>
                    </div>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-settings' ) ); ?>" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:18px; display:flex; align-items:center; gap:16px;">
                    <span style="font-size:32px;">⚙️</span>
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e( 'Settings & API Keys', 'svago' ); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Configure provider credentials, Razorpay keys, Twilio, and policies.', 'svago' ); ?></span>
                    </div>
                </a>
            </div>

            <!-- Themed Rooms Showcase -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:20px; margin-bottom:28px;">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; font-size:15px; color:#1e293b;">
                    🛌 <?php esc_html_e( 'Active Themed Rooms Catalog', 'svago' ); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:80px;"><?php esc_html_e( 'OTA ID', 'svago' ); ?></th>
                            <th><?php esc_html_e( 'Room Name & Themed Concept', 'svago' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Room Size', 'svago' ); ?></th>
                            <th style="width:140px;"><?php esc_html_e( 'Bedding', 'svago' ); ?></th>
                            <th style="width:110px;"><?php esc_html_e( 'Max Guests', 'svago' ); ?></th>
                            <th style="width:130px;"><?php esc_html_e( 'Standard Rate', 'svago' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rooms as $r ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $r['id'] ); ?></code></td>
                                <td>
                                    <strong><?php echo esc_html( $r['name'] ); ?></strong>
                                    <div style="font-size:11px; color:#64748b; margin-top:2px;"><?php echo esc_html( $r['description'] ); ?></div>
                                </td>
                                <td><?php echo esc_html( $r['size'] ?? '-' ); ?></td>
                                <td><?php echo esc_html( $r['bed_type'] ?? '-' ); ?></td>
                                <td><?php echo esc_html( sprintf( '%d Adults, %d Ch', $r['max_adults'] ?? 2, $r['max_children'] ?? 0 ) ); ?></td>
                                <td><strong style="color:#2563eb;">₹<?php echo esc_html( number_format( $r['base_price'] ?? ( 'RM001' === $r['id'] ? 70854 : ( 'RM002' === $r['id'] ? 125000 : ( 'RM003' === $r['id'] ? 92000 : 145000 ) ) ) ) ); ?></strong> / night</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity Logs Preview -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h3 style="margin:0; font-size:15px; color:#1e293b;">
                        📜 <?php esc_html_e( 'Recent Activity & Audit Logs', 'svago' ); ?>
                    </h3>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-logs' ) ); ?>" class="button button-small">
                        <?php esc_html_e( 'View All Logs &rarr;', 'svago' ); ?>
                    </a>
                </div>
                <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:150px;"><?php esc_html_e( 'Timestamp', 'svago' ); ?></th>
                            <th style="width:90px;"><?php esc_html_e( 'Level', 'svago' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'svago' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr><td colspan="3" style="text-align:center;"><?php esc_html_e( 'No log entries recorded yet.', 'svago' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $l ) : ?>
                                <tr>
                                    <td><code><?php echo esc_html( $l['timestamp'] ); ?></code></td>
                                    <td><span class="svago-log-level svago-log-<?php echo esc_attr( $l['level'] ); ?>"><?php echo esc_html( strtoupper( $l['level'] ) ); ?></span></td>
                                    <td><?php echo esc_html( $l['message'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <style>
            .svago-log-level { padding: 2px 7px; border-radius: 3px; font-size: 10px; font-weight: 700; }
            .svago-log-info { background: #dbeafe; color: #1e40af; }
            .svago-log-warning { background: #fef3c7; color: #b45309; }
            .svago-log-error { background: #fee2e2; color: #b91c1c; }
        </style>
        <?php
    }

    /**
     * Render Hotel Bookings Screen
     */
    public function render_bookings_page(): void {
        global $wpdb;

        // Query WooCommerce orders that have hotel booking meta
        $order_ids = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_booking_room_id' ORDER BY post_id DESC LIMIT 100"
        );

        $bookings = [];
        foreach ( $order_ids as $oid ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
            $room_name   = get_post_meta( $oid, '_booking_room_name', true ) ?: __( 'Room', 'svago' );
            $room_id     = get_post_meta( $oid, '_booking_room_id', true );
            $check_in    = get_post_meta( $oid, '_booking_check_in', true );
            $check_out   = get_post_meta( $oid, '_booking_check_out', true );
            $nights      = get_post_meta( $oid, '_booking_nights', true ) ?: 1;
            $adults      = get_post_meta( $oid, '_booking_adults', true ) ?: 1;
            $children    = get_post_meta( $oid, '_booking_children', true ) ?: 0;
            $source      = get_post_meta( $oid, '_booking_source', true ) ?: 'website';
            $ota_status  = get_post_meta( $oid, '_booking_ota_status', true ) ?: 'pending';
            $ota_id      = get_post_meta( $oid, '_booking_ota_id', true ) ?: '';

            $guest_name  = $order ? $order->get_formatted_billing_full_name() : ( get_post_meta( $oid, '_billing_first_name', true ) . ' ' . get_post_meta( $oid, '_billing_last_name', true ) );
            $guest_email = $order ? $order->get_billing_email() : get_post_meta( $oid, '_billing_email', true );
            $guest_phone = $order ? $order->get_billing_phone() : get_post_meta( $oid, '_billing_phone', true );
            $total       = $order ? $order->get_total() : get_post_meta( $oid, '_order_total', true );
            $status      = $order ? $order->get_status() : get_post_status( $oid );

            $bookings[] = [
                'id'          => $oid,
                'guest_name'  => trim( $guest_name ) ?: __( 'Guest', 'svago' ),
                'guest_email' => $guest_email,
                'guest_phone' => $guest_phone,
                'room_name'   => $room_name,
                'room_id'     => $room_id,
                'check_in'    => $check_in,
                'check_out'   => $check_out,
                'nights'      => $nights,
                'adults'      => $adults,
                'children'    => $children,
                'source'      => $source,
                'ota_status'  => $ota_status,
                'ota_id'      => $ota_id,
                'total'       => (float) $total,
                'status'      => str_replace( 'wc-', '', $status ),
            ];
        }
        ?>
        <div class="wrap">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h1><?php esc_html_e( 'Hotel Bookings & Reservations', 'svago' ); ?></h1>
                <div style="display:flex; gap:8px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-onsite-booking' ) ); ?>" class="button button-primary">
                        🛎️ <?php esc_html_e( 'New Onsite Booking', 'svago' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="button button-secondary">
                        🛍️ <?php esc_html_e( 'View WooCommerce Orders', 'svago' ); ?>
                    </a>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e( 'Complete overview of confirmed online reservations and front-desk walk-in reservations, along with Channel Manager synchronization status.', 'svago' ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th style="width:90px;"><?php esc_html_e( 'Order #', 'svago' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'Guest Details', 'svago' ); ?></th>
                        <th><?php esc_html_e( 'Room Reserved', 'svago' ); ?></th>
                        <th style="width:150px;"><?php esc_html_e( 'Stay Dates', 'svago' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Occupancy', 'svago' ); ?></th>
                        <th style="width:110px;"><?php esc_html_e( 'Total', 'svago' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Status', 'svago' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Source', 'svago' ); ?></th>
                        <th style="width:110px;"><?php esc_html_e( 'OTA Status', 'svago' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $bookings ) ) : ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:30px;">
                                <div style="font-size:16px; font-weight:600; color:#475569; margin-bottom:6px;">
                                    <?php esc_html_e( 'No hotel reservations found yet.', 'svago' ); ?>
                                </div>
                                <div style="font-size:13px; color:#64748b; margin-bottom:14px;">
                                    <?php esc_html_e( 'Create your first walk-in reservation using the front-desk terminal or test the booking widget on the website.', 'svago' ); ?>
                                </div>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-onsite-booking' ) ); ?>" class="button button-primary">
                                    🛎️ <?php esc_html_e( 'Create Onsite Booking', 'svago' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $bookings as $b ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $b['id'] . '&action=edit' ) ); ?>" style="font-weight:700;">
                                        #<?php echo esc_html( $b['id'] ); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $b['guest_name'] ); ?></strong>
                                    <?php if ( ! empty( $b['guest_phone'] ) ) : ?>
                                        <div style="font-size:11px; color:#64748b;"><?php echo esc_html( $b['guest_phone'] ); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $b['room_name'] ); ?></strong>
                                    <span style="font-size:11px; color:#64748b;">(<code><?php echo esc_html( $b['room_id'] ); ?></code>)</span>
                                </td>
                                <td>
                                    <div><?php echo esc_html( $b['check_in'] ); ?> &rarr; <?php echo esc_html( $b['check_out'] ); ?></div>
                                    <div style="font-size:11px; color:#64748b;"><?php echo esc_html( $b['nights'] ); ?> night(s)</div>
                                </td>
                                <td>
                                    <?php echo esc_html( sprintf( '%d Ad, %d Ch', $b['adults'], $b['children'] ) ); ?>
                                </td>
                                <td>
                                    <strong>₹<?php echo esc_html( number_format( $b['total'] ) ); ?></strong>
                                </td>
                                <td>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#f1f5f9; color:#334155;">
                                        <?php echo esc_html( ucfirst( $b['status'] ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( 'onsite_walkin' === $b['source'] ) : ?>
                                        <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; background:#fef3c7; color:#b45309;">
                                            🛎️ Walk-in
                                        </span>
                                    <?php else : ?>
                                        <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; background:#e0f2fe; color:#0369a1;">
                                            🌐 Website
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( 'pushed' === $b['ota_status'] ) : ?>
                                        <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; background:#dcfce7; color:#15803d;" title="<?php echo esc_attr( $b['ota_id'] ); ?>">
                                            ✓ Pushed
                                        </span>
                                    <?php else : ?>
                                        <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; background:#fee2e2; color:#b91c1c;">
                                            ⏳ Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'svago_settings_group' );
                do_settings_sections( 'svago_settings' );
                submit_button( __( 'Save Settings', 'svago' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Shortcodes Reference Page
     */
    public function render_shortcodes_page(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'svago' ) );
        }

        $shortcodes = [
            [
                'label'       => __( 'Complete Booking Flow', 'svago' ),
                'shortcode'   => '[hotel_booking]',
                'badge'       => __( 'Recommended', 'svago' ),
                'badge_color' => '#16a34a',
                'description' => __( 'Renders the complete 5-step hotel booking journey (Calendar → Room Selection → Guest Details → Review → Confirmation) in a single page. Best for your primary "/booking" page.', 'svago' ),
                'attributes'  => __( 'Inherits live URL parameters and booking session automatically.', 'svago' ),
            ],
            [
                'label'       => __( 'Calendar Screen (Step 1)', 'svago' ),
                'shortcode'   => '[hotel_calendar]',
                'badge'       => __( 'Step 1', 'svago' ),
                'badge_color' => '#0284c7',
                'description' => __( 'Dual-month interactive calendar with prominent date numbers, secondary pricing, selected date range highlight, and booking summary sidebar with room/guest dropdowns.', 'svago' ),
                'attributes'  => __( 'Optional attributes: action_url, check_in, check_out, adults, children, rooms', 'svago' ),
            ],
            [
                'label'       => __( 'Room Search Widget', 'svago' ),
                'shortcode'   => '[hotel_search]',
                'badge'       => __( 'Hero / Homepage', 'svago' ),
                'badge_color' => '#2563eb',
                'description' => __( 'Compact search bar with check-in & check-out date picker and guest selector. Ideal for home page hero banners and landing pages.', 'svago' ),
                'attributes'  => __( 'Optional attributes: action_url, check_in, check_out, adults', 'svago' ),
            ],
            [
                'label'       => __( 'Room & Rate Selection', 'svago' ),
                'shortcode'   => '[hotel_rooms]',
                'badge'       => __( 'Step 2', 'svago' ),
                'badge_color' => '#7c3aed',
                'description' => __( 'Two-step room & rate selection: Inner Step 1 presents vertical room cards with 2-column image/details layout, 3x2 amenities grid, and "VIEW RATES & OFFERS" button; Inner Step 2 provides dedicated rate plan cards (Room Only, With Breakfast, etc.) with "Book Now" CTA.', 'svago' ),
                'attributes'  => __( 'Optional attributes: check_in, check_out, adults, children', 'svago' ),
            ],
            [
                'label'       => __( 'Guest Details Form', 'svago' ),
                'shortcode'   => '[hotel_guest_details]',
                'badge'       => __( 'Step 3', 'svago' ),
                'badge_color' => '#d97706',
                'description' => __( 'Standalone guest contact form collecting primary guest details (full name, phone, email, country) and optional special requests.', 'svago' ),
                'attributes'  => __( 'Requires an active selected room in the booking session.', 'svago' ),
            ],
            [
                'label'       => __( 'Booking Review & Pricing', 'svago' ),
                'shortcode'   => '[hotel_review]',
                'badge'       => __( 'Step 4', 'svago' ),
                'badge_color' => '#0891b2',
                'description' => __( 'Pre-payment booking review showing nightly breakdown, extra guest fees, taxes / GST, discount breakdown, and Razorpay / Pay-at-Hotel checkout button.', 'svago' ),
                'attributes'  => __( 'Summarizes the currently active reservation.', 'svago' ),
            ],
            [
                'label'       => __( 'Booking Confirmation', 'svago' ),
                'shortcode'   => '[hotel_confirmation]',
                'badge'       => __( 'Step 5', 'svago' ),
                'badge_color' => '#059669',
                'description' => __( 'Success screen displaying the confirmed booking code, reservation status, dates, room details, and guest contact information. Triggers WhatsApp and email notifications.', 'svago' ),
                'attributes'  => __( 'Optional attribute: booking_id (automatically reads ?booking_id=... from URL)', 'svago' ),
            ],
        ];
        ?>
        <div class="wrap svago-shortcodes-wrap">
            <h1 style="display:flex; align-items:center; gap:8px;">
                <span>📋</span> <?php esc_html_e( 'Hotel Booking Shortcodes', 'svago' ); ?>
            </h1>
            <p class="description" style="font-size:14px; margin-top:4px;">
                <?php esc_html_e( 'Quick reference guide for all embeddable shortcodes provided by the plugin. Copy any shortcode below to embed it into pages, posts, or page builder widgets.', 'svago' ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <thead>
                    <tr>
                        <th style="width:200px; font-weight:700;"><?php esc_html_e( 'Label', 'svago' ); ?></th>
                        <th style="width:250px; font-weight:700;"><?php esc_html_e( 'Shortcode', 'svago' ); ?></th>
                        <th style="font-weight:700;"><?php esc_html_e( 'Usage / Description', 'svago' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $shortcodes as $item ) : ?>
                        <tr>
                            <td style="vertical-align:top;">
                                <strong style="font-size:14px; color:#1e293b;"><?php echo esc_html( $item['label'] ); ?></strong>
                                <?php if ( ! empty( $item['badge'] ) ) : ?>
                                    <div style="margin-top:4px;">
                                        <span style="display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:2px 7px; border-radius:10px; color:#fff; background:<?php echo esc_attr( $item['badge_color'] ); ?>;">
                                            <?php echo esc_html( $item['badge'] ); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align:top;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <code style="font-size:13px; font-weight:700; padding:4px 8px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; color:#0f172a;"><?php echo esc_html( $item['shortcode'] ); ?></code>
                                    <button type="button" class="button button-small svago-copy-btn" data-clipboard="<?php echo esc_attr( $item['shortcode'] ); ?>" title="<?php esc_attr_e( 'Click to copy shortcode', 'svago' ); ?>">
                                        📋 <?php esc_html_e( 'Copy', 'svago' ); ?>
                                    </button>
                                </div>
                            </td>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 6px 0; font-size:13px; line-height:1.5; color:#334155;">
                                    <?php echo esc_html( $item['description'] ); ?>
                                </p>
                                <div style="font-size:12px; color:#64748b;">
                                    <strong><?php esc_html_e( 'Attributes / Notes:', 'svago' ); ?></strong>
                                    <code><?php echo esc_html( $item['attributes'] ); ?></code>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:28px; display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:18px;">
                    <h3 style="margin-top:0; font-size:15px; color:#0f172a;">
                        💡 <?php esc_html_e( 'How to Embed in Pages', 'svago' ); ?>
                    </h3>
                    <ol style="margin-bottom:0; font-size:13px; color:#475569; padding-left:20px; line-height:1.6;">
                        <li><?php esc_html_e( 'Create or edit any WordPress page (e.g., Pages → Add New "Booking").', 'svago' ); ?></li>
                        <li><?php esc_html_e( 'Add a Shortcode block in the Block Editor (Gutenberg) or a Shortcode widget in Elementor.', 'svago' ); ?></li>
                        <li><?php esc_html_e( 'Paste [hotel_booking] into the field and publish your page.', 'svago' ); ?></li>
                        <li><?php esc_html_e( 'Visitors will immediately see the complete interactive 5-step booking engine!', 'svago' ); ?></li>
                    </ol>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:18px;">
                    <h3 style="margin-top:0; font-size:15px; color:#0f172a;">
                        ⚡ <?php esc_html_e( 'Recommended Page Setup', 'svago' ); ?>
                    </h3>
                    <ul style="margin-bottom:0; font-size:13px; color:#475569; padding-left:20px; line-height:1.6;">
                        <li>
                            <strong><?php esc_html_e( 'Homepage Hero:', 'svago' ); ?></strong>
                            <code>[hotel_search action_url="/booking/"]</code>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Main Booking Page (/booking):', 'svago' ); ?></strong>
                            <code>[hotel_booking]</code>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Confirmation Page (Optional):', 'svago' ); ?></strong>
                            <code>[hotel_confirmation]</code>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
            jQuery(function($) {
                $('.svago-copy-btn').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var text = btn.data('clipboard');

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            var originalHtml = btn.html();
                            btn.html('✓ Copied!').css({'background':'#dcfce7','color':'#15803d','border-color':'#86efac'});
                            setTimeout(function() {
                                btn.html(originalHtml).removeAttr('style');
                            }, 2000);
                        });
                    } else {
                        // Fallback textarea method
                        var temp = $('<input>');
                        $('body').append(temp);
                        temp.val(text).select();
                        document.execCommand('copy');
                        temp.remove();
                        var originalHtml = btn.html();
                        btn.html('✓ Copied!').css({'background':'#dcfce7','color':'#15803d','border-color':'#86efac'});
                        setTimeout(function() {
                            btn.html(originalHtml).removeAttr('style');
                        }, 2000);
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Sanitize provider mode
     */
    public function sanitize_provider_mode( string $value ): string {
        return in_array( $value, [ 'mock', 'live' ], true ) ? $value : 'mock';
    }

    // Section callbacks
    public function render_section_ota(): void {
        echo '<p>' . __( 'Configure connection to your OTA Channel Manager API.', 'svago' ) . '</p>';
    }

    public function render_section_payment(): void {
        echo '<p>' . __( 'Configure Razorpay API credentials for online checkout and payment capture.', 'svago' ) . '</p>';
    }

    public function render_section_hotel_info(): void {
        echo '<p>' . __( 'Hotel contact details, physical address, and location map link for confirmation receipts.', 'svago' ) . '</p>';
    }

    public function render_section_notifications(): void {
        echo '<p>' . __( 'Configure WhatsApp and email notifications for bookings.', 'svago' ) . '</p>';
    }

    public function render_section_fallback(): void {
        echo '<p>' . __( 'Fallback settings and provider health monitoring.', 'svago' ) . '</p>';
    }

    // Field callbacks
    public function render_field_provider_mode(): void {
        $value = get_option( 'svago_provider_mode', 'mock' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span><?php _e( 'Provider Mode', 'svago' ); ?></span></legend>
            <label>
                <input type="radio" name="svago_provider_mode" value="mock" <?php checked( $value, 'mock' ); ?> />
                <?php _e( 'Mock (Offline / Development)', 'svago' ); ?>
            </label><br>
            <label>
                <input type="radio" name="svago_provider_mode" value="live" <?php checked( $value, 'live' ); ?> />
                <?php _e( 'Live (Production OTA API)', 'svago' ); ?>
            </label>
            <p class="description"><?php _e( 'Switch to Live mode only after configuring API credentials and testing in sandbox.', 'svago' ); ?></p>
        </fieldset>
        <script>
            jQuery(function($) {
                function updateProviderFields() {
                    var isLive = $('input[name="svago_provider_mode"]:checked').val() === 'live';
                    $('#svago_ota_api_url, #svago_ota_api_key, #svago_ota_api_secret').prop('disabled', !isLive);
                }
                $('input[name="svago_provider_mode"]').on('change', updateProviderFields);
                updateProviderFields();

                // Re-enable fields right before form submission so values are preserved
                $('form').on('submit', function() {
                    $('#svago_ota_api_url, #svago_ota_api_key, #svago_ota_api_secret').prop('disabled', false);
                });
            });
        </script>
        <?php
    }

    public function render_field_ota_api_url(): void {
        $value = get_option( 'svago_ota_api_url', '' );
        $disabled = get_option( 'svago_provider_mode', 'mock' ) !== 'live' ? 'disabled' : '';
        printf(
            '<input type="url" name="svago_ota_api_url" id="svago_ota_api_url" value="%s" class="regular-text" %s />',
            esc_attr( $value ),
            $disabled
        );
        echo ' <p class="description">' . __( 'Base URL for the OTA API (e.g., https://api.ota-provider.com/v1)', 'svago' ) . '</p>';
    }

    public function render_field_ota_api_key(): void {
        $value = get_option( 'svago_ota_api_key', '' );
        $disabled = get_option( 'svago_provider_mode', 'mock' ) !== 'live' ? 'disabled' : '';
        printf(
            '<input type="text" name="svago_ota_api_key" id="svago_ota_api_key" value="%s" class="regular-text" %s />',
            esc_attr( $value ),
            $disabled
        );
        echo ' <p class="description">' . __( 'API Key provided by your OTA provider', 'svago' ) . '</p>';
    }

    public function render_field_ota_api_secret(): void {
        $value = get_option( 'svago_ota_api_secret', '' );
        $disabled = get_option( 'svago_provider_mode', 'mock' ) !== 'live' ? 'disabled' : '';
        printf(
            '<input type="password" name="svago_ota_api_secret" id="svago_ota_api_secret" value="%s" class="regular-text" %s autocomplete="off" />',
            esc_attr( $value ),
            $disabled
        );
        echo ' <p class="description">' . __( 'API Secret provided by your OTA provider', 'svago' ) . '</p>';
    }

    public function render_field_hotel_phone(): void {
        $value = get_option( 'svago_hotel_phone', '' );
        printf(
            '<input type="tel" name="svago_hotel_phone" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Displayed to guests when live availability is unavailable and fallback data is missing', 'svago' ) . '</p>';
    }

    public function render_field_enable_whatsapp(): void {
        $value = get_option( 'svago_enable_whatsapp', true );
        printf(
            '<input type="checkbox" name="svago_enable_whatsapp" value="1" %s />',
            checked( $value, true, false )
        );
    }

    public function render_field_twilio_account_sid(): void {
        $value = get_option( 'svago_twilio_account_sid', '' );
        printf(
            '<input type="text" name="svago_twilio_account_sid" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Your Twilio Account SID', 'svago' ) . '</p>';
    }

    public function render_field_twilio_auth_token(): void {
        $value = get_option( 'svago_twilio_auth_token', '' );
        printf(
            '<input type="password" name="svago_twilio_auth_token" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Your Twilio Auth Token', 'svago' ) . '</p>';
    }

    public function render_field_twilio_whatsapp_number(): void {
        $value = get_option( 'svago_twilio_whatsapp_number', '' );
        printf(
            '<input type="tel" name="svago_twilio_whatsapp_number" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'WhatsApp sender number in E.164 format (e.g., +15551234567)', 'svago' ) . '</p>';
    }

    public function render_field_enable_email(): void {
        $value = get_option( 'svago_enable_email', true );
        printf(
            '<input type="checkbox" name="svago_enable_email" value="1" %s />',
            checked( $value, true, false )
        );
        echo ' <p class="description">' . __( 'Uses WordPress / WooCommerce email system. Configure via WP Mail SMTP plugin.', 'svago' ) . '</p>';
    }

    public function render_field_razorpay_key_id(): void {
        $value = get_option( 'svago_razorpay_key_id', '' );
        printf(
            '<input type="text" name="svago_razorpay_key_id" id="svago_razorpay_key_id" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Your Razorpay Key ID (test or live, e.g. rzp_test_...)', 'svago' ) . '</p>';
    }

    public function render_field_razorpay_key_secret(): void {
        $value = get_option( 'svago_razorpay_key_secret', '' );
        printf(
            '<input type="password" name="svago_razorpay_key_secret" id="svago_razorpay_key_secret" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Your Razorpay Key Secret', 'svago' ) . '</p>';
    }

    public function render_field_razorpay_webhook_secret(): void {
        $value = get_option( 'svago_razorpay_webhook_secret', '' );
        printf(
            '<input type="password" name="svago_razorpay_webhook_secret" id="svago_razorpay_webhook_secret" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Secret for verifying Razorpay webhook event signatures', 'svago' ) . '</p>';
    }

    public function render_field_hotel_email(): void {
        $value = get_option( 'svago_hotel_email', '' );
        printf(
            '<input type="email" name="svago_hotel_email" id="svago_hotel_email" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Hotel reservations inbox address that receives instant copies of all confirmed bookings.', 'svago' ) . '</p>';
    }

    public function render_field_hotel_address(): void {
        $value = get_option( 'svago_hotel_address', '' );
        printf(
            '<input type="text" name="svago_hotel_address" id="svago_hotel_address" value="%s" class="large-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Full hotel address shown on the guest confirmation page and invoices.', 'svago' ) . '</p>';
    }

    public function render_field_hotel_map_url(): void {
        $value = get_option( 'svago_hotel_map_url', '' );
        printf(
            '<input type="url" name="svago_hotel_map_url" id="svago_hotel_map_url" value="%s" class="large-text" />',
            esc_attr( $value )
        );
        echo ' <p class="description">' . __( 'Google Maps share link or directions URL for guests arriving at the hotel.', 'svago' ) . '</p>';
    }

    public function render_field_health_check(): void {
        $nonce = wp_create_nonce( 'svago_health_check' );
        ?>
        <button type="button" id="svago-health-check" class="button button-secondary" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php _e( 'Check Provider Health', 'svago' ); ?>
        </button>
        <span id="svago-health-result" class="svago-health-result" style="margin-left:10px;"></span>
        <p class="description"><?php _e( 'Tests connectivity to the active provider (verifies fallback files in Mock mode, or OTA API endpoint in Live mode).', 'svago' ); ?></p>
        <script>
            jQuery(function($) {
                $('#svago-health-check').on('click', function() {
                    var $btn = $(this);
                    var $result = $('#svago-health-result');
                    $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Checking...', 'svago' ) ); ?>');
                    $result.removeClass('success error').text('');

                    $.post(ajaxurl, {
                        action: 'svago_health_check',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        if (response.success && response.data && response.data.ok) {
                            $result.addClass('success').text('✓ ' + response.data.message + ' (Latency: ' + response.data.latency + 'ms)');
                        } else {
                            var errMsg = (response.data && response.data.message) ? response.data.message : (typeof response.data === 'string' ? response.data : 'Health check failed');
                            $result.addClass('error').text('✗ ' + errMsg);
                        }
                    }).fail(function() {
                        $result.addClass('error').text('✗ ' + '<?php echo esc_js( __( 'Request failed. Please try again.', 'svago' ) ); ?>');
                    }).always(function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Check Provider Health', 'svago' ) ); ?>');
                    });
                });
            });
        </script>
        <style>
            .svago-health-result.success { color: #46b450; font-weight: 600; }
            .svago-health-result.error { color: #dc3232; font-weight: 600; }
        </style>
        <?php
    }

    /**
     * AJAX handler for health check
     */
    public function ajax_health_check(): void {
        check_ajax_referer( 'svago_health_check', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'svago' ) ] );
        }

        $provider = \SVAGO\Provider_Factory::get();
        $health   = $provider->check_health();

        if ( ! empty( $health['ok'] ) ) {
            wp_send_json_success( $health );
        } else {
            wp_send_json_error( $health );
        }
    }

    /**
     * Register WordPress Admin Dashboard Widget (Task 7.6)
     */
    public function register_dashboard_widget(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( current_user_can( $cap ) ) {
            wp_add_dashboard_widget(
                'svago_ota_dashboard_widget',
                __( 'Hotel Booking & OTA Channel Status (Svago)', 'svago' ),
                [ $this, 'render_dashboard_widget' ]
            );
        }
    }

    /**
     * Render WordPress Admin Dashboard Widget (Task 7.6)
     */
    public function render_dashboard_widget(): void {
        $mode      = get_option( 'svago_provider_mode', 'mock' );
        $provider  = \SVAGO\Provider_Factory::get();
        $health    = $provider->check_health();
        $is_ok     = ! empty( $health['ok'] );
        $latency   = $health['latency'] ?? 0;
        $message   = $health['message'] ?? '';
        $sync_time = get_option( 'svago_last_sync_time', current_time( 'mysql' ) );

        $nonce = wp_create_nonce( 'svago_health_check' );
        ?>
        <div class="svago-dashboard-widget-content" style="padding:4px 0;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <?php if ( $is_ok ) : ?>
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#16a34a; box-shadow:0 0 6px rgba(22,163,74,0.6);" title="<?php esc_attr_e( 'System Healthy', 'svago' ); ?>"></span>
                        <strong style="color:#15803d; font-size:14px;"><?php esc_html_e( 'OTA Status: Operational', 'svago' ); ?></strong>
                    <?php else : ?>
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#dc2626; box-shadow:0 0 6px rgba(220,38,38,0.6);" title="<?php esc_attr_e( 'Connection Outage', 'svago' ); ?>"></span>
                        <strong style="color:#b91c1c; font-size:14px;"><?php esc_html_e( 'OTA Status: Outage / Fallback Active', 'svago' ); ?></strong>
                    <?php endif; ?>
                </div>
                <span style="font-size:11px; padding:3px 8px; border-radius:4px; font-weight:600; background:<?php echo 'live' === $mode ? '#e0f2fe; color:#0369a1;' : '#fef3c7; color:#b45309;'; ?>">
                    <?php echo 'live' === $mode ? esc_html__( 'Live Mode', 'svago' ) : esc_html__( 'Mock Mode', 'svago' ); ?>
                </span>
            </div>

            <table style="width:100%; font-size:12px; margin-bottom:14px; line-height:1.8;">
                <tr>
                    <td style="color:#64748b; width:40%;"><?php esc_html_e( 'Response Latency:', 'svago' ); ?></td>
                    <td><strong><?php echo esc_html( $latency ); ?> ms</strong></td>
                </tr>
                <tr>
                    <td style="color:#64748b;"><?php esc_html_e( 'Last Sync:', 'svago' ); ?></td>
                    <td><code><?php echo esc_html( $sync_time ); ?></code></td>
                </tr>
                <tr>
                    <td style="color:#64748b;"><?php esc_html_e( 'Health Message:', 'svago' ); ?></td>
                    <td><span><?php echo esc_html( $message ); ?></span></td>
                </tr>
            </table>

            <div style="display:flex; gap:8px; border-top:1px solid #f1f5f9; padding-top:12px; flex-wrap:wrap;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-availability' ) ); ?>" class="button button-secondary button-small">
                    📅 <?php esc_html_e( 'Availability', 'svago' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-onsite-booking' ) ); ?>" class="button button-secondary button-small">
                    🛎️ <?php esc_html_e( 'Onsite Booking', 'svago' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-logs' ) ); ?>" class="button button-secondary button-small">
                    📜 <?php esc_html_e( 'Activity Logs', 'svago' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=svago-settings' ) ); ?>" class="button button-secondary button-small">
                    ⚙️ <?php esc_html_e( 'Settings', 'svago' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Get a setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get( string $key, $default = '' ) {
        return get_option( "svago_{$key}", $default );
    }
}
<?php
/**
 * Activity and Error Logger
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logger for plugin activity, errors, and debugging
 * Uses custom database table wp_svago_logs
 */
final class Logger {

    /**
     * Singleton instance
     *
     * @var Logger|null
     */
    private static ?self $instance = null;

    /**
     * Database table name
     *
     * @var string
     */
    private string $table_name;

    /**
     * Maximum log entries to keep
     *
     * @var int
     */
    private const MAX_ENTRIES = 500;

    /**
     * Get singleton instance
     *
     * @return Logger
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'svago_logs';

        // Auto-create database table if missing
        $this->ensure_table_exists();

        // Register hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 15 );
        add_action( 'wp_ajax_svago_purge_logs', [ $this, 'ajax_purge_logs' ] );
    }

    /**
     * Ensure logger database table exists
     */
    public function ensure_table_exists(): void {
        global $wpdb;
        if ( ! empty( $wpdb ) && ! empty( $this->table_name ) && method_exists( $wpdb, 'get_var' ) ) {
            // Avoid calling SHOW TABLES in mock DB environments without get_charset_collate
            if ( ! method_exists( $wpdb, 'get_charset_collate' ) ) {
                return;
            }
            $table = $this->table_name;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
            if ( ! $exists ) {
                self::activate();
            }
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Create database table on activation
     */
    public static function activate(): void {
        global $wpdb;
        if ( empty( $wpdb ) || ! is_object( $wpdb ) ) {
            return;
        }
        $table_name = $wpdb->prefix . 'svago_logs';
        $charset_collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_level (level)
        ) {$charset_collate};";

        if ( ! function_exists( 'dbDelta' ) && defined( 'WP_CONTENT_DIR' ) && function_exists( 'get_locale' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if ( function_exists( 'dbDelta' ) ) {
            dbDelta( $sql );
        } elseif ( method_exists( $wpdb, 'query' ) ) {
            $wpdb->query( $sql );
        }
    }

    /**
     * Log a message
     *
     * @param string $level    Log level: debug, info, notice, warning, error, critical, alert, emergency
     * @param string $message  Log message
     * @param array  $context  Additional context data
     */
    public function log( string $level, string $message, array $context = [] ): void {
        global $wpdb;

        $valid_levels = [ 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' ];
        if ( ! in_array( $level, $valid_levels, true ) ) {
            $level = 'info';
        }

        $user_id = get_current_user_id() ?: null;

        $wpdb->insert(
            $this->table_name,
            [
                'timestamp' => current_time( 'mysql' ),
                'level'     => $level,
                'message'   => $message,
                'context'   => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'user_id'   => $user_id,
            ],
            [ '%s', '%s', '%s', '%s', '%d' ]
        );

        // Auto-purge old entries occasionally (1 in 50 chance)
        if ( mt_rand( 1, 50 ) === 1 ) {
            $this->purge_old();
        }
    }

    /**
     * Get recent logs
     *
     * @param int $limit
     * @param string|null $level_filter
     * @return array
     */
    public function get_logs( int $limit = self::MAX_ENTRIES, ?string $level_filter = null ): array {
        global $wpdb;

        $this->ensure_table_exists();

        $query = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $params = [];

        if ( $level_filter ) {
            $query .= " AND level = %s";
            $params[] = $level_filter;
        }

        $query .= " ORDER BY timestamp DESC LIMIT %d";
        $params[] = $limit;

        $output_type = defined( 'ARRAY_A' ) ? \ARRAY_A : 'ARRAY_A';
        $results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ), $output_type );

        // Decode context JSON
        foreach ( $results as &$row ) {
            if ( $row['context'] ) {
                $row['context'] = json_decode( $row['context'], true ) ?? [];
            } else {
                $row['context'] = [];
            }
        }

        return $results ?? [];
    }

    /**
     * Purge old log entries, keeping only the most recent
     *
     * @param int $keep Number of entries to keep
     * @return int Number of deleted rows
     */
    public function purge_old( int $keep = self::MAX_ENTRIES ): int {
        global $wpdb;

        $this->ensure_table_exists();

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        if ( $total <= $keep ) {
            return 0;
        }

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$this->table_name} ORDER BY timestamp DESC LIMIT %d
                    ) AS keep_these
                )",
                $keep
            )
        );

        return $deleted ?? 0;
    }

    /**
     * Add admin menu for logs
     */
    public function add_admin_menu(): void {
        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

        global $submenu;
        $already_registered = false;
        if ( isset( $submenu['svago-dashboard'] ) ) {
            foreach ( $submenu['svago-dashboard'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'svago-logs' ) {
                    $already_registered = true;
                    break;
                }
            }
        }

        if ( ! $already_registered ) {
            // Add under Hotel Booking menu
            add_submenu_page(
                'svago-dashboard',
                __( 'Hotel Booking Logs', 'svago' ),
                __( 'Logs', 'svago' ),
                $cap,
                'svago-logs',
                [ $this, 'render_logs_page' ]
            );
        }

        // Also add shortcut under WooCommerce Orders if available
        add_submenu_page(
            'woocommerce',
            __( 'Hotel Booking Logs', 'svago' ),
            __( 'Booking Logs', 'svago' ),
            $cap,
            'svago-logs-wc',
            [ $this, 'render_logs_page' ]
        );
    }

    /**
     * Render logs admin page
     */
    public function render_logs_page(): void {
        $this->ensure_table_exists();

        // Handle bulk actions
        if ( isset( $_GET['action'] ) && 'purge' === $_GET['action'] && check_admin_referer( 'svago_purge_logs' ) ) {
            $deleted = $this->purge_old();
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Purged %d old log entries.', 'svago' ), $deleted ) . '</p></div>';
        }

        $level_filter = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : '';
        $logs = $this->get_logs( 200, $level_filter ?: null );

        $levels = [ 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ?: __( 'Hotel Booking Logs', 'svago' ) ); ?></h1>

            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="svago-logs" />
                    <label class="screen-reader-text" for="level"><?php _e( 'Filter by level', 'svago' ); ?></label>
                    <select name="level" id="level">
                        <option value=""><?php _e( 'All Levels', 'svago' ); ?></option>
                        <?php foreach ( $levels as $level ) : ?>
                            <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $level_filter, $level ); ?>>
                                <?php echo esc_html( ucfirst( $level ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" class="button" value="<?php _e( 'Filter', 'svago' ); ?>" />
                </form>

                <form method="get" action="" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to purge old logs?', 'svago' ) ); ?>')">
                    <input type="hidden" name="page" value="svago-logs" />
                    <input type="hidden" name="action" value="purge" />
                    <?php wp_nonce_field( 'svago_purge_logs' ); ?>
                    <input type="submit" class="button button-secondary" value="<?php _e( 'Purge Old Logs (keep 500)', 'svago' ); ?>" />
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 150px;"><?php _e( 'Timestamp', 'svago' ); ?></th>
                        <th scope="col" style="width: 100px;"><?php _e( 'Level', 'svago' ); ?></th>
                        <th scope="col"><?php _e( 'Message', 'svago' ); ?></th>
                        <th scope="col" style="width: 200px;"><?php _e( 'Context', 'svago' ); ?></th>
                        <th scope="col" style="width: 100px;"><?php _e( 'User', 'svago' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr>
                            <td colspan="5" style="text-align:center;"><?php _e( 'No logs found.', 'svago' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                                <td>
                                    <span class="svago-log-level svago-log-<?php echo esc_attr( $log['level'] ); ?>">
                                        <?php echo esc_html( ucfirst( $log['level'] ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log['message'] ); ?></td>
                                <td>
                                    <pre style="max-height:100px; overflow:auto; font-size:11px; margin:0;"><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) ); ?></pre>
                                </td>
                                <td>
                                    <?php if ( $log['user_id'] ) : ?>
                                        <?php $user = get_userdata( $log['user_id'] ); ?>
                                        <?php echo $user ? esc_html( $user->display_name ) : esc_html( 'User #' . $log['user_id'] ); ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <style>
                .svago-log-level { padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
                .svago-log-debug { background: #e0e0e0; color: #333; }
                .svago-log-info { background: #d0eaff; color: #0066cc; }
                .svago-log-notice { background: #fff3cd; color: #856404; }
                .svago-log-warning { background: #ffe0b2; color: #e65100; }
                .svago-log-error { background: #ffcdd2; color: #c62828; }
                .svago-log-critical { background: #f8bbd0; color: #ad1457; }
                .svago-log-alert { background: #ffab91; color: #bf360c; }
                .svago-log-emergency { background: #ef9a9a; color: #b71c1c; }
            </style>
        </div>
        <?php
    }

    /**
     * AJAX handler for purging logs
     */
    public function ajax_purge_logs(): void {
        check_ajax_referer( 'svago_purge_logs', 'nonce' );

        $cap = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'svago' ) );
        }

        $deleted = $this->purge_old();
        wp_send_json_success( [ 'deleted' => $deleted ] );
    }

    /**
     * Convenience methods for common log levels
     */
    public function debug( string $message, array $context = [] ): void {
        $this->log( 'debug', $message, $context );
    }

    public function info( string $message, array $context = [] ): void {
        $this->log( 'info', $message, $context );
    }

    public function notice( string $message, array $context = [] ): void {
        $this->log( 'notice', $message, $context );
    }

    public function warning( string $message, array $context = [] ): void {
        $this->log( 'warning', $message, $context );
    }

    public function error( string $message, array $context = [] ): void {
        $this->log( 'error', $message, $context );
    }

    public function critical( string $message, array $context = [] ): void {
        $this->log( 'critical', $message, $context );
    }
}
<?php
/**
 * WooCommerce Room Meta Fields
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds custom meta fields to WooCommerce products for hotel rooms
 */
final class Room_Meta {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Guard to prevent duplicate saves in same request
     */
    private static array $saved = [];

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
     * Meta field definitions
     *
     * @var array
     */
    private array $fields = [
        '_ota_room_id' => [
            'label'       => 'OTA Room ID',
            'description' => 'Unique room identifier from OTA system (e.g., RM001)',
            'type'        => 'text',
            'placeholder' => 'RM001',
        ],
        '_ota_rate_ids' => [
            'label'       => 'OTA Rate IDs (JSON)',
            'description' => 'JSON array of rate plan IDs and names from OTA. Example: [{"rate_id":"RT001","name":"Room Only"},{"rate_id":"RT002","name":"With Breakfast"}]',
            'type'        => 'textarea',
            'placeholder' => '[{"rate_id":"RT001","name":"Room Only"}]',
        ],
        '_room_size' => [
            'label'       => 'Room Size',
            'description' => 'e.g., "350 sq ft"',
            'type'        => 'text',
            'placeholder' => '350 sq ft',
        ],
        '_bed_type' => [
            'label'       => 'Bed Type',
            'description' => 'e.g., "King size Bed", "Two Double Beds"',
            'type'        => 'text',
            'placeholder' => 'King size Bed',
        ],
        '_max_adults' => [
            'label'       => 'Max Adults',
            'description' => 'Maximum number of adults allowed',
            'type'        => 'number',
            'placeholder' => '2',
            'min'         => 1,
            'max'         => 10,
        ],
        '_max_children' => [
            'label'       => 'Max Children',
            'description' => 'Maximum number of children allowed',
            'type'        => 'number',
            'placeholder' => '1',
            'min'         => 0,
            'max'         => 5,
        ],
        '_amenities' => [
            'label'       => 'Amenities (JSON)',
            'description' => 'JSON array of amenity labels or newline/comma-separated list. Example: ["Private Balcony","King size Bed","Shower area","TV Unit"]',
            'type'        => 'textarea',
            'placeholder' => '["Private Balcony","King size Bed"]',
        ],
        '_room_images' => [
            'label'       => 'Room Images (JSON)',
            'description' => 'JSON array of image URLs or WordPress attachment IDs. Example: ["/wp-content/uploads/rooms/deluxe-1.jpg"]',
            'type'        => 'textarea',
            'placeholder' => '["/wp-content/uploads/rooms/deluxe-1.jpg"]',
        ],
    ];

    /**
     * Constructor - register hooks
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_product', [ $this, 'save_meta' ], 10, 1 );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_meta' ], 10, 1 );
        add_filter( 'manage_product_posts_columns', [ $this, 'add_admin_columns' ] );
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );
    }

    /**
     * Add meta box to product edit screen
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'svago_room_meta',
            __( 'Hotel Room Details (OTA)', 'svago' ),
            [ $this, 'render_meta_box' ],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     *
     * @param \WP_Post $post
     */
    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'svago_room_meta_save', 'svago_room_meta_nonce' );

        echo '<table class="form-table svago-room-meta-table">';

        foreach ( $this->fields as $meta_key => $field ) {
            $value = get_post_meta( $post->ID, $meta_key, true );
            $this->render_field( $meta_key, $field, $value );
        }

        echo '</table>';
    }

    /**
     * Render a single field
     *
     * @param string $meta_key
     * @param array  $field
     * @param mixed  $value
     */
    private function render_field( string $meta_key, array $field, $value ): void {
        $label       = $field['label'];
        $description = $field['description'] ?? '';
        $type        = $field['type'];
        $placeholder = $field['placeholder'] ?? '';
        $min         = $field['min'] ?? '';
        $max         = $field['max'] ?? '';

        // Format JSON values for display
        if ( 'textarea' === $type && $value ) {
            $decoded = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $value = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            }
        }

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $meta_key ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>';

        switch ( $type ) {
            case 'text':
                printf(
                    '<input type="text" name="%1$s" id="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
                    esc_attr( $meta_key ),
                    esc_attr( $value ),
                    esc_attr( $placeholder )
                );
                break;

            case 'textarea':
                printf(
                    '<textarea name="%1$s" id="%1$s" class="large-text code" rows="4" placeholder="%2$s">%3$s</textarea>',
                    esc_attr( $meta_key ),
                    esc_attr( $placeholder ),
                    esc_textarea( $value )
                );
                break;

            case 'number':
                printf(
                    '<input type="number" name="%1$s" id="%1$s" value="%2$s" class="small-text" placeholder="%3$s" min="%4$s" max="%5$s" />',
                    esc_attr( $meta_key ),
                    esc_attr( $value ),
                    esc_attr( $placeholder ),
                    esc_attr( $min ),
                    esc_attr( $max )
                );
                break;
        }

        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }

        echo '</td>';
        echo '</tr>';
    }

    /**
     * Save meta box data
     *
     * @param int $post_id
     */
    public function save_meta( int $post_id ): void {
        // Prevent running multiple times during the same request
        if ( isset( self::$saved[ $post_id ] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['svago_room_meta_nonce'] ) ||
             ! wp_verify_nonce( $_POST['svago_room_meta_nonce'], 'svago_room_meta_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saved[ $post_id ] = true;

        // Save each field
        foreach ( $this->fields as $meta_key => $field ) {
            if ( isset( $_POST[ $meta_key ] ) ) {
                $value = $this->sanitize_field( $_POST[ $meta_key ], $field['type'] );
                update_post_meta( $post_id, $meta_key, $value );
                if ( '_ota_room_id' === $meta_key ) {
                    update_post_meta( $post_id, '_svago_ota_room_id', $value );
                }
            }
        }
    }

    /**
     * Sanitize field value based on type
     *
     * @param mixed  $value
     * @param string $type
     * @return mixed
     */
    private function sanitize_field( $value, string $type ) {
        if ( 'textarea' === $type ) {
            $trimmed = trim( (string) $value );
            if ( empty( $trimmed ) ) {
                return '[]';
            }

            // Check if already valid JSON
            $decoded = json_decode( wp_unslash( $trimmed ), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return wp_json_encode( $decoded );
            }

            // Convert newline or comma separated values into JSON array
            $items = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', wp_unslash( $trimmed ) ) ) );
            if ( ! empty( $items ) ) {
                return wp_json_encode( array_values( $items ) );
            }

            return '[]';
        }

        if ( 'number' === $type ) {
            return intval( $value );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Add custom columns to product admin list
     *
     * @param array $columns
     * @return array
     */
    public function add_admin_columns( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'name' === $key ) {
                $new_columns['svago_ota_room_id'] = __( 'OTA Room ID', 'svago' );
                $new_columns['svago_room_type']   = __( 'Room Type', 'svago' );
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column content
     *
     * @param string $column
     * @param int    $post_id
     */
    public function render_admin_columns( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'svago_ota_room_id':
                $ota_id = get_post_meta( $post_id, '_ota_room_id', true );
                echo $ota_id ? esc_html( $ota_id ) : '<span style="color:#999;">—</span>';
                break;

            case 'svago_room_type':
                $bed_type = get_post_meta( $post_id, '_bed_type', true );
                $size     = get_post_meta( $post_id, '_room_size', true );
                $parts    = array_filter( [ $bed_type, $size ] );
                echo $parts ? esc_html( implode( ' · ', $parts ) ) : '<span style="color:#999;">—</span>';
                break;
        }
    }

    /**
     * Get room meta for a product
     *
     * @param int $product_id
     * @return array
     */
    public static function get_room_meta( int $product_id ): array {
        $meta = [];
        foreach ( [ '_ota_room_id', '_ota_rate_ids', '_room_size', '_bed_type', '_max_adults', '_max_children', '_amenities', '_room_images' ] as $key ) {
            $meta[ $key ] = get_post_meta( $product_id, $key, true );
        }

        // Decode JSON fields
        $json_fields = [ '_ota_rate_ids', '_amenities', '_room_images' ];
        foreach ( $json_fields as $field ) {
            if ( $meta[ $field ] ) {
                $decoded = json_decode( $meta[ $field ], true );
                $meta[ $field ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
            } else {
                $meta[ $field ] = [];
            }
        }

        // Cast numeric fields
        $meta['_max_adults']   = intval( $meta['_max_adults'] );
        $meta['_max_children'] = intval( $meta['_max_children'] );

        return $meta;
    }

    /**
     * Get all products that have OTA room ID set (i.e., are hotel rooms)
     *
     * @return array Array of product IDs
     */
    public static function get_hotel_room_products(): array {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ota_room_id' AND meta_value != ''"
        );
    }
}
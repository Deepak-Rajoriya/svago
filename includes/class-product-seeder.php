<?php
/**
 * WooCommerce Product Seeder for Hotel Rooms
 *
 * Automatically populates WooCommerce with the 4 themed hotel rooms
 * (Jazz Room, Literature Room, Love Room, Theatre Room) as simple products
 * with dummy pricing, amenities, room specifications, and OTA meta.
 *
 * @package SVAGO
 */

namespace SVAGO;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seeder class for Hotel Room products
 */
final class Product_Seeder {

    /**
     * The 4 Room definitions with complete dummy pricing, amenities, and specifications
     */
    public const ROOMS = [
        [
            'id'          => 'RM001',
            'name'        => 'Jazz Room',
            'slug'        => 'jazz-room',
            'description' => 'Inspired by rhythmic melodies and smooth brass, featuring warm acoustics, vintage jazz vinyl player, acoustic soundproofing, and elegant urban decor.',
            'short_desc'  => 'Melodic haven with vintage vinyl record player and urban balcony.',
            'price'       => 70854,
            'size'        => '380 sq ft',
            'bed_type'    => 'King size Bed',
            'max_adults'  => 2,
            'max_children'=> 1,
            'amenities'   => [
                'Private Balcony',
                'King size Bed',
                'Vinyl Record Player',
                'Acoustic Soundproofing',
                'Smart TV Unit',
                'Mini Bar',
                'Walk-In Wardrobe',
                'Dressing area',
            ],
            'rates'       => [
                [ 'rate_id' => 'RT001', 'name' => 'Room Only', 'price_per_night' => 70854 ],
                [ 'rate_id' => 'RT002', 'name' => 'With Breakfast', 'price_per_night' => 78000 ],
                [ 'rate_id' => 'RT003', 'name' => 'Half Board', 'price_per_night' => 89500 ],
            ],
            'images'      => [ '/wp-content/uploads/rooms/jazz-1.jpg' ],
            'types'       => [ 'Balcony', 'Jacuzzi' ],
        ],
        [
            'id'          => 'RM002',
            'name'        => 'Literature Room',
            'slug'        => 'literature-room',
            'description' => 'A tranquil haven for book lovers, equipped with a curated private library wall, dedicated reading nook, plush armchairs, writing desk, and calming garden views.',
            'short_desc'  => 'Tranquil retreat with curated library wall and deep soaking bathtub.',
            'price'       => 125000,
            'size'        => '420 sq ft',
            'bed_type'    => 'King size Bed',
            'max_adults'  => 2,
            'max_children'=> 1,
            'amenities'   => [
                'Private Library Wall',
                'Reading Nook & Recliner',
                'King size Bed',
                'Writing Desk',
                'Warm Ambient Lighting',
                'Espresso Machine',
                'Ensuite Bathtub',
                'Walk-In Wardrobe',
            ],
            'rates'       => [
                [ 'rate_id' => 'RT010', 'name' => 'Room Only', 'price_per_night' => 125000 ],
                [ 'rate_id' => 'RT011', 'name' => 'With Breakfast', 'price_per_night' => 135000 ],
                [ 'rate_id' => 'RT012', 'name' => 'Full Board', 'price_per_night' => 155000 ],
            ],
            'images'      => [ '/wp-content/uploads/rooms/literature-1.jpg' ],
            'types'       => [ 'Balcony', 'Jacuzzi' ],
        ],
        [
            'id'          => 'RM003',
            'name'        => 'Love Room',
            'slug'        => 'love-room',
            'description' => 'An intimate romantic retreat featuring mood ambient lighting, freestanding whirlpool bathtub, plush bedding, chilled champagne cooler, and panoramic sunset balcony.',
            'short_desc'  => 'Romantic suite with whirlpool Jacuzzi bathtub and sunset balcony.',
            'price'       => 92000,
            'size'        => '460 sq ft',
            'bed_type'    => 'King size Bed',
            'max_adults'  => 2,
            'max_children'=> 0,
            'amenities'   => [
                'Whirlpool / Jacuzzi Bathtub',
                'Private Sunset Balcony',
                'Mood Lighting System',
                'King size Bed',
                'Chilled Champagne Cooler',
                'Plush Bathrobes',
                'Rain Shower',
                'Dressing Vanity',
            ],
            'rates'       => [
                [ 'rate_id' => 'RT020', 'name' => 'Room Only', 'price_per_night' => 92000 ],
                [ 'rate_id' => 'RT021', 'name' => 'With Breakfast', 'price_per_night' => 102000 ],
                [ 'rate_id' => 'RT022', 'name' => 'Half Board', 'price_per_night' => 115000 ],
            ],
            'images'      => [ '/wp-content/uploads/rooms/love-1.jpg' ],
            'types'       => [ 'Balcony', 'Jacuzzi' ],
        ],
        [
            'id'          => 'RM004',
            'name'        => 'Theatre Room',
            'slug'        => 'theatre-room',
            'description' => 'The ultimate cinematic experience with an in-room 4K laser projector, Dolby Atmos surround sound, motorized blackout shades, popcorn snack bar, and luxury recliner seating.',
            'short_desc'  => 'Cinematic paradise with 4K laser projector and Dolby Atmos audio.',
            'price'       => 145000,
            'size'        => '520 sq ft',
            'bed_type'    => 'Two Double Beds',
            'max_adults'  => 4,
            'max_children'=> 2,
            'amenities'   => [
                '4K Laser Projector & Screen',
                'Dolby Atmos Surround Sound',
                'Motorized Blackout Blinds',
                'Two Double Beds',
                'Recliner Cinema Sofa',
                'Popcorn & Snack Bar',
                'Walk-In Wardrobe',
                'Ensuite Shower',
            ],
            'rates'       => [
                [ 'rate_id' => 'RT030', 'name' => 'Room Only', 'price_per_night' => 145000 ],
                [ 'rate_id' => 'RT031', 'name' => 'With Breakfast', 'price_per_night' => 158000 ],
                [ 'rate_id' => 'RT032', 'name' => 'Full Board', 'price_per_night' => 180000 ],
            ],
            'images'      => [ '/wp-content/uploads/rooms/theatre-1.jpg' ],
            'types'       => [ 'Balcony', 'Jacuzzi' ],
        ],
    ];

    /**
     * Populate WooCommerce with the 4 hotel rooms
     *
     * @return array Array of created/updated product results
     */
    public static function seed_rooms(): array {
        if ( ! function_exists( 'wp_insert_post' ) ) {
            return [];
        }

        $results = [];

        // Ensure "Hotel Rooms" product category exists
        $cat_id = 0;
        if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) && function_exists( 'get_term_by' ) ) {
            $cat = get_term_by( 'slug', 'hotel-rooms', 'product_cat' );
            if ( ! $cat && function_exists( 'wp_insert_term' ) ) {
                $inserted_cat = wp_insert_term(
                    'Hotel Rooms',
                    'product_cat',
                    [
                        'description' => 'Available hotel rooms and luxury suites',
                        'slug'        => 'hotel-rooms',
                    ]
                );
                if ( ! is_wp_error( $inserted_cat ) && isset( $inserted_cat['term_id'] ) ) {
                    $cat_id = (int) $inserted_cat['term_id'];
                }
            } elseif ( $cat ) {
                $cat_id = (int) $cat->term_id;
            }
        }

        foreach ( self::ROOMS as $room ) {
            $existing_id = self::find_existing_room_product( $room['id'], $room['name'] );

            $post_data = [
                'post_title'   => $room['name'],
                'post_content' => $room['description'],
                'post_excerpt' => $room['short_desc'],
                'post_status'  => 'publish',
                'post_type'    => 'product',
            ];

            if ( $existing_id > 0 && function_exists( 'wp_update_post' ) ) {
                $post_data['ID'] = $existing_id;
                $product_id = wp_update_post( $post_data );
                $action = 'updated';
            } else {
                $post_data['post_name'] = $room['slug'];
                $product_id = wp_insert_post( $post_data );
                $action = 'created';
            }

            if ( is_wp_error( $product_id ) || ! $product_id ) {
                continue;
            }

            // Assign simple product type
            if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_type' ) && function_exists( 'wp_set_object_terms' ) ) {
                wp_set_object_terms( $product_id, 'simple', 'product_type' );
            }

            // Assign category
            if ( $cat_id > 0 && function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) && function_exists( 'wp_set_object_terms' ) ) {
                wp_set_object_terms( $product_id, [ $cat_id ], 'product_cat' );
            }

            // Standard WooCommerce product metadata
            update_post_meta( $product_id, '_price', (string) $room['price'] );
            update_post_meta( $product_id, '_regular_price', (string) $room['price'] );
            update_post_meta( $product_id, '_sale_price', '' );
            update_post_meta( $product_id, '_stock_status', 'instock' );
            update_post_meta( $product_id, '_manage_stock', 'no' );
            update_post_meta( $product_id, '_virtual', 'yes' );
            update_post_meta( $product_id, '_visibility', 'visible' );

            // Svago Hotel Booking Room Metadata
            update_post_meta( $product_id, '_ota_room_id', $room['id'] );
            update_post_meta( $product_id, '_svago_ota_room_id', $room['id'] );
            update_post_meta( $product_id, '_ota_rate_ids', wp_json_encode( $room['rates'] ) );
            update_post_meta( $product_id, '_room_size', $room['size'] );
            update_post_meta( $product_id, '_bed_type', $room['bed_type'] );
            update_post_meta( $product_id, '_max_adults', $room['max_adults'] );
            update_post_meta( $product_id, '_max_children', $room['max_children'] );
            update_post_meta( $product_id, '_amenities', wp_json_encode( $room['amenities'] ) );
            update_post_meta( $product_id, '_room_images', wp_json_encode( $room['images'] ) );
            update_post_meta( $product_id, '_room_types', wp_json_encode( $room['types'] ?? [ 'Balcony', 'Jacuzzi' ] ) );
            update_post_meta( $product_id, '_svago_room_types', wp_json_encode( $room['types'] ?? [ 'Balcony', 'Jacuzzi' ] ) );

            // Clear WooCommerce lookup cache if WC is active
            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients( $product_id );
            }

            $results[] = [
                'id'         => $product_id,
                'room_id'    => $room['id'],
                'name'       => $room['name'],
                'price'      => $room['price'],
                'action'     => $action,
            ];
        }

        // Log seeding in Logger if available
        if ( class_exists( 'SVAGO\Logger' ) ) {
            Logger::get_instance()->info(
                sprintf( 'Successfully populated %d hotel room products into WooCommerce.', count( $results ) ),
                [ 'rooms' => array_column( $results, 'name' ) ]
            );
        }

        return $results;
    }

    /**
     * Auto-Map & Sync Rooms (Workflow requested in admin panel):
     * 1. Fetches available rooms and details from OTA API (or mock data).
     * 2. Automatically creates the corresponding WooCommerce products (with images, descriptions, amenities, specs).
     * 3. Maps the WooCommerce products to the OTA Room IDs (_ota_room_id and _svago_ota_room_id).
     * 4. Syncs initial availability and pricing.
     * 5. Keeps WooCommerce products normally editable by admin (does not overwrite custom admin prices).
     *
     * @return array
     */
    public static function auto_map_and_sync(): array {
        $provider = Provider_Factory::get();
        $loader   = new Fallback_Loader();

        // 1. Fetch available rooms from provider or fallback
        $rooms = [];
        try {
            $provider_rooms = $provider->get_rooms();
            if ( ! empty( $provider_rooms ) && is_array( $provider_rooms ) ) {
                $rooms = $provider_rooms;
            }
        } catch ( \Throwable $e ) {
            // fallback
        }

        if ( empty( $rooms ) ) {
            $rooms_data = $loader->load_rooms();
            $rooms = $rooms_data['rooms'] ?? ( is_array( $rooms_data ) ? $rooms_data : self::ROOMS );
        }

        // 2. Fetch rates catalog
        $rates_data = [];
        try {
            $rates_data = $loader->load_rates();
        } catch ( \Throwable $e ) {
            // ignore
        }

        // 3. Ensure category "Hotel Rooms" exists
        $cat_id = 0;
        if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) && function_exists( 'get_term_by' ) ) {
            $cat = get_term_by( 'slug', 'hotel-rooms', 'product_cat' );
            if ( ! $cat && function_exists( 'wp_insert_term' ) ) {
                $inserted_cat = wp_insert_term(
                    'Hotel Rooms',
                    'product_cat',
                    [
                        'description' => 'Available hotel rooms and luxury suites',
                        'slug'        => 'hotel-rooms',
                    ]
                );
                if ( ! is_wp_error( $inserted_cat ) && isset( $inserted_cat['term_id'] ) ) {
                    $cat_id = (int) $inserted_cat['term_id'];
                }
            } elseif ( $cat ) {
                $cat_id = (int) $cat->term_id;
            }
        }

        $results = [];

        foreach ( $rooms as $room ) {
            $room_id = $room['id'] ?? '';
            $name    = $room['name'] ?? '';
            if ( empty( $room_id ) || empty( $name ) ) {
                continue;
            }

            // Rates for this room
            $room_rates = $rates_data[ $room_id ] ?? ( $room['rates'] ?? [] );
            $base_price = 0;
            if ( ! empty( $room_rates[0]['price_per_night'] ) ) {
                $base_price = (float) $room_rates[0]['price_per_night'];
            } elseif ( ! empty( $room['price'] ) ) {
                $base_price = (float) $room['price'];
            } elseif ( ! empty( $room['base_price'] ) ) {
                $base_price = (float) $room['base_price'];
            } else {
                $base_price = 50000;
            }

            $existing_id = self::find_existing_room_product( $room_id, $name );
            $product_id  = 0;
            $action      = '';

            if ( $existing_id > 0 ) {
                $product_id = $existing_id;
                $action     = 'synced';

                // Check existing product price: only set if unset or 0 so admin edits are preserved!
                $current_price = get_post_meta( $product_id, '_price', true );
                if ( empty( $current_price ) || (float) $current_price <= 0 ) {
                    update_post_meta( $product_id, '_price', (string) $base_price );
                    update_post_meta( $product_id, '_regular_price', (string) $base_price );
                }
            } else {
                // Create new WooCommerce product
                $slug = ! empty( $room['slug'] ) ? sanitize_title( $room['slug'] ) : sanitize_title( $name );
                $post_data = [
                    'post_title'   => $name,
                    'post_content' => $room['description'] ?? '',
                    'post_excerpt' => $room['short_desc'] ?? ( substr( $room['description'] ?? '', 0, 120 ) . '...' ),
                    'post_name'    => $slug,
                    'post_status'  => 'publish',
                    'post_type'    => 'product',
                ];

                if ( function_exists( 'wp_insert_post' ) ) {
                    $product_id = wp_insert_post( $post_data );
                }

                if ( is_wp_error( $product_id ) || ! $product_id ) {
                    continue;
                }

                $action = 'created';

                // Set initial pricing on new product
                update_post_meta( $product_id, '_price', (string) $base_price );
                update_post_meta( $product_id, '_regular_price', (string) $base_price );
                update_post_meta( $product_id, '_sale_price', '' );
            }

            // Assign simple product type
            if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_type' ) && function_exists( 'wp_set_object_terms' ) ) {
                wp_set_object_terms( $product_id, 'simple', 'product_type' );
            }

            // Assign category
            if ( $cat_id > 0 && function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) && function_exists( 'wp_set_object_terms' ) ) {
                wp_set_object_terms( $product_id, [ $cat_id ], 'product_cat' );
            }

            // Standard WooCommerce product flags
            update_post_meta( $product_id, '_stock_status', 'instock' );
            update_post_meta( $product_id, '_manage_stock', 'no' );
            update_post_meta( $product_id, '_virtual', 'yes' );
            update_post_meta( $product_id, '_visibility', 'visible' );

            // Always ensure OTA room mapping keys are linked
            update_post_meta( $product_id, '_ota_room_id', $room_id );
            update_post_meta( $product_id, '_svago_ota_room_id', $room_id );

            // Ensure specifications and amenities are present
            if ( ! empty( $room_rates ) ) {
                update_post_meta( $product_id, '_ota_rate_ids', wp_json_encode( $room_rates ) );
            }
            if ( ! empty( $room['size'] ) ) {
                update_post_meta( $product_id, '_room_size', $room['size'] );
            }
            if ( ! empty( $room['bed_type'] ) ) {
                update_post_meta( $product_id, '_bed_type', $room['bed_type'] );
            }
            if ( isset( $room['max_adults'] ) ) {
                update_post_meta( $product_id, '_max_adults', (int) $room['max_adults'] );
            }
            if ( isset( $room['max_children'] ) ) {
                update_post_meta( $product_id, '_max_children', (int) $room['max_children'] );
            }
            if ( ! empty( $room['amenities'] ) ) {
                update_post_meta( $product_id, '_amenities', is_array( $room['amenities'] ) ? wp_json_encode( $room['amenities'] ) : $room['amenities'] );
            }
            if ( ! empty( $room['images'] ) ) {
                update_post_meta( $product_id, '_room_images', is_array( $room['images'] ) ? wp_json_encode( $room['images'] ) : $room['images'] );
            }

            // Clear WooCommerce transients
            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients( $product_id );
            }

            // 4. Sync initial availability (30 days inventory)
            $today = current_time( 'Y-m-d' );
            $dates_to_sync = [];
            for ( $d = 0; $d < 30; $d++ ) {
                $d_str = date( 'Y-m-d', strtotime( "$today +$d days" ) );
                $dates_to_sync[] = [
                    'date'    => $d_str,
                    'qty'     => 5,
                    'price'   => $base_price,
                    'blocked' => false,
                ];
            }

            // Push initial availability to OTA Channel Manager
            try {
                $provider->push_availability( $room_id, $dates_to_sync, 0 );
            } catch ( \Throwable $e ) {
                // non-blocking
            }

            $current_product_price = get_post_meta( $product_id, '_price', true );
            $results[] = [
                'room_id'    => $room_id,
                'name'       => $name,
                'product_id' => $product_id,
                'price'      => (float) $current_product_price,
                'action'     => $action,
                'rates'      => count( $room_rates ),
            ];
        }

        // Log auto-map operation
        if ( class_exists( 'SVAGO\Logger' ) ) {
            Logger::get_instance()->info(
                sprintf( 'Auto-Map & Sync completed: %d rooms synchronized with WooCommerce products.', count( $results ) ),
                [ 'rooms' => array_column( $results, 'name' ), 'results' => $results ]
            );
        }

        return [
            'success' => true,
            'message' => sprintf( __( 'Auto-Map & Sync completed! %d rooms mapped to WooCommerce products.', 'svago' ), count( $results ) ),
            'count'   => count( $results ),
            'rooms'   => $results,
        ];
    }

    /**
     * Find if a product already exists by OTA room ID or title
     *
     * @param string $room_id OTA Room ID (e.g. RM001)
     * @param string $name Room Name
     * @return int Product post ID or 0
     */
    public static function find_existing_room_product( string $room_id, string $name ): int {
        global $wpdb;
        if ( empty( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return 0;
        }

        // 1. Look up by _ota_room_id or _svago_ota_room_id postmeta
        $by_meta = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE (meta_key = '_ota_room_id' OR meta_key = '_svago_ota_room_id') 
                 AND meta_value = %s LIMIT 1",
                $room_id
            )
        );
        if ( ! empty( $by_meta ) ) {
            return (int) $by_meta;
        }

        // 2. Look up by post_title
        $by_title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title = %s AND post_status != 'trash' LIMIT 1",
                $name
            )
        );
        if ( ! empty( $by_title ) ) {
            return (int) $by_title;
        }

        return 0;
    }
}

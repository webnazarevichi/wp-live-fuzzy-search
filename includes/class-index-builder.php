<?php
class WPLFS_Index_Builder {

    const CRON_HOOK = 'wplfs_rebuild_index_delayed';

    public static function rebuild_index() {
        $index = [];
        $settings = get_option( 'wplfs_settings', [] );
        $post_types = ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : [ 'post', 'page' ];
        $priorities = ! empty( $settings['post_type_priority'] ) ? (array) $settings['post_type_priority'] : [];

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'nopaging'       => true,
        ] );

        // Сортируем посты согласно приоритету перед формированием индекса
        usort( $posts, function( $a, $b ) use ( $priorities ) {
            $prio_a = isset( $priorities[ $a->post_type ] ) ? (int) $priorities[ $a->post_type ] : 10;
            $prio_b = isset( $priorities[ $b->post_type ] ) ? (int) $priorities[ $b->post_type ] : 10;

            if ( $prio_a === $prio_b ) {
                // Если приоритет одинаковый, сортируем по дате (новые выше)
                return strtotime( $b->post_date ) - strtotime( $a->post_date );
            }

            return $prio_a - $prio_b;
        } );

        foreach ( $posts as $post ) {
            $taxonomy_terms = [];
            $taxonomies = get_object_taxonomies( $post->post_type, 'names' );
            
            // Исключаем технические и служебные таксономии WooCommerce
            $exclude_taxonomies = [
                'product_visibility',
                'product_type',
                'product_shipping_class',
                'wc_rate_rating'
            ];
            
            $taxonomies = array_diff( $taxonomies, $exclude_taxonomies );

            if ( ! empty( $taxonomies ) ) {
                $terms = get_the_terms( $post->ID, $taxonomies );

                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        // Дополнительная проверка - пропускаем термины рейтинга (rated-1, rated-2 и т.д.)
                        if ( strpos( $term->slug, 'rated-' ) === 0 ) {
                            continue;
                        }
                        $taxonomy_terms[] = $term->name;
                    }
                }
            }

            // URL Миниатюр для товаров
            $thumbnail_url = '';
            if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $post->ID );
                if ( $product ) {
                    $image_id = $product->get_image_id();
                    if ( $image_id ) {
                        $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
                        $thumbnail_url = $image_src ? $image_src[0] : '';
                    }
                    if ( ! $thumbnail_url ) {
                        $thumbnail_url = wc_placeholder_img_src( 'medium' );
                    }
                }
            }

            // Цена для товаров
            $price = '';
            if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $post->ID );
                if ( $product ) {
                    $price = $product->get_price_html();
                }
            }

            $index[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'terms'     => array_values( array_unique( $taxonomy_terms ) ),
                'phrases'   => self::get_extra_phrases( $post->ID ),
                'url'       => get_permalink( $post->ID ),
                'thumb'    => $thumbnail_url,
                'price'    => $price,
                'type'      => $post->post_type,
            ];
        }

        $dir = dirname( WPLFS_INDEX_FILE );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        file_put_contents( WPLFS_INDEX_FILE, json_encode( $index, JSON_UNESCAPED_UNICODE ) );
    }

    public static function maybe_rebuild_index( $arg1 = null, $arg2 = null ) {
        $post_id = is_numeric( $arg1 ) ? absint( $arg1 ) : 0;

        if ( $arg2 instanceof WP_Post ) {
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            if ( ! in_array( $arg2->post_status, [ 'publish', 'future', 'draft', 'pending', 'private' ], true ) ) {
                return;
            }

            $settings = get_option( 'wplfs_settings', [] );
            $post_types = ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : [ 'post', 'page' ];

            if ( ! in_array( $arg2->post_type, $post_types, true ) ) {
                return;
            }
        }

        if ( $post_id && ! empty( $arg2 ) && ! ( $arg2 instanceof WP_Post ) ) {
            $settings = get_option( 'wplfs_settings', [] );
            $post_types = ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : [ 'post', 'page' ];
            $post_type = get_post_type( $post_id );

            if ( $post_type && ! in_array( $post_type, $post_types, true ) ) {
                return;
            }
        }

        self::schedule_rebuild();
    }

    public static function schedule_rebuild() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 10, self::CRON_HOOK );
        }
    }

    public static function rest_get_index( $request ) {
        if ( file_exists( WPLFS_INDEX_FILE ) ) {
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            readfile( WPLFS_INDEX_FILE );
            exit;
        }

        return new WP_Error( 'index_not_found', 'Поисковый индекс ещё не создан', [ 'status' => 404 ] );
    }

    public static function get_index_url() {
        return rest_url( 'wp-live-fuzzy-search/v1/index' );
    }

    public static function get_extra_phrases( $post_id ) {
        $phrases = get_post_meta( $post_id, '_wplfs_extra_phrases', true );

        if ( empty( $phrases ) ) {
            return [];
        }

        $lines = preg_split( '/\r\n|\r|\n/', (string) $phrases );

        return array_values( array_filter( array_map( 'trim', $lines ) ) );
    }
}

// Регистрируем отложенное событие
add_action( WPLFS_Index_Builder::CRON_HOOK, [ 'WPLFS_Index_Builder', 'rebuild_index' ] );

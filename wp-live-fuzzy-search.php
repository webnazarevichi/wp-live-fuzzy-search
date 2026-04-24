<?php
/**
 * Plugin Name: WP Live Fuzzy Search
 * Description: Wordpress fuzzy search plugin
 * Version: 1.0.0
 * Author: Andrey WN
 * Text Domain: wp-live-fuzzy-search
 * License: GPLv2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPLFS_VERSION', '1.0.0' );
define( 'WPLFS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPLFS_URL', plugin_dir_url( __FILE__ ) );
define( 'WPLFS_INDEX_FILE', wp_upload_dir()['basedir'] . '/wp-live-fuzzy-search/search-index.json' );

require_once WPLFS_PATH . 'includes/class-index-builder.php';
require_once WPLFS_PATH . 'includes/class-admin.php';
require_once WPLFS_PATH . 'includes/class-shortcode.php';

class WP_Live_Fuzzy_Search {

    public function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        new WPLFS_Admin();
        new WPLFS_Shortcode();

        // === РЕГИСТРАЦИЯ REST API ===
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Переменные запроса для получения стандартных результатов поиска WordPress
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_action( 'parse_query', [ $this, 'capture_search_ids' ], 999 );
        add_action( 'pre_get_posts', [ $this, 'filter_search_query' ], 1 );
        add_filter( 'posts_search', [ $this, 'disable_standard_search_sql' ], 999, 2 );
        add_filter( 'posts_orderby', [ $this, 'preserve_relevance_order' ], 999, 2 );
        add_filter( 'redirect_canonical', [ $this, 'maybe_disable_canonical_redirect' ], 10, 2 );

        // Подключение assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        
        // Фикс для get_search_query()
        add_filter( 'get_search_query', [ $this, 'restore_search_query' ] );

        // Пересоздаём индекс
        add_action( 'save_post', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ], 10, 2 );
        add_action( 'deleted_post', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ], 10, 1 );
        add_action( 'trash_post', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ], 10, 1 );
        add_action( 'set_object_terms', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ], 10, 6 );
        add_action( 'edited_term', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ] );
        add_action( 'created_term', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ] );
        add_action( 'deleted_term', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ] );
        add_action( 'woocommerce_update_product', [ 'WPLFS_Index_Builder', 'maybe_rebuild_index' ] );
    }

    public function register_rest_routes() {
        register_rest_route( 'wp-live-fuzzy-search/v1', '/index', [
            'methods'             => 'GET',
            'callback'            => [ 'WPLFS_Index_Builder', 'rest_get_index' ],
            'permission_callback' => '__return_true', // публичный доступ (индекс нужен всем)
        ]);
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'wplfs_ids';
        $vars[] = 'wplfs_search_ids';
        return $vars;
    }

    public function capture_search_ids( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        $ids_raw = $query->get( 'wplfs_ids' );
        if ( empty( $ids_raw ) ) {
            return;
        }

        $ids = array_values( array_filter( array_map( 'absint', explode( ',', wp_unslash( (string) $ids_raw ) ) ) ) );
        if ( empty( $ids ) ) {
            $query->set( 'wplfs_search_ids', [ 0 ] );
            return;
        }

        $query->set( 'wplfs_search_ids', $ids );
    }

    public function filter_search_query( $query ) {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        $ids = $query->get( 'wplfs_search_ids' );
        if ( empty( $ids ) ) {
            return;
        }

        $query->set( 'post__in', $ids );
        $query->set( 'orderby', 'post__in' );
        if ( ! $query->get( 'posts_per_page' ) ) {
            // $query->set( 'posts_per_page', get_option( 'posts_per_page' ) );
            $query->set( 'posts_per_page', -1 );
        }

        // Сохраняем оригинальный поисковый запрос для get_search_query()
        $original_s = $query->get( 's' );
        
        $query->set( 's', '' );
        $query->set( 'search_terms', [] );
        $query->set( 'search_terms_count', 0 );
        $query->set( 'search_orderby_title', [] );
        $query->set( 'sentence', false );
        
        // Возвращаем запрос обратно чтобы он был доступен в шаблоне
        $query->set( 's', $original_s );
    }

    public function disable_standard_search_sql( $search, $query ) {
        if ( ! is_admin() && $query->is_main_query() && $query->is_search() && ! empty( $query->get( 'wplfs_search_ids' ) ) ) {
            // Фикс чтобы get_search_query() продолжал работать
            global $wp_query;
            if ( ! isset( $wp_query->original_s ) ) {
                $wp_query->original_s = $query->get( 's' );
            }
            // return ' ';
            return ''; // заменили пробел на пустую строку
        }
        return $search;
    }

    public function preserve_relevance_order( $orderby, $query ) {
        if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
            $ids = $query->get( 'wplfs_search_ids' );
            if ( ! empty( $ids ) ) {
                global $wpdb;
                $ids_list = implode( ',', array_map( 'absint', (array) $ids ) );
                return "FIELD({$wpdb->posts}.ID,{$ids_list})";
            }
        }
        return $orderby;
    }

    public function maybe_disable_canonical_redirect( $redirect_url, $requested_url ) {
        if ( isset( $_GET['wplfs_ids'] ) ) {
            return false;
        }

        return $redirect_url;
    }

    public function enqueue_assets() {
        // if ( ! is_singular() && ! is_page( 'fuzzy-search-results' ) && ! has_shortcode( get_post_field( 'post_content' ), 'wp_live_fuzzy_search' ) ) {
        //     return;
        // }

        wp_enqueue_style( 'wplfs-fuzzy-search', WPLFS_URL . 'assets/css/fuzzy-search.css', [], WPLFS_VERSION );

        wp_enqueue_script( 'fuse-js', WPLFS_URL . 'assets/js/fuse.min.js', [], '7.0.0', true );

        wp_enqueue_script( 'wplfs-fuzzy-search', WPLFS_URL . 'assets/js/fuzzy-search.js', ['fuse-js'], WPLFS_VERSION, true );

        wp_localize_script( 'wplfs-fuzzy-search', 'wplfs', [
            'rest_url'    => rest_url( 'wp-live-fuzzy-search/v1/index' ),
            'min_length'  => 2,
            'debounce_ms' => 500,
            'search_page_url' => home_url( '/' ),
        ]);
    }

    public function activate() {
        $upload_dir = wp_upload_dir();
        $index_dir = $upload_dir['basedir'] . '/wp-live-fuzzy-search';
        if ( ! is_dir( $index_dir ) ) {
            wp_mkdir_p( $index_dir );
        }

        WPLFS_Index_Builder::rebuild_index();
    }

    public function restore_search_query( $query ) {
        global $wp_query;
        
        // Если у нас есть wplfs_ids в запросе - возвращаем оригинальный поисковый запрос
        if ( ! is_admin() && is_main_query() && is_search() && isset( $_GET['s'] ) ) {
            return wp_unslash( $_GET['s'] );
        }
        
        return $query;
    }

    public function deactivate() {
        // по желанию можно удалить индекс
    }
}

new WP_Live_Fuzzy_Search();

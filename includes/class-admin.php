<?php
class WPLFS_Admin {

    const SETTINGS_OPTION = 'wplfs_settings';
    const DEFAULT_POST_TYPES = [ 'post', 'page' ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ] );
        add_action( 'admin_post_wplfs_reset_settings', [ $this, 'reset_settings' ] );
    }

    public function add_settings_page() {
        add_options_page(
            'WP Live Fuzzy Search',
            'Fuzzy Search',
            'manage_options',
            'wp-live-fuzzy-search',
            [ $this, 'settings_page_html' ]
        );
    }

    public function register_settings() {
        register_setting( 'wplfs_settings_group', self::SETTINGS_OPTION, [ $this, 'sanitize_settings' ] );
    }

    public function sanitize_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : [];

        $available_post_types = $this->get_available_post_types();
        $selected_post_types = (array) ( $settings['post_types'] ?? [] );
        $priorities = (array) ( $settings['post_type_priority'] ?? [] );

        // Filter selected post types to ensure they exist
        $selected_post_types = array_values( array_intersect( $selected_post_types, array_keys( $available_post_types ) ) );
        $settings['post_types'] = $selected_post_types;

        // Sanitize priorities
        $sanitized_priorities = [];
        foreach ( $priorities as $pt => $priority ) {
            if ( array_key_exists( $pt, $available_post_types ) ) {
                $sanitized_priorities[ $pt ] = intval( $priority );
            }
        }
        $settings['post_type_priority'] = $sanitized_priorities;

        $settings['extra_phrases_enabled'] = ! empty( $settings['extra_phrases_enabled'] ) ? 1 : 0;
        $settings['popular_queries'] = isset( $settings['popular_queries'] ) ? sanitize_textarea_field( $settings['popular_queries'] ) : '';
        $settings['dropdown_empty_text'] = isset( $settings['dropdown_empty_text'] ) ? sanitize_text_field( $settings['dropdown_empty_text'] ) : 'Введите хотя бы 2 символа для поиска';
        $settings['dropdown_popular_title'] = isset( $settings['dropdown_popular_title'] ) ? sanitize_text_field( $settings['dropdown_popular_title'] ) : 'Часто ищут';
        $settings['dropdown_all_results_text'] = isset( $settings['dropdown_all_results_text'] ) ? sanitize_text_field( $settings['dropdown_all_results_text'] ) : 'Все результаты';
        $settings['dropdown_no_results_text'] = isset( $settings['dropdown_no_results_text'] ) ? sanitize_text_field( $settings['dropdown_no_results_text'] ) : 'Ничего не найдено';

        return $settings;
    }

    public function settings_page_html() {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        $available_post_types = $this->get_available_post_types();
        $selected_post_types = $settings['post_types'] ?? self::DEFAULT_POST_TYPES;
        $post_type_priority = $settings['post_type_priority'] ?? [];
        $popular_queries = $settings['popular_queries'] ?? '';
        ?>
        <div class="wrap">
            <h1>Настройки WP Live Fuzzy Search</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wplfs_settings_group' );
                do_settings_sections( 'wp-live-fuzzy-search' );
                ?>
                <table class="form-table">
                    <tr>
                        <th colspan="2">Поиск можно использовать с помощью шорткода [wp_live_fuzzy_search placeholder="Поиск товаров, страниц, категорий..."]</th>
                    </tr>
                    <tr>
                        <th>Пост-тайпы для индексации и приоритет</th>
                        <td>
                            <table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">Вкл.</th>
                                        <th>Тип записи</th>
                                        <th style="width: 100px;">Приоритет</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $available_post_types as $post_type => $label ) :
                                        $priority = isset( $post_type_priority[ $post_type ] ) ? $post_type_priority[ $post_type ] : 10;
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $selected_post_types, true ) ); ?>>
                                            </td>
                                            <td><?php echo esc_html( $label ); ?> (<code><?php echo esc_html( $post_type ); ?></code>)</td>
                                            <td>
                                                <input type="number" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_type_priority][<?php echo esc_attr( $post_type ); ?>]" value="<?php echo esc_attr( $priority ); ?>" class="small-text">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description">Чем <b>меньше</b> число, тем <b>выше</b> приоритет (например, 1 — самый высокий). Если ничего не выбрано, будут использоваться post и page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Дополнительные фразы</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[extra_phrases_enabled]" value="1" <?php checked( ! empty( $settings['extra_phrases_enabled'] ) ); ?>>
                                Включить поле дополнительных фраз у записей
                            </label>
                            <p class="description">Добавляет meta textarea в редакторе записей для строк, по которым запись будет находиться в поиске.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Популярные запросы</th>
                        <td>
                            <textarea name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[popular_queries]" rows="4" style="width:100%;" placeholder="botox|innotox|toxins"><?php echo esc_textarea( $popular_queries ); ?></textarea>
                            <p class="description">Перечислите фразы через символ <code>|</code>. Они будут показаны в dropdown, когда поле поиска пустое.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Тексты dropdown</th>
                        <td>
                            <p><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[dropdown_popular_title]" value="<?php echo esc_attr( $settings['dropdown_popular_title'] ?? 'Часто ищут' ); ?>" class="regular-text"> <span class="description">Заголовок блока популярных запросов</span></p>
                            <p><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[dropdown_empty_text]" value="<?php echo esc_attr( $settings['dropdown_empty_text'] ?? 'Введите хотя бы 2 символа для поиска' ); ?>" class="regular-text"> <span class="description">Текст при пустом input</span></p>
                            <p><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[dropdown_no_results_text]" value="<?php echo esc_attr( $settings['dropdown_no_results_text'] ?? 'Ничего не найдено' ); ?>" class="regular-text"> <span class="description">Текст при отсутствии результатов</span></p>
                            <p><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[dropdown_all_results_text]" value="<?php echo esc_attr( $settings['dropdown_all_results_text'] ?? 'Все результаты' ); ?>" class="regular-text"> <span class="description">Текст кнопки для перехода ко всем результатам</span></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Сохранить и пересобрать индекс' ); ?>
            </form>
            <p>
                <button class="button" onclick="jQuery.post(ajaxurl, {action:'wplfs_force_rebuild'}, function(){alert('Индекс пересобран!')}); return false;">Пересобрать индекс сейчас</button>
                <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wplfs_reset_settings' ), 'wplfs_reset_settings' ) ); ?>" onclick="return confirm('Сбросить настройки до базовых?');">Сбросить настройки</a>
            </p>
        </div>
        <?php
    }

    public function get_available_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $available = [];

        foreach ( $post_types as $post_type ) {
            if ( in_array( $post_type->name, [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ], true ) ) {
                continue;
            }

            $label = $post_type->labels->singular_name ?? $post_type->label ?? $post_type->name;
            $available[ $post_type->name ] = $label;
        }

        return $available;
    }

    public function add_meta_box() {
        $settings = get_option( self::SETTINGS_OPTION, [] );

        if ( empty( $settings['extra_phrases_enabled'] ) ) {
            return;
        }

        $post_types = $settings['post_types'] ?? self::DEFAULT_POST_TYPES;

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'wplfs_extra_phrases',
                'WP Live Fuzzy Search',
                [ $this, 'render_meta_box' ],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'wplfs_save_meta', 'wplfs_meta_nonce' );
        $value = get_post_meta( $post->ID, '_wplfs_extra_phrases', true );
        echo '<p><label for="wplfs_extra_phrases">Дополнительные фразы для поиска</label></p>';
        echo '<textarea id="wplfs_extra_phrases" name="wplfs_extra_phrases" rows="5" style="width:100%;">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">Одна фраза или ключевое слово на строку.</p>';
    }

    public function save_post_meta( $post_id ) {
        if ( ! isset( $_POST['wplfs_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wplfs_meta_nonce'] ) ), 'wplfs_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $value = isset( $_POST['wplfs_extra_phrases'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wplfs_extra_phrases'] ) ) : '';
        update_post_meta( $post_id, '_wplfs_extra_phrases', $value );
    }

    public function reset_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'wplfs_reset_settings' );

        delete_option( self::SETTINGS_OPTION );
        WPLFS_Index_Builder::rebuild_index();

        wp_safe_redirect( add_query_arg( [ 'page' => 'wp-live-fuzzy-search', 'wplfs_reset' => '1' ], admin_url( 'options-general.php' ) ) );
        exit;
    }
}

// Хук для принудительной пересборки
add_action( 'wp_ajax_wplfs_force_rebuild', function() {
    WPLFS_Index_Builder::rebuild_index();
    wp_die( 'ok' );
});

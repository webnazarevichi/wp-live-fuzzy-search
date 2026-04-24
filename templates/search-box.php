<?php $settings = get_option( 'wplfs_settings', [] ); ?>
<div
    id="wplfs-wrapper"
    class="wplfs-search-box"
    data-empty-text="<?php echo esc_attr( $settings['dropdown_empty_text'] ?? 'Введите хотя бы 2 символа для поиска' ); ?>"
    data-popular-title="<?php echo esc_attr( $settings['dropdown_popular_title'] ?? 'Часто ищут' ); ?>"
    data-all-results-text="<?php echo esc_attr( $settings['dropdown_all_results_text'] ?? 'Все результаты' ); ?>"
    data-no-results-text="<?php echo esc_attr( $settings['dropdown_no_results_text'] ?? 'Ничего не найдено' ); ?>"
    data-has-popular-queries="<?php echo ! empty( trim( (string) ( $settings['popular_queries'] ?? '' ) ) ) ? '1' : '0'; ?>"
    data-popular-queries="<?php echo esc_attr( $settings['popular_queries'] ?? '' ); ?>"
>
    <input type="text" id="wplfs-input" placeholder="<?php _e('Search here', 'default'); ?>..." autocomplete="off">
    <div class="wplfs-input-actions" aria-hidden="true">
        <button type="button" class="wplfs-clear-btn" data-wplfs-clear hidden aria-label="Clear search">
            <span aria-hidden="true">×</span>
        </button>
        <button type="button" class="wplfs-search-btn" data-wplfs-search aria-label="Search">
            <span aria-hidden="true">⌕</span>
        </button>
    </div>
    <div id="wplfs-results" class="wplfs-dropdown"></div>
</div>
<div id="wplfs-results-container"></div>

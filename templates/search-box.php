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
    <input type="text" id="wplfs-input" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" autocomplete="off">
    <div class="wplfs-input-actions" aria-hidden="true">
        <button type="button" class="wplfs-clear-btn" data-wplfs-clear hidden aria-label="Clear search">
            <span aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.8478 2.05444L2.15216 17.7501" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="round"/>
                    <path d="M2.15216 2.25L17.8478 17.9457" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="round"/>
                </svg>
            </span>
        </button>
        <button type="button" class="wplfs-search-btn" data-wplfs-search aria-label="Search">
            <span aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.91582 2.23635e-08C8.33451 0.00013474 6.77616 0.378425 5.37079 1.10331C3.96541 1.82819 2.75376 2.87865 1.83693 4.16705C0.920099 5.45544 0.324668 6.9444 0.100316 8.50972C-0.124036 10.075 0.0291973 11.6713 0.547231 13.1653C1.06526 14.6594 1.93308 16.0079 3.07827 17.0983C4.22345 18.1888 5.61281 18.9896 7.13042 19.4339C8.64803 19.8782 10.2499 19.9531 11.8023 19.6524C13.3548 19.3517 14.8128 18.6841 16.0548 17.7053L20.3155 21.966C20.5355 22.1785 20.8302 22.2961 21.1361 22.2935C21.442 22.2908 21.7346 22.1681 21.9509 21.9518C22.1673 21.7355 22.2899 21.4429 22.2926 21.137C22.2953 20.8311 22.1777 20.5364 21.9652 20.3163L17.7045 16.0557C18.8572 14.5934 19.5748 12.8361 19.7754 10.985C19.976 9.13389 19.6514 7.26369 18.8388 5.58844C18.0261 3.91319 16.7582 2.50058 15.1802 1.51227C13.6022 0.523957 11.7778 -0.000125001 9.91582 2.23635e-08ZM2.33249 9.91667C2.33249 7.90544 3.13145 5.97659 4.5536 4.55444C5.97575 3.13229 7.9046 2.33333 9.91582 2.33333C11.927 2.33333 13.8559 3.13229 15.278 4.55444C16.7002 5.97659 17.4992 7.90544 17.4992 9.91667C17.4992 11.9279 16.7002 13.8567 15.278 15.2789C13.8559 16.701 11.927 17.5 9.91582 17.5C7.9046 17.5 5.97575 16.701 4.5536 15.2789C3.13145 13.8567 2.33249 11.9279 2.33249 9.91667Z" fill="currentColor"/>
                </svg>
            </span>
        </button>
    </div>
    <div id="wplfs-results" class="wplfs-dropdown fadeIn"></div>
</div>
<div id="wplfs-results-container"></div>

<?php
get_header();
?>

<div class="wplfs-results-page">
    <h1>Поиск</h1>
    
    <div class="wplfs-search-box-container wplfs-results-page-mode">
        <?php echo do_shortcode( '[wp_live_fuzzy_search]' ); ?>
    </div>
    
    <div class="wplfs-results-container">
        <p class="wplfs-no-query">Введите запрос в поле выше для поиска по сайту</p>
    </div>
</div>

<?php
get_footer();
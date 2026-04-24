<?php
class WPLFS_Shortcode {

    public function __construct() {
        add_shortcode( 'wp_live_fuzzy_search', [ $this, 'render_shortcode' ] );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'placeholder' => 'Search here...',
        ], $atts );
        
        ob_start();
        include WPLFS_PATH . 'templates/search-box.php';
        return ob_get_clean();
    }
}
<?php
if (!defined('ABSPATH')) { exit; }
get_header();
if (have_posts()) {
    while (have_posts()) {
        the_post();
        if (trim((string) get_the_content()) !== '') {
            the_content();
        } elseif (is_file(get_template_directory() . '/templates/home.html')) {
            echo apply_filters('the_content', file_get_contents(get_template_directory() . '/templates/home.html'));
        } else {
            echo '<main class="section fubao-page"><h1>FUBAO JEWELRY</h1>' . fubao_noir_product_grid() . '</main>';
        }
    }
} else {
    echo '<main class="section fubao-page"><h1>FUBAO JEWELRY</h1>' . fubao_noir_product_grid() . '</main>';
}
get_footer();

<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) {
    the_post();
    $raw = (string) get_the_content();
    if (preg_match('/<main\b/i', $raw)) {
        the_content();
    } else {
        $commerce_page = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
        echo '<main class="section fubao-page ' . ($commerce_page ? 'fubao-commerce' : 'sources') . '">';
        if (!preg_match('/<h1\b/i', $raw)) { echo '<h1 class="entry-title">' . esc_html(get_the_title()) . '</h1>'; }
        the_content();
        wp_link_pages();
        echo '</main>';
    }
}
get_footer();

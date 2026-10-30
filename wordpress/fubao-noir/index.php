<?php
if (!defined('ABSPATH')) { exit; }
get_header();
echo '<main class="section sources fubao-page">';
if (have_posts()) {
    while (have_posts()) {
        the_post();
        echo '<article><h1 class="entry-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h1>';
        if (is_singular()) { the_content(); } else { the_excerpt(); }
        echo '</article>';
    }
    the_posts_pagination();
} else { echo '<h1 class="entry-title">Nothing here yet.</h1><p>Explore the collection and selection guides.</p>'; }
echo '</main>';
get_footer();

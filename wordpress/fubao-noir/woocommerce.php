<?php
if (!defined('ABSPATH')) { exit; }
if (function_exists('is_product') && is_product()) {
    require get_template_directory() . '/single-product.php';
    return;
}
get_header();
echo '<main class="section sources fubao-woocommerce">';
if (function_exists('woocommerce_content')) { woocommerce_content(); }
echo '</main>';
get_footer();

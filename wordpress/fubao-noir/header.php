<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101010">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main-content">Skip to content</a>
<div class="announcement"><span>FUBAO JEWELRY — THE NOIR EDITION</span><span>WEST COAST SOUL · INDIVIDUAL EXPRESSION</span></div>
<header class="header">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="wordmark" aria-label="FUBAO JEWELRY home">Fubao<span class="wordmark-sub">JEWELRY</span></a>
    <nav id="navigation" aria-label="Main navigation">
        <?php if (has_nav_menu('primary')) {
            wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1));
        } else { ?>
            <a href="<?php echo esc_url(home_url('/#collection')); ?>">Collection</a>
            <a href="<?php echo esc_url(home_url('/#story')); ?>">Our story</a>
            <a href="<?php echo esc_url(home_url('/#lookbook')); ?>">Lookbook</a>
        <?php } ?>
    </nav>
    <div class="nav-actions"><button id="bag-open" class="bag-button" aria-label="Open wishlist">WISHLIST <span id="bag-count">0</span></button><?php echo fubao_noir_cart_link(); ?><button id="menu-toggle" aria-expanded="false" aria-controls="navigation" aria-label="Toggle navigation">☰</button></div>
</header>
<div id="main-content" tabindex="-1"></div>

<?php
/** Theme presentation. Catalog import, facts and metadata live in the companion plugin. */
if (!defined('ABSPATH')) { exit; }

function fubao_noir_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('woocommerce');
    register_nav_menus(array('primary' => __('Main navigation', 'fubao-noir')));
}
add_action('after_setup_theme', 'fubao_noir_setup');

function fubao_noir_asset($path) {
    return trailingslashit(get_template_directory_uri()) . 'assets/site/' . ltrim($path, '/');
}

function fubao_noir_enqueue() {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('fubao-base', fubao_noir_asset('styles.css'), array(), $version);
    wp_enqueue_style('fubao-luxury', fubao_noir_asset('luxury.css'), array('fubao-base'), $version);
    if (is_file(get_template_directory() . '/assets/site/guide.css')) {
        wp_enqueue_style('fubao-guides', fubao_noir_asset('guide.css'), array('fubao-luxury'), $version);
    }
    wp_enqueue_style('fubao-theme', get_stylesheet_uri(), array('fubao-luxury'), $version);
    wp_enqueue_script('fubao-theme', get_template_directory_uri() . '/assets/theme.js', array(), $version, true);
    if (function_exists('WC')) { wp_enqueue_script('wc-cart-fragments'); }
    wp_add_inline_script('fubao-theme', 'window.catalog = ' . wp_json_encode(fubao_noir_catalog(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'fubao_noir_enqueue');

function fubao_noir_products() {
    static $products = null;
    if ($products === null) {
        $products = function_exists('wc_get_products') ? wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order', 'order' => 'ASC')) : array();
    }
    return $products;
}

function fubao_noir_is_research($product) {
    return in_array((string) $product->get_meta('_fubao_research'), array('1', 'yes', 'true'), true);
}

function fubao_noir_product_data($product) {
    $id = $product->get_id();
    $asin = strtolower((string) $product->get_meta('_fubao_asin'));
    if (!$asin) { $asin = (string) get_post_field('post_name', $id); }
    $terms = get_the_terms($id, 'product_cat');
    $category = (!is_wp_error($terms) && $terms) ? $terms[0]->name : __('Jewelry', 'fubao-noir');
    $reference = $product->get_meta('_fubao_reference_price');
    $image = wp_get_attachment_image_url($product->get_image_id(), 'full');
    if (!$image && preg_match('/^[a-z0-9]+$/', $asin) && is_file(get_template_directory() . '/assets/site/assets/catalog/' . $asin . '.png')) {
        $image = fubao_noir_asset('assets/catalog/' . $asin . '.png');
    }
    return array(
        'id' => $asin,
        'asin' => strtoupper($asin),
        'name' => $product->get_name(),
        'category' => $category,
        'finish' => (string) $product->get_meta('_fubao_finish'),
        'quantityLabel' => (string) $product->get_meta('_fubao_quantity'),
        'referencePrice' => $reference !== '' && is_numeric($reference) ? (float) $reference : null,
        'sellingPrice' => !$product->is_type('variable') && $product->get_price() !== '' ? (float) wc_get_price_to_display($product) : null,
        'isPurchasable' => $product->is_purchasable() && $product->is_in_stock(),
        'addToCartUrl' => $product->is_purchasable() && $product->is_in_stock() ? $product->add_to_cart_url() : '',
        'image' => $image ? $image : '',
        'imageAlt' => (string) get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true),
        'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
        'url' => get_permalink($id),
        'permalink' => get_permalink($id),
        'sourceUrl' => (string) $product->get_meta('_fubao_source_url'),
        'sourceDate' => (string) $product->get_meta('_fubao_source_date'),
        'research' => fubao_noir_is_research($product),
    );
}

/** The WooCommerce cart is separate from the browser's saved inspiration list. */
function fubao_noir_cart_link() {
    if (!function_exists('WC') || !function_exists('wc_get_cart_url')) { return ''; }
    $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    return '<a id="fubao-cart-link" class="cart-link" href="' . esc_url(wc_get_cart_url()) . '" aria-label="' . esc_attr(sprintf('Shopping cart, %d items', $count)) . '">CART <span>' . esc_html($count) . '</span></a>';
}
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $fragments['#fubao-cart-link'] = fubao_noir_cart_link();
    return $fragments;
});
add_filter('woocommerce_add_to_cart_redirect', function ($url, $product = null) {
    return $product && $product->get_meta('_fubao_sale_approved') === 'yes' ? wc_get_cart_url() : $url;
}, 10, 2);

function fubao_noir_catalog() {
    return array_map('fubao_noir_product_data', fubao_noir_products());
}

function fubao_noir_reference_price($data) {
    return $data['referencePrice'] === null ? __('Reference price unavailable', 'fubao-noir') : '$' . number_format_i18n($data['referencePrice'], 2) . ' USD reference';
}

function fubao_noir_product_grid() {
    ob_start();
    echo '<div id="products" class="product-grid">';
    foreach (fubao_noir_products() as $product) {
        $data = fubao_noir_product_data($product);
        ?>
        <article class="product-card" data-category="<?php echo esc_attr($data['category']); ?>">
            <div class="product-visual">
                <a class="product-open" href="<?php echo esc_url($data['url']); ?>" aria-label="<?php echo esc_attr('View ' . $data['name']); ?>">
                    <?php if ($data['image']) : ?>
                        <img class="catalog-image" src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['imageAlt'] ?: $data['name']); ?>" loading="lazy" width="1024" height="1024">
                    <?php else : ?>
                        <div class="catalog-image image-pending"><span>PRODUCT PHOTO<br>AWAITING VERIFICATION</span></div>
                    <?php endif; ?>
                </a>
                <button class="product-add" data-add="<?php echo esc_attr($data['id']); ?>" aria-label="<?php echo esc_attr('Save ' . $data['name'] . ' to wishlist'); ?>">♡</button>
            </div>
            <div class="product-meta"><div>
                <h3><a href="<?php echo esc_url($data['url']); ?>"><?php echo esc_html($data['name']); ?></a></h3>
                <p><?php echo esc_html($data['category'] . ($data['finish'] ? ' / ' . $data['finish'] : '')); ?></p>
                <span class="price"><?php echo $data['research'] ? esc_html__('Not currently for sale', 'fubao-noir') : wp_kses_post($product->get_price_html()); ?></span>
            </div></div>
            <div class="product-actions">
                <?php if ($data['isPurchasable']) : ?><a class="catalog-cart-link" href="<?php echo esc_url($data['addToCartUrl']); ?>" aria-label="<?php echo esc_attr('Add ' . $data['name'] . ' to cart'); ?>">Add to cart +</a><?php else : ?><span class="catalog-unavailable">Unavailable</span><?php endif; ?>
                <button class="quick-view" data-detail="<?php echo esc_attr($data['id']); ?>">Quick view ↗</button>
            </div>
        </article>
        <?php
    }
    if (!fubao_noir_products()) { echo '<p class="fubao-empty">The collection is being prepared. Please check back soon.</p>'; }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('fubao_product_grid', 'fubao_noir_product_grid');

/** Resolve imported relative assets and legacy internal URLs against this installation. */
function fubao_noir_resolve_url($url) {
    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    if ($url === '' || $url[0] === '#' || $url[0] === '?' || strpos($url, 'data:') === 0) { return $url; }
    $parsed = wp_parse_url($url);
    if ($parsed === false) { return $url; }
    if (!empty($parsed['host']) && !in_array(strtolower($parsed['host']), array('fubaojewelry.com', 'www.fubaojewelry.com', 'westside-shine-jw.astutejay15.chatgpt.site'), true)) { return $url; }
    if (!empty($parsed['scheme']) && empty($parsed['host'])) { return $url; }
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    $path = preg_replace('~^(?:\./|\.\./)+~', '', ltrim($path, '/'));
    $suffix = (isset($parsed['query']) ? '?' . $parsed['query'] : '') . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
    if (preg_match('~^assets/catalog/([a-z0-9]+)\.(?:png|webp)$~i', $path, $match) && function_exists('wc_get_product')) {
        $post = get_page_by_path(strtolower($match[1]), OBJECT, 'product');
        $product = $post ? wc_get_product($post->ID) : false;
        $image = $product ? wp_get_attachment_image_url($product->get_image_id(), 'full') : false;
        if ($image) { return $image . $suffix; }
    }
    if (strpos($path, 'assets/') === 0) {
        $webp_path = preg_replace('/\.png$/i', '.webp', $path);
        if (is_file(get_template_directory() . '/assets/site/' . $webp_path)) { $path = $webp_path; }
        return fubao_noir_asset($path) . $suffix;
    }
    if ($path === '' || $path === 'index.html') { return home_url('/') . $suffix; }
    if (preg_match('~^products/([a-z0-9-]+)\.html$~i', $path, $match)) {
        $product = get_page_by_path(strtolower($match[1]), OBJECT, 'product');
        if ($product) { return get_permalink($product) . $suffix; }
    } elseif (preg_match('~^([a-z0-9-]+)\.html$~i', $path, $match)) {
        $page = get_page_by_path($match[1], OBJECT, 'page');
        if ($page) { return get_permalink($page) . $suffix; }
    }
    return home_url('/' . $path) . $suffix;
}

function fubao_noir_content($content) {
    if (is_admin() || is_feed()) { return $content; }
    $content = preg_replace_callback('~\b(href|src)=("|\')([^"\']*)\2~i', function ($match) {
        return $match[1] . '=' . $match[2] . esc_url(fubao_noir_resolve_url($match[3])) . $match[2];
    }, $content);
    if (is_front_page()) {
        $count = count(fubao_noir_products());
        $content = preg_replace_callback('~<div id="products" class="product-grid">.*?</div>(?=\s*<p class="demo-note">)~s', function () { return fubao_noir_product_grid(); }, $content);
        $content = preg_replace('~THE CURATED COLLECTION — \d+ (?:REFERENCES|SELECTIONS|PIECES)~u', 'THE CURATED COLLECTION — ' . $count . ' PIECES', $content);
        $content = preg_replace('~(<span id="results-count"[^>]*>).*?(</span>)~s', '${1}' . $count . ' PIECES${2}', $content);
    }
    return $content;
}
add_filter('the_content', 'fubao_noir_content', 20);

function fubao_noir_page_url($slug) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    return $page ? get_permalink($page) : home_url('/' . $slug . '.html');
}

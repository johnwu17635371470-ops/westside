<?php
/**
 * Plugin Name: FUBAO Catalog
 * Description: Source-linked FUBAO research catalog import, editable evidence fields and GEO/SEO metadata.
 * Version: 1.1.1
 * Requires PHP: 7.4
 * Requires at least: 6.6
 */
if (!defined('ABSPATH')) { exit; }

function fubao_catalog_managed($post_id) {
    return get_post_meta($post_id, '_fubao_managed', true) === 'yes';
}
function fubao_catalog_research($product) {
    return $product && in_array((string) $product->get_meta('_fubao_research'), array('yes', '1', 'true'), true);
}
function fubao_catalog_sale_approved($product) {
    return $product && $product->get_meta('_fubao_sale_approved') === 'yes';
}
/** Read only PayPal's boolean connection facade, never its credential values. */
function fubao_catalog_paypal_connection_state() {
    if (!class_exists('WooCommerce\\PayPalCommerce\\PPCP')) { return null; }
    try {
        $container = \WooCommerce\PayPalCommerce\PPCP::container();
        if ($container->has('settings.connection-state')) {
            $state = $container->get('settings.connection-state');
            if (is_object($state) && method_exists($state, 'is_connected')) { return (bool) $state->is_connected(); }
        }
        if ($container->has('settings.flag.is-connected')) { return (bool) $container->get('settings.flag.is-connected'); }
    } catch (Throwable $ignored) { /* An unknown plugin API must not disable an existing connection. */ }
    return null;
}
/**
 * PayPal Payments 4.2.0 registers its SDK v6 Blocks integration even before
 * onboarding. Use Woo's registration lifecycle, before integrations initialize,
 * so an unconnected frontend never receives its client-token/SDK dependency.
 * Keep admin onboarding intact and automatically restore normal registration
 * once PayPal reports a connected account. No settings or payment grants change.
 */
add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
    if (is_admin() || fubao_catalog_paypal_connection_state() !== false) { return; }
    foreach ($registry->get_all_registered() as $integration) {
        if (strpos(get_class($integration), 'WooCommerce\\PayPalCommerce\\') === 0) { $registry->unregister($integration); }
    }
}, 100);
function fubao_catalog_url($post_id) {
    $path = get_post_meta($post_id, '_fubao_path', true);
    return $path ? home_url('/' . ltrim($path, '/')) : '';
}
/** Only the explicitly bundled research pages are acquisition/indexing targets. */
function fubao_catalog_index_targets() {
    static $targets = null;
    if ($targets === null) {
        $targets = array();
        $data = json_decode((string) @file_get_contents(__DIR__ . '/data/import.json'), true);
        foreach (isset($data['pages']) ? $data['pages'] : array() as $page) {
            if (!isset($page['path'], $page['slug']) || !preg_match('~^(?:/|/[a-z0-9-]+\.html)$~', $page['path'])) { continue; }
            $targets[$page['path']] = array('type' => 'page', 'key' => 'page:' . $page['slug']);
        }
        foreach (isset($data['products']) ? $data['products'] : array() as $product) {
            $asin = isset($product['asin']) ? strtolower($product['asin']) : '';
            if (!preg_match('/^[a-z0-9]{10}$/', $asin)) { continue; }
            $targets['/products/' . $asin . '.html'] = array('type' => 'product', 'key' => 'product:' . $asin);
        }
    }
    return $targets;
}
function fubao_catalog_indexable_post($post_id) {
    if (!fubao_catalog_managed($post_id) || get_post_status($post_id) !== 'publish') { return false; }
    $targets = fubao_catalog_index_targets();
    $path = get_post_meta($post_id, '_fubao_path', true);
    return isset($targets[$path]) && get_post_type($post_id) === $targets[$path]['type'] && get_post_meta($post_id, '_fubao_key', true) === $targets[$path]['key'];
}
function fubao_catalog_indexable_view() {
    return is_singular(array('page', 'product')) && !is_preview() && !is_feed() && !is_embed() && !is_paged() && fubao_catalog_indexable_post(get_queried_object_id());
}
// The custom sitemap is the sole acquisition list; core maps would include users,
// category archives and WooCommerce utility pages that are not editorial content.
add_filter('wp_sitemaps_enabled', '__return_false');
add_filter('wp_robots', function ($robots) {
    if (!get_option('blog_public') || !fubao_catalog_indexable_view()) {
        unset($robots['index']);
        $robots['noindex'] = true;
        if (get_option('blog_public')) { unset($robots['nofollow']); $robots['follow'] = true; }
    }
    return $robots;
}, 100);
add_action('template_redirect', function () {
    $path = fubao_catalog_request_path();
    $home_path = rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    $relative = $home_path !== '' && strpos($path, $home_path . '/') === 0 ? substr($path, strlen($home_path)) : $path;
    if (get_option('blog_public') && preg_match('~^/wp-sitemap(?:-[a-z0-9-]+)?\.xml$~', $relative)) {
        wp_safe_redirect(home_url('/sitemap.xml'), 301, 'FUBAO Catalog');
        exit;
    }
    if (!get_query_var('fubao_sitemap') && (!get_option('blog_public') || !fubao_catalog_indexable_view()) && !headers_sent()) {
        header('X-Robots-Tag: noindex' . (get_option('blog_public') ? ', follow' : ''));
    }
}, 0);
function fubao_catalog_page_link($url, $post_id) {
    return fubao_catalog_managed($post_id) ? (fubao_catalog_url($post_id) ?: $url) : $url;
}
add_filter('page_link', 'fubao_catalog_page_link', 10, 2);
function fubao_catalog_product_link($url, $post) {
    return fubao_catalog_managed($post->ID) ? (fubao_catalog_url($post->ID) ?: $url) : $url;
}
add_filter('post_type_link', 'fubao_catalog_product_link', 10, 2);

function fubao_catalog_rewrites() {
    add_rewrite_rule('^sitemap\.xml$', 'index.php?fubao_sitemap=1', 'top');
    add_rewrite_rule('^(sources|guides|ring-guide|necklace-guide|bracelet-guide|earring-guide)\.html/?$', 'index.php?pagename=$matches[1]', 'top');
    add_rewrite_rule('^products/([a-z0-9-]+)\.html/?$', 'index.php?post_type=product&name=$matches[1]', 'top');
}
add_action('init', 'fubao_catalog_rewrites');
add_filter('query_vars', function ($vars) { $vars[] = 'fubao_sitemap'; return $vars; });
register_activation_hook(__FILE__, function () { fubao_catalog_rewrites(); flush_rewrite_rules(false); });
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(false); });

function fubao_catalog_request_path() {
    return wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/', PHP_URL_PATH);
}
add_filter('redirect_canonical', function ($redirect) {
    $id = get_queried_object_id();
    if (is_singular() && fubao_catalog_managed($id) && fubao_catalog_request_path() === wp_parse_url(fubao_catalog_url($id), PHP_URL_PATH)) { return false; }
    return $redirect;
});
add_action('template_redirect', function () {
    if (get_query_var('fubao_sitemap')) {
        if (!(bool) get_option('blog_public')) { status_header(404); nocache_headers(); return; }
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $posts = get_posts(array('post_type' => array('page', 'product'), 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_fubao_managed', 'meta_value' => 'yes', 'orderby' => 'ID', 'order' => 'ASC'));
        foreach ($posts as $post) {
            if (!fubao_catalog_indexable_post($post->ID)) { continue; }
            $url = fubao_catalog_url($post->ID);
            if (!$url) { continue; }
            echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc><lastmod>' . esc_html(get_post_modified_time('c', true, $post)) . '</lastmod></url>';
        }
        echo '</urlset>';
        exit;
    }
    $id = get_queried_object_id();
    if (is_singular() && fubao_catalog_managed($id) && !is_preview() && !is_feed()) {
        $canonical = fubao_catalog_url($id);
        if ($canonical && fubao_catalog_request_path() !== wp_parse_url($canonical, PHP_URL_PATH)) {
            wp_safe_redirect($canonical, 301, 'FUBAO Catalog');
            exit;
        }
    }
}, 1);
add_filter('robots_txt', function ($output, $public) {
    if ($public && get_option('blog_public')) { $output .= "\nSitemap: " . home_url('/sitemap.xml') . "\n"; }
    return $output;
}, 10, 2);

function fubao_catalog_schema_urls($value) {
    if (is_array($value)) { return array_map('fubao_catalog_schema_urls', $value); }
    if (!is_string($value)) { return $value; }
    $old = 'https://fubaojewelry.com';
    if (strpos($value, $old . '/assets/') === 0) {
        $asset_path = substr($value, strlen($old) + 1);
        if (preg_match('~^assets/catalog/([a-z0-9]+)\.(?:png|webp)$~i', $asset_path, $match) && function_exists('wc_get_product')) {
            $post = get_page_by_path(strtolower($match[1]), OBJECT, 'product');
            $product = $post ? wc_get_product($post->ID) : false;
            $image = $product ? wp_get_attachment_image_url($product->get_image_id(), 'full') : false;
            if ($image) { return $image; }
        }
        $webp_path = preg_replace('/\.png$/i', '.webp', $asset_path);
        if (is_file(get_template_directory() . '/assets/site/' . $webp_path)) { $asset_path = $webp_path; }
        return trailingslashit(get_template_directory_uri()) . 'assets/site/' . $asset_path;
    }
    return strpos($value, $old) === 0 ? untrailingslashit(home_url()) . substr($value, strlen($old)) : $value;
}
add_action('wp', function () { if (is_singular() && fubao_catalog_managed(get_queried_object_id())) { remove_action('wp_head', 'rel_canonical'); } });
add_filter('pre_get_document_title', function ($title) {
    $id = get_queried_object_id();
    if (fubao_catalog_managed($id) && get_post_type($id) === 'product' && function_exists('wc_get_product')) {
        $product = wc_get_product($id);
        if (fubao_catalog_research($product) || fubao_catalog_sale_approved($product)) { return $product->get_name() . ' | FUBAO JEWELRY'; }
    }
    $custom = fubao_catalog_managed($id) ? get_post_meta($id, '_fubao_seo_title', true) : '';
    return $custom ?: $title;
});
add_action('wp_head', function () {
    $id = get_queried_object_id();
    if (!is_singular() || !fubao_catalog_managed($id)) { return; }
    $description = get_post_meta($id, '_fubao_description', true);
    if (get_post_type($id) === 'product' && function_exists('wc_get_product')) {
        $current_product = wc_get_product($id);
        if (fubao_catalog_research($current_product) || fubao_catalog_sale_approved($current_product)) { $description = wp_strip_all_tags($current_product->get_short_description() ?: $current_product->get_description()); }
    }
    if ($description) { echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n"; }
    echo '<link rel="canonical" href="' . esc_url(fubao_catalog_url($id)) . '">' . "\n";
    if (get_post_type($id) === 'product' && function_exists('wc_get_product')) {
        $product = wc_get_product($id);
        if (!fubao_catalog_research($product) && !fubao_catalog_sale_approved($product)) { return; }
        $schema = array('@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->get_name(), 'url' => fubao_catalog_url($id), 'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()), 'identifier' => array('@type' => 'PropertyValue', 'propertyID' => 'ASIN', 'value' => $product->get_meta('_fubao_asin')));
        $terms = get_the_terms($id, 'product_cat');
        if ($terms && !is_wp_error($terms)) { $schema['category'] = $terms[0]->name; }
        $image = wp_get_attachment_image_url($product->get_image_id(), 'full');
        if ($image) { $schema['image'] = $image; }
        if ($product->get_meta('_fubao_source_url')) { $schema['subjectOf'] = array('@type' => 'WebPage', 'url' => $product->get_meta('_fubao_source_url')); }
        if (fubao_catalog_sale_approved($product) && !fubao_catalog_research($product) && $product->get_price() !== '' && is_numeric($product->get_price())) {
            $schema['offers'] = array('@type' => 'Offer', 'url' => fubao_catalog_url($id), 'priceCurrency' => get_woocommerce_currency(), 'price' => wc_format_decimal($product->get_price(), wc_get_price_decimals()), 'seller' => array('@type' => 'Organization', 'name' => 'FUBAO JEWELRY', 'url' => home_url('/')));
            // Inventory quantities and physical availability have not been verified;
            // no availability claim is inferred from WooCommerce's default state.
        }
    } else {
        $schema = get_post_meta($id, '_fubao_schema', true);
        if (is_string($schema)) { $schema = json_decode($schema, true); }
        $schema = fubao_catalog_schema_urls($schema);
        if (is_front_page() && function_exists('wc_get_products')) {
            $items = array();
            foreach (wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order', 'order' => 'ASC')) as $entry) {
                $items[] = array('@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $entry->get_name(), 'url' => get_permalink($entry->get_id()));
            }
            $schema = array('@context' => 'https://schema.org', '@graph' => array(
                array('@type' => 'WebSite', '@id' => home_url('/#website'), 'url' => home_url('/'), 'name' => 'FUBAO JEWELRY', 'publisher' => array('@id' => home_url('/#organization'))),
                array('@type' => 'Organization', '@id' => home_url('/#organization'), 'name' => 'FUBAO JEWELRY', 'url' => home_url('/')),
                array('@type' => 'CollectionPage', 'name' => 'FUBAO JEWELRY source-linked collection', 'url' => home_url('/'), 'description' => $description, 'mainEntity' => array('@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items))
            ));
        }
    }
    if (is_array($schema) && $schema) { echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n"; }
}, 5);
add_filter('woocommerce_structured_data_product', function ($schema, $product) { return fubao_catalog_research($product) || fubao_catalog_sale_approved($product) ? array() : $schema; }, 20, 2);
add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    if (fubao_catalog_research($product) || ($product && fubao_catalog_managed($product->get_id()) && !fubao_catalog_sale_approved($product))) { return false; }
    return $purchasable;
}, 20, 2);
add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $product) {
    $parent = $product && $product->get_parent_id() ? wc_get_product($product->get_parent_id()) : false;
    if (fubao_catalog_research($product) || fubao_catalog_research($parent) || ($parent && fubao_catalog_managed($parent->get_id()) && !fubao_catalog_sale_approved($parent))) { return false; }
    return $purchasable;
}, 20, 2);
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (!fubao_catalog_research($product)) { return $html; }
    $price = $product->get_meta('_fubao_reference_price');
    return $price !== '' && is_numeric($price) ? esc_html('$' . number_format_i18n((float) $price, 2) . ' USD reference') : esc_html__('Reference price unavailable', 'fubao-catalog');
}, 20, 2);

function fubao_catalog_import_html($html) {
    $allowed = wp_kses_allowed_html('post');
    $attrs = array('class' => true, 'id' => true, 'role' => true, 'aria-label' => true, 'aria-labelledby' => true, 'aria-live' => true, 'tabindex' => true);
    foreach (array('main', 'section', 'article', 'aside', 'nav', 'header', 'footer', 'figure', 'figcaption', 'details', 'summary') as $tag) { $allowed[$tag] = $attrs; }
    $allowed['button'] = array_merge($attrs, array('type' => true, 'data-filter' => true, 'data-add' => true, 'data-detail' => true, 'aria-pressed' => true, 'disabled' => true));
    foreach (array('div', 'span', 'article') as $tag) { $allowed[$tag] = array_merge(isset($allowed[$tag]) ? $allowed[$tag] : array(), $attrs, array('data-category' => true, 'hidden' => true)); }
    $allowed['img'] = array_merge(isset($allowed['img']) ? $allowed['img'] : array(), array('loading' => true, 'fetchpriority' => true, 'width' => true, 'height' => true));
    return wp_kses($html, $allowed);
}
function fubao_catalog_find_key($key) {
    $posts = get_posts(array('post_type' => array('page', 'product'), 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => '_fubao_key', 'meta_value' => $key));
    return $posts ? $posts[0]->ID : 0;
}
/** Accept only byte-matched default content from the tested WooCommerce release. */
function fubao_catalog_is_woo_default($post) {
    static $fixture = null;
    if ($fixture === null) {
        $fixture = json_decode((string) @file_get_contents(__DIR__ . '/data/woo-defaults.json'), true);
    }
    if (!is_array($fixture) || !defined('WC_VERSION') || WC_VERSION !== $fixture['woocommerce'] || get_locale() !== $fixture['locale']) { return false; }
    if ($post->post_type === 'page') {
        foreach ($fixture['pages'] as $default) {
            if ($post->post_name === $default['slug'] && $post->post_status === $default['status'] && (int) get_option($default['option']) === (int) $post->ID && hash_equals($default['sha256'], hash('sha256', trim($post->post_content)))) { return true; }
        }
    }
    if ($post->post_type === 'attachment' && isset($fixture['placeholder'])) {
        $default = $fixture['placeholder'];
        if ($post->post_status !== $default['status'] || (int) get_option($default['option']) !== (int) $post->ID) { return false; }
        $file = get_attached_file($post->ID);
        return $file && wp_basename($file) === $default['basename'] && is_file($file) && hash_equals($default['sha256'], hash_file('sha256', $file));
    }
    return false;
}
function fubao_catalog_import_guard() {
    if (!current_user_can('manage_options')) { return new WP_Error('fubao_forbidden', 'Administrator access is required.'); }
    foreach (array(home_url(), site_url(), isset($_SERVER['HTTP_HOST']) ? 'https://' . wp_unslash($_SERVER['HTTP_HOST']) : '') as $url) {
        $host = rtrim(strtolower((string) wp_parse_url($url, PHP_URL_HOST)), '.');
        if ($host === 'bettersport.fashion' || substr($host, -strlen('.bettersport.fashion')) === '.bettersport.fashion') { return new WP_Error('fubao_wrong_site', 'Import is blocked on BetterSport. Use a separate, empty FUBAO WordPress installation.'); }
    }
    if (!class_exists('WooCommerce') || !class_exists('WC_Product_Simple')) { return new WP_Error('fubao_woocommerce', 'Install and activate WooCommerce in the separate FUBAO test installation first.'); }
    if ((bool) get_option('blog_public')) { return new WP_Error('fubao_public_site', 'Run this import on a private test installation: turn off search-engine visibility first. The importer does not change that setting.'); }
    $existing = get_posts(array('post_type' => array('post', 'page', 'product', 'attachment'), 'post_status' => array('publish', 'private', 'draft', 'pending', 'future', 'inherit'), 'numberposts' => -1));
    foreach ($existing as $post) {
        if (fubao_catalog_managed($post->ID) || get_post_meta($post->ID, '_fubao_image_key', true)) { continue; }
        if ($post->post_type === 'attachment' && $post->post_parent && fubao_catalog_managed($post->post_parent)) { continue; }
        if (in_array($post->post_type, array('post', 'page'), true) && in_array($post->post_name, array('hello-world', 'sample-page', 'privacy-policy'), true)) { continue; }
        if (fubao_catalog_is_woo_default($post)) { continue; }
        $default_woo = array('shop' => '', 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]', 'myaccount' => '[woocommerce_my_account]');
        foreach ($default_woo as $option => $default_content) {
            if ($post->post_type === 'page' && (int) get_option('woocommerce_' . $option . '_page_id') === (int) $post->ID && trim($post->post_content) === $default_content) { continue 2; }
        }
        return new WP_Error('fubao_existing_content', 'Existing non-default content was detected. Import is only allowed on a new, empty FUBAO installation. No existing site content has been replaced.');
    }
    return true;
}
function fubao_catalog_import_image($asin, $post_id, $name) {
    $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_fubao_image_key', 'meta_value' => $asin));
    if ($existing) { return $existing[0]->ID; }
    $extension = is_file(__DIR__ . '/images/' . strtolower($asin) . '.webp') ? 'webp' : 'png';
    $source = __DIR__ . '/images/' . strtolower($asin) . '.' . $extension;
    if (!is_file($source)) { return new WP_Error('fubao_image_missing', 'Missing bundled source image: ' . $asin); }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $temporary = wp_tempnam($asin . '.' . $extension);
    if (!$temporary || !copy($source, $temporary)) { return new WP_Error('fubao_image_copy', 'The bundled image could not be copied to a temporary upload.'); }
    $id = media_handle_sideload(array('name' => strtolower($asin) . '.' . $extension, 'tmp_name' => $temporary), $post_id, $name);
    if (is_wp_error($id)) { if (is_file($temporary)) { wp_delete_file($temporary); } return $id; }
    update_post_meta($id, '_fubao_image_key', $asin);
    update_post_meta($id, '_fubao_source_url', get_post_meta($post_id, '_fubao_source_url', true));
    update_post_meta($id, '_fubao_source_date', get_post_meta($post_id, '_fubao_source_date', true));
    update_post_meta($id, '_wp_attachment_image_alt', $name . ' — AI-styled image based on linked product photo');
    return $id;
}

/** Explicit callable for an authenticated administrator; activation never imports content. */
function fubao_import_catalog() {
    $guard = fubao_catalog_import_guard();
    if (is_wp_error($guard)) { return $guard; }
    $data = json_decode((string) @file_get_contents(__DIR__ . '/data/import.json'), true);
    if (!is_array($data) || !isset($data['pages'], $data['products']) || count($data['pages']) !== 7 || count($data['products']) !== 18) { return new WP_Error('fubao_import_data', 'The import bundle must contain seven pages and eighteen reference products.'); }
    $page_slugs = array(); $product_asins = array();
    foreach ($data['pages'] as $page) {
        if (!isset($page['slug'], $page['title'], $page['description'], $page['seoTitle'], $page['content'], $page['path'], $page['schema'])) { return new WP_Error('fubao_import_data', 'A page is missing a required import field.'); }
        $page_slugs[] = sanitize_title($page['slug']);
    }
    foreach ($data['products'] as $item) {
        if (!isset($item['asin'], $item['name'], $item['description'], $item['sourceUrl'], $item['sourceDate'], $item['finish'], $item['quantityLabel'], $item['category'])) { return new WP_Error('fubao_import_data', 'A product is missing a required import field.'); }
        $product_asins[] = strtoupper($item['asin']);
    }
    if (count(array_unique($page_slugs)) !== 7 || count(array_unique($product_asins)) !== 18 || !in_array('home', $page_slugs, true)) { return new WP_Error('fubao_import_data', 'Import identifiers must be unique and include the home page.'); }
    if (!add_option('fubao_catalog_import_lock', time(), '', false)) {
        $lock = (int) get_option('fubao_catalog_import_lock');
        if (time() - $lock < 300) { return new WP_Error('fubao_import_locked', 'Another FUBAO import is running. Wait for it to finish before retrying.'); }
        delete_option('fubao_catalog_import_lock');
        if (!add_option('fubao_catalog_import_lock', time(), '', false)) { return new WP_Error('fubao_import_locked', 'The import lock could not be acquired.'); }
    }
    $result = array('pages_created' => 0, 'products_created' => 0, 'skipped' => 0);
    try {
        foreach ($data['pages'] as $page) {
            $slug = sanitize_title($page['slug']);
            $key = 'page:' . $slug;
            if (fubao_catalog_find_key($key)) { $result['skipped']++; continue; }
            $id = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => sanitize_text_field($page['title']), 'post_content' => fubao_catalog_import_html($page['content']), 'meta_input' => array('_fubao_key' => $key, '_fubao_managed' => 'yes', '_fubao_path' => '/' . ltrim($page['path'], '/'), '_fubao_description' => sanitize_text_field($page['description']), '_fubao_seo_title' => sanitize_text_field($page['seoTitle']), '_fubao_schema' => $page['schema']))), true);
            if (is_wp_error($id)) { return $id; }
            $result['pages_created']++;
        }
        foreach ($data['products'] as $index => $item) {
            $asin = strtoupper(sanitize_text_field($item['asin']));
            if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) { return new WP_Error('fubao_asin', 'Invalid bundled product identifier.'); }
            $key = 'product:' . strtolower($asin);
            $id = fubao_catalog_find_key($key);
            if ($id && get_post_meta($id, '_fubao_import_complete', true) === 'yes') { $result['skipped']++; continue; }
            if (!$id) {
                $id = wp_insert_post(wp_slash(array('post_type' => 'product', 'post_status' => 'draft', 'post_name' => strtolower($asin), 'post_title' => sanitize_text_field($item['name']), 'meta_input' => array('_fubao_key' => $key, '_fubao_managed' => 'yes', '_fubao_research' => 'yes'))), true);
                if (is_wp_error($id)) { return $id; }
                $product = new WC_Product_Simple($id);
                $product->set_description(fubao_catalog_import_html(isset($item['content']) ? $item['content'] : ''));
                $product->set_short_description(sanitize_text_field($item['description']));
                $product->set_menu_order($index);
                $product->set_catalog_visibility('visible');
                $product->set_regular_price(''); $product->set_sale_price(''); $product->set_price('');
                $product->set_manage_stock(false);
                $product->save();
                $meta = array('_fubao_path' => '/products/' . strtolower($asin) . '.html', '_fubao_reference_price' => isset($item['referencePrice']) && is_numeric($item['referencePrice']) ? (string) $item['referencePrice'] : '', '_fubao_source_url' => esc_url_raw($item['sourceUrl']), '_fubao_source_date' => sanitize_text_field($item['sourceDate']), '_fubao_finish' => sanitize_text_field($item['finish']), '_fubao_quantity' => sanitize_text_field($item['quantityLabel']), '_fubao_asin' => $asin, '_fubao_description' => sanitize_text_field($item['description']), '_fubao_seo_title' => sanitize_text_field($item['name']) . ' | FUBAO JEWELRY', '_fubao_guide' => isset($item['guide']) ? sanitize_text_field($item['guide']) : '');
                foreach ($meta as $name => $value) { update_post_meta($id, $name, wp_slash($value)); }
                $category = sanitize_text_field($item['category']);
                $term = term_exists($category, 'product_cat');
                if (!$term) { $term = wp_insert_term($category, 'product_cat'); }
                if (is_wp_error($term)) { return $term; }
                $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                $assigned = wp_set_object_terms($id, array($term_id), 'product_cat');
                if (is_wp_error($assigned)) { return $assigned; }
            }
            $product = wc_get_product($id);
            if (!$product->get_image_id()) {
                $image = fubao_catalog_import_image($asin, $id, sanitize_text_field($item['name']));
                if (is_wp_error($image)) { return $image; }
                $product->set_image_id($image);
            }
            $product->set_status('publish');
            $product->save();
            update_post_meta($id, '_fubao_import_complete', 'yes');
            $result['products_created']++;
        }
        if (!get_option('fubao_catalog_initial_setup')) {
            $home = fubao_catalog_find_key('page:home');
            if (!$home) { return new WP_Error('fubao_home_missing', 'The imported home page was not found.'); }
            update_option('show_on_front', 'page'); update_option('page_on_front', $home);
            update_option('blogname', 'FUBAO JEWELRY');
            update_option('permalink_structure', '/%postname%/');
            update_option('fubao_catalog_initial_setup', 'yes');
        }
        fubao_catalog_rewrites(); flush_rewrite_rules(false);
        return $result;
    } finally { delete_option('fubao_catalog_import_lock'); }
}

add_action('admin_menu', function () { add_management_page('FUBAO Setup', 'FUBAO Setup', 'manage_options', 'fubao-setup', 'fubao_catalog_setup_page'); });
function fubao_catalog_setup_page() {
    if (!current_user_can('manage_options')) { return; }
    echo '<div class="wrap"><h1>FUBAO Setup</h1><p>Import into a separate, new FUBAO test installation only. This import never runs on activation and is blocked on BetterSport. Existing imported content is skipped so later dashboard edits are preserved.</p>';
    if (isset($_POST['fubao_import'])) {
        check_admin_referer('fubao_import_catalog');
        $result = fubao_import_catalog();
        if (is_wp_error($result)) { echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>'; }
        else { echo '<div class="notice notice-success"><p>' . esc_html(sprintf('Import complete: %d pages, %d products processed, %d existing entries preserved.', $result['pages_created'], $result['products_created'], $result['skipped'])) . '</p></div>'; }
    }
    echo '<form method="post">'; wp_nonce_field('fubao_import_catalog'); submit_button('Import FUBAO reference collection', 'primary', 'fubao_import'); echo '</form></div>';
}

add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group"><p class="form-field"><strong>FUBAO source reference</strong></p>';
    foreach (array('_fubao_reference_price' => 'Historical reference price (USD)', '_fubao_source_url' => 'Original source URL', '_fubao_source_date' => 'Source snapshot date', '_fubao_asin' => 'Source ASIN', '_fubao_finish' => 'Appearance / finish', '_fubao_quantity' => 'Included pieces') as $id => $label) {
        woocommerce_wp_text_input(array('id' => $id, 'label' => $label, 'type' => $id === '_fubao_source_url' ? 'url' : 'text', 'description' => $id === '_fubao_reference_price' ? 'A dated research reference, never a WooCommerce selling price.' : ''));
    }
    echo '</div>';
});
add_action('woocommerce_admin_process_product_object', function ($product) {
    if (!current_user_can('edit_post', $product->get_id())) { return; }
    foreach (array('_fubao_reference_price', '_fubao_source_url', '_fubao_source_date', '_fubao_asin', '_fubao_finish', '_fubao_quantity') as $key) {
        if (!isset($_POST[$key])) { continue; }
        $value = wp_unslash($_POST[$key]);
        if ($key === '_fubao_reference_price') { $value = $value === '' ? '' : wc_format_decimal($value); }
        elseif ($key === '_fubao_source_url') { $value = esc_url_raw($value); }
        else { $value = sanitize_text_field($value); }
        $product->update_meta_data($key, $value);
    }
});
add_action('add_meta_boxes_page', function ($post) {
    if (fubao_catalog_managed($post->ID)) { add_meta_box('fubao-page-metadata', 'FUBAO GEO / SEO description', 'fubao_catalog_page_box', 'page', 'normal'); }
});
function fubao_catalog_page_box($post) {
    wp_nonce_field('fubao_page_metadata', 'fubao_page_nonce');
    echo '<p><label for="fubao-seo-title">Search title</label><input class="widefat" id="fubao-seo-title" name="fubao_seo_title" value="' . esc_attr(get_post_meta($post->ID, '_fubao_seo_title', true)) . '"></p><p><label for="fubao-description">Page description</label><textarea class="widefat" rows="3" id="fubao-description" name="fubao_description">' . esc_textarea(get_post_meta($post->ID, '_fubao_description', true)) . '</textarea></p>';
}
add_action('save_post_page', function ($post_id) {
    if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id) || !isset($_POST['fubao_page_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fubao_page_nonce'])), 'fubao_page_metadata')) { return; }
    foreach (array('fubao_seo_title' => '_fubao_seo_title', 'fubao_description' => '_fubao_description') as $input => $meta) { if (isset($_POST[$input])) { update_post_meta($post_id, $meta, sanitize_text_field(wp_unslash($_POST[$input]))); } }
});

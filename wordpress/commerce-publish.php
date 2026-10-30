<?php
/**
 * Explicit, one-time conversion of the approved FUBAO reference-price snapshot.
 * Run shipping setup first, then:
 * FUBAO_APPROVE_REFERENCE_PRICES=1 wp --path=/www/wwwroot/fubaojewelry.com \
 *   --user=<FUBAO-administrator> eval-file /private/path/commerce-publish.php
 * Existing conversion markers preserve subsequent dashboard price/content edits.
 * No payment-account, bank, customer, order or numeric inventory data is created.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    if (PHP_SAPI !== 'cli') { http_response_code(403); }
    echo "This helper requires an authenticated WordPress CLI context.\n";
    exit(1);
}
$fubao_publish_lock = false;
try {
    $fubao_publish_require = static function ($condition, $message) {
        if (!$condition) { throw new RuntimeException($message); }
    };
    $fubao_publish_require(realpath(ABSPATH) === '/www/wwwroot/fubaojewelry.com', 'Refusing an installation outside the independent FUBAO directory.');
    $fubao_publish_require(defined('DB_NAME') && DB_NAME === 'sql_fubaojewelry', 'Refusing an unexpected database.');
    $fubao_publish_require(untrailingslashit(home_url()) === 'https://fubaojewelry.com' && untrailingslashit(site_url()) === 'https://fubaojewelry.com', 'FUBAO home/siteurl must use the confirmed HTTPS origin.');
    $fubao_publish_require(current_user_can('manage_options') && current_user_can('manage_woocommerce'), 'Run with the FUBAO administrator using --user.');
    $fubao_publish_require(getenv('FUBAO_APPROVE_REFERENCE_PRICES') === '1', 'No changes made: FUBAO_APPROVE_REFERENCE_PRICES=1 is required to approve the seventeen reference amounts as selling prices.');
    $fubao_publish_require(function_exists('fubao_catalog_sale_approved') && function_exists('fubao_catalog_find_key') && function_exists('wc_get_product'), 'Deploy the sales-capable FUBAO catalog plugin before publication.');
    $fubao_publish_require(get_woocommerce_currency() === 'USD', 'Configure the approved USD storefront currency first.');
    $fubao_publish_data = json_decode((string) @file_get_contents(WP_PLUGIN_DIR . '/fubao-catalog/data/import.json'), true);
    $fubao_publish_require(is_array($fubao_publish_data) && isset($fubao_publish_data['products'], $fubao_publish_data['pages']) && count($fubao_publish_data['products']) === 18 && count($fubao_publish_data['pages']) === 7, 'The approved eighteen-product/seven-page bundle is missing.');
    $fubao_publish_products = array(); $fubao_priced = 0; $fubao_unpriced = 0;
    $fubao_source_keys = array('_fubao_reference_price', '_fubao_source_url', '_fubao_source_date', '_fubao_asin', '_fubao_finish', '_fubao_quantity');
    foreach ($fubao_publish_data['products'] as $fubao_item) {
        $fubao_asin = strtolower((string) $fubao_item['asin']);
        $fubao_publish_require((bool) preg_match('/^[a-z0-9]{10}$/', $fubao_asin), 'An invalid bundled identifier was found.');
        $fubao_id = fubao_catalog_find_key('product:' . $fubao_asin);
        $fubao_product = $fubao_id ? wc_get_product($fubao_id) : false;
        $fubao_publish_require($fubao_product && fubao_catalog_managed($fubao_id) && $fubao_product->get_status() === 'publish' && $fubao_product->get_type() === 'simple', 'A bundled FUBAO simple product is missing or has changed type/status.');
        $fubao_publish_require(!isset($fubao_publish_products[$fubao_id]), 'The bundle contains duplicate product identities.');
        $fubao_reference = $fubao_product->get_meta('_fubao_reference_price');
        $fubao_has_price = $fubao_reference !== '' && is_numeric($fubao_reference) && (float) $fubao_reference > 0;
        if ($fubao_reference !== '' && !$fubao_has_price) { throw new RuntimeException('A reference amount is invalid; inspect it rather than inventing a selling price.'); }
        $fubao_has_price ? $fubao_priced++ : $fubao_unpriced++;
        $fubao_history = array();
        foreach ($fubao_source_keys as $fubao_key) { $fubao_history[$fubao_key] = $fubao_product->get_meta($fubao_key); }
        $fubao_publish_products[$fubao_id] = array('product' => $fubao_product, 'reference' => $fubao_reference, 'priced' => $fubao_has_price, 'history' => $fubao_history);
    }
    $fubao_publish_require(count($fubao_publish_products) === 18 && $fubao_priced === 17 && $fubao_unpriced === 1, 'Expected exactly seventeen valid reference amounts and one missing price.');
    $fubao_publish_require(add_option('fubao_commerce_publish_lock', time(), '', false), 'Another publication is running, or a previous interruption needs review.');
    $fubao_publish_lock = true;
    $fubao_version = 'reference-price-v1';
    $fubao_converted = 0; $fubao_skipped = 0; $fubao_unavailable = 0;
    foreach ($fubao_publish_products as $fubao_id => $fubao_entry) {
        $fubao_product = $fubao_entry['product'];
        if ($fubao_product->get_meta('_fubao_sale_conversion_version') === $fubao_version) { $fubao_skipped++; continue; }
        $fubao_publish_require(!fubao_catalog_sale_approved($fubao_product), 'An approved product has no matching conversion marker; preserve it for manual review rather than overwriting its price.');
        if ($fubao_entry['priced']) {
            $fubao_price = wc_format_decimal($fubao_entry['reference'], wc_get_price_decimals());
            $fubao_product->set_regular_price($fubao_price);
            $fubao_product->set_sale_price('');
            $fubao_product->set_price($fubao_price);
            $fubao_product->set_manage_stock(false);
            $fubao_product->set_stock_quantity(null);
            $fubao_product->update_meta_data('_fubao_sale_approved', 'yes');
            $fubao_product->update_meta_data('_fubao_research', 'no');
            $fubao_product->update_meta_data('_fubao_initial_sale_price', $fubao_price);
            $fubao_product->update_meta_data('_fubao_sale_price_basis', 'owner-approved-historical-reference');
            $fubao_product->update_meta_data('_fubao_sale_approved_at', current_time('mysql', true));
            $fubao_converted++;
        } else {
            $fubao_product->set_regular_price(''); $fubao_product->set_sale_price(''); $fubao_product->set_price('');
            $fubao_product->update_meta_data('_fubao_sale_approved', 'no');
            $fubao_product->update_meta_data('_fubao_research', 'yes');
            $fubao_unavailable++;
        }
        $fubao_product->update_meta_data('_fubao_sale_conversion_version', $fubao_version);
        $fubao_product->save();
        foreach ($fubao_entry['history'] as $fubao_key => $fubao_before) { $fubao_publish_require($fubao_product->get_meta($fubao_key) === $fubao_before, 'Source/history data unexpectedly changed during conversion.'); }
    }
    // Exact, approved replacements only; factual source claims and other prose
    // remain intact. Each page is marked once so later dashboard edits survive.
    $fubao_copy = array(
        'THE CURATED COLLECTION — 18 REFERENCES' => 'THE CURATED COLLECTION — 18 SELECTIONS',
        '18 research selections, not items offered for sale. Prices are historical Amazon references from October 25, 2026, converted at ¥150 = US$1.' => '18 source-linked selections. Seventeen priced selections are offered for sale in USD; the selection without a confirmed price is unavailable for purchase. Initial selling prices were adopted from the historical Amazon reference snapshot of October 25, 2026 (¥150 = US$1). Current selling prices are shown on each product page.',
        'Explore a research collection of rings, necklaces, bracelets, arm cuffs and earrings. Compare shapes and styling ideas, then check the original listing for current details.' => 'Explore a source-linked collection of rings, necklaces, bracelets, arm cuffs and earrings. Compare shapes and styling ideas, then check each product page for current selling prices and purchase availability.',
        'No. FUBAO JEWELRY currently presents product research and styling inspiration. Saving a piece creates a list in this browser, not an order. Prices are dated references, not current offers.' => 'Priced selections can be purchased through FUBAO JEWELRY. The item without a confirmed price remains unavailable for purchase. Check each product page for the current USD selling price. Original Amazon links document the historical research source; they are not the checkout destination.',
        'No reference price constitutes a FUBAO JEWELRY retail offer.' => 'Historical reference amounts document the research snapshot. Approved selections now have a separate, current FUBAO JEWELRY selling price on their product pages.',
        'These research references are not FUBAO retail offers.' => 'Historical source references are separate from current FUBAO selling prices; see each product page for purchase availability.',
        'The saved list stays in your browser. This site takes no payments and creates no orders.' => 'The storefront uses WooCommerce for its cart and checkout. Purchase availability and current USD selling prices are shown on each product page. Historical Amazon reference data remains documented separately.',
        'Prices remain historical research references, not FUBAO selling prices.' => 'Historical Amazon reference amounts remain documented as source data. Current FUBAO selling prices and purchase availability are shown separately on each product page.',
        'not independently tested product specifications or FUBAO retail offers.' => 'not independently tested product specifications. Current selling prices and purchase availability are shown on the product pages.',
        'not independent product tests or FUBAO retail offers.' => 'not independent product tests. Current selling prices and purchase availability are shown on the product pages.',
        'No. Product-page prices are historical Amazon references recorded October 25, 2026 and converted at ¥150 = US$1. One displayed ring has no reference price. This site accepts no orders or payments.' => 'Priced selections are available to purchase through FUBAO JEWELRY at the current USD selling prices shown on their product pages. Initial selling prices were adopted from the historical Amazon snapshot of October 25, 2026 (¥150 = US$1). The ring without a confirmed price remains unavailable for purchase.',
        'Product-page prices remain historical references from October 25, 2026, converted at ¥150 = US$1. AI-styled images do not establish physical measurements or included quantities. No orders or payments are accepted here.' => 'Product pages show current USD selling prices for approved selections, alongside their historical Amazon source references from October 25, 2026 (¥150 = US$1). AI-styled images do not establish physical measurements or included quantities.',
        'Images are AI-styled references and do not establish dimensions or package contents. Prices on product pages are historical Amazon references dated October 25, 2026, converted at ¥150 = US$1. No orders or payments are accepted.' => 'Images are AI-styled references and do not establish dimensions or package contents. Product pages show current USD selling prices for approved selections. Historical Amazon references dated October 25, 2026 (¥150 = US$1), remain documented as source data.',
        'The product-page price is a historical Amazon reference from October 25, 2026, converted at ¥150 = US$1. AI-styled images are not measurement evidence. No orders or payments are accepted.' => 'The product page shows the current USD selling price and documents the historical Amazon source reference from October 25, 2026 (¥150 = US$1). AI-styled images are not measurement evidence.'
    );
    $fubao_replace_schema = static function ($value) use (&$fubao_replace_schema, $fubao_copy) {
        if (is_array($value)) { return array_map($fubao_replace_schema, $value); }
        return is_string($value) ? strtr($value, $fubao_copy) : $value;
    };
    $fubao_pages_updated = 0;
    foreach ($fubao_publish_data['pages'] as $fubao_page) {
        $fubao_page_id = fubao_catalog_find_key('page:' . $fubao_page['slug']);
        $fubao_publish_require($fubao_page_id && fubao_catalog_managed($fubao_page_id) && get_post_type($fubao_page_id) === 'page', 'A bundled page is missing.');
        if (get_post_meta($fubao_page_id, '_fubao_sales_copy_version', true) === $fubao_version) { continue; }
        $fubao_old_content = (string) get_post_field('post_content', $fubao_page_id);
        $fubao_new_content = strtr($fubao_old_content, $fubao_copy);
        if ($fubao_new_content !== $fubao_old_content) {
            $fubao_update = wp_update_post(wp_slash(array('ID' => $fubao_page_id, 'post_content' => $fubao_new_content)), true);
            $fubao_publish_require(!is_wp_error($fubao_update), 'A page copy update failed; completed product conversions remain marked for safe retry.');
            $fubao_pages_updated++;
        }
        $fubao_schema = get_post_meta($fubao_page_id, '_fubao_schema', true);
        if (is_string($fubao_schema)) { $fubao_schema = json_decode($fubao_schema, true); }
        if (is_array($fubao_schema)) { update_post_meta($fubao_page_id, '_fubao_schema', wp_slash($fubao_replace_schema($fubao_schema))); }
        if ($fubao_page['slug'] === 'home' && get_post_meta($fubao_page_id, '_fubao_description', true) === $fubao_page['description']) {
            update_post_meta($fubao_page_id, '_fubao_description', 'Shop approved FUBAO JEWELRY selections in USD, compare source-linked product details, and explore styling guides. The unpriced selection remains unavailable for purchase.');
        }
        update_post_meta($fubao_page_id, '_fubao_sales_copy_version', $fubao_version);
    }
    update_option('fubao_commerce_sale_publish_version', $fubao_version, false);
    WP_CLI::line(wp_json_encode(array('converted_to_sales' => $fubao_converted, 'unpriced_kept_unavailable' => $fubao_unavailable, 'already_converted_preserved' => $fubao_skipped, 'pages_updated' => $fubao_pages_updated, 'currency' => 'USD', 'source_history_preserved' => true, 'numeric_stock_created' => false, 'payment_accounts_changed' => false, 'orders_created' => 0), JSON_UNESCAPED_SLASHES));
} catch (Throwable $fubao_publish_error) {
    WP_CLI::error($fubao_publish_error->getMessage(), false);
    $fubao_publish_failed = true;
} finally {
    if ($fubao_publish_lock) { delete_option('fubao_commerce_publish_lock'); }
}
if (!empty($fubao_publish_failed)) { WP_CLI::halt(1); }

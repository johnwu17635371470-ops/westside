<?php
/**
 * Publish the owner's approved shipping/returns page and scoped inventory facts.
 * FUBAO_POLICY_APPROVED=1 wp --path=/www/wwwroot/fubaojewelry.com --user=<admin>
 *   eval-file /private/path/commerce-policy.php
 * Source: fubao-catalog/data/commerce-policy.json, copied by build_package.py.
 * Re-running preserves the existing page, policy and subsequent dashboard edits.
 * No price, stock quantity, stock status, payment or order changes are made.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    if (PHP_SAPI !== 'cli') { http_response_code(403); }
    echo "This helper requires an authenticated WordPress CLI context.\n";
    exit(1);
}
$fubao_policy_lock = false;
try {
    $fubao_policy_require = static function ($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
    $fubao_policy_require(realpath(ABSPATH) === '/www/wwwroot/fubaojewelry.com', 'Refusing an installation outside the independent FUBAO directory.');
    $fubao_policy_require(defined('DB_NAME') && DB_NAME === 'sql_fubaojewelry', 'Refusing an unexpected database.');
    $fubao_policy_require(untrailingslashit(home_url()) === 'https://fubaojewelry.com' && untrailingslashit(site_url()) === 'https://fubaojewelry.com', 'FUBAO home/siteurl must use the confirmed HTTPS origin.');
    $fubao_policy_require(current_user_can('manage_options') && current_user_can('manage_woocommerce'), 'Run with the FUBAO administrator using --user.');
    $fubao_policy_require(getenv('FUBAO_POLICY_APPROVED') === '1', 'No changes made: FUBAO_POLICY_APPROVED=1 is required.');
    $fubao_policy_require(function_exists('fubao_catalog_policy_url') && function_exists('fubao_catalog_sale_approved') && function_exists('wc_get_product'), 'Deploy the policy-capable FUBAO Catalog plugin first.');
    $fubao_policy_data = json_decode((string) @file_get_contents(WP_PLUGIN_DIR . '/fubao-catalog/data/commerce-policy.json'), true);
    $fubao_policy_require(is_array($fubao_policy_data) && ($fubao_policy_data['version'] ?? '') === 'owner-policy-v1', 'The approved policy source is missing or has an unexpected version.');
    $fubao_policy_require(($fubao_policy_data['countries'] ?? null) === array('US', 'CA') && ($fubao_policy_data['currency'] ?? '') === 'USD' && get_woocommerce_currency() === 'USD', 'The policy must match the approved USD / US and Canada storefront.');
    $fubao_policy_require(($fubao_policy_data['shipping'] ?? null) === array('first_unit' => 12, 'additional_unit' => 1, 'quantity_mode' => 'store-unit') && get_option('fubao_commerce_shipping_quantity_mode') === 'store-unit', 'The approved store-unit shipping setup must be applied first.');
    $fubao_shipping_instance = (int) get_option('fubao_commerce_method_instance_id', 0);
    $fubao_shipping_settings = $fubao_shipping_instance ? get_option('woocommerce_flat_rate_' . $fubao_shipping_instance . '_settings', array()) : array();
    $fubao_policy_require(($fubao_shipping_settings['cost'] ?? '') === '11 + [qty]', 'The current shipping cost differs from the approved policy; inspect it without overwriting dashboard settings.');
    $fubao_policy_require(($fubao_policy_data['returns'] ?? '') === 'no-change-of-mind', 'An unexpected returns policy needs explicit review.');
    $fubao_delivery = $fubao_policy_data['delivery'] ?? null;
    $fubao_policy_require($fubao_delivery === null || (is_array($fubao_delivery) && ($fubao_delivery['countries'] ?? null) === array('US', 'CA') && in_array($fubao_delivery['day_basis'] ?? '', array('business', 'calendar'), true) && isset($fubao_delivery['handling_days'], $fubao_delivery['transit_days']) && is_int($fubao_delivery['handling_days']) && is_int($fubao_delivery['transit_days']) && $fubao_delivery['handling_days'] >= 0 && $fubao_delivery['transit_days'] >= 0), 'Delivery days must have an explicitly approved day basis and destination scope; leave delivery null while unconfirmed.');
    $fubao_policy_asins = $fubao_policy_data['inventory_confirmed_asins'] ?? array();
    $fubao_policy_require(is_array($fubao_policy_asins) && count($fubao_policy_asins) === 17 && count(array_unique($fubao_policy_asins)) === 17, 'The inventory confirmation must identify exactly the seventeen approved selections.');
    $fubao_policy_products = array();
    foreach ($fubao_policy_asins as $fubao_policy_asin) {
        $fubao_policy_require(is_string($fubao_policy_asin) && (bool) preg_match('/^[A-Z0-9]{10}$/', $fubao_policy_asin), 'An inventory-confirmed source identifier is invalid.');
        $fubao_policy_id = fubao_catalog_find_key('product:' . strtolower($fubao_policy_asin));
        $fubao_policy_product = $fubao_policy_id ? wc_get_product($fubao_policy_id) : false;
        $fubao_policy_require($fubao_policy_product && $fubao_policy_product->get_status() === 'publish' && fubao_catalog_managed($fubao_policy_id) && fubao_catalog_sale_approved($fubao_policy_product) && !fubao_catalog_research($fubao_policy_product) && $fubao_policy_product->get_price() !== '', 'An inventory-confirmed selection is not an existing, approved priced FUBAO product.');
        $fubao_policy_products[] = $fubao_policy_product;
    }
    $fubao_policy_page_id = (int) get_option('fubao_commerce_policy_page_id', 0);
    $fubao_policy_existing = get_page_by_path('shipping-returns', OBJECT, array('page', 'post', 'product', 'attachment'));
    if ($fubao_policy_page_id) {
        $fubao_policy_require($fubao_policy_existing && (int) $fubao_policy_existing->ID === $fubao_policy_page_id && fubao_catalog_policy_url(), 'The owned policy page was removed, renamed or replaced; manual review is required.');
        WP_CLI::success('Existing FUBAO policy and inventory confirmations preserved; no page or product settings changed.');
    } else {
        $fubao_policy_require(!$fubao_policy_existing && get_option('fubao_commerce_policy', null) === null, 'The policy slug or unowned policy option already exists; no existing page will be adopted or overwritten.');
        $fubao_policy_require(add_option('fubao_commerce_policy_lock', time(), '', false), 'Another policy publication is running, or a previous interruption needs review.');
        $fubao_policy_lock = true;
        $fubao_policy_description = 'Shipping to the United States and Canada: $12 for the first purchased unit and $1 for each additional unit. Shipping estimates and change-of-mind returns policy.';
        $fubao_policy_content = '<h2>Destinations and shipping charges</h2><p>We ship to the United States and Canada. All shipping charges are in USD.</p><p>Shipping is $12 for the first purchased unit and $1 for each additional purchased unit in the order. One purchased set counts as one unit, regardless of the number of pieces inside the set. The final charge is calculated in your cart.</p><table><thead><tr><th scope="col">Purchased units</th><th scope="col">Shipping (USD)</th></tr></thead><tbody><tr><td>1</td><td>$12</td></tr><tr><td>2</td><td>$13</td></tr><tr><td>3</td><td>$14</td></tr></tbody></table>';
        if ($fubao_delivery !== null) {
            $fubao_unit = $fubao_delivery['day_basis'] === 'business' ? 'business days' : 'calendar days';
            $fubao_summary = sprintf('Estimated delivery to the United States and Canada: about %d %s (%d %s handling and %d %s transit).', $fubao_delivery['handling_days'] + $fubao_delivery['transit_days'], $fubao_unit, $fubao_delivery['handling_days'], $fubao_unit, $fubao_delivery['transit_days'], $fubao_unit);
            $fubao_policy_content .= '<h2>Estimated handling and delivery</h2><p>' . esc_html($fubao_summary) . '</p><p>These times are estimates, not a guaranteed arrival date.</p>';
        }
        $fubao_policy_content .= '<h2 id="returns">Returns</h2><p>We do not accept returns for change of mind. This policy does not limit any rights or remedies available under applicable law.</p><h2>Problems with an order</h2><p>If an order arrives damaged, defective, incorrect, or not as described, reply to your order-confirmation email with your order number and details so we can review the issue.</p>';
        $fubao_policy_page_id = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'shipping-returns', 'post_title' => 'Shipping & Returns', 'post_content' => $fubao_policy_content, 'meta_input' => array('_fubao_key' => 'page:shipping-returns', '_fubao_managed' => 'yes', '_fubao_path' => '/shipping-returns.html', '_fubao_description' => $fubao_policy_description, '_fubao_seo_title' => 'Shipping & Returns | FUBAO JEWELRY', '_fubao_policy_version' => 'owner-policy-v1', '_fubao_schema' => array('@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'Shipping & Returns', 'url' => home_url('/shipping-returns.html'), 'publisher' => array('@id' => home_url('/#organization')))))), true);
        $fubao_policy_require(!is_wp_error($fubao_policy_page_id) && $fubao_policy_page_id > 0, 'The owned policy page could not be created.');
        foreach ($fubao_policy_products as $fubao_policy_product) {
            $fubao_policy_product->update_meta_data('_fubao_inventory_confirmed', 'owner-policy-v1');
            $fubao_policy_product->update_meta_data('_fubao_inventory_confirmed_at', current_time('mysql', true));
            $fubao_policy_product->save_meta_data();
        }
        $fubao_policy_data['approval'] = 'owner-approved-v1';
        $fubao_policy_data['approved_at'] = current_time('mysql', true);
        $fubao_policy_data['inventory_confirmed_product_ids'] = array_map(static function ($product) { return $product->get_id(); }, $fubao_policy_products);
        update_option('fubao_commerce_policy', $fubao_policy_data, false);
        update_option('fubao_commerce_policy_page_id', $fubao_policy_page_id, false);
        $fubao_policy_require(fubao_catalog_policy_url() === home_url('/shipping-returns.html'), 'The policy ownership or final URL did not match.');
        fubao_catalog_rewrites();
        flush_rewrite_rules(false);
        WP_CLI::line(wp_json_encode(array('policy_page_id' => $fubao_policy_page_id, 'url' => fubao_catalog_policy_url(), 'inventory_facts_marked' => count($fubao_policy_products), 'delivery_summary' => fubao_catalog_delivery_summary(), 'selling_prices_changed' => false, 'stock_quantities_changed' => false, 'stock_statuses_changed' => false, 'orders_created' => 0), JSON_UNESCAPED_SLASHES));
    }
} catch (Throwable $fubao_policy_error) {
    WP_CLI::error($fubao_policy_error->getMessage(), false);
    $fubao_policy_failed = true;
} finally {
    if ($fubao_policy_lock) { delete_option('fubao_commerce_policy_lock'); }
}
if (!empty($fubao_policy_failed)) { WP_CLI::halt(1); }

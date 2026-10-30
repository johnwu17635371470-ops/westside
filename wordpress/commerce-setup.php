<?php
/**
 * One-time, idempotent WooCommerce shipping setup for the separate FUBAO site.
 * No payment-account, bank, tax-rate, product-price or order operations occur here.
 *
 * Run ONLY after the owner confirms that "one unit" means one WooCommerce cart
 * quantity (one sellable SKU/set, not the number of physical pieces inside a set):
 * FUBAO_SHIPPING_QUANTITY_MODE=store-unit wp --path=/www/wwwroot/fubaojewelry.com \
 *   --user=<FUBAO-administrator> eval-file /private/path/commerce-setup.php
 * Without that explicit mode the helper refuses all changes.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    if (PHP_SAPI !== 'cli') { http_response_code(403); }
    echo "This helper requires an authenticated WordPress CLI context.\n";
    exit(1);
}

$fubao_lock = false;
try {
    $fubao_require = static function ($condition, $message) {
        if (!$condition) { throw new RuntimeException($message); }
    };
    $fubao_require(realpath(ABSPATH) === '/www/wwwroot/fubaojewelry.com', 'Refusing an installation outside the independent FUBAO directory.');
    $fubao_require(defined('DB_NAME') && DB_NAME === 'sql_fubaojewelry', 'Refusing an unexpected database.');
    $fubao_require(untrailingslashit(home_url()) === 'https://fubaojewelry.com' && untrailingslashit(site_url()) === 'https://fubaojewelry.com', 'FUBAO home/siteurl must use the confirmed HTTPS domain.');
    $fubao_require(current_user_can('manage_woocommerce') && current_user_can('manage_options'), 'Run with the FUBAO administrator using --user.');
    $fubao_require(getenv('FUBAO_SHIPPING_QUANTITY_MODE') === 'store-unit', 'No changes made: explicitly confirm FUBAO_SHIPPING_QUANTITY_MODE=store-unit first. A store unit is cart quantity, including a multi-piece set as one unit.');
    $fubao_require(class_exists('WC_Shipping_Zone') && function_exists('wc_get_products'), 'WooCommerce must be active.');

    $fubao_products = wc_get_products(array('status' => 'publish', 'limit' => -1));
    $fubao_require(count($fubao_products) === 18, 'Expected the eighteen existing FUBAO catalog products.');
    $fubao_product_snapshot = array();
    foreach ($fubao_products as $fubao_product) {
        $fubao_require(function_exists('fubao_catalog_managed') && fubao_catalog_managed($fubao_product->get_id()), 'An unexpected unmanaged product is present; inspect it before setup.');
        $fubao_product_snapshot[$fubao_product->get_id()] = array($fubao_product->get_name(), $fubao_product->get_price(), $fubao_product->get_meta('_fubao_research'), $fubao_product->is_purchasable());
    }
    $fubao_require(add_option('fubao_commerce_setup_lock', time(), '', false), 'Another setup is running, or a previous interrupted setup needs review.');
    $fubao_lock = true;
    $fubao_rest = new WC_Shipping_Zone(0);
    $fubao_require(count($fubao_rest->get_shipping_methods(false)) === 0, 'The rest-of-world zone already contains a shipping method. It will not be deleted or overwritten.');
    $fubao_zone_id = (int) get_option('fubao_commerce_zone_id', 0);
    $fubao_method_id = (int) get_option('fubao_commerce_method_instance_id', 0);
    $fubao_require($fubao_zone_id > 0 || $fubao_method_id === 0, 'An orphaned ownership marker requires manual review.');
    $fubao_zone_name = 'FUBAO — United States & Canada';
    $fubao_max_order = 0;
    foreach (WC_Shipping_Zones::get_zones() as $fubao_existing) {
        $fubao_existing_id = (int) $fubao_existing['zone_id'];
        $fubao_max_order = max($fubao_max_order, (int) $fubao_existing['zone_order']);
        if ($fubao_existing_id === $fubao_zone_id) { continue; }
        $fubao_existing_zone = new WC_Shipping_Zone($fubao_existing_id);
        $fubao_require($fubao_existing_zone->get_zone_name() !== $fubao_zone_name, 'An unowned zone has the FUBAO setup name. It will not be adopted or overwritten.');
        $fubao_has_geography = false; $fubao_overlap = false;
        foreach ($fubao_existing_zone->get_zone_locations() as $fubao_location) {
            if (in_array($fubao_location->type, array('country', 'state', 'continent'), true)) { $fubao_has_geography = true; }
            if (($fubao_location->type === 'country' && in_array($fubao_location->code, array('US', 'CA'), true)) || ($fubao_location->type === 'continent' && $fubao_location->code === 'NA') || ($fubao_location->type === 'state' && in_array(substr($fubao_location->code, 0, 3), array('US:', 'CA:'), true))) { $fubao_overlap = true; }
        }
        $fubao_require($fubao_has_geography && !$fubao_overlap, 'An existing user shipping zone may already match US/Canada. It will not be changed or bypassed.');
    }
    $fubao_settings = array('title' => 'Shipping — US & Canada', 'tax_status' => 'none', 'cost' => '11 + [qty]', 'type' => 'order', 'no_class_cost' => '');
    if ($fubao_zone_id > 0) {
        $fubao_zone = new WC_Shipping_Zone($fubao_zone_id);
        $fubao_require((int) $fubao_zone->get_id() === $fubao_zone_id && $fubao_zone->get_zone_name() === $fubao_zone_name, 'The owned zone was removed or renamed; refusing to overwrite user changes.');
        $fubao_locations = array();
        foreach ($fubao_zone->get_zone_locations() as $fubao_location) { $fubao_locations[] = $fubao_location->type . ':' . $fubao_location->code; }
        sort($fubao_locations);
        $fubao_require($fubao_locations === array('country:CA', 'country:US'), 'The owned zone geography has changed; inspect it before reapplying setup.');
        $fubao_methods = $fubao_zone->get_shipping_methods(false);
        foreach ($fubao_methods as $fubao_method) {
            $fubao_require($fubao_method_id > 0 && (int) $fubao_method->instance_id === $fubao_method_id && $fubao_method->id === 'flat_rate' && $fubao_method->enabled === 'yes', 'The owned zone contains an unowned, replaced or disabled method; refusing to overwrite it.');
        }
        $fubao_require($fubao_method_id === 0 || count($fubao_methods) === 1, 'The owned shipping method was removed; inspect it before setup.');
        if ($fubao_method_id > 0) {
            $fubao_current = get_option('woocommerce_flat_rate_' . $fubao_method_id . '_settings', array());
            foreach ($fubao_settings as $fubao_key => $fubao_value) { $fubao_require(isset($fubao_current[$fubao_key]) && $fubao_current[$fubao_key] === $fubao_value, 'The owned flat-rate settings have changed; refusing to overwrite dashboard edits.'); }
            foreach ($fubao_current as $fubao_key => $fubao_value) { $fubao_require(strpos($fubao_key, 'class_cost_') !== 0 || $fubao_value === '', 'An additional shipping-class charge exists; manual review is required.'); }
        }
    } else {
        $fubao_zone = new WC_Shipping_Zone();
        $fubao_zone->set_zone_name($fubao_zone_name);
        $fubao_zone->set_zone_order($fubao_max_order + 1);
        $fubao_zone->add_location('US', 'country');
        $fubao_zone->add_location('CA', 'country');
        $fubao_zone_id = (int) $fubao_zone->save();
        $fubao_require($fubao_zone_id > 0, 'The dedicated shipping zone could not be saved.');
        update_option('fubao_commerce_zone_id', $fubao_zone_id, false);
    }
    if ($fubao_method_id === 0) {
        $fubao_method_id = (int) $fubao_zone->add_shipping_method('flat_rate');
        $fubao_require($fubao_method_id > 0, 'The dedicated flat-rate method could not be created.');
        update_option('fubao_commerce_method_instance_id', $fubao_method_id, false);
        update_option('woocommerce_flat_rate_' . $fubao_method_id . '_settings', $fubao_settings, false);
    }
    update_option('woocommerce_currency', 'USD');
    update_option('woocommerce_allowed_countries', 'specific');
    update_option('woocommerce_specific_allowed_countries', array('US', 'CA'));
    update_option('woocommerce_ship_to_countries', 'specific');
    update_option('woocommerce_specific_ship_to_countries', array('US', 'CA'));
    update_option('fubao_commerce_shipping_quantity_mode', 'store-unit', false);
    WC_Cache_Helper::get_transient_version('shipping', true);
    foreach ($fubao_product_snapshot as $fubao_id => $fubao_before) {
        $fubao_after = wc_get_product($fubao_id);
        $fubao_require($fubao_after && array($fubao_after->get_name(), $fubao_after->get_price(), $fubao_after->get_meta('_fubao_research'), $fubao_after->is_purchasable()) === $fubao_before, 'Unexpected product data drift during shipping setup.');
    }
    WP_CLI::line(wp_json_encode(array('currency' => 'USD', 'sell_to' => array('US', 'CA'), 'ship_to' => array('US', 'CA'), 'zone_id' => $fubao_zone_id, 'flat_rate_instance_id' => $fubao_method_id, 'cost' => '11 + [qty]', 'quantity_mode' => 'store-unit', 'shipping_tax_status' => 'none', 'tax_review' => 'Pending confirmation; no tax rates or tax exemptions configured.', 'research_products_preserved' => count($fubao_product_snapshot), 'payments_configured' => false, 'orders_created' => 0), JSON_UNESCAPED_SLASHES));
} catch (Throwable $fubao_error) {
    WP_CLI::error($fubao_error->getMessage(), false);
    $fubao_failed = true;
} finally {
    if ($fubao_lock) { delete_option('fubao_commerce_setup_lock'); }
}
if (!empty($fubao_failed)) { WP_CLI::halt(1); }

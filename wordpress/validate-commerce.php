<?php
/**
 * Read-only acceptance checks for the independent FUBAO commerce installation.
 * Default: database facts, source preservation, real Woo shipping calculation,
 * and an in-memory render of only the FUBAO Product JSON-LD callback.
 * Optional served-page checks:
 * FUBAO_HTTP_VALIDATE=1 timeout 110s wp --path=/www/wwwroot/fubaojewelry.com \
 *   --user=<FUBAO-administrator> eval-file /private/path/validate-commerce.php
 * No products, carts, customer sessions, settings, orders or log files are created.
 * This is a prepared acceptance helper, not evidence that its checks have run.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) { exit(1); }

(static function () {
    $origin = 'https://fubaojewelry.com';
    $started = microtime(true);
    $http = getenv('FUBAO_HTTP_VALIDATE') === '1';
    $require = static function ($ok, $message) {
        if (!$ok) { throw new RuntimeException($message); }
    };
    // Prevent an acceptance query from triggering any WordPress HTTP client
    // calls, including PayPal API/token requests. Optional cURL checks below
    // connect only to this host's loopback HTTPS vhost with TLS verification.
    $block_http = static function () { return new WP_Error('fubao_validation_read_only', 'External HTTP is disabled during FUBAO validation.'); };
    add_filter('pre_http_request', $block_http, PHP_INT_MAX, 3);
    try {
        $require(realpath(ABSPATH) === '/www/wwwroot/fubaojewelry.com', 'Refusing an installation outside the independent FUBAO directory.');
        $require(defined('DB_NAME') && DB_NAME === 'sql_fubaojewelry', 'Refusing an unexpected database.');
        $require(untrailingslashit(home_url()) === $origin && untrailingslashit(site_url()) === $origin, 'FUBAO home/siteurl must both use the confirmed HTTPS origin.');
        $require(current_user_can('manage_options') && current_user_can('manage_woocommerce'), 'Run with the FUBAO administrator using --user.');
        $require(function_exists('wc_get_products') && function_exists('fubao_catalog_sale_approved') && function_exists('fubao_catalog_managed'), 'WooCommerce and the sales-capable FUBAO Catalog must be active.');
        $require(class_exists('DOMDocument'), 'PHP DOM is required to inspect emitted metadata.');
        $require(get_woocommerce_currency() === 'USD', 'Store currency is not USD.');
        $sort_codes = static function ($values) { $values = array_values(array_unique((array) $values)); sort($values); return $values; };
        $require(get_option('woocommerce_allowed_countries') === 'specific' && $sort_codes(get_option('woocommerce_specific_allowed_countries')) === array('CA', 'US'), 'Selling countries must be exactly US and Canada.');
        $require(get_option('woocommerce_ship_to_countries') === 'specific' && $sort_codes(get_option('woocommerce_specific_ship_to_countries')) === array('CA', 'US'), 'Shipping countries must be exactly US and Canada.');
        $require($sort_codes(array_keys(WC()->countries->get_allowed_countries())) === array('CA', 'US') && $sort_codes(array_keys(WC()->countries->get_shipping_countries())) === array('CA', 'US'), 'Effective Woo country lists differ from the approved countries.');
        $require(get_option('fubao_commerce_shipping_quantity_mode') === 'store-unit', 'The approved store-unit shipping mode is missing.');
        $require((bool) get_option('blog_public'), 'The launched acquisition pages must have blog_public=1.');

        $bundle_file = WP_PLUGIN_DIR . '/fubao-catalog/data/import.json';
        $require(is_file($bundle_file), 'The source bundle is missing.');
        $bundle = json_decode(file_get_contents($bundle_file), true, 512, JSON_THROW_ON_ERROR);
        $require(isset($bundle['pages'], $bundle['products']) && count($bundle['pages']) === 7 && count($bundle['products']) === 18, 'Expected the seven-page/eighteen-product source bundle.');
        $published = wc_get_products(array('status' => 'publish', 'limit' => -1));
        $require(count($published) === 18, 'Expected exactly eighteen published products.');
        $products = array(); $priced = 0; $unpriced = 0; $paths = array(); $sample = null;
        foreach ($bundle['pages'] as $page) {
            $id = fubao_catalog_find_key('page:' . $page['slug']);
            $require($id && get_post_status($id) === 'publish' && fubao_catalog_managed($id), 'A bundled acquisition page is missing or unpublished.');
            $require(get_post_meta($id, '_fubao_path', true) === $page['path'], 'An acquisition page has lost its preserved path.');
            $paths[$origin . $page['path']] = null;
        }
        foreach ($bundle['products'] as $item) {
            $asin = strtolower((string) $item['asin']);
            $require((bool) preg_match('/^[a-z0-9]{10}$/', $asin), 'Invalid source product identifier.');
            $id = fubao_catalog_find_key('product:' . $asin);
            $product = $id ? wc_get_product($id) : false;
            $require($product && $product->get_status() === 'publish' && $product->get_type() === 'simple' && fubao_catalog_managed($id), 'A bundled simple product is missing or unpublished: ' . $asin);
            $require(!isset($products[$id]), 'Duplicate product identity in the source bundle.');
            foreach (array('_fubao_asin' => 'asin', '_fubao_source_url' => 'sourceUrl', '_fubao_source_date' => 'sourceDate', '_fubao_finish' => 'finish', '_fubao_quantity' => 'quantityLabel') as $meta => $field) {
                $require((string) $product->get_meta($meta) === (string) $item[$field], 'Historical/source field drift for ' . $asin . ': ' . $field);
            }
            $reference = $product->get_meta('_fubao_reference_price');
            $require(isset($item['referencePrice']) ? ($reference !== '' && is_numeric($reference) && abs((float) $reference - (float) $item['referencePrice']) < 0.00001) : $reference === '', 'Historical reference price changed: ' . $asin);
            $path = '/products/' . $asin . '.html';
            $require(get_post_meta($id, '_fubao_path', true) === $path && get_permalink($id) === $origin . $path, 'A product has lost its preserved HTTPS path: ' . $asin);
            if ($item['referencePrice'] !== null) {
                $require(fubao_catalog_sale_approved($product) && !fubao_catalog_research($product), 'A priced selection lacks explicit sale approval: ' . $asin);
                $require($product->get_price() !== '' && is_numeric($product->get_price()) && (float) $product->get_price() > 0 && $product->is_purchasable(), 'An approved product has no positive selling price or is not purchasable: ' . $asin);
                $priced++;
                if ($sample === null && $product->needs_shipping()) { $sample = $product; }
            } else {
                $require(fubao_catalog_research($product) && !fubao_catalog_sale_approved($product) && $product->get_price() === '' && !$product->is_purchasable(), 'The unpriced reference has become purchasable: ' . $asin);
                $unpriced++;
            }
            $require($product->get_image_id() && is_file((string) get_attached_file($product->get_image_id())), 'An imported product image is missing: ' . $asin);
            $products[$id] = $product;
            $paths[$origin . $path] = $product;
        }
        $require($priced === 17 && $unpriced === 1 && count($paths) === 25 && $sample !== null, 'Expected seventeen sale products, one unpriced reference and twenty-five unique paths.');

        // A policy is optional for the original commerce baseline. Treat it as
        // published only through the plugin's approval + owned-page contract.
        $policy = get_option('fubao_commerce_policy', array());
        $policy_url = function_exists('fubao_catalog_policy_url') ? fubao_catalog_policy_url() : '';
        $policy_active = is_array($policy) && ($policy['approval'] ?? '') === 'owner-approved-v1' && is_string($policy_url) && $policy_url !== '';
        $check_policy_copy = static function ($html) use ($require) {
            $text = strtolower(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
            $require(strpos($text, '18 business days') !== false && strpos($text, '3 business days handling') !== false && strpos($text, '15 business days transit') !== false, 'The approved 18-business-day delivery estimate is not visible.');
            $require(strpos($text, 'we do not accept returns for change of mind') !== false || strpos($text, 'we do not offer voluntary change-of-mind returns') !== false, 'The approved voluntary change-of-mind returns statement is missing.');
            $require(strpos($text, 'statutory rights') !== false || (strpos($text, 'rights or remedies') !== false && strpos($text, 'applicable law') !== false), 'The policy omits the preservation of applicable statutory rights.');
        };
        $policy_report = array('configured' => false, 'expected_acquisition_pages' => 25);
        if ($policy_active) {
            $policy_id = (int) get_option('fubao_commerce_policy_page_id', 0);
            $require($policy_url === $origin . '/shipping-returns.html' && $policy_id > 0 && get_post_type($policy_id) === 'page' && get_post_status($policy_id) === 'publish' && fubao_catalog_managed($policy_id) && get_post_meta($policy_id, '_fubao_key', true) === 'page:shipping-returns' && get_post_meta($policy_id, '_fubao_path', true) === '/shipping-returns.html' && get_permalink($policy_id) === $policy_url, 'The approved policy page lost its owned identity or canonical URL.');
            $require(function_exists('fubao_catalog_indexable_post') && fubao_catalog_indexable_post($policy_id), 'The approved policy page is not an acquisition/indexing target.');
            $delivery = $policy['delivery'] ?? array();
            $require(is_array($delivery) && ($delivery['day_basis'] ?? '') === 'business' && ($delivery['handling_days'] ?? null) === 3 && ($delivery['transit_days'] ?? null) === 15 && ($delivery['countries'] ?? null) === array('US', 'CA') && ($policy['returns'] ?? '') === 'no-change-of-mind', 'The stored approved delivery/returns policy differs from the confirmed terms.');
            $delivery_summary = function_exists('fubao_catalog_delivery_summary') ? fubao_catalog_delivery_summary() : '';
            $require(is_string($delivery_summary) && strpos($delivery_summary, '18 business days') !== false, 'The approved delivery-summary interface is missing.');
            $check_policy_copy(get_post_field('post_content', $policy_id));
            $confirmed_ids = $policy['inventory_confirmed_product_ids'] ?? array();
            $require(is_array($confirmed_ids) && count($confirmed_ids) === 17 && count(array_unique($confirmed_ids)) === 17, 'Expected seventeen specifically inventory-confirmed products.');
            $expected_confirmed = array();
            foreach ($products as $product) {
                if (!fubao_catalog_sale_approved($product) || fubao_catalog_research($product)) { continue; }
                $expected_confirmed[] = $product->get_id();
                $require(in_array($product->get_id(), $confirmed_ids, true) && $product->get_meta('_fubao_inventory_confirmed') === 'owner-policy-v1', 'An approved sale product lacks the scoped inventory confirmation.');
            }
            $actual_confirmed = $confirmed_ids; sort($actual_confirmed); sort($expected_confirmed);
            $require($actual_confirmed === $expected_confirmed, 'The inventory confirmation includes a product outside the approved seventeen.');
            $paths[$policy_url] = null;
            $policy_report = array('configured' => true, 'url' => $policy_url, 'delivery_summary' => $delivery_summary, 'return_policy' => 'no-change-of-mind; statutory rights retained', 'inventory_confirmed_products' => count($expected_confirmed), 'expected_acquisition_pages' => 26);
        }
        $expected_page_count = $policy_active ? 26 : 25;
        $require(count($paths) === $expected_page_count, 'The approved acquisition URL count differs from policy state.');

        $package = static function ($country, $qty) use ($sample) {
            $cost = (float) $sample->get_price() * $qty;
            return array('contents' => array('fubao-validation-synthetic' => array('product_id' => $sample->get_id(), 'variation_id' => 0, 'variation' => array(), 'quantity' => $qty, 'data' => $sample, 'line_total' => $cost, 'line_subtotal' => $cost, 'line_tax' => 0, 'line_subtotal_tax' => 0, 'line_tax_data' => array('subtotal' => array(), 'total' => array()))), 'contents_cost' => $cost, 'applied_coupons' => array(), 'user' => array('ID' => 0), 'cart_subtotal' => $cost, 'destination' => array('country' => $country, 'state' => $country === 'US' ? 'CA' : 'ON', 'postcode' => $country === 'US' ? '90210' : 'M5V1A1', 'city' => '', 'address' => '', 'address_1' => '', 'address_2' => ''));
        };
        $shipping = array();
        foreach (array('US', 'CA') as $country) {
            foreach (array(1, 2, 3) as $qty) {
                $test = $package($country, $qty);
                $zone = WC_Shipping_Zones::get_zone_matching_package($test);
                $require((int) $zone->get_id() === (int) get_option('fubao_commerce_zone_id') && (int) $zone->get_id() > 0, 'A different shipping zone matches the approved destination.');
                $methods = $zone->get_shipping_methods(true);
                $require(count($methods) === 1, 'Expected exactly one enabled shipping method for US/Canada.');
                $method = reset($methods);
                $require($method->id === 'flat_rate' && (int) $method->instance_id === (int) get_option('fubao_commerce_method_instance_id'), 'The matching shipping method is not the approved flat rate.');
                $require($method->get_option('tax_status') === 'none', 'The approved shipping rate has acquired a tax surcharge.');
                foreach ((array) $method->instance_settings as $key => $value) {
                    if ($key === 'no_class_cost' || strpos($key, 'class_cost_') === 0) { $require($value === '', 'A shipping class surcharge has been added.'); }
                }
                $rates = $method->get_rates_for_package($test);
                $require(count($rates) === 1, 'The real Woo method did not return exactly one rate.');
                $rate = reset($rates);
                $require(abs((float) $rate->get_cost() - (11 + $qty)) < 0.00001 && array_sum((array) $rate->get_taxes()) == 0, 'The real Woo method returned the wrong shipping amount.');
                $shipping[$country][$qty] = (float) $rate->get_cost();
            }
        }
        $rest = new WC_Shipping_Zone(0);
        $require(count($rest->get_shipping_methods(false)) === 0, 'Rest-of-world zone must have no methods.');
        $outside = $package('AU', 1); $outside['destination']['state'] = 'NSW'; $outside['destination']['postcode'] = '2000';
        $require((int) WC_Shipping_Zones::get_zone_matching_package($outside)->get_id() === 0, 'An unintended shipping zone matches an outside destination.');

        $bacs = get_option('woocommerce_bacs_settings', array());
        $accounts = get_option('woocommerce_bacs_accounts', array());
        $require(is_array($bacs) && isset($bacs['enabled']) && $bacs['enabled'] === 'yes' && is_array($accounts) && count($accounts) > 0, 'Bank transfer is disabled or lacks an account.');
        foreach ($accounts as $account) {
            $require(is_array($account) && !empty($account['account_name']) && !empty($account['bank_name']) && (!empty($account['account_number']) || !empty($account['iban'])), 'A bank transfer account is incomplete.');
        }
        // Only counts/booleans leave this scope; never print bank field values.
        $bank_report = array('enabled' => true, 'complete_account_count' => count($accounts));
        unset($accounts, $bacs, $account);
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $require(is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php'), 'The official PayPal Payments plugin is not active.');
        $paypal = array('plugin_active' => true, 'connection' => 'unverified', 'remote_api_tested' => false);
        // Use only the plugin's boolean connection-status facade. Do not read
        // credential options, IDs, merchant profiles, tokens or DTOs.
        try {
            if (class_exists('WooCommerce\\PayPalCommerce\\PPCP')) {
                $container = \WooCommerce\PayPalCommerce\PPCP::container();
                if ($container->has('settings.connection-state')) {
                    $state = $container->get('settings.connection-state');
                    if (is_object($state) && method_exists($state, 'is_connected')) { $paypal['connection'] = $state->is_connected() ? 'connected' : 'not-connected'; }
                } elseif ($container->has('settings.flag.is-connected')) { $paypal['connection'] = $container->get('settings.flag.is-connected') ? 'connected' : 'not-connected'; }
            }
        } catch (Throwable $ignored) { $paypal['connection'] = 'unverified'; }

        $parse_html = static function ($body) use ($require) {
            $previous = libxml_use_internal_errors(true);
            try {
                $doc = new DOMDocument();
                $require($doc->loadHTML('<?xml encoding="utf-8" ?>' . $body, LIBXML_NONET), 'HTML metadata could not be parsed.');
                return new DOMXPath($doc);
            } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        };
        $product_nodes = static function ($value) use (&$product_nodes) {
            if (!is_array($value)) { return array(); }
            $found = isset($value['@type']) && in_array('Product', (array) $value['@type'], true) ? array($value) : array();
            foreach ($value as $child) { if (is_array($child)) { $found = array_merge($found, $product_nodes($child)); } }
            return $found;
        };
        $check_product_schema = static function ($html, $product) use ($parse_html, $product_nodes, $require, $origin, $policy_active, $policy_url, $sort_codes) {
            $xpath = $parse_html($html); $nodes = array();
            foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) { $nodes = array_merge($nodes, $product_nodes(json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR))); }
            $require(count($nodes) === 1, 'Expected exactly one Product schema for a catalog product.');
            $schema = $nodes[0];
            $require(isset($schema['url']) && $schema['url'] === get_permalink($product->get_id()), 'Product schema URL differs from the preserved permalink.');
            $require(!array_key_exists('aggregateRating', $schema) && !array_key_exists('review', $schema), 'Product schema has acquired an unverified review or rating.');
            $require(isset($schema['subjectOf']['url'], $schema['identifier']['value']) && $schema['subjectOf']['url'] === $product->get_meta('_fubao_source_url') && $schema['identifier']['value'] === $product->get_meta('_fubao_asin'), 'Product schema no longer preserves its source URL/identifier.');
            if (fubao_catalog_sale_approved($product) && !fubao_catalog_research($product)) {
                $offer = isset($schema['offers']) ? $schema['offers'] : null;
                $require(is_array($offer) && isset($offer['@type'], $offer['priceCurrency'], $offer['price'], $offer['url']) && $offer['@type'] === 'Offer' && $offer['priceCurrency'] === 'USD' && $offer['url'] === get_permalink($product->get_id()) && is_numeric($offer['price']) && wc_format_decimal($offer['price'], wc_get_price_decimals()) === wc_format_decimal($product->get_price(), wc_get_price_decimals()), 'Product Offer price/currency/URL differs from the current Woo selling price.');
                if ($policy_active) {
                    $availability = array('instock' => 'InStock', 'outofstock' => 'OutOfStock', 'onbackorder' => 'BackOrder');
                    $stock_status = $product->get_stock_status();
                    $require(isset($availability[$stock_status]) && isset($offer['availability']) && $offer['availability'] === 'https://schema.org/' . $availability[$stock_status], 'Confirmed Product availability does not match its current Woo stock status.');
                    $returns = $offer['hasMerchantReturnPolicy'] ?? null;
                    $require(is_array($returns) && ($returns['@type'] ?? '') === 'MerchantReturnPolicy' && ($returns['@id'] ?? '') === $policy_url . '#returns-policy' && ($returns['returnPolicyCategory'] ?? '') === 'https://schema.org/MerchantReturnNotPermitted' && ($returns['merchantReturnLink'] ?? '') === $policy_url && $sort_codes($returns['applicableCountry'] ?? array()) === array('CA', 'US') && !isset($returns['merchantReturnDays']), 'Product Offer return policy differs from the approved US/CA no-change-of-mind policy.');
                }
            } else { $require(!array_key_exists('offers', $schema), 'The unpriced reference has acquired an Offer.'); }
        };
        // Capture only our known metadata callback; do not run generic wp_head,
        // gateways, cart or other plugins while performing this default check.
        $metadata_callbacks = array();
        if (isset($GLOBALS['wp_filter']['wp_head'])) {
            foreach ($GLOBALS['wp_filter']['wp_head']->callbacks as $priority => $callbacks) {
                if ((int) $priority !== 5) { continue; }
                foreach ($callbacks as $entry) {
                    if ($entry['function'] instanceof Closure) {
                        $reflection = new ReflectionFunction($entry['function']);
                        if (realpath((string) $reflection->getFileName()) === realpath(WP_PLUGIN_DIR . '/fubao-catalog/fubao-catalog.php')) { $metadata_callbacks[] = $entry['function']; }
                    }
                }
            }
        }
        $require(count($metadata_callbacks) === 1, 'The FUBAO metadata callback changed; review the schema validation rather than guessing.');
        $saved_globals = array();
        foreach (array('wp_query', 'wp_the_query', 'post') as $name) { $saved_globals[$name] = array_key_exists($name, $GLOBALS) ? array(true, $GLOBALS[$name]) : array(false, null); }
        try {
            foreach ($products as $product) {
                $GLOBALS['wp_query'] = new WP_Query(array('post_type' => 'product', 'p' => $product->get_id(), 'posts_per_page' => 1));
                $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
                $GLOBALS['wp_query']->the_post();
                ob_start();
                try { $metadata_callbacks[0](); $head = ob_get_contents(); } finally { ob_end_clean(); }
                $check_product_schema($head, $product);
            }
        } finally {
            foreach ($saved_globals as $name => $saved) { if ($saved[0]) { $GLOBALS[$name] = $saved[1]; } else { unset($GLOBALS[$name]); } }
        }
        $report = array('database' => 'PASS', 'currency' => 'USD', 'sell_ship' => array('US', 'CA'), 'approved_purchasable_products' => $priced, 'unpriced_unpurchasable_references' => $unpriced, 'source_records_preserved' => count($products), 'shipping_real_method' => $shipping, 'rest_of_world_methods' => 0, 'bank_transfer' => $bank_report, 'paypal' => $paypal, 'policy' => $policy_report, 'product_schema_dry_render' => $policy_active ? '17 matching USD Offers with current stock availability and approved return policy; unpriced reference has no Offer' : '17 matching USD Offers; unpriced reference has no Offer', 'http' => $http ? 'running' : 'SKIPPED: set FUBAO_HTTP_VALIDATE=1', 'orders_created' => 0, 'persistent_changes_requested' => 0);
        WP_CLI::line(wp_json_encode($report, JSON_UNESCAPED_SLASHES));

        if ($http) {
            $require(function_exists('curl_init'), 'PHP cURL is required for optional served-page checks.');
            $fetch = static function ($url, $allowed = array(200)) use ($origin, $require, $started) {
                $require(strpos($url, $origin . '/') === 0 && wp_parse_url($url, PHP_URL_SCHEME) === 'https' && wp_parse_url($url, PHP_URL_HOST) === 'fubaojewelry.com' && !wp_parse_url($url, PHP_URL_PORT) && !wp_parse_url($url, PHP_URL_USER) && !wp_parse_url($url, PHP_URL_PASS), 'HTTP validation escaped the approved HTTPS origin.');
                $remaining = 105 - (microtime(true) - $started);
                $require($remaining >= 1, 'Validation reached its 105-second budget; remaining served-page checks are unverified.');
                $headers = array(); $handle = curl_init($url);
                curl_setopt_array($handle, array(CURLOPT_RESOLVE => array('fubaojewelry.com:443:127.0.0.1'), CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => min(5, (int) floor($remaining)), CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'FUBAO-Commerce-Acceptance/1.0', CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$headers) { if (strpos($line, ':') !== false) { list($key, $value) = explode(':', $line, 2); $key = strtolower(trim($key)); $headers[$key] = isset($headers[$key]) ? $headers[$key] . ', ' . trim($value) : trim($value); } return strlen($line); }));
                $body = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE); $error_number = curl_errno($handle); curl_close($handle);
                $require($body !== false, 'Loopback HTTPS request failed with cURL code ' . $error_number . ' for ' . wp_parse_url($url, PHP_URL_PATH));
                $require(in_array($status, $allowed, true), 'Unexpected HTTP ' . $status . ' for ' . wp_parse_url($url, PHP_URL_PATH));
                return array('body' => (string) $body, 'headers' => $headers, 'status' => $status, 'type' => $type);
            };
            $has_noindex = static function ($response, $xpath) {
                if (preg_match('/\b(noindex|none)\b/i', isset($response['headers']['x-robots-tag']) ? $response['headers']['x-robots-tag'] : '')) { return true; }
                foreach ($xpath->query('//meta[@name]') as $meta) { if (in_array(strtolower($meta->getAttribute('name')), array('robots', 'googlebot', 'bingbot'), true) && preg_match('/\b(noindex|none)\b/i', $meta->getAttribute('content'))) { return true; } }
                return false;
            };
            $titles = array(); $checked = 0;
            foreach ($paths as $url => $product) {
                $response = $fetch($url); $require(stripos($response['type'], 'text/html') === 0, 'An acquisition URL did not return HTML.');
                $xpath = $parse_html($response['body']);
                $canonical = $xpath->query('//link[contains(concat(" ",normalize-space(@rel)," ")," canonical ")]');
                $require($canonical->length === 1 && $canonical->item(0)->getAttribute('href') === $url, 'Missing, duplicate or incorrect canonical: ' . wp_parse_url($url, PHP_URL_PATH));
                $require(!$has_noindex($response, $xpath), 'An acquisition page has noindex: ' . wp_parse_url($url, PHP_URL_PATH));
                $title = $xpath->query('//title'); $require($title->length === 1 && trim($title->item(0)->textContent) !== '', 'An acquisition page lacks its title.'); $titles[] = trim($title->item(0)->textContent);
                if ($policy_active) {
                    $linked_policy = false;
                    foreach ($xpath->query('//a[@href]') as $link) { if ($link->getAttribute('href') === $policy_url) { $linked_policy = true; break; } }
                    $require($linked_policy, 'A served page lacks its visible policy link: ' . wp_parse_url($url, PHP_URL_PATH));
                    if ($url === $policy_url || ($product && fubao_catalog_sale_approved($product) && $product->is_purchasable() && $product->is_in_stock())) { $check_policy_copy($response['body']); }
                }
                if ($product) {
                    $check_product_schema($response['body'], $product);
                    $forms = $xpath->query('//form[contains(concat(" ",normalize-space(@class)," ")," cart ")]//button[contains(concat(" ",normalize-space(@class)," ")," single_add_to_cart_button ")]');
                    $has_purchase_form = fubao_catalog_sale_approved($product) && $product->is_purchasable() && $product->is_in_stock();
                    $require($has_purchase_form ? $forms->length === 1 : $forms->length === 0, 'The served product purchase form disagrees with its current purchase/stock status.');
                }
                foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) { json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR); }
                $checked++;
            }
            $require(count(array_unique($titles)) === $expected_page_count && $checked === $expected_page_count, 'Acquisition page/title counts are incorrect or titles are not unique.');
            $sitemap = $fetch($origin . '/sitemap.xml');
            $xml = new DOMDocument(); $require($xml->loadXML($sitemap['body'], LIBXML_NONET), 'The sitemap is not valid XML.');
            $actual = array(); foreach ($xml->getElementsByTagNameNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'loc') as $node) { $actual[] = trim($node->textContent); }
            $expected = array_keys($paths); sort($actual); sort($expected);
            $require(count($actual) === $expected_page_count && count(array_unique($actual)) === $expected_page_count && $actual === $expected, 'The sitemap differs from the approved ' . $expected_page_count . ' HTTPS acquisition URLs.');
            foreach (array('/cart/', '/checkout/') as $path) {
                $auxiliary = $fetch($origin . $path, array(200, 301, 302, 303, 307, 308));
                $require($has_noindex($auxiliary, $parse_html($auxiliary['body'] ?: '<html></html>')), 'A cart/checkout response lacks noindex: ' . $path);
                if (isset($auxiliary['headers']['location'])) { $location = $auxiliary['headers']['location']; $require(strpos($location, $origin . '/') === 0 || (strpos($location, '/') === 0 && strpos($location, '//') !== 0), 'An auxiliary route redirects away from FUBAO.'); }
            }
            WP_CLI::line('PASS HTTP: ' . $expected_page_count . ' same-host HTTPS 200 pages, exact canonicals, indexable metadata, 17 current-price Product Offers, one Product without Offer, exact ' . $expected_page_count . '-URL sitemap, cart and checkout noindex.' . ($policy_active ? ' Approved policy links, delivery/returns copy, live stock availability and return-policy schema verified.' : ''));
        }
        WP_CLI::success('FUBAO commerce checks completed. No cart, order, customer session or settings write was requested. PayPal authorization and real checkout/payment are not tested.');
    } catch (Throwable $error) {
        WP_CLI::error($error->getMessage(), false);
        $failed = true;
    } finally { remove_filter('pre_http_request', $block_http, PHP_INT_MAX); }
    if (!empty($failed)) { WP_CLI::halt(1); }
})();

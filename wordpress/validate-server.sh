#!/usr/bin/env bash
# Read-only validation for the separate FUBAO deployment, before DNS cutover.
# Routes are the 25 entries in wordpress/url-map.json at generation time.
set -euo pipefail
umask 077
SITE='/www/wwwroot/fubaojewelry.com'
PHP_BIN='/www/server/php/83/bin/php'
for command in readlink mktemp timeout; do
  command -v "$command" >/dev/null || { printf 'FAIL: required command missing: %s\n' "$command" >&2; exit 1; }
done
[[ -d "$SITE" && "$(readlink -f -- "$SITE")" == "$SITE" ]] || { printf 'FAIL: exact independent FUBAO directory was not found.\n' >&2; exit 1; }
[[ -x "$PHP_BIN" && -f "$SITE/wp-config.php" && -f "$SITE/wp-load.php" ]] || { printf 'FAIL: FUBAO WordPress or PHP 8.3 is missing.\n' >&2; exit 1; }
WORK=$(mktemp -d /tmp/fubao-server-check.XXXXXX)
cleanup() { [[ "$WORK" == /tmp/fubao-server-check.* && -d "$WORK" ]] && rm -rf -- "$WORK"; }
trap cleanup EXIT
cat > "$WORK/validate.php" <<'PHP'
<?php
declare(strict_types=1);
const FUBAO_SITE = '/www/wwwroot/fubaojewelry.com';
const FUBAO_ORIGIN = 'https://fubaojewelry.com';
const FUBAO_DB = 'sql_fubaojewelry';
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function fetch_fubao(string $url, bool $head = false): array {
    check(parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === 'fubaojewelry.com' && !parse_url($url, PHP_URL_PORT), 'A test URL escaped the allowed FUBAO HTTPS origin.');
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RESOLVE => ['fubaojewelry.com:443:127.0.0.1'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_NOBODY => $head,
        CURLOPT_USERAGENT => 'FUBAO-Server-Validation/1.0',
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    $error = curl_error($handle);
    curl_close($handle);
    check($body !== false, 'HTTPS request failed for ' . parse_url($url, PHP_URL_PATH) . ': ' . $error);
    check($status === 200, 'Expected HTTP 200 for ' . parse_url($url, PHP_URL_PATH) . ', received ' . $status . '.');
    return ['body' => (string) $body, 'type' => $type];
}
function inspect_schema($value, string $path, int &$products): void {
    if (!is_array($value)) { return; }
    $types = isset($value['@type']) ? (array) $value['@type'] : [];
    if (in_array('Product', $types, true)) {
        $products++;
        check(!array_key_exists('offers', $value) && !array_key_exists('aggregateRating', $value) && !array_key_exists('review', $value), 'Research Product schema contains an offer, rating or review: ' . $path);
    }
    foreach ($value as $child) { if (is_array($child)) { inspect_schema($child, $path, $products); } }
}
try {
    check(realpath(FUBAO_SITE) === FUBAO_SITE, 'Unexpected WordPress directory.');
    $config = file_get_contents(FUBAO_SITE . '/wp-config.php');
    check((bool) preg_match('/define\s*\(\s*[\'"]DB_NAME[\'"]\s*,\s*[\'"]sql_fubaojewelry[\'"]\s*\)/', $config), 'The wp-config.php database guard did not match the independent FUBAO database.');
    check(function_exists('curl_init') && class_exists('DOMDocument'), 'PHP cURL and DOM extensions are required.');
    define('DISABLE_WP_CRON', true);
    $_SERVER['HTTP_HOST'] = 'fubaojewelry.com';
    $_SERVER['SERVER_NAME'] = 'fubaojewelry.com';
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_URI'] = '/';
    require FUBAO_SITE . '/wp-load.php';
    check(defined('DB_NAME') && DB_NAME === FUBAO_DB && realpath(ABSPATH) === FUBAO_SITE, 'Loaded an unexpected WordPress installation or database.');
    check(untrailingslashit(home_url()) === FUBAO_ORIGIN && untrailingslashit(site_url()) === FUBAO_ORIGIN, 'WordPress home/siteurl must both use the final FUBAO HTTPS origin.');
    check(function_exists('wc_get_products') && function_exists('fubao_catalog_managed'), 'WooCommerce or the FUBAO catalog plugin is inactive.');
    $products = wc_get_products(['status' => 'publish', 'limit' => -1]);
    check(count($products) === 18, 'Expected exactly eighteen published FUBAO products.');
    foreach ($products as $product) {
        check(fubao_catalog_managed($product->get_id()) && fubao_catalog_research($product), 'An unexpected non-research product is present.');
        check(!$product->is_purchasable() && $product->get_price() === '', 'A research product is purchasable or has a selling price.');
        $image = $product->get_image_id();
        check($image > 0 && is_file((string) get_attached_file($image)), 'A reference product lacks its imported image.');
    }
    $routes = [
        ['page','/'], ['page','/sources.html'], ['page','/guides.html'],
        ['page','/ring-guide.html'], ['page','/necklace-guide.html'], ['page','/bracelet-guide.html'], ['page','/earring-guide.html'],
        ['product','/products/b0h694668r.html'], ['product','/products/b0gbw7cbvm.html'], ['product','/products/b0gglt29nf.html'],
        ['product','/products/b0gv3rv7tb.html'], ['product','/products/b0gtpydkrz.html'], ['product','/products/b0f5h4k46m.html'],
        ['product','/products/b0cng13qc3.html'], ['product','/products/b0c23ybcl5.html'], ['product','/products/b0f9ymxl4b.html'],
        ['product','/products/b0f6bz9ptz.html'], ['product','/products/b0d9gwx6l6.html'], ['product','/products/b0dhh3jtqs.html'],
        ['product','/products/b0bw91bkr4.html'], ['product','/products/b0d172fc5v.html'], ['product','/products/b0fvsf2mkj.html'],
        ['product','/products/b0f2m4cnwh.html'], ['product','/products/b0bd5563wd.html'], ['product','/products/b0bvzk4z1x.html'],
    ];
    $images = []; $schemas = 0; $product_pages = 0;
    libxml_use_internal_errors(true);
    foreach ($routes as [$kind, $path]) {
        $url = FUBAO_ORIGIN . $path;
        $response = fetch_fubao($url);
        check(stripos($response['type'], 'text/html') === 0, 'A page did not return HTML: ' . $path);
        $document = new DOMDocument();
        check($document->loadHTML($response['body']), 'HTML could not be parsed: ' . $path);
        $xpath = new DOMXPath($document);
        $canonical = $xpath->query('//link[@rel="canonical"]');
        check($canonical->length === 1 && $canonical->item(0)->getAttribute('href') === $url, 'Missing, duplicate or incorrect HTTPS canonical: ' . $path);
        $descriptions = $xpath->query('//meta[@name="description"]');
        check($descriptions->length === 1 && trim($descriptions->item(0)->getAttribute('content')) !== '', 'Missing or duplicate page description: ' . $path);
        $product_schemas = 0;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $schema = json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
            inspect_schema($schema, $path, $product_schemas);
            $schemas++;
        }
        if ($kind === 'product') {
            $product_pages++;
            check($product_schemas === 1, 'Expected exactly one research Product JSON-LD node: ' . $path);
            check(strpos($response['body'], 'single_add_to_cart_button') === false, 'A research page exposes a purchase button: ' . $path);
            $main_images = $xpath->query('//main//img[contains(concat(" ",normalize-space(@class)," ")," catalog-image ")]');
            check($main_images->length >= 1, 'A product page has no product image: ' . $path);
            $src = html_entity_decode($main_images->item(0)->getAttribute('src'), ENT_QUOTES, 'UTF-8');
            check(strpos($src, FUBAO_ORIGIN . '/') === 0, 'A product image escaped the allowed FUBAO origin.');
            $images[$src] = true;
        } elseif ($path === '/') {
            check($xpath->query('//article[contains(concat(" ",normalize-space(@class)," ")," product-card ")]')->length === 18, 'Homepage does not contain eighteen product cards.');
        }
        libxml_clear_errors();
    }
    check(count($images) === 18 && $product_pages === 18, 'Expected eighteen distinct product images and product pages.');
    foreach (array_keys($images) as $url) {
        $image = fetch_fubao($url, true);
        check(stripos($image['type'], 'image/') === 0, 'A product image URL returned a non-image response.');
    }
    echo 'PASS: 25 HTTPS pages and canonicals; ' . $schemas . ' valid JSON-LD blocks; 18 research products without Offers/prices/purchase buttons; 18 image files and URLs. DNS was bypassed only via curl --resolve-equivalent CURLOPT_RESOLVE.' . PHP_EOL;
    echo 'Visibility: blog_public=' . (get_option('blog_public') ? '1' : '0') . '; no settings or content changed.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
PHP
set +e
timeout --signal=TERM --kill-after=2s 55s "$PHP_BIN" "$WORK/validate.php"
STATUS=$?
set -e
if [[ "$STATUS" == 124 || "$STATUS" == 137 ]]; then
  printf 'FAIL: FUBAO validation exceeded its 55-second time limit.\n' >&2
fi
exit "$STATUS"

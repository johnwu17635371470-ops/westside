#!/usr/bin/env bash
# Narrow FUBAO 1.1.2 policy release. Run as root; optional FUBAO_HTTP_VALIDATE=1.
# Never restore the full database automatically: later orders must be preserved.
set -euo pipefail
umask 077
SITE=/www/wwwroot/fubaojewelry.com
PKG=/www/backup/fubao-migration-20261030
PHP=/www/server/php/83/bin/php
CLI=/tmp/fubao-wp-cli.phar
HELPERS=/tmp/fubao-policy-helpers-1.1.2
HTTP_MODE=${FUBAO_HTTP_VALIDATE:-0}
BACKUP=''
READY=''
READY_FILES=()
STEP=preflight
cleanup_ready() {
    local status=$?
    trap - ERR
    set +e
    if [[ -n "$READY" && -n "$BACKUP" && "$BACKUP" == "$PKG"/policy-1.1.2.* && "$READY" == "$BACKUP/ready" && -d "$READY" && ! -L "$READY" && "$(realpath -e "$READY")" == "$READY" ]]; then
        local temporary
        for temporary in "${READY_FILES[@]}"; do
            if [[ "$temporary" == "$READY/"* && -f "$temporary" && ! -L "$temporary" && "$(realpath -e "$temporary")" == "$temporary" ]]; then
                rm -f -- "$temporary"
            fi
        done
        rmdir -- "$READY" 2>/dev/null || true
    fi
    return "$status"
}
on_error() {
    local status=$?
    trap - ERR
    if [[ -n "$BACKUP" && -d "$BACKUP" ]]; then
        printf 'FAIL: step=%s status=%s; stopped without automatic database restore.\n' "$STEP" "$status" >> "$BACKUP/result.log"
    fi
    printf 'FAIL: FUBAO policy deployment stopped at %s. No automatic database restore. Private record: %s\n' "$STEP" "${BACKUP:-not-created}" >&2
    exit "$status"
}
trap on_error ERR
trap cleanup_ready EXIT
test "$(id -u)" = 0
test "$HTTP_MODE" = 0 || test "$HTTP_MODE" = 1
for command in realpath runuser sha256sum stat unzip tar install mktemp timeout cmp grep tee mv rm rmdir; do command -v "$command" >/dev/null; done
for directory in "$SITE" "$PKG" "$SITE/wp-content/themes/fubao-noir" "$SITE/wp-content/plugins/fubao-catalog" "$SITE/wp-content/plugins/fubao-catalog/data"; do
    test -d "$directory"
    test ! -L "$directory"
    test "$(realpath -e "$directory")" = "$directory"
done
for file in "$PHP" "$CLI" "$SITE/wp-config.php" "$PKG/fubao-noir-1.1.2.zip" "$PKG/fubao-catalog-1.1.2.zip" "$PKG/commerce-policy-1.1.2.php" "$PKG/validate-commerce-1.1.2.php"; do
    test -f "$file"
    test ! -L "$file"
    test "$(realpath -e "$file")" = "$file"
done
test -x "$PHP"
test "$(stat -c %a "$SITE/wp-config.php")" = 640
test "$(stat -c %U "$SITE/wp-config.php")" = www
CONFIG_MODE=$(stat -c '%a:%u:%g' "$SITE/wp-config.php")
wpnew() { runuser -u www -- "$PHP" "$CLI" --path="$SITE" --user=fubao_admin "$@"; }
wpguard() { wpnew --skip-plugins --skip-themes "$@"; }
test "$(wpguard config get DB_NAME)" = sql_fubaojewelry
test "$(wpguard option get home)" = https://fubaojewelry.com
test "$(wpguard option get siteurl)" = https://fubaojewelry.com

STEP=package-verification
test "$(stat -c %s "$PKG/fubao-noir-1.1.2.zip")" = 651614
test "$(stat -c %s "$PKG/fubao-catalog-1.1.2.zip")" = 3039180
printf '%s  %s\n' f3662282c80396eb07cb6cb1c2a131e123b5cbd936bf5b6e1dcb8de87b482722 "$PKG/fubao-noir-1.1.2.zip" | sha256sum -c - >/dev/null
printf '%s  %s\n' d7fe65f2b3561298b1c74dd543c984499b147efb610e2ec3c1218148ab3a2063 "$PKG/fubao-catalog-1.1.2.zip" | sha256sum -c - >/dev/null
printf '%s  %s\n' e2bed6e2f652526a07e19494694c49016d49e0834460e6cfc89db4aa82a13721 "$PKG/commerce-policy-1.1.2.php" | sha256sum -c - >/dev/null
printf '%s  %s\n' e634f051924b992d1ad320e39d610a603c1ce2033ab059eeb0406c6909757c6e "$PKG/validate-commerce-1.1.2.php" | sha256sum -c - >/dev/null
BACKUP=$(mktemp -d "$PKG/policy-1.1.2.XXXXXXXX")
test ! -L "$BACKUP"
test "$(realpath -e "$BACKUP")" = "$BACKUP"
test "$(stat -c '%a:%U' "$BACKUP")" = 700:root
STAGE="$BACKUP/stage"
mkdir -m 0700 "$STAGE"
unzip -p "$PKG/fubao-noir-1.1.2.zip" fubao-noir/footer.php > "$STAGE/footer.php"
unzip -p "$PKG/fubao-noir-1.1.2.zip" fubao-noir/single-product.php > "$STAGE/single-product.php"
unzip -p "$PKG/fubao-noir-1.1.2.zip" fubao-noir/style.css > "$STAGE/style.css"
unzip -p "$PKG/fubao-catalog-1.1.2.zip" fubao-catalog/fubao-catalog.php > "$STAGE/fubao-catalog.php"
unzip -p "$PKG/fubao-catalog-1.1.2.zip" fubao-catalog/data/url-map.json > "$STAGE/url-map.json"
unzip -p "$PKG/fubao-catalog-1.1.2.zip" fubao-catalog/data/commerce-policy.json > "$STAGE/commerce-policy.json"
for file in footer.php single-product.php style.css fubao-catalog.php url-map.json commerce-policy.json; do test -s "$STAGE/$file"; done
for file in "$STAGE/footer.php" "$STAGE/single-product.php" "$STAGE/fubao-catalog.php" "$PKG/commerce-policy-1.1.2.php" "$PKG/validate-commerce-1.1.2.php"; do "$PHP" -l "$file" > /dev/null; done
grep -Fq 'Version: 1.1.2' "$STAGE/style.css"
grep -Fq 'Version: 1.1.2' "$STAGE/fubao-catalog.php"
"$PHP" -r 'foreach(array_slice($argv,1) as $file){json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}' "$STAGE/url-map.json" "$STAGE/commerce-policy.json"

TARGETS=(
    "$SITE/wp-content/themes/fubao-noir/footer.php"
    "$SITE/wp-content/themes/fubao-noir/single-product.php"
    "$SITE/wp-content/themes/fubao-noir/style.css"
    "$SITE/wp-content/plugins/fubao-catalog/fubao-catalog.php"
    "$SITE/wp-content/plugins/fubao-catalog/data/url-map.json"
    "$SITE/wp-content/plugins/fubao-catalog/data/commerce-policy.json"
)
SOURCES=(footer.php single-product.php style.css fubao-catalog.php url-map.json commerce-policy.json)
for index in "${!TARGETS[@]}"; do
    target=${TARGETS[$index]}
    parent=${target%/*}
    test ! -L "$target"
    test "$(realpath -e "$parent")" = "$parent"
    if test -e "$target"; then
        test -f "$target"
        test "$(realpath -e "$target")" = "$target"
    else
        test "$index" -ge 4
        test "$(realpath -m "$target")" = "$target"
    fi
done

# All six ready copies are complete before the first live-file replacement.
# A cross-filesystem move is refused so mv below can only perform a rename.
STEP=atomic-code-staging
READY="$BACKUP/ready"
mkdir -m 0700 "$READY"
test "$(realpath -e "$READY")" = "$READY"
READY_DEVICE=$(stat -c %d "$READY")
for index in "${!TARGETS[@]}"; do
    parent=${TARGETS[$index]%/*}
    test "$(stat -c %d "$parent")" = "$READY_DEVICE"
    temporary="$READY/$index.${SOURCES[$index]}"
    test ! -e "$temporary"
    test ! -L "$temporary"
    READY_FILES+=("$temporary")
    install -m 0644 -o www -g www "$STAGE/${SOURCES[$index]}" "$temporary"
    cmp -s "$STAGE/${SOURCES[$index]}" "$temporary"
    test "$(stat -c '%a:%U:%G' "$temporary")" = 644:www:www
done

STEP=private-backups
test ! -e "$BACKUP/pre-policy-code.tar.gz"
(set -o noclobber; tar -czf "$BACKUP/pre-policy-code.tar.gz" -C "$SITE/wp-content" themes/fubao-noir plugins/fubao-catalog)
chmod 0600 "$BACKUP/pre-policy-code.tar.gz"
# Root owns the redirection. SQL and export diagnostics never reach stdout.
(set -o noclobber; wpguard db export - --single-transaction --quiet > "$BACKUP/pre-policy-database.sql" 2> "$BACKUP/database-export.stderr")
test -s "$BACKUP/pre-policy-database.sql"
chmod 0600 "$BACKUP/pre-policy-database.sql" "$BACKUP/database-export.stderr"

STEP=private-helpers
test ! -L "$HELPERS"
if test -e "$HELPERS"; then
    test -d "$HELPERS"
    test "$(realpath -e "$HELPERS")" = "$HELPERS"
    test "$(stat -c %U "$HELPERS")" = www
    test "$(stat -c %a "$HELPERS")" = 700
fi
install -d -m 0700 -o www -g www "$HELPERS"
for helper in commerce-policy.php validate-commerce.php configuration-fingerprint.php; do
    test ! -L "$HELPERS/$helper"
    if test -e "$HELPERS/$helper"; then test -f "$HELPERS/$helper"; test "$(realpath -e "$HELPERS/$helper")" = "$HELPERS/$helper"; fi
done
install -m 0600 -o www -g www "$PKG/commerce-policy-1.1.2.php" "$HELPERS/commerce-policy.php"
install -m 0600 -o www -g www "$PKG/validate-commerce-1.1.2.php" "$HELPERS/validate-commerce.php"
cat > "$STAGE/configuration-fingerprint.php" <<'PHP'
<?php
if (!defined('WP_CLI') || !WP_CLI || realpath(ABSPATH) !== '/www/wwwroot/fubaojewelry.com' || !defined('DB_NAME') || DB_NAME !== 'sql_fubaojewelry' || untrailingslashit(home_url()) !== 'https://fubaojewelry.com' || untrailingslashit(site_url()) !== 'https://fubaojewelry.com') { exit(1); }
global $wpdb;
$keys = array('woocommerce_bacs_accounts', 'wp_mail_smtp', 'wp_mail_smtp_options', 'woocommerce_currency', 'woocommerce_allowed_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries', 'fubao_commerce_zone_id', 'fubao_commerce_method_instance_id', 'fubao_commerce_shipping_quantity_mode');
$placeholders = implode(',', array_fill(0, count($keys), '%s'));
$sql = "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name IN ($placeholders) ORDER BY option_name";
$configuration = array('options' => $wpdb->get_results($wpdb->prepare($sql, array_merge(array('woocommerce%settings'), $keys)), ARRAY_A));
foreach (array('woocommerce_shipping_zones' => 'zone_id', 'woocommerce_shipping_zone_locations' => 'location_id', 'woocommerce_shipping_zone_methods' => 'instance_id') as $table => $order) {
    $configuration[$table] = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . $table . ' ORDER BY ' . $order, ARRAY_A);
}
if ($wpdb->last_error) { exit(1); }
// Hash only: no account, SMTP, payment field or configuration value is output.
echo hash('sha256', serialize($configuration)) . PHP_EOL;
PHP
"$PHP" -l "$STAGE/configuration-fingerprint.php" > /dev/null
install -m 0600 -o www -g www "$STAGE/configuration-fingerprint.php" "$HELPERS/configuration-fingerprint.php"
wpguard eval-file "$HELPERS/configuration-fingerprint.php" > "$BACKUP/configuration.before.sha256" 2> "$BACKUP/configuration-before.stderr"
BEFORE_FINGERPRINT=$(< "$BACKUP/configuration.before.sha256")
[[ "$BEFORE_FINGERPRINT" =~ ^[0-9a-f]{64}$ ]]

STEP=six-controlled-files
for index in "${!TARGETS[@]}"; do
    test ! -L "${TARGETS[$index]}"
    test "$(realpath -e "${TARGETS[$index]%/*}")" = "${TARGETS[$index]%/*}"
    test "$(stat -c %d "${TARGETS[$index]%/*}")" = "$READY_DEVICE"
    mv -Tf -- "${READY_FILES[$index]}" "${TARGETS[$index]}"
done

STEP=approved-policy-publication
runuser -u www -- env FUBAO_POLICY_APPROVED=1 "$PHP" "$CLI" --path="$SITE" --user=fubao_admin eval-file "$HELPERS/commerce-policy.php" > "$BACKUP/policy-helper.log" 2>&1
STEP=required-policy-postcondition
wpnew eval '
$policy = get_option("fubao_commerce_policy", array());
$url = function_exists("fubao_catalog_policy_url") ? fubao_catalog_policy_url() : "";
if (!is_array($policy) || ($policy["approval"] ?? "") !== "owner-approved-v1" || $url !== home_url("/shipping-returns.html") || !function_exists("fubao_catalog_index_targets") || count(fubao_catalog_index_targets()) !== 26) { WP_CLI::error("The required approved policy and twenty-six indexing targets are not configured."); }
$urls = array();
foreach (get_posts(array("post_type" => array("page", "product"), "post_status" => "publish", "numberposts" => -1, "meta_key" => "_fubao_managed", "meta_value" => "yes")) as $post) {
    if (fubao_catalog_indexable_post($post->ID)) { $urls[fubao_catalog_url($post->ID)] = true; }
}
if (count($urls) !== 26 || !isset($urls[$url])) { WP_CLI::error("Expected twenty-six distinct published acquisition URLs including the approved policy."); }
WP_CLI::success("Approved policy and twenty-six published acquisition targets verified.");
' > "$BACKUP/policy-postcondition.log" 2>&1
STEP=read-only-acceptance
timeout --signal=TERM --kill-after=2s 160s runuser -u www -- env FUBAO_HTTP_VALIDATE="$HTTP_MODE" "$PHP" "$CLI" --path="$SITE" --user=fubao_admin eval-file "$HELPERS/validate-commerce.php" > "$BACKUP/validation.log" 2>&1
STEP=configuration-preservation
wpguard eval-file "$HELPERS/configuration-fingerprint.php" > "$BACKUP/configuration.after.sha256" 2> "$BACKUP/configuration-after.stderr"
cmp -s "$BACKUP/configuration.before.sha256" "$BACKUP/configuration.after.sha256"
test "$(stat -c '%a:%u:%g' "$SITE/wp-config.php")" = "$CONFIG_MODE"
printf 'PASS: six FUBAO 1.1.2 files installed; approved policy published; read-only acceptance passed (HTTP=%s); existing payment/SMTP/bank/shipping configuration unchanged.\n' "$HTTP_MODE" | tee "$BACKUP/result.log"
printf 'Private code/database backups and diagnostic records: %s\n' "$BACKUP"
printf 'No order, payment or email was created; no automatic database restoration was performed.\n'

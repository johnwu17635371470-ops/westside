#!/usr/bin/env bash
# Owner-approved FUBAO sale prices, bank transfer and US/Canada shipping.
set -euo pipefail
umask 077
SITE=/www/wwwroot/fubaojewelry.com
PKG=/www/backup/fubao-migration-20261030
PHP=/www/server/php/83/bin/php
CLI=/tmp/fubao-wp-cli.phar
test "$(realpath -e "$SITE")" = "$SITE"
test ! -L "$SITE"
for directory in "$SITE/wp-content/themes/fubao-noir" "$SITE/wp-content/plugins/fubao-catalog"; do
  test ! -L "$directory"
  test "$(realpath -e "$directory")" = "$directory"
done
wpnew() { runuser -u www -- "$PHP" "$CLI" --path="$SITE" --user=fubao_admin "$@"; }
test "$(wpnew config get DB_NAME)" = sql_fubaojewelry
test "$(wpnew option get home)" = https://fubaojewelry.com
test "$(wpnew option get siteurl)" = https://fubaojewelry.com
test -s "$PKG/prelaunch-database.sql"
printf '%s  %s\n' 597897c83a452c6c807eb3d2245a62a7b2dbd85576d5b2e25dfe3f4019c77514 "$PKG/fubao-noir-1.1.1.zip" | sha256sum -c -
printf '%s  %s\n' 1bcf198cf958ee0600269743d5ccba0049bfb498cadc04e325d10ea3b08b68bc "$PKG/fubao-catalog-1.1.1.zip" | sha256sum -c -
STAGE="$PKG/commerce-staged"
test ! -L "$STAGE"
mkdir -p "$STAGE"
unzip -q -o "$PKG/fubao-noir-1.1.1.zip" -d "$STAGE"
unzip -q -o "$PKG/fubao-catalog-1.1.1.zip" -d "$STAGE"
find "$STAGE/fubao-noir" -name '*.php' -type f -exec "$PHP" -l '{}' \;
"$PHP" -l "$STAGE/fubao-catalog/fubao-catalog.php"
"$PHP" -l "$PKG/commerce-setup.php"
"$PHP" -l "$PKG/commerce-publish.php"
"$PHP" -l "$PKG/setup-bank-transfer.php"
HELPERS=/tmp/fubao-commerce-helpers-20261030
test ! -L "$HELPERS"
mkdir -p "$HELPERS"
test "$(realpath -e "$HELPERS")" = "$HELPERS"
chown www:www "$HELPERS"
chmod 0700 "$HELPERS"
for helper in commerce-setup.php commerce-publish.php setup-bank-transfer.php validate-commerce.php; do
  install -m 0600 -o www -g www "$PKG/$helper" "$HELPERS/$helper"
done
if ! test -f "$PKG/pre-commerce-code.tar.gz"; then
  tar -czf "$PKG/pre-commerce-code.tar.gz" -C "$SITE/wp-content" themes/fubao-noir plugins/fubao-catalog
fi
cp -a "$STAGE/fubao-noir/." "$SITE/wp-content/themes/fubao-noir/"
chown -R www:www "$SITE/wp-content/themes/fubao-noir"
install -m 0644 -o www -g www "$STAGE/fubao-catalog/fubao-catalog.php" "$SITE/wp-content/plugins/fubao-catalog/fubao-catalog.php"
runuser -u www -- env FUBAO_SHIPPING_QUANTITY_MODE=store-unit "$PHP" "$CLI" --path="$SITE" --user=fubao_admin eval-file "$HELPERS/commerce-setup.php"
BANK_TMP=/tmp/fubao-bank-import-private.json
test ! -e "$BANK_TMP"
test ! -L "$BANK_TMP"
install -m 0600 -o www -g www "$PKG/bacs-source-private.json" "$BANK_TMP"
cleanup_bank() { test ! -L "$BANK_TMP" && rm -f -- "$BANK_TMP"; }
trap cleanup_bank EXIT
runuser -u www -- env FUBAO_APPROVE_SHARED_BANK=1 "$PHP" "$CLI" --path="$SITE" --user=fubao_admin eval-file "$HELPERS/setup-bank-transfer.php"
runuser -u www -- env FUBAO_APPROVE_REFERENCE_PRICES=1 "$PHP" "$CLI" --path="$SITE" --user=fubao_admin eval-file "$HELPERS/commerce-publish.php"
wpnew option update woocommerce_coming_soon no
wpnew option update woocommerce_store_pages_only no
wpnew option update woocommerce_enable_guest_checkout yes
wpnew plugin get woocommerce-paypal-payments --fields=name,status,version --format=json
wpnew eval-file "$HELPERS/validate-commerce.php"
echo 'FUBAO commerce configured. PayPal account connection is still pending; no real or test order was submitted.'

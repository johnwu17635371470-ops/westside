#!/usr/bin/env bash
# Explicitly enable crawling only for the separate, verified FUBAO installation.
set -euo pipefail
umask 077
SITE=/www/wwwroot/fubaojewelry.com
PKG=/www/backup/fubao-migration-20261030
PHP=/www/server/php/83/bin/php
CLI=/tmp/fubao-wp-cli.phar
PLUGIN="$SITE/wp-content/plugins/fubao-catalog/fubao-catalog.php"
test "$(realpath -e "$SITE")" = "$SITE"
test ! -L "$SITE"
test ! -L "$PLUGIN"
test "$(realpath -e "$PLUGIN")" = "$PLUGIN"
wpnew() { runuser -u www -- "$PHP" "$CLI" --path="$SITE" "$@"; }
test "$(wpnew config get DB_NAME)" = sql_fubaojewelry
test "$(wpnew option get home)" = https://fubaojewelry.com
test "$(wpnew option get siteurl)" = https://fubaojewelry.com
wpnew eval 'foreach(wc_get_products(["status"=>"publish","limit"=>-1]) as $p){if($p->get_price()!=="" || $p->get_meta("_fubao_sale_approved")==="yes"){WP_CLI::error("Historical research-only launch helper cannot replace a commerce installation.");}}'
test -s "$PKG/prelaunch-database.sql"
printf '%s  %s\n' 6b2473a126b91dce83b90cfb8e9c884259bb64f395bb66d02041e7ebf0dde6a7 "$PKG/fubao-catalog-1.0.2.zip" | sha256sum -c -
NEW="$PKG/fubao-catalog-1.0.2.php"
unzip -p "$PKG/fubao-catalog-1.0.2.zip" fubao-catalog/fubao-catalog.php > "$NEW"
printf '%s  %s\n' b4ada2abebca7338b83f613a1be0b8e7712ac44370580a05c5bc7d9a8b47fa87 "$NEW" | sha256sum -c -
"$PHP" -l "$NEW"
if ! test -f "$PKG/fubao-catalog-before-indexing.php"; then
  install -m 0600 "$PLUGIN" "$PKG/fubao-catalog-before-indexing.php"
fi
install -m 0644 -o www -g www "$NEW" "$PLUGIN"
wpnew plugin is-active fubao-catalog
test "$(wpnew plugin get fubao-catalog --field=version)" = 1.0.2
wpnew rewrite flush
wpnew option update blog_public 1
if ! bash "$PKG/validate-server-1.0.2.sh" --public; then
  wpnew option update blog_public 0
  echo 'Public checks failed; search visibility was restored to disabled.' >&2
  exit 1
fi
echo 'FUBAO public crawling enabled and verified. Orders and payments remain unconfigured.'

#!/usr/bin/env bash
# Run only after the owner has approved a separate FUBAO installation.
set -euo pipefail
SITE=/www/wwwroot/fubaojewelry.com
PKG=/www/backup/fubao-migration-20261030
PHP=/www/server/php/83/bin/php
CLI=/tmp/fubao-wp-cli.phar
test "$(realpath -e "$SITE")" = "$SITE"
test ! -L "$SITE"
test -f "$SITE/wp-config.php"
test -x "$PHP"
printf '%s  %s\n' a350a24fd0f3bb336f8dfe3431421b97d582922a8ee7494e55cfd26e13a4b558 "$PKG/fubao-noir.zip" | sha256sum -c -
printf '%s  %s\n' fe32bbd06444e00d919e702075c179be3ae72d1db54ab5e9ed1d104c760eaf42 "$PKG/fubao-catalog.zip" | sha256sum -c -
if ! test -f "$CLI"; then
  curl --fail --silent --show-error --retry 2 --connect-timeout 10 --max-time 90 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$CLI"
  chmod 0644 "$CLI"
fi
wpnew() { runuser -u www -- "$PHP" "$CLI" --path="$SITE" "$@"; }
wpnew core is-installed
test "$(wpnew config get DB_NAME)" = sql_fubaojewelry
HOME_URL=$(wpnew option get home)
case "$HOME_URL" in http://fubaojewelry.com|https://fubaojewelry.com) ;; *) echo 'Unexpected site origin; stopping.' >&2; exit 1;; esac
test "$(wpnew plugin get woocommerce --field=version)" = 11.2.1
wpnew option update blog_public 0
wpnew plugin activate woocommerce
for pair in 'fubao-noir themes' 'fubao-catalog plugins'; do
  read -r name kind <<< "$pair"
  target="$SITE/wp-content/$kind/$name"
  test ! -L "$target"
  test "$(realpath -m "$target")" = "$target"
  if ! test -d "$target"; then
    unzip -q -n "$PKG/$name.zip" -d "$SITE/wp-content/$kind"
    chown -R www:www "$target"
  fi
done
wpnew theme activate fubao-noir
wpnew plugin activate fubao-catalog
wpnew --user=fubao_admin eval '$r=fubao_import_catalog(); if(is_wp_error($r)){WP_CLI::error($r->get_error_message());} WP_CLI::success(wp_json_encode($r));'
wpnew eval '$p=wc_get_products(["status"=>"publish","limit"=>-1]); if(count($p)!==18){WP_CLI::error("Unexpected product count");} foreach($p as $x){if($x->is_purchasable()||$x->get_price()!==""||!$x->get_image_id()){WP_CLI::error("Research product verification failed");}} WP_CLI::success("18 source-linked reference products; orders disabled for these products.");'
wpnew plugin get woocommerce --fields=name,status,version --format=json
curl --resolve fubaojewelry.com:80:127.0.0.1 --max-time 30 --silent --show-error --dump-header /tmp/fubao-server-preview.headers --output /tmp/fubao-server-preview.html --write-out 'Server-local homepage HTTP: %{http_code}\n' http://fubaojewelry.com/
echo 'FUBAO content installed; domain, HTTPS and public-launch checks remain.'

#!/usr/bin/env bash
# Narrow code-only update: cart badge synchronization and unconnected PayPal SDK.
set -euo pipefail
umask 077
SITE=/www/wwwroot/fubaojewelry.com
PKG=/www/backup/fubao-migration-20261030
PHP=/www/server/php/83/bin/php
test "$(realpath -e "$SITE")" = "$SITE"
test ! -L "$SITE"
test "$(runuser -u www -- "$PHP" /tmp/fubao-wp-cli.phar --path="$SITE" config get DB_NAME)" = sql_fubaojewelry
printf '%s  %s\n' 597897c83a452c6c807eb3d2245a62a7b2dbd85576d5b2e25dfe3f4019c77514 "$PKG/fubao-noir-1.1.1.zip" | sha256sum -c -
printf '%s  %s\n' 1bcf198cf958ee0600269743d5ccba0049bfb498cadc04e325d10ea3b08b68bc "$PKG/fubao-catalog-1.1.1.zip" | sha256sum -c -
STAGE="$PKG/commerce-fix-1.1.1"
test ! -L "$STAGE"
mkdir -p "$STAGE"
unzip -p "$PKG/fubao-noir-1.1.1.zip" fubao-noir/assets/theme.js > "$STAGE/theme.js"
unzip -p "$PKG/fubao-noir-1.1.1.zip" fubao-noir/style.css > "$STAGE/style.css"
unzip -p "$PKG/fubao-catalog-1.1.1.zip" fubao-catalog/fubao-catalog.php > "$STAGE/fubao-catalog.php"
"$PHP" -l "$STAGE/fubao-catalog.php"
for target in "$SITE/wp-content/themes/fubao-noir/assets/theme.js" "$SITE/wp-content/themes/fubao-noir/style.css" "$SITE/wp-content/plugins/fubao-catalog/fubao-catalog.php"; do
  test ! -L "$target"
  test "$(realpath -e "$target")" = "$target"
done
install -m 0644 -o www -g www "$STAGE/theme.js" "$SITE/wp-content/themes/fubao-noir/assets/theme.js"
install -m 0644 -o www -g www "$STAGE/style.css" "$SITE/wp-content/themes/fubao-noir/style.css"
install -m 0644 -o www -g www "$STAGE/fubao-catalog.php" "$SITE/wp-content/plugins/fubao-catalog/fubao-catalog.php"
echo 'FUBAO 1.1.1 code-only fixes installed; prices, bank and shipping settings were not changed.'

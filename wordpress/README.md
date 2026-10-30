# FUBAO WordPress + WooCommerce migration

This is an independent WordPress implementation for FUBAO JEWELRY. Do not install it into the existing Better Sport WordPress site. The current Sites publication and DNS stay in place until the new server installation passes staging checks.

Deployment update, 2026-10-30: the separate RackNerd installation now serves the formal HTTPS domain, with the 25-page acquisition sitemap and public indexing enabled. The owner subsequently approved seventeen historical amounts as current WooCommerce USD selling prices; one unpriced selection stays unavailable. Direct bank transfer and US/Canada shipping are configured. PayPal Payments 4.2.0 is active but not connected. See `../PROGRESS.md` for current verification and pending account/email tasks. Earlier research-only steps below document the initial installation, not the current sales state.

## Approved commerce release

The Noir theme and FUBAO Catalog are version 1.1.1. Real WooCommerce cart/checkout links are distinct from the browser-only wishlist. Product pages retain original source evidence, the historical reference amount and AI image notes alongside the current selling price. Approved products have one current-price USD Offer in their Product JSON-LD; the unpriced reference has no Offer. No ratings, material guarantees, numeric stock counts or inferred physical availability were added.

`commerce-setup.php` configures only USD, US/CA country lists and one owned shipping zone. It requires the explicit `FUBAO_SHIPPING_QUANTITY_MODE=store-unit` flag. One purchased SKU/set counts as one unit, so the cost is `11 + [qty]`: $12/$13/$14 for one/two/three units. Rest of world has no shipping method, and class surcharges remain empty. Tax rates were not configured; shipping currently adds no tax surcharge.

`commerce-publish.php` requires `FUBAO_APPROVE_REFERENCE_PRICES=1`, the exact independent database/origin and an administrator. It converts the seventeen amounts once, preserves the missing-price product and source history, and updates exact old no-sale statements. Per-product/page markers preserve later dashboard edits. `setup-bank-transfer.php` requires explicit approval and a mode-0600 temporary source outside the web root; it stores the existing owner's bank details only in WooCommerce and never logs them. Never add its source JSON, account fields or any PayPal credential to this repository.

`deploy-commerce.sh` verifies versioned ZIP hashes, saves the prior code, installs code and runs the approved helpers. The server backup parent is private to root; only non-secret helper code is copied to a mode-0700 www-owned temporary directory for WP-CLI. It does not connect PayPal or submit an order. `validate-commerce.php` is the current acceptance helper: default checks data, real Woo shipping calculations, BACS completeness, PayPal connection state and Product schemas; optional `FUBAO_HTTP_VALIDATE=1` adds 25 served pages, canonicals, purchase forms, current-price Offers, sitemap and noindex checkout/cart checks. Keep a bounded timeout and serial requests on this small VPS.

The older `launch-indexing.sh` and `validate-server.sh` describe the research-only stage and deliberately require no selling prices/Offers. Do not use them to validate or replace an approved commerce installation. Check Woo Coming Soon is disabled and validate anonymous cart/checkout, rather than treating HTTP 200 or schema alone as proof of a working store. Never submit a live/test order without specific authorization.

## Build and files

Run `python wordpress/build_package.py` from the repository root with Pillow and its WebP encoder available. The build fails explicitly if that encoder is missing. It copies reviewed public content from `dist/` and `data/catalog.json`, preserves all 25 page paths and creates two uploadable ZIPs in the sibling `fubao-wordpress-packages/` directory:

- `fubao-noir.zip`: classic WordPress theme retaining the Noir design, colorful photography, mobile navigation, category filter, accessible quick-view dialog and browser reference list.
- `fubao-catalog.zip`: companion plugin with the seven-page and eighteen-product import, bundled media, editable source facts, historical reference prices, GEO/SEO metadata and legacy URL routing.

Generated bundle copies are ignored by Git; their reviewed source and generator are tracked. No WordPress core, WooCommerce vendor code, local database, administrator credentials, server session link or runtime cache belongs in this repository.

Packaging encodes original-size WebP derivatives at quality 88 without resizing, cropping or changing the source PNGs in `dist/`. The theme includes only its three editorial images and font; the companion plugin supplies the eighteen product images for Media Library import, avoiding duplicate catalog images and the obsolete eight-product sprite. Imported homepage content uses the dynamic WooCommerce product-grid shortcode. `fubao-catalog/data/image-derivatives.json` records source/derivative hashes and dimensions so the encoding can be reproduced and checked.

## Install into a new staging site

1. Use a separate site directory, database and database user on RackNerd. Take a current backup before changing any shared server configuration. Reuse the working web stack; do not reinstall the VPS or its control panel. Production needs PHP and MySQL/MariaDB; the local Playground database is only a testing aid.
2. Install WordPress into the new site, with staging protected from public access and search indexing disabled. Do not change the existing `fubaojewelry.com` DNS yet.
3. Install and activate the official WooCommerce plugin. Leave payment gateways and orders unconfigured.
4. Upload and activate `fubao-noir.zip` under Appearance → Themes. Upload and activate `fubao-catalog.zip` under Plugins.
5. Open Tools → FUBAO Setup and run the explicit import. Activation itself imports nothing. The importer rejects Better Sport, existing non-default content, a missing WooCommerce installation or an indexable staging site.
6. Confirm seven editable Pages, eighteen Woo Products and their Media Library images. Research selections have no Woo selling price and cannot be added to a purchase cart. Historical Amazon prices are stored in separate FUBAO fields under Product data → General.
7. Update product facts and articles in WordPress. Re-running a completed import preserves dashboard edits instead of overwriting them.

The bundle was tested with WordPress 7.1.4, WooCommerce 11.2.1 and PHP 8.3. Its empty-site protection recognizes exact, untouched WooCommerce 11.2.1 English default pages by fingerprint. A different Woo version or language may be refused safely; inspect that version's defaults before extending the fixture instead of bypassing the guard.

## Validate and switch

Verify the home page, four guides, source page, guide hub, all eighteen products and original `.html` paths in `url-map.json`. Test filtering, quick view, keyboard focus, mobile navigation, saved references and refresh recovery. Confirm source attribution, historical pricing, AI image notes and the fabric clarification remain visible. Images must load from the new server; no private source documents or cost analysis may be published.

Check dynamic title, description, canonical and JSON-LD. Products in this research collection deliberately have no merchant Offer or fabricated ratings. If an additional SEO plugin is installed later, reconcile its output with FUBAO Catalog to avoid duplicate metadata. Keep one owner for schema/canonical output.

On production, route requests for missing physical files to WordPress (`try_files $uri $uri/ /index.php?$args;` in the site-specific Nginx configuration, or normal WordPress Apache rewrites). The plugin preserves `/products/{asin}.html` and the six guide/source paths. Keep `blog_public=0` and staging access protection until the launch decision. Once public, `/sitemap.xml` uses current WordPress page data and `robots.txt` links it. Private staging does not expose this sitemap.

Only after the separate server site, HTTPS and 25 URLs pass should the domain be switched from Sites. Confirm the final production origin in WordPress, then submit the sitemap. Actual sales require separately confirmed own SKUs, selling prices, variants, inventory, shipping/returns, merchant details and payment configuration.

`deploy-server.sh` is restricted to the verified RackNerd directory, database, versions and package hashes; review these values before any future reuse. `validate-server.sh` is a read-only, 110-second server-local HTTPS check for the research-only stage: all 25 routes, canonical/description/JSON-LD output, eighteen non-purchasable research products and imported images. It requires PHP 8.3 cURL and DOM, keeps TLS validation enabled, writes temporary files outside the web root and leaves visibility settings unchanged. Use `validate-commerce.php` for the current sales release instead.

## Local test environment

The sibling `fubao-wp-local/` directory is an isolated loopback-only WordPress Playground installation. Its runtime and local database are excluded from the migration packages. It verifies theme/plugin behavior but does not replace testing against RackNerd's production PHP/MySQL stack. See its `README-LOCAL.txt` for the runtime startup command. Never copy `local-admin.json` or its database to GitHub or hosting packages.

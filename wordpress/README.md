# FUBAO WordPress + WooCommerce migration

This is an independent WordPress implementation for FUBAO JEWELRY. Do not install it into the existing Better Sport WordPress site. The current Sites publication and DNS stay in place until the new server installation passes staging checks.

## Build and files

Run `python wordpress/build_package.py` from the repository root. It copies reviewed public content from `dist/` and `data/catalog.json`, preserves all 25 page paths and creates two uploadable ZIPs in the sibling `fubao-wordpress-packages/` directory:

- `fubao-noir.zip`: classic WordPress theme retaining the Noir design, colorful photography, mobile navigation, category filter, accessible quick-view dialog and browser reference list.
- `fubao-catalog.zip`: companion plugin with the seven-page and eighteen-product import, bundled media, editable source facts, historical reference prices, GEO/SEO metadata and legacy URL routing.

Generated bundle copies are ignored by Git; their reviewed source and generator are tracked. No WordPress core, WooCommerce vendor code, local database, administrator credentials, server session link or runtime cache belongs in this repository.

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

## Local test environment

The sibling `fubao-wp-local/` directory is an isolated loopback-only WordPress Playground installation. Its runtime and local database are excluded from the migration packages. It verifies theme/plugin behavior but does not replace testing against RackNerd's production PHP/MySQL stack. See its `README-LOCAL.txt` for the runtime startup command. Never copy `local-admin.json` or its database to GitHub or hosting packages.

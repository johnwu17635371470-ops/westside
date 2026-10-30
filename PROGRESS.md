# FUBAO JEWELRY progress

## RackNerd content deployed — 2026-10-30

The owner confirmed creation of the separate FUBAO installation. WordPress 7.1.4, WooCommerce 11.2.1 and PHP 8.3 are installed at `/www/wwwroot/fubaojewelry.com`; aaPanel shortened the independent database name to `sql_fubaojewelry`. Both ZIP hashes matched before activation. The Noir theme and FUBAO Catalog plugin are active; seven Pages and eighteen image-backed research Products were imported. CLI verification confirms no Woo selling prices or purchasable research products. No Better Sport files, content or PHP 8.2 settings were changed.

Issued and deployed a TLS certificate for the apex domain using manual DNS validation. WordPress home/siteurl now use `https://fubaojewelry.com`. An initial 502 was traced to the missing PHP 8.3 Unix socket despite a live master process. Configuration validation passed; restarting only PHP 8.3 restored its socket and HTTPS HTTP 200. The existing FUBAO-specific WordPress rewrite already handles the legacy guide/product paths; sampled home, guide and product requests return 200.

`wordpress/deploy-server.sh` guards the exact directory, independent database, package hashes and origin. `wordpress/validate-server.sh` passed on RackNerd: 25 HTTPS pages with matching canonicals/descriptions, 24 valid JSON-LD blocks, eighteen research products without offers/selling prices/purchase buttons, and eighteen image files/URLs. It performs bounded server-local HTTPS checks without changing DNS, content or settings. A new-site database export was saved with root-only permissions under `/www/backup/fubao-migration-20261030/prelaunch-database.sql`.

DNS cutover completed at about 17:45 China time: the enabled apex A record now points to RackNerd; the remaining Sites A record is paused. For rollback, restore the edited value `172.66.3.26` and re-enable `162.159.143.30`. Existing validation TXT records are preserved. The formal HTTPS domain was opened successfully in a fresh browser tab; loaded theme scripts and images use WordPress paths, confirming the new server is serving it. Proof image is saved outside Git as the sibling `fubao-wordpress-live.png`. Original Sites hosting and its owner-private audience are unchanged. Resolver caches may continue showing the previous target temporarily.

Search indexing remains disabled (`blog_public=0`); public crawling, default-content cleanup and sitemap submission must be verified separately. This is an accessible product-research preview, not an operational checkout. The certificate was issued using manual DNS verification; automatic renewal has not yet been established. Do not treat the panel's expiry badge as proof of renewal automation. Next: finish launch hygiene, establish certificate renewal and backups, then enable/verify crawling for GEO first and SEO alongside it. No recurring two-day security scan is configured yet.

## RackNerd installation prepared — 2026-10-30

The owner logged into aaPanel. WP Toolkit confirms the one existing installation is Better Sport (WordPress 7.1.4, PHP 8.2); FUBAO must be a new installation. A FUBAO install form has been prepared for the formal domain, PHP 8.3, WordPress 7.1.4, English, independent database and administrator. Automatic SSL and cache/static HTML generation are off for preparation; the domain still points to Sites. New privileged-account creation is awaiting action-time confirmation, and no WordPress server installation has yet been submitted. Do not store the supplied administrator email, generated passwords or panel session URL in source.

Optimized the migration packages to 648,283 bytes (theme) and 3,036,459 bytes (plugin), about 95.5% smaller. All 21 WebP derivatives preserve original pixel dimensions, with source hashes unchanged; original PNGs remain in dist. Product images are imported once into Media Library and shared by product and guide views. Actual local PHP WebP media upload, 25 original URLs, canonical/JSON-LD, 34 local image/CSS resources, no merchant offers, import replay and preservation of edits passed. Source/media metadata and source documents remain separate.

Both complete optimized ZIPs were uploaded through aaPanel to `/www/backup/fubao-migration-20261030/`; UI sizes match the local packages. The initial slow 13 MB partial upload remains as a `.upload.tmp` file in that same agent-created directory and can be cleaned up later. No Better Sport files were overwritten. The supplied panel certificate warning was handled directly by the owner; do not bypass browser security warnings automatically.

## WordPress / WooCommerce migration in progress — 2026-10-30

The user chose WordPress + WooCommerce on the existing RackNerd US server. The supplied WordPress admin is the separate live Better Sport site and must not be used for this import. RackNerd VPS management is accessible, but the supplied website-panel session URL and its base origin both return Nginx 404. Do not store its session path or credentials. No remote installation, server reinstallation, DNS cutover or audience change has been performed.

`wordpress/` contains a separate Noir classic theme, FUBAO Catalog import plugin, source-to-bundle generator and migration instructions. Generated image/content copies and ZIPs are excluded from Git. A loopback-only local WordPress Playground with WooCommerce runs from the sibling `fubao-wp-local/` directory; SQLite there is a testing aid, not the RackNerd production database.

Local verification completed on WordPress 7.1.4 / WooCommerce 11.2.1 / PHP 8.3: seven Pages and eighteen Products imported with bundled media, all 25 original URLs return 200 with matching canonical/description and valid structured JSON, research selections have no purchase price or merchant Offer, and repeating the import preserves editor changes. Browser checks at 1440/768/390 show no horizontal overflow or broken loaded images. Category filter, quick view, Escape/focus return, reference totals, removal, refresh restoration, mobile navigation and guide links passed; error log empty. ZIPs were built in the sibling fubao-wordpress-packages directory. The runtime and credentials are outside this repository and are not part of the packages.

Read-only RackNerd VNC access revealed an existing root session. Queried current memory, uptime and the panel's default entrance with credentials filtered from display. Current available memory was approximately 1.4 GB; historical kernel logs show repeated MySQL OOM termination. Retrieved and verified the complete aaPanel security entrance: it now displays the username/password login page. Do not record that private entrance or credentials in Git. Awaiting panel login and a site-specific deployment inspection; FUBAO has not been deployed to RackNerd yet.

## Formal domain connected — 2026-10-30

Aliyun now contains the two Sites apex A records and two validation TXT records. Sites confirmed domain, provider and SSL statuses active. Canonical origin is now https://fubaojewelry.com; regenerated all 25 pages, structured data, sitemap and robots. Hosting remains owner-private, so public crawlability and public launch are still separate pending decisions. Previous pending-domain notes below are historical.

## Guide hub and earring review — 2026-10-30

Added earring-guide.html for the single displayed earring selection and guides.html for all four guides. Source evidence distinguishes inner diameters, post/body material claims and unresolved weight figures. All 18 product pages now link to their relevant guide and the hub. Homepage entry, visible breadcrumbs and matching structured breadcrumbs added to the two new pages. build_guide_hub.cjs runs last in the catalog build to preserve links across regeneration.

GEO remains primary; user explicitly requested SEO alongside it. Expanded validation confirms 25 unique titles, nonempty descriptions, correct same-origin canonicals and sitemap membership, all local links and JSON, 18 product-guide links and four hub cards. Hub layouts at 390/768/1440, mobile earring page, FAQ expansion and homepage/hub/product navigation passed; console errors empty. Formal domain is fubaojewelry.com, already recorded, with DNS validation still pending; hosting audience remains private.

## Bracelet and arm-cuff guide — 2026-10-30

Added bracelet-guide.html for six displayed selections, with attributed listing descriptions in data/bracelet-evidence.json. Distinguishes diameter, product length and recommended body circumference; identifies single-piece stacked wraps and unresolved closures/variants. Homepage entry and sitemap are regenerated by build_bracelet_guide.cjs after the other guides. Catalog integrity, local links, JSON and syntax checks passed. Browser checks at 390/768/1440 showed no document overflow; FAQ expansion passed; error log empty. Private access remains unchanged.

## Necklace comparison guide — 2026-10-30

Added four-selection necklace-guide.html, homepage entry and sitemap URL. data/necklace-evidence.json preserves listing-derived lengths, extensions, widths and variant limits. User clarified that B0D9GWX6L6 is fabric; this is explicitly attributed to the owner, with the conflicting listing field retained as a note. Unknown package counts and lariat drop length are not inferred. The main catalog build regenerates both guides. Catalog/link/JSON checks passed; layouts at 390, 768 and 1440 showed no document overflow, fabric FAQ expansion passed and console errors were empty. Owner-private access and unresolved domain connection are unchanged.

## Ring comparison guide — 2026-10-30

Added a seven-selection ring guide at dist/ring-guide.html, linked from the homepage Q&A and sitemap. data/ring-evidence.json records seller-stated specification fields for four listings and limited title evidence for three. Retrieved Amazon extracts can be cached; review date is not a live inventory check. Unresolved sizes, variants and conflicting stone/material claims remain explicitly qualified. No price, image or sales guarantee was changed.

build_ring_guide.cjs generates the guide; build_catalog.py calls it after rebuilding the catalog so the sitemap entry and homepage link survive regeneration. Full regeneration and check_catalog.py passed. Mobile 390 and desktop 1440 showed no document overflow; the comparison table scrolls within its region. FAQ expansion passed and browser errors were empty. Domain and public-access blockers are unchanged; this update preserves owner-private access.

## Noir Edition update — 2026-10-30

User selected the separate luxury hip-hop preview and requested continued work. Adopted its black/ivory/silver palette, gothic Fubao wordmark, serif editorial typography, split hero and spacious collection. All person photographs are in color; center lookbook hand photo colorized using built-in imagegen. Product photos and 18 source-linked entries remain unchanged. The prior version remains recoverable through Git.

Published successfully: source cacd013135b10af5e92a6fdac75102be743f4c25; deployment appgdep_6ae38a66a4f48191b682719a0085f53d; native status succeeded at 2026-10-29T18:03:43Z. URL: https://westside-shine-jw.astutejay15.chatgpt.site . The same source was pushed to GitHub main. Homepage checked at 1440, 768, 390 with no horizontal overflow; mobile navigation, filtering and product page verified; preview dialogs and saved list passed; browser error logs empty. Local links, JSON and JavaScript checks passed. Access stays owner-private. This is a later documentation-only release note.

## Current update — 2026-10-30

- Brand changed to FUBAO JEWELRY, preserving the charcoal / silver / sunset-orange style.
- GEO / AI recommendations are the primary future objective; SEO is secondary. This preference is recorded in AGENTS.md.
- Sanitized 21 distinct workbook references; 18 are displayed with matching link-based generated photos. User explicitly requested skipping B0BRPZ5529, B0CR45V6ZM and B0FNCSPDLQ for now because their source photos could not be reliably obtained.
- Product images are generated from matched Amazon-link photos, never workbook images. All 18 files and prompt/source mapping are saved in the project.
- Added 18 static detail pages, visible source/pricing explanations, practical Q&A, CollectionPage/ItemList/Product/WebSite/Organization structured data, sitemap and robots. No seller offers, ratings or invented material guarantees.
- Reference prices retain the workbook snapshot date and conversion rate; one displayed item is unpriced. Saved-list totals flag unknown prices.
- 1440 / 768 / 390 checks found no document horizontal overflow. Verified filtering, modal Escape and focus return, saved-list add/increment/remove/empty state, reference totals, unpriced total warning, refresh persistence and mobile navigation. Browser error log empty. Source integrity, links, JSON and JavaScript syntax checks passed.
- fubaojewelry.com is recorded as the planned domain; no DNS binding or public-access change has been made. Current private hosting cannot serve as public GEO acquisition yet.
- Published successfully to the existing owner-private Site. Source commit: e70b76453bd223fe85d621f3c2329f22f47eed23. Deployment: appgdep_6ae37c09c7408191b2d7f59df9e55ece. Native deployment tool returned succeeded at 2026-10-29T17:02:29Z. URL: https://westside-shine-jw.astutejay15.chatgpt.site . The same source commit was pushed to GitHub main. This release note is a later documentation-only change.

Next: confirm actual product specifications/supplier information; connect the domain and choose public launch when requested; expand useful selection guides and measure AI citations/recommendation traffic after public launch. The three skipped references remain in data/catalog.json for later completion.

## Historical WESTSIDE release (superseded)

Saved: 2026-10-29 (Asia/Shanghai).

## Current state

Implemented and privately published the English West Coast jewelry concept homepage, referencing Better Sport's page structure. Original Better Sport site was not modified.

Published URL: https://westside-shine-jw.astutejay15.chatgpt.site/

Site project ID: appgprj_6ae25ec512148191832f0185f9bf3fb3

Published source commit: 6f4622548e0416a4fa03ff2988326e66a8e7b488

Deployment ID: appgdep_6ae2605bae64819180aace174bb8a38b

Deployment status: succeeded.

## Completed

- Charcoal, silver and sunset-orange visual design; original generated hero, eight-product image sheet and three-panel lookbook.
- Responsive homepage, collection filtering, product detail dialogs, mobile navigation.
- Demo shopping bag with quantity changes, removal, totals and localStorage persistence.
- Explicit concept-product and demo-price labeling; no payment or real ordering.
- Source and bundled assets in dist/; generation prompts in ASSETS.md.

## Validation

- JavaScript syntax check passed.
- Browser checks at 1440, 768 and 390 pixel viewport widths found no horizontal document overflow.
- Verified filtering, details, Escape close and focus return, add-to-bag, quantity calculations, refresh persistence, removal and empty state.
- Verified mobile navigation opening and closing after anchor selection.
- Full-page visual review completed; browser error log was empty.

## Resume

This historical section describes the prior eight-product concept release, not the current FUBAO update.

Local preview server was stopped after delivery. Restart with `node server.cjs` from this directory, then open http://127.0.0.1:4173.

Reuse the existing Site ID in .openai/hosting.json for subsequent edits. Preserve private access and use the Sites workflow to publish source changes. Do not create a duplicate Site.

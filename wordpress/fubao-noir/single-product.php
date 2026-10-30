<?php
if (!defined('ABSPATH')) { exit; }
get_header();
global $product;
$fubao_policy_url = function_exists('fubao_catalog_policy_url') ? fubao_catalog_policy_url() : '';
$fubao_delivery_summary = function_exists('fubao_catalog_delivery_summary') ? fubao_catalog_delivery_summary() : '';
$fubao_policy_url = is_string($fubao_policy_url) ? trim($fubao_policy_url) : '';
$fubao_delivery_summary = is_string($fubao_delivery_summary) ? trim($fubao_delivery_summary) : '';
while (have_posts()) {
    the_post();
    $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : false;
    if (!$product) { continue; }
    $data = fubao_noir_product_data($product);
    ?>
    <main class="section fubao-product" id="product-<?php the_ID(); ?>">
        <?php if (function_exists('woocommerce_output_all_notices')) { woocommerce_output_all_notices(); } ?>
        <p class="eyebrow"><?php echo $data['research'] ? 'RESEARCH SELECTION' : 'THE NOIR COLLECTION'; ?> / <?php echo esc_html($data['category']); ?></p>
        <div class="detail-grid">
            <div class="fubao-product-image"><?php if ($data['image']) : ?><img class="catalog-image" src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['name'] . ' — AI-styled image based on linked product photo'); ?>" width="1024" height="1024"><?php else : ?><div class="catalog-image image-pending"><span>PRODUCT PHOTO<br>AWAITING VERIFICATION</span></div><?php endif; ?></div>
            <article class="detail-copy">
                <h1 class="product-title"><?php echo esc_html($data['name']); ?></h1>
                <div class="detail-price"><?php echo $data['research'] ? esc_html__('Not currently for sale', 'fubao-noir') : wp_kses_post($product->get_price_html()); ?></div>
                <?php if ($product->get_short_description()) { echo wp_kses_post(wpautop($product->get_short_description())); } ?>
                <dl>
                    <?php if ($data['finish']) : ?><dt>Appearance</dt><dd><?php echo esc_html($data['finish']); ?></dd><?php endif; ?>
                    <?php if ($data['quantityLabel']) : ?><dt>Set information</dt><dd><?php echo esc_html($data['quantityLabel']); ?></dd><?php endif; ?>
                    <dt>Source identifier</dt><dd><?php echo esc_html($data['asin']); ?></dd>
                </dl>
                <?php if ($data['isPurchasable']) : ?>
                    <div class="fubao-purchase woocommerce"><?php woocommerce_template_single_add_to_cart(); ?></div>
                    <p class="shipping-note">Delivery to the United States and Canada. Shipping is $12 for the first purchased unit and $1 for each additional unit; one set counts as one unit. Calculated in your cart.</p>
                    <?php if ($fubao_delivery_summary !== '') : ?><p class="policy-note"><?php echo esc_html($fubao_delivery_summary); ?></p><?php endif; ?>
                    <?php if ($fubao_policy_url !== '') : ?><p class="policy-note">We do not offer voluntary change-of-mind returns. Your statutory rights are unaffected. <a href="<?php echo esc_url($fubao_policy_url); ?>">Shipping &amp; Returns ↗</a></p><?php endif; ?>
                <?php else : ?><p>This piece is not available for purchase.</p><?php endif; ?>
                <p class="source-note">Historical Amazon source<?php echo $data['sourceDate'] ? ' recorded ' . esc_html($data['sourceDate']) : ''; ?><?php echo $data['referencePrice'] !== null ? ' at $' . esc_html(number_format_i18n($data['referencePrice'], 2)) . ' USD reference, converted from JPY at ¥150 = US$1' : ''; ?>. <?php echo $data['research'] ? 'No FUBAO selling price has been set.' : 'The price above is the current FUBAO selling price; the source reference is retained for context.'; ?> <?php if ($data['sourceUrl']) : ?><a href="<?php echo esc_url($data['sourceUrl']); ?>" target="_blank" rel="noopener noreferrer">View original source ↗</a><?php endif; ?></p>
                <p>Appearance and source details are listed below. Confirm the sizing and included pieces before ordering; source claims are not independent material guarantees.</p>
                <p>Images are AI-styled from the linked source photo. Fine details require comparison with the original.</p>
                <button class="button orange" data-add="<?php echo esc_attr($data['id']); ?>">SAVE TO WISHLIST <span>♡</span></button>
                <p><a class="text-link" href="<?php echo esc_url(home_url('/#collection')); ?>">← Back to collection</a></p>
            </article>
        </div>
        <div class="fubao-product-body"><?php the_content(); ?></div>
        <?php
        $guide_slugs = array('Rings' => 'ring-guide', 'Necklaces' => 'necklace-guide', 'Bracelets' => 'bracelet-guide', 'Arm cuffs' => 'bracelet-guide', 'Earrings' => 'earring-guide');
        if (isset($guide_slugs[$data['category']])) : ?>
            <aside class="related-guide"><h2>Before you choose</h2><p><a href="<?php echo esc_url(fubao_noir_page_url($guide_slugs[$data['category']])); ?>">Compare sizing, pieces and source details ↗</a></p><p><a href="<?php echo esc_url(fubao_noir_page_url('guides')); ?>">Explore all selection guides ↗</a></p></aside>
        <?php endif; ?>
    </main>
    <?php
}
get_footer();

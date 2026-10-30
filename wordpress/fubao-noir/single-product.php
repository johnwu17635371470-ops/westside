<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) {
    the_post();
    $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : false;
    if (!$product) { continue; }
    $data = fubao_noir_product_data($product);
    if (!$data['research']) {
        echo '<main class="section fubao-woocommerce">';
        wc_get_template_part('content', 'single-product');
        echo '</main>';
        continue;
    }
    ?>
    <main class="section fubao-product" id="product-<?php the_ID(); ?>">
        <p class="eyebrow">RESEARCH SELECTION / <?php echo esc_html($data['category']); ?></p>
        <div class="detail-grid">
            <div class="fubao-product-image"><?php if ($data['image']) : ?><img class="catalog-image" src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['name'] . ' — AI-styled image based on linked product photo'); ?>" width="1024" height="1024"><?php else : ?><div class="catalog-image image-pending"><span>PRODUCT PHOTO<br>AWAITING VERIFICATION</span></div><?php endif; ?></div>
            <article class="detail-copy">
                <h1 class="product-title"><?php echo esc_html($data['name']); ?></h1>
                <p class="detail-price"><?php echo esc_html(fubao_noir_reference_price($data)); ?></p>
                <?php if ($product->get_short_description()) { echo wp_kses_post(wpautop($product->get_short_description())); } ?>
                <dl>
                    <?php if ($data['finish']) : ?><dt>Appearance</dt><dd><?php echo esc_html($data['finish']); ?></dd><?php endif; ?>
                    <?php if ($data['quantityLabel']) : ?><dt>Set information</dt><dd><?php echo esc_html($data['quantityLabel']); ?></dd><?php endif; ?>
                    <dt>Source identifier</dt><dd><?php echo esc_html($data['asin']); ?></dd>
                </dl>
                <p class="source-note">Historical Amazon reference<?php echo $data['sourceDate'] ? ' recorded ' . esc_html($data['sourceDate']) : ''; ?>; converted from JPY at ¥150 = US$1. Not a FUBAO selling price or live quote. <?php if ($data['sourceUrl']) : ?><a href="<?php echo esc_url($data['sourceUrl']); ?>" target="_blank" rel="noopener noreferrer">View source listing ↗</a><?php endif; ?></p>
                <p>Research selection, not a FUBAO offer. Verify current variants, sizing, included pieces and material specifications with the original seller.</p>
                <p>Images are AI-styled from the linked source photo. Fine details require comparison with the original.</p>
                <button class="button orange" data-add="<?php echo esc_attr($data['id']); ?>">SAVE REFERENCE <span>+</span></button>
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

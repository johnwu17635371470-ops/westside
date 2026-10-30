<?php if (!defined('ABSPATH')) { exit; } ?>
<footer>
    <div class="footer-top"><a href="<?php echo esc_url(home_url('/')); ?>" class="wordmark">Fubao<span class="wordmark-sub">JEWELRY</span></a><p>West Coast spirit.<br>Everyday statements.</p><nav aria-label="Footer navigation"><a href="<?php echo esc_url(home_url('/#collection')); ?>">Collection ↗</a><a href="<?php echo esc_url(fubao_noir_page_url('guides')); ?>">Selection guides ↗</a><a href="<?php echo esc_url(fubao_noir_page_url('sources')); ?>">Sources & image notes ↗</a></nav></div>
    <div class="footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> FUBAO JEWELRY — THE NOIR EDITION</span><span>USD prices. Source-linked product details and AI-styled imagery.</span><a href="#">BACK TO TOP ↑</a></div>
</footer>
<dialog id="product-dialog" aria-labelledby="detail-title"><button class="close" data-close aria-label="Close product details">×</button><div id="detail-content"></div></dialog>
<dialog id="bag-dialog" class="bag-dialog" aria-labelledby="bag-title"><div class="bag-heading"><div><p class="eyebrow orange-text">YOUR ROTATION</p><h2 id="bag-title">WISHLIST.</h2></div><button class="close" data-close aria-label="Close wishlist">×</button></div><p class="bag-disclaimer">Saved in this browser for later. Your wishlist is separate from the shopping cart and does not create an order.</p><div id="bag-items"></div><div class="bag-total"><span>Saved subtotal <small>(USD)</small></span><strong id="bag-total">$0.00</strong></div><p class="bag-disclaimer">Unpriced pieces are excluded. Shipping is calculated in the shopping cart. Add each available piece to your cart to continue.</p><?php if (function_exists('wc_get_cart_url')) : ?><a class="button orange" href="<?php echo esc_url(wc_get_cart_url()); ?>">VIEW SHOPPING CART <span>↗</span></a><?php endif; ?><button class="button orange" data-close>KEEP EXPLORING <span>↗</span></button></dialog>
<div id="status" class="toast" role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>

<?php if (!defined('ABSPATH')) { exit; } ?>
<footer>
    <div class="footer-top"><a href="<?php echo esc_url(home_url('/')); ?>" class="wordmark">Fubao<span class="wordmark-sub">JEWELRY</span></a><p>West Coast spirit.<br>Everyday statements.</p><nav aria-label="Footer navigation"><a href="<?php echo esc_url(home_url('/#collection')); ?>">Collection ↗</a><a href="<?php echo esc_url(fubao_noir_page_url('guides')); ?>">Selection guides ↗</a><a href="<?php echo esc_url(fubao_noir_page_url('sources')); ?>">Sources & image notes ↗</a></nav></div>
    <div class="footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> FUBAO JEWELRY — PRODUCT RESEARCH COLLECTION</span><span>Editorial AI imagery. Product research collection. No orders or payments.</span><a href="#">BACK TO TOP ↑</a></div>
</footer>
<dialog id="product-dialog" aria-labelledby="detail-title"><button class="close" data-close aria-label="Close product details">×</button><div id="detail-content"></div></dialog>
<dialog id="bag-dialog" class="bag-dialog" aria-labelledby="bag-title"><div class="bag-heading"><div><p class="eyebrow orange-text">YOUR ROTATION</p><h2 id="bag-title">SAVED PIECES.</h2></div><button class="close" data-close aria-label="Close saved list">×</button></div><p class="bag-disclaimer">Your local inspiration list. No purchase or order is created.</p><div id="bag-items"></div><div class="bag-total"><span>Reference total <small>(USD)</small></span><strong id="bag-total">$0.00</strong></div><p class="bag-disclaimer">Historical Amazon reference prices only. Missing prices are excluded and flagged. No checkout or orders.</p><button class="button orange" data-close>KEEP EXPLORING <span>↗</span></button></dialog>
<div id="status" class="toast" role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>

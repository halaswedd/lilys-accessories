<?php
$wa = function_exists('whatsapp_number') ? whatsapp_number() : '';
$ig = trim(INSTAGRAM);
if ($ig !== '' && !preg_match('#^https?://#i', $ig)) {
    $ig = 'https://instagram.com/' . ltrim($ig, '@');
}
$delivery = 'Delivery available all over Lebanon';
?>
<footer class="site-footer">
    <?php if ($ig || $wa): ?>
    <div class="social">
        <?php if ($ig): ?>
        <a class="social-btn" href="<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($wa): ?>
        <a class="social-btn" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z"/><path d="M12.05 2a9.9 9.9 0 0 0-8.4 15.1L2 22l5.05-1.32A9.9 9.9 0 1 0 12.05 2zm0 18.1a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3 .78.8-2.92-.2-.31a8.2 8.2 0 1 1 6.88 3.77z"/></svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <p class="footer-delivery"><?= e($delivery) ?> 🇱🇧</p>
    <p class="footer-copy">© <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
</footer>
<!-- Product popup (filled by main.js) -->
<div class="modal" id="productModal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="pmName">
        <button type="button" class="modal-close" data-close aria-label="Close">&#10005;</button>
        <div class="pm-media">
            <img id="pmImage" alt="" hidden>
            <span class="pm-placeholder" id="pmPlaceholder">&#10047;</span>
        </div>
        <div class="pm-info">
            <h3 class="pm-name" id="pmName"></h3>
            <div class="pm-price" id="pmPrice"></div>
            <p class="pm-desc" id="pmDesc"></p>
            <div class="pm-colors" id="pmColorsWrap" hidden>
                <span class="pm-label">Color: <b id="pmColorName"></b></span>
                <div class="swatches" id="pmColors"></div>
            </div>
            <div class="pm-buy">
                <div class="qty">
                    <button type="button" id="pmMinus" aria-label="Less">&minus;</button>
                    <span id="pmQty">1</span>
                    <button type="button" id="pmPlus" aria-label="More">+</button>
                </div>
                <button type="button" class="pm-add" id="pmAdd">Add to cart</button>
            </div>
        </div>
    </div>
</div>
<!-- Package popup (filled by packages.js) -->
<div class="modal" id="pkgModal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="pkName">
        <button type="button" class="modal-close" data-close aria-label="Close">&#10005;</button>
        <div class="pm-media">
            <img id="pkImage" alt="" hidden>
            <span class="pm-placeholder" id="pkPlaceholder">&#10024;</span>
        </div>
        <div class="pm-info">
            <h3 class="pm-name" id="pkName"></h3>
            <p class="pm-desc" id="pkDesc"></p>
            <div class="pk-includes-title">Includes</div>
            <div class="pk-items" id="pkItems"></div>
            <div class="pm-price" id="pkPrice"></div>
            <div class="pm-buy">
                <div class="qty">
                    <button type="button" id="pkMinus" aria-label="Less">&minus;</button>
                    <span id="pkQty">1</span>
                    <button type="button" id="pkPlus" aria-label="More">+</button>
                </div>
                <button type="button" class="pm-add" id="pkAdd">Add to cart</button>
            </div>
        </div>
    </div>
</div>

<!-- Cart drawer (filled by cart-ui.js) -->
<div class="drawer" id="cartDrawer" hidden>
    <div class="drawer-backdrop" data-close-cart></div>
    <aside class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cartTitle">
        <div class="drawer-head">
            <h3 id="cartTitle">Your Cart</h3>
            <button type="button" class="drawer-close" data-close-cart aria-label="Close cart">&#10005;</button>
        </div>

        <!-- View 1: cart -->
        <div class="drawer-body" id="cartBody"></div>
        <div class="drawer-foot" id="cartFoot" hidden>
            <div class="cart-total"><span>Total</span><strong id="cartTotal">$0</strong></div>
            <button type="button" class="pm-add" id="checkoutBtn">Order via WhatsApp</button>
            <button type="button" class="link-btn" id="clearCart">Clear cart</button>
        </div>

        <!-- View 2: order details -->
        <form class="order-view" id="orderView" hidden novalidate>
            <div class="order-scroll">
                <button type="button" class="link-btn back-link" id="orderBack">&larr; Back to cart</button>
                <div class="field">
                    <label for="of-name">Name *</label>
                    <input type="text" id="of-name" name="name" autocomplete="name" placeholder="Your full name">
                </div>
                <div class="field">
                    <label for="of-address">Full address *</label>
                    <textarea id="of-address" name="address" rows="3" autocomplete="street-address" placeholder="Area, street, building, floor"></textarea>
                </div>
                <div class="field">
                    <label for="of-note">Additional details (optional)</label>
                    <textarea id="of-note" name="note" rows="3" placeholder="Anything we should know?"></textarea>
                </div>
                <div class="order-summary" id="orderSummary"></div>
            </div>
            <div class="drawer-foot">
                <div class="order-error" id="orderError" hidden></div>
                <button type="submit" class="pm-add">Continue to WhatsApp</button>
            </div>
        </form>

        <!-- View 3: done -->
        <div class="done-view" id="doneView" hidden>
            <div class="cart-empty-icon">&#10047;</div>
            <h4>Your order is ready in WhatsApp</h4>
            <p>Just press <b>Send</b> in WhatsApp to place your order. We will confirm with you there.</p>
            <button type="button" class="pm-add" id="doneOpen">Open WhatsApp again</button>
            <button type="button" class="link-btn" id="doneBack">Back to cart</button>
            <button type="button" class="link-btn" id="doneClear">I sent it &mdash; clear my cart</button>
        </div>
    </aside>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.SITE = { whatsapp: <?= json_encode($wa) ?>, baseUrl: <?= json_encode(BASE_URL) ?> };</script>
<script src="<?= asset('js/cart.js') ?>"></script>
<script src="<?= asset('js/cart-ui.js') ?>"></script>
<script src="<?= asset('js/shop.js') ?>"></script>
<script src="<?= asset('js/packages.js') ?>"></script>
<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>
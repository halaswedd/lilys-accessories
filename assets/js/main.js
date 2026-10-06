// Lily's Accessories - main.js
// Header, cart badge, toast, product popup (Step 8). Cart drawer comes in Step 9.

(function () {
    const $ = (s, r = document) => r.querySelector(s);

    // ---------- Header shadow ----------
    const header = $('.site-header');
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    // ---------- Mobile menu (two lines) ----------
    const menuBtn = $('#menuBtn');
    const menu = $('#siteMenu');
    function setMenu(open) {
        menu.classList.toggle('open', open);
        menuBtn.classList.toggle('open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    menuBtn.addEventListener('click', e => { e.stopPropagation(); setMenu(!menu.classList.contains('open')); });
    menu.addEventListener('click', e => { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('click', e => { if (!menu.contains(e.target)) setMenu(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });

    // ---------- Sections fade in while scrolling ----------
    const fades = document.querySelectorAll('.fade-section');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            entries.forEach(en => {
                if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { threshold: 0.06, rootMargin: '0px 0px -4% 0px' });
        fades.forEach(f => io.observe(f));
    } else {
        fades.forEach(f => f.classList.add('in'));
    }

    // ---------- Cart badge ----------
    const badge = $('#cartCount');
    document.addEventListener('cart:change', e => {
        const n = e.detail.count;
        badge.textContent = n;
        badge.hidden = n < 1;
    });

    // ---------- Toast ----------
    const toast = $('#toast');
    let toastTimer;
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
    }
    window.showToast = showToast;

    // ---------- Product popup ----------
    const modal    = $('#productModal');
    const pmImage  = $('#pmImage');
    const pmHolder = $('#pmPlaceholder');
    const pmName   = $('#pmName');
    const pmPrice  = $('#pmPrice');
    const pmDesc   = $('#pmDesc');
    const pmColorsWrap = $('#pmColorsWrap');
    const pmColors = $('#pmColors');
    const pmColorName = $('#pmColorName');
    const pmQty    = $('#pmQty');
    const pmAdd    = $('#pmAdd');

    let current = null;      // product data
    let color = '';          // selected color name
    let qty = 1;
    let opener = null;
    let closeTimer;

    const money = n => {
        n = Number(n);
        return '$' + (Math.floor(n) === n ? n.toFixed(0) : n.toFixed(2));
    };

    function renderQty() { pmQty.textContent = qty; }

    function selectColor(name) {
        color = name;
        pmColorName.textContent = name;
        pmColors.querySelectorAll('.swatch').forEach(b => {
            const on = b.dataset.name === name;
            b.classList.toggle('selected', on);
            b.setAttribute('aria-pressed', on);
        });
        // show the photo of this color (if the admin added one)
        if (current) {
            const c = (current.colors || []).find(x => x.name === name);
            const src = (c && c.image) || current.image;
            if (src && pmImage.getAttribute('src') !== src) {
                pmImage.classList.add('swapping');
                pmImage.onload = () => pmImage.classList.remove('swapping');
                setTimeout(() => pmImage.classList.remove('swapping'), 500);
                pmImage.src = src;
                pmImage.hidden = false;
                pmHolder.hidden = true;
            }
        }
    }

    function openModal(product, fromEl, preColor) {
        clearTimeout(closeTimer);
        current = product;
        opener = fromEl || null;
        qty = 1;
        renderQty();

        pmName.textContent = product.name;
        pmDesc.textContent = product.description || '';
        pmDesc.hidden = !product.description;

        if (product.image) {
            pmImage.src = product.image;
            pmImage.alt = product.name;
            pmImage.hidden = false;
            pmHolder.hidden = true;
        } else {
            pmImage.hidden = true;
            pmHolder.hidden = false;
        }

        pmPrice.innerHTML = '';
        const now = document.createElement('span');
        now.className = 'now';
        now.textContent = money(product.price);
        pmPrice.appendChild(now);
        if (product.oldPrice) {
            const was = document.createElement('span');
            was.className = 'was';
            was.textContent = money(product.oldPrice);
            pmPrice.appendChild(was);
        }
        if (product.bogo) {
            const b = document.createElement('span');
            b.className = 'pm-bogo';
            b.textContent = '\uD83C\uDF81 Buy ' + product.bogo.buy + ' Get ' + product.bogo.get + ' FREE';
            pmPrice.appendChild(b);
        }

        // Colors
        pmColors.innerHTML = '';
        const colors = product.colors || [];
        pmColorsWrap.hidden = colors.length === 0;
        colors.forEach(c => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'swatch' + (c.code ? '' : ' swatch-text');
            b.dataset.name = c.name;
            b.title = c.name;
            b.setAttribute('aria-label', c.name);
            if (c.code) {
                b.style.setProperty('--c', c.code);
            } else {
                if (c.image) {
                    const t = document.createElement('img');
                    t.src = c.thumb || c.image; t.alt = '';
                    b.appendChild(t);
                    new Image().src = c.image;      // preload so switching is instant
                }
                b.appendChild(document.createTextNode(c.name));
            }
            b.addEventListener('click', () => selectColor(c.name));
            pmColors.appendChild(b);
        });
        color = '';
        if (colors.length) {
            const pick = colors.find(c => c.name === preColor)
                      || colors.find(c => c.name === product.defaultColor)
                      || colors[0];
            selectColor(pick.name);
        }

        // Availability
        pmAdd.disabled = !product.available;
        pmAdd.textContent = product.available ? 'Add to cart' : 'Not available';

        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.classList.add('open'));
        $('.modal-close', modal).focus();
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
        closeTimer = setTimeout(() => { modal.hidden = true; }, 220);
        if (opener) opener.focus();
        current = null;
    }

    modal.addEventListener('click', e => { if (e.target.closest('[data-close]')) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
    $('#pmMinus').addEventListener('click', () => { qty = Math.max(1, qty - 1); renderQty(); });
    $('#pmPlus').addEventListener('click',  () => { qty = Math.min(99, qty + 1); renderQty(); });

    pmAdd.addEventListener('click', () => {
        if (!current || !current.available) return;
        window.Cart.add({
            id: current.id, name: current.name, price: current.price,
            image: ((current.colors || []).find(x => x.name === color) || {}).image || current.image,
            baseImage: current.image, color, colors: current.colors, bogo: current.bogo || null, qty
        });
        showToast('Added to cart ✓');
        closeModal();
    });

    // ---------- Product card: color bubbles, add to cart, details ----------
    function productOf(card) {
        try { return JSON.parse(card.dataset.product); } catch (err) { return null; }
    }

    // Tap a color bubble on the card: only the card photo changes (no popup)
    function pickCardColor(card, name) {
        const product = productOf(card);
        if (!product) return;
        const c = (product.colors || []).find(x => x.name === name);
        if (!c) return;
        card.dataset.color = name;

        card.querySelectorAll('.card-swatch').forEach(b => b.classList.toggle('selected', b.dataset.color === name));
        const label = card.querySelector('.card-color-label');
        if (label) label.textContent = name;

        const src = c.image || product.image;
        const media = card.querySelector('.product-media');
        let img = media.querySelector('img');
        if (src) {
            if (!img) {
                img = document.createElement('img');
                img.alt = product.name;
                media.prepend(img);
                const ph = media.querySelector('.placeholder');
                if (ph) ph.remove();
            }
            if (img.getAttribute('src') !== src) {
                img.classList.add('swapping');
                img.onload = () => img.classList.remove('swapping');
                setTimeout(() => img.classList.remove('swapping'), 500);
                img.src = src;
            }
        }
    }

    // load the color photos in the background once a card is on screen, so tapping is instant
    if ('IntersectionObserver' in window) {
        const pre = new IntersectionObserver(entries => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                pre.unobserve(en.target);
                const p = productOf(en.target);
                if (p) (p.colors || []).forEach(c => { if (c.image) new Image().src = c.image; });
            });
        }, { rootMargin: '200px' });
        document.querySelectorAll('.product').forEach(c => pre.observe(c));
    }

    document.addEventListener('click', e => {
        const card = e.target.closest('.product');
        if (!card) return;

        const sw = e.target.closest('.card-swatch');
        if (sw) { pickCardColor(card, sw.dataset.color); return; }

        const product = productOf(card);
        if (!product) return;
        const chosen = card.dataset.color || product.defaultColor || '';

        // "Add to cart" on the card: adds the color that is showing right now
        const addBtn = e.target.closest('.js-add');
        if (addBtn) {
            if (!product.available) return;
            const c = (product.colors || []).find(x => x.name === chosen);
            window.Cart.add({
                id: product.id, name: product.name, price: product.price,
                image: (c && c.image) || product.image, baseImage: product.image,
                color: chosen, colors: product.colors || [], bogo: product.bogo || null, qty: 1
            });
            showToast(chosen ? 'Added to cart ✓ (' + chosen + ')' : 'Added to cart ✓');
            return;
        }

        // Image or "Details": the popup opens on the color that is showing
        const detailsBtn = e.target.closest('.js-details');
        if (detailsBtn) openModal(product, detailsBtn, chosen);
    });
})();
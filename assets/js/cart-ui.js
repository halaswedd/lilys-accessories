// Lily's Accessories - cart drawer UI + WhatsApp order (Steps 9 & 10)
// Views inside the drawer: cart -> order details -> done

(function () {
    const $ = (s, r = document) => r.querySelector(s);

    const drawer    = $('#cartDrawer');
    const body      = $('#cartBody');
    const foot      = $('#cartFoot');
    const totalEl   = $('#cartTotal');
    const titleEl   = $('#cartTitle');
    const orderView = $('#orderView');
    const doneView  = $('#doneView');
    const orderErr  = $('#orderError');

    const TITLES = { cart: 'Your Cart', order: 'Your Details', done: 'Almost done' };
    const CUSTOMER_KEY = 'lily_customer_v1';

    let view = 'cart';
    let closeTimer, opener, lastUrl = '';

    const money = n => {
        n = Number(n);
        return '$' + (Math.floor(n) === n ? n.toFixed(0) : n.toFixed(2));
    };

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    // ---------- Views ----------
    function setView(v) {
        view = v;
        titleEl.textContent = TITLES[v];
        body.hidden      = v !== 'cart';
        orderView.hidden = v !== 'order';
        doneView.hidden  = v !== 'done';
        if (v === 'cart') render(); else foot.hidden = true;
        if (v === 'order') prepareOrderView();
        drawer.querySelector('.drawer-panel').scrollTop = 0;
    }

    // ---------- Open / close ----------
    function open(from) {
        clearTimeout(closeTimer);
        opener = from || null;
        setView('cart');
        drawer.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => drawer.classList.add('open'));
        $('.drawer-close', drawer).focus();
    }

    function close() {
        drawer.classList.remove('open');
        document.body.classList.remove('modal-open');
        closeTimer = setTimeout(() => { drawer.hidden = true; }, 240);
        if (opener) opener.focus();
    }

    // package contents: "2 × Heart Charm (Gold)"
    function groupPkg(list) {
        const map = new Map();
        list.forEach(p => {
            const k = p.name + '|' + (p.color || '');
            const g = map.get(k) || { name: p.name, color: p.color || '', n: 0 };
            g.n++;
            map.set(k, g);
        });
        return Array.from(map.values());
    }

    // ---------- Cart view ----------
    function row(it) {
        const r = el('div', 'cart-row');

        const media = el('div', 'cart-img');
        if (it.image) {
            const img = document.createElement('img');
            img.src = it.image; img.alt = it.name; img.loading = 'lazy';
            media.appendChild(img);
        } else {
            media.textContent = '\u273F';
        }
        r.appendChild(media);

        const info = el('div', 'cart-info');
        info.appendChild(el('div', 'cart-name', it.name));

        if (it.pkg) {
            info.appendChild(el('div', 'cart-colortext', 'Package includes:'));
            const ul = el('ul', 'cart-pkg');
            groupPkg(it.pkg.items).forEach(g => ul.appendChild(el('li', '', (g.n > 1 ? g.n + ' \u00D7 ' : '') + g.name + (g.color ? ' (' + g.color + ')' : ''))));
            info.appendChild(ul);
        } else if (it.colors && it.colors.length) {
            const sel = el('select', 'cart-color');
            sel.setAttribute('aria-label', 'Color');
            it.colors.forEach(c => {
                const o = el('option', '', c.name);
                o.value = c.name;
                if (c.name === it.color) o.selected = true;
                sel.appendChild(o);
            });
            sel.addEventListener('change', () => window.Cart.setColor(it.key, sel.value));
            info.appendChild(sel);
        } else if (it.color) {
            info.appendChild(el('div', 'cart-colortext', 'Color: ' + it.color));
        }

        const qtyRow = el('div', 'cart-qtyrow');
        const qty = el('div', 'qty qty-sm');
        const minus = el('button', '', '\u2212'); minus.type = 'button'; minus.setAttribute('aria-label', 'Less');
        const num   = el('span', '', String(it.qty));
        const plus  = el('button', '', '+');      plus.type = 'button';  plus.setAttribute('aria-label', 'More');
        minus.addEventListener('click', () => window.Cart.setQty(it.key, it.qty - 1));
        plus.addEventListener('click',  () => window.Cart.setQty(it.key, it.qty + 1));
        qty.append(minus, num, plus);
        qtyRow.appendChild(qty);

        const price = el('div', 'cart-price');
        price.appendChild(el('strong', '', money(window.Cart.lineTotal(it))));
        if (it.bogo) {
            const free = window.Cart.freeQty(it);
            price.appendChild(el('small', 'cart-free', free > 0
                ? '\uD83C\uDF81 ' + free + ' free'
                : 'Buy ' + it.bogo.buy + ' Get ' + it.bogo.get + ' free'));
        } else if (it.qty > 1) {
            price.appendChild(el('small', '', money(it.price) + ' each'));
        }
        qtyRow.appendChild(price);
        info.appendChild(qtyRow);
        r.appendChild(info);

        const rm = el('button', 'cart-remove', '\u2715');
        rm.type = 'button';
        rm.setAttribute('aria-label', 'Remove ' + it.name);
        rm.addEventListener('click', () => window.Cart.remove(it.key));
        r.appendChild(rm);

        return r;
    }

    function render() {
        const items = window.Cart.items();
        body.innerHTML = '';

        if (!items.length) {
            const empty = el('div', 'cart-empty');
            empty.appendChild(el('div', 'cart-empty-icon', '\u273F'));
            empty.appendChild(el('p', '', 'Your cart is empty'));
            const go = el('button', 'btn-details', 'Continue shopping');
            go.type = 'button';
            go.addEventListener('click', close);
            empty.appendChild(go);
            body.appendChild(empty);
            foot.hidden = true;
            return;
        }

        items.forEach(it => body.appendChild(row(it)));
        totalEl.textContent = money(window.Cart.total());
        foot.hidden = view !== 'cart';
    }

    // ---------- Order details view ----------
    function loadCustomer() {
        try { return JSON.parse(localStorage.getItem(CUSTOMER_KEY)) || {}; }
        catch (e) { return {}; }
    }
    function saveCustomer(d) {
        try {
            localStorage.setItem(CUSTOMER_KEY, JSON.stringify({ name: d.name, address: d.address }));
        } catch (e) { /* ignore */ }
    }

    function prepareOrderView() {
        orderErr.hidden = true;
        const saved = loadCustomer();
        ['name', 'address'].forEach(k => {
            const input = orderView.elements[k];
            if (!input.value && saved[k]) input.value = saved[k];
        });
        const n = window.Cart.count();
        $('#orderSummary').textContent =
            n + (n === 1 ? ' item' : ' items') + ' \u00B7 Total ' + money(window.Cart.total());
    }

    function getDetails() {
        const f = orderView.elements;
        return {
            name:    f.name.value.trim(),
            address: f.address.value.trim(),
            note:    f.note.value.trim()
        };
    }

    function buildMessage(d) {
        const lines = ["Hello! I'd like to order:", ''];
        window.Cart.items().forEach(it => {
            if (it.pkg) {
                lines.push('\u2022 ' + it.name + ' (Package)');
                lines.push('Includes:');
                groupPkg(it.pkg.items).forEach(g => lines.push('   - ' + (g.n > 1 ? g.n + ' \u00D7 ' : '') + g.name + (g.color ? ' (Color: ' + g.color + ')' : '')));
            } else {
                lines.push('\u2022 ' + it.name);
                if (it.color) lines.push('Color: ' + it.color);
            }
            lines.push('Quantity: ' + it.qty);
            lines.push('Price: ' + money(it.price) + (it.qty > 1 ? ' each' : ''));
            if (it.bogo) {
                const free = window.Cart.freeQty(it);
                lines.push('Offer: Buy ' + it.bogo.buy + ' Get ' + it.bogo.get + ' FREE' + (free > 0 ? ' (' + free + ' free)' : ''));
            }
            lines.push('');
        });
        lines.push('*Total: ' + money(window.Cart.total()) + '*', '');
        lines.push('Name: ' + d.name);
        lines.push('Address: ' + d.address);
        if (d.note) lines.push('Details: ' + d.note);
        lines.push('', 'Delivery: Lebanon');
        return lines.join('\n');
    }

    function openWhatsApp(url) {
        const w = window.open(url, '_blank', 'noopener');
        if (!w) window.location.href = url;     // popup blocked -> same tab
    }

    function showError(msg) {
        orderErr.textContent = msg;
        orderErr.hidden = false;
    }

    orderView.addEventListener('submit', e => {
        e.preventDefault();
        const number = (window.SITE && window.SITE.whatsapp) || '';
        if (!number) { showError('Ordering is not available right now. Please try again later.'); return; }
        if (!window.Cart.count()) { setView('cart'); return; }

        const d = getDetails();
        if (!d.name)    return showError('Please enter your name.');
        if (!d.address) return showError('Please enter your full address.');

        saveCustomer(d);
        lastUrl = 'https://wa.me/' + number + '?text=' + encodeURIComponent(buildMessage(d));
        openWhatsApp(lastUrl);
        setView('done');
    });

    // ---------- Events ----------
    $('#cartBtn').addEventListener('click', e => open(e.currentTarget));
    drawer.addEventListener('click', e => { if (e.target.closest('[data-close-cart]')) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !drawer.hidden) close(); });

    document.addEventListener('cart:change', () => {
        if (drawer.hidden) return;
        if (view === 'cart') render();
        else if (!window.Cart.count()) setView('cart');
        else if (view === 'order') prepareOrderView();
    });

    $('#checkoutBtn').addEventListener('click', () => { if (window.Cart.count()) setView('order'); });
    $('#orderBack').addEventListener('click', () => setView('cart'));
    $('#doneBack').addEventListener('click', () => setView('cart'));
    $('#doneOpen').addEventListener('click', () => { if (lastUrl) openWhatsApp(lastUrl); });
    $('#doneClear').addEventListener('click', () => { window.Cart.clear(); close(); if (window.showToast) window.showToast('Thank you! \u2728'); });

    $('#clearCart').addEventListener('click', () => {
        if (confirm('Remove everything from your cart?')) window.Cart.clear();
    });

    window.openCart = open;
})();
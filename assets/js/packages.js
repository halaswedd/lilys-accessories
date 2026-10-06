// Lily's Accessories - packages: cards + popup + add to cart
// Package cards carry their data in data-package (see render_package_card in includes/package-card.php)

(function () {
    const $ = (s, r = document) => r.querySelector(s);
    const modal = $('#pkgModal');
    if (!modal) return;

    const pkImage = $('#pkImage'), pkHolder = $('#pkPlaceholder');
    const pkName = $('#pkName'), pkDesc = $('#pkDesc'), pkItems = $('#pkItems'), pkPrice = $('#pkPrice');
    const pkQty = $('#pkQty'), pkAdd = $('#pkAdd');

    let current = null, qty = 1, opener = null, closeTimer;

    const money = n => {
        n = Number(n);
        return '$' + (Math.floor(n) === n ? n.toFixed(0) : n.toFixed(2));
    };
    const parse = card => { try { return JSON.parse(card.dataset.package); } catch (e) { return null; } };
    const needsChoice = pkg => pkg.items.some(i => i.colors && i.colors.length);
    const toast = m => { if (window.showToast) window.showToast(m); };

    // what the customer picked for each product of the package
    function readChoices() {
        return current.items.map((it, i) => {
            const sel = pkItems.querySelector('select[data-i="' + i + '"]');
            return { name: it.name, color: sel ? sel.value : '' };
        });
    }

    function addToCart(pkg, choices, q) {
        window.Cart.add({
            id: 'pkg-' + pkg.id, name: pkg.name, price: pkg.price, image: pkg.image,
            baseImage: pkg.image, color: '', colors: [], bogo: null,
            pkg: { items: choices }, qty: q
        });
        toast('Added to cart ✓');
    }

    function renderItem(it, i) {
        const row = document.createElement('div');
        row.className = 'pk-item' + (it.available ? '' : ' is-out');

        const thumb = document.createElement('img');
        thumb.alt = '';
        thumb.src = it.image || '';
        if (!it.image) thumb.style.visibility = 'hidden';
        row.appendChild(thumb);

        const info = document.createElement('div');
        info.className = 'pk-item-info';
        const name = document.createElement('div');
        name.className = 'pk-item-name';
        name.textContent = it.name;
        info.appendChild(name);

        if (it.colors && it.colors.length) {
            const sel = document.createElement('select');
            sel.className = 'cart-color';
            sel.dataset.i = i;
            sel.setAttribute('aria-label', 'Color of ' + it.name);
            it.colors.forEach(c => {
                const o = document.createElement('option');
                o.value = c.name; o.textContent = c.name;
                if (c.name === it.defaultColor) o.selected = true;
                sel.appendChild(o);
            });
            sel.addEventListener('change', () => {
                const c = it.colors.find(x => x.name === sel.value);
                const src = (c && c.image) || it.image;
                if (src) { thumb.src = src; thumb.style.visibility = 'visible'; }
            });
            info.appendChild(sel);
        }
        if (!it.available) {
            const o = document.createElement('div');
            o.className = 'muted-note';
            o.textContent = 'Not available right now';
            info.appendChild(o);
        }
        row.appendChild(info);
        return row;
    }

    function open(pkg, from) {
        clearTimeout(closeTimer);
        current = pkg; qty = 1; opener = from || null;
        pkQty.textContent = qty;

        pkName.textContent = '✨ ' + pkg.name;
        pkDesc.textContent = pkg.description || '';
        pkDesc.hidden = !pkg.description;

        if (pkg.image) { pkImage.src = pkg.image; pkImage.alt = pkg.name; pkImage.hidden = false; pkHolder.hidden = true; }
        else { pkImage.hidden = true; pkHolder.hidden = false; }

        pkItems.innerHTML = '';
        pkg.items.forEach((it, i) => pkItems.appendChild(renderItem(it, i)));

        pkPrice.innerHTML = '';
        const now = document.createElement('span');
        now.className = 'now'; now.textContent = money(pkg.price);
        pkPrice.appendChild(now);
        if (pkg.save > 0) {
            const was = document.createElement('span');
            was.className = 'was'; was.textContent = money(pkg.regular);
            pkPrice.appendChild(was);
            const save = document.createElement('span');
            save.className = 'pm-bogo'; save.textContent = 'Save ' + money(pkg.save);
            pkPrice.appendChild(save);
        }

        pkAdd.disabled = !pkg.available;
        pkAdd.textContent = pkg.available ? 'Add to cart' : 'Not available';

        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.classList.add('open'));
        $('.modal-close', modal).focus();
    }

    function close() {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
        closeTimer = setTimeout(() => { modal.hidden = true; }, 220);
        if (opener) opener.focus();
        current = null;
    }

    modal.addEventListener('click', e => { if (e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) close(); });
    $('#pkMinus').addEventListener('click', () => { qty = Math.max(1, qty - 1); pkQty.textContent = qty; });
    $('#pkPlus').addEventListener('click',  () => { qty = Math.min(99, qty + 1); pkQty.textContent = qty; });

    pkAdd.addEventListener('click', () => {
        if (!current || !current.available) return;
        addToCart(current, readChoices(), qty);
        close();
    });

    // ----- buttons on the package cards -----
    document.addEventListener('click', e => {
        const card = e.target.closest('.pkg-card');
        if (!card) return;
        const pkg = parse(card);
        if (!pkg) return;

        if (e.target.closest('.js-pkg-add')) {
            if (!pkg.available) return;
            if (needsChoice(pkg)) {
                open(pkg, e.target.closest('.js-pkg-add'));   // the customer must pick colors first
            } else {
                addToCart(pkg, pkg.items.map(i => ({ name: i.name, color: '' })), 1);
            }
            return;
        }
        const view = e.target.closest('.js-pkg-view');
        if (view) open(pkg, view);
    });
})();
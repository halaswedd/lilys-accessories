// Lily's Accessories - cart store (no UI here; the cart drawer UI is built in Step 9)
// Saved in the visitor's browser (localStorage). Fires "cart:change" whenever it changes.

(function () {
    const KEY = 'lily_cart_v1';
    let items = [];

    function load() {
        try { items = JSON.parse(localStorage.getItem(KEY)) || []; }
        catch (e) { items = []; }
        if (!Array.isArray(items)) items = [];
    }

    function emit() {
        document.dispatchEvent(new CustomEvent('cart:change', {
            detail: { items: items.slice(), count: count(), total: total() }
        }));
    }

    function save() {
        try { localStorage.setItem(KEY, JSON.stringify(items)); } catch (e) { /* private mode etc. */ }
        emit();
    }

    function keyOf(id, color) { return id + '|' + (color || ''); }

    function count() { return items.reduce((n, i) => n + i.qty, 0); }
    // "Buy X Get Y free" (per product): in every group of X+Y pieces, Y are free
    function freeQty(it) {
        if (!it.bogo) return 0;
        const group = it.bogo.buy + it.bogo.get;
        return Math.floor(it.qty / group) * it.bogo.get;
    }
    function lineTotal(it) { return (it.qty - freeQty(it)) * it.price; }
    function total() { return items.reduce((s, i) => s + lineTotal(i), 0); }

    // item: { id, name, price, image, color, qty }
    function add(item) {
        const color = item.color || '';
        // a package: the products inside it (and their colors) are part of the identity of the line
        const pkg = item.pkg
            ? { items: item.pkg.items.map(i => ({ name: i.name, color: i.color || '' })) }
            : null;
        const sig = pkg ? '#' + pkg.items.map(i => i.name + ':' + i.color).join(';') : '';
        const key = keyOf(item.id, color) + sig;
        const found = items.find(i => i.key === key);
        const qty = Math.max(1, Math.min(99, parseInt(item.qty, 10) || 1));
        const colors = Array.isArray(item.colors)
            ? item.colors.map(c => ({ name: c.name, code: c.code || '', image: c.image || '' }))
            : [];
        if (found) {
            found.qty = Math.min(99, found.qty + qty);
            found.price = Number(item.price);   // keep price fresh
            found.bogo = item.bogo || null;
            found.pkg = pkg;
            if (item.image) found.image = item.image;
            if (colors.length) found.colors = colors;
        } else {
            items.push({
                key, id: item.id, name: item.name, price: Number(item.price),
                image: item.image || '', baseImage: item.baseImage || item.image || '', color, colors, qty,
                bogo: item.bogo ? { buy: item.bogo.buy, get: item.bogo.get } : null,
                pkg
            });
        }
        save();
    }

    function setQty(key, qty) {
        const it = items.find(i => i.key === key);
        if (!it) return;
        qty = parseInt(qty, 10) || 1;
        if (qty < 1) return remove(key);
        it.qty = Math.min(99, qty);
        save();
    }

    function remove(key) {
        items = items.filter(i => i.key !== key);
        save();
    }

    // Change the color of a line (merges with an existing line of the same color)
    function setColor(key, color) {
        const it = items.find(i => i.key === key);
        if (!it || it.pkg) return;
        const newKey = keyOf(it.id, color);
        const twin = items.find(i => i.key === newKey && i !== it);
        if (twin) {
            twin.qty = Math.min(99, twin.qty + it.qty);
            items = items.filter(i => i !== it);
        } else {
            it.color = color;
            it.key = newKey;
            // show the photo of the newly chosen color
            const c = (it.colors || []).find(x => x.name === color);
            it.image = (c && c.image) || it.baseImage || it.image;
        }
        save();
    }

    function clear() { items = []; save(); }

    load();
    window.Cart = { add, setQty, setColor, remove, clear, count, total, lineTotal, freeQty, items: () => items.slice() };
    document.addEventListener('DOMContentLoaded', emit);
})();
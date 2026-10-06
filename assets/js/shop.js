// Lily's Accessories - shop filtering WITHOUT reloading the page (smooth scroll + animated products)
// Reads window.SHOP = { categories:[{id,name,subs:[{id,name}]}], offers:[{id,title}] }

(function () {
    const data = window.SHOP;
    const grid = document.getElementById('shopGrid');
    if (!data || !grid) return;

    const $ = (s, r = document) => r.querySelector(s);
    const section = $('#shop');
    const titleEl = $('#shopTitle');
    const subBox  = $('#shopSubChips');
    const emptyEl = $('#shopEmpty');
    const cards   = Array.from(grid.querySelectorAll('.product'));

    const catById   = new Map(data.categories.map(c => [c.id, c]));
    const offerById = new Map(data.offers.map(o => [o.id, o]));
    const state = { cat: 0, sub: 0, offer: 0 };

    // ----- read the starting state from the URL (?cat=1&sub=2 or ?offer=3) -----
    (function init() {
        const p = new URLSearchParams(location.search);
        const offer = parseInt(p.get('offer'), 10) || 0;
        const cat   = parseInt(p.get('cat'), 10) || 0;
        const sub   = parseInt(p.get('sub'), 10) || 0;
        if (offer && offerById.has(offer)) state.offer = offer;
        else if (cat && catById.has(cat)) {
            state.cat = cat;
            if (sub && catById.get(cat).subs.some(s => s.id === sub)) state.sub = sub;
        }
        paint();
    })();

    // ----- helpers -----
    function urlForState() {
        const p = new URLSearchParams();
        if (state.offer) p.set('offer', state.offer);
        else {
            if (state.cat) p.set('cat', state.cat);
            if (state.sub) p.set('sub', state.sub);
        }
        const q = p.toString();
        return location.pathname + (q ? '?' + q : '') + '#shop';
    }

    function matches(card) {
        if (state.offer) {
            const ids = (card.dataset.offers || '').split(',').filter(Boolean);
            if (!ids.includes(String(state.offer))) return false;
        }
        if (state.cat && card.dataset.cat !== String(state.cat)) return false;
        if (state.sub && card.dataset.sub !== String(state.sub)) return false;
        return true;
    }

    function buildSubChips() {
        subBox.innerHTML = '';
        const cat = state.cat && !state.offer ? catById.get(state.cat) : null;
        if (!cat || !cat.subs.length) { subBox.hidden = true; return; }

        const make = (label, subId) => {
            const a = document.createElement('a');
            a.className = 'chip small' + (state.sub === subId ? ' active' : '');
            a.href = '?cat=' + cat.id + (subId ? '&sub=' + subId : '') + '#shop';
            a.dataset.cat = cat.id;
            a.dataset.sub = subId;
            a.textContent = label;
            return a;
        };
        subBox.appendChild(make('All ' + cat.name, 0));
        cat.subs.forEach(s => subBox.appendChild(make(s.name, s.id)));
        subBox.hidden = false;
    }

    function paint() {
        // title
        let title = 'All pieces';
        if (state.offer) title = offerById.get(state.offer).title;
        else if (state.cat) title = catById.get(state.cat).name;
        titleEl.textContent = title;

        // active states (main chips + category circles)
        document.querySelectorAll('#shopChips [data-cat]').forEach(a => {
            const c = parseInt(a.dataset.cat, 10);
            const on = !state.offer && c === state.cat && !state.sub;
            a.classList.toggle('active', on);
        });
        document.querySelectorAll('.cat-item[data-cat]').forEach(a => {
            a.classList.toggle('active', !state.offer && parseInt(a.dataset.cat, 10) === state.cat);
        });
        document.querySelectorAll('.offer-card[data-offer]').forEach(a => {
            a.classList.toggle('active', parseInt(a.dataset.offer, 10) === state.offer);
        });

        buildSubChips();

        // products (hide / show + staggered pop-in)
        let shown = 0;
        cards.forEach(c => {
            const ok = matches(c);
            c.hidden = !ok;
            c.classList.remove('pop-in');
            if (ok) { c.style.setProperty('--i', Math.min(shown, 10)); shown++; }
        });
        void grid.offsetWidth;                       // restart the animation
        cards.forEach(c => { if (!c.hidden) c.classList.add('pop-in'); });
        emptyEl.hidden = shown > 0;
    }

    function change(next, scroll) {
        Object.assign(state, next);
        try { history.replaceState(null, '', urlForState()); } catch (e) { /* file:// etc. */ }

        if (scroll && section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // soft fade of the grid while the content swaps
        grid.classList.add('is-changing');
        setTimeout(() => {
            paint();
            grid.classList.remove('is-changing');
        }, scroll ? 260 : 120);
    }

    // ----- clicks on categories, chips, sub-chips, offers, feature button -----
    document.addEventListener('click', e => {
        const a = e.target.closest('a[data-cat], a[data-offer]');
        if (!a) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();

        const insideShop = !!a.closest('#shop');
        if (a.dataset.offer) {
            change({ offer: parseInt(a.dataset.offer, 10), cat: 0, sub: 0 }, !insideShop);
        } else {
            change({ cat: parseInt(a.dataset.cat, 10) || 0, sub: parseInt(a.dataset.sub, 10) || 0, offer: 0 }, !insideShop);
        }
    });
})();
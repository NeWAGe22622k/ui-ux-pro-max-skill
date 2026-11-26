// Preview layer: makes the static copy of the site behave like the real one, inside this browser only.
(function () {
  'use strict';
  var P = window.PE_PREVIEW || {};
  var KEY = 'pe-preview-v1';

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY)) || { stock: {}, reviews: {} }; }
    catch (e) { return { stock: {}, reviews: {} }; }
  }
  function store(d) { try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) { /* storage blocked: changes last until reload */ } }
  function sget(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function sset(k, v) { try { if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) { /* ignore */ } }

  var data = load();
  data.stock = data.stock || {};
  data.reviews = data.reviews || {};

  function naira(k) {
    return '₦' + (k / 100).toLocaleString('en-NG', { minimumFractionDigits: k % 100 ? 2 : 0, maximumFractionDigits: 2 });
  }
  function timeLabel(ts) {
    var d = new Date(ts), h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'pm' : 'am';
    h = h % 12 || 12;
    return 'today at ' + h + ':' + (m < 10 ? '0' : '') + m + ap;
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  var toastEl;
  function toast(msg, href, linkText) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'pv-toast';
      toastEl.setAttribute('role', 'status');
      document.body.appendChild(toastEl);
    }
    toastEl.innerHTML = '<span>' + esc(msg) + '</span>' + (href ? ' <a href="' + href + '">' + esc(linkText) + '</a>' : '');
    toastEl.classList.add('is-on');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.classList.remove('is-on'); }, 6000);
  }
  function flashAtTop(html) {
    var main = document.querySelector('.admin-main') || document.querySelector('main');
    var old = main.querySelector('.pv-flash');
    if (old) old.remove();
    var div = document.createElement('div');
    div.className = 'flash flash-success pv-flash';
    div.style.marginBottom = '1rem';
    div.innerHTML = (P.checkIcon || '') + '<span>' + html + '</span>';
    main.insertBefore(div, main.firstChild);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ---------------------------------------------------------------- banner
  var reset = document.querySelector('[data-pv-reset]');
  if (reset) reset.addEventListener('click', function () {
    try { localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
    sset('pe-name', null); sset('pe-q', null);
    location.reload();
  });

  // ---------------------------------------------------------------- staff changes shown on shopper pages
  var branchStock = (P.branch && data.stock[P.branch]) || {};
  var branchReviews = (P.branch && data.reviews[P.branch]) || {};

  if (!P.admin) {
    document.querySelectorAll('[data-pid]').forEach(function (el) {
      var o = branchStock[el.getAttribute('data-pid')];
      if (!o) return;
      if (o.status === 'none') { el.hidden = true; el.setAttribute('data-gone', ''); return; }
      var pill = el.querySelector('.pill');
      if (pill && P.pills[o.status]) pill.outerHTML = P.pills[o.status];
      var price = el.querySelector('.price');
      if (price) price.textContent = o.price > 0 ? naira(o.price) : 'Ask in store';
      var small = el.querySelector('.live-name small');
      if (small) small.textContent = o.price > 0 ? naira(o.price) : 'Ask in store';
      if (el.classList.contains('product-card')) {
        el.classList.toggle('is-out', o.status === 'out_of_stock');
        el.dataset.price = o.price;
        el.dataset.status = o.status;
      }
    });

    var latest = 0;
    Object.keys(branchReviews).forEach(function (cat) {
      var r = branchReviews[cat];
      latest = Math.max(latest, r.ts);
      document.querySelectorAll('#' + cat + ' .fresh-ribbon, #aisle-' + cat + ' .fresh-ribbon').forEach(function (ribbon) {
        ribbon.classList.remove('is-stale');
        ribbon.innerHTML = '<span class="chip-dot"></span> Checked ' + timeLabel(r.ts) + ' by ' + esc(r.name);
      });
    });
    if (latest) {
      var pillSmall = document.querySelector('.branch-pill-text small');
      if (pillSmall) pillSmall.textContent = 'Checked ' + timeLabel(latest);
      var freshTime = document.querySelector('.fresh-line .fresh-time');
      if (freshTime) freshTime.textContent = timeLabel(latest);
    }
  }

  // ---------------------------------------------------------------- shop search, "hide sold out" and sorting
  var shopForm = document.querySelector('form.toolbar');
  var siteQ = document.getElementById('site-q');
  var sortRank = { in_stock: 0, low_stock: 1, out_of_stock: 2 };
  function sortCards(mode) {
    document.querySelectorAll('.cat-section .product-grid').forEach(function (grid) {
      var cards = Array.prototype.slice.call(grid.querySelectorAll('.product-card'));
      cards.sort(function (a, b) {
        var na = a.querySelector('.product-name').textContent, nb = b.querySelector('.product-name').textContent;
        var pa = +a.dataset.price, pb = +b.dataset.price;
        if (mode === 'price_asc') return pa - pb || na.localeCompare(nb);
        if (mode === 'price_desc') return pb - pa || na.localeCompare(nb);
        if (mode === 'name') return na.localeCompare(nb);
        return (sortRank[a.dataset.status] - sortRank[b.dataset.status]) || na.localeCompare(nb);
      });
      cards.forEach(function (c) { grid.appendChild(c); });
    });
  }
  function applyShopFilter() {
    if (!shopForm) return;
    var q = ((siteQ && siteQ.value) || '').trim().toLowerCase();
    var hide = shopForm.querySelector('input[name="hide_out"]').checked;
    sortCards(shopForm.querySelector('select[name="sort"]').value);
    var total = 0;
    document.querySelectorAll('.cat-section').forEach(function (sec) {
      var shown = 0;
      sec.querySelectorAll('.product-card').forEach(function (card) {
        var name = card.querySelector('.product-name').textContent.toLowerCase();
        var ok = !card.hasAttribute('data-gone') && (!q || name.indexOf(q) !== -1) && !(hide && card.classList.contains('is-out'));
        card.hidden = !ok;
        if (ok) shown++;
      });
      sec.hidden = shown === 0;
      total += shown;
    });
    var count = document.querySelector('[data-result-count]');
    if (count) count.textContent = total;
    var h1 = document.querySelector('.page-head h1');
    if (h1 && q) h1.textContent = 'Results for “' + q + '”';
    var meta = document.querySelector('.pv-meta');
    if (!meta) {
      meta = document.createElement('p');
      meta.className = 'results-meta pv-meta';
      shopForm.parentNode.insertBefore(meta, shopForm.nextSibling);
    }
    meta.hidden = total > 0;
    meta.textContent = 'Nothing matches that here. Try a shorter word or another aisle.';
  }
  if (shopForm) {
    var pending = sget('pe-q');
    if (pending && siteQ) { siteQ.value = pending; sset('pe-q', null); applyShopFilter(); }
  }

  // ---------------------------------------------------------------- careers store filter
  if (P.page === 'careers') {
    document.querySelectorAll('.chips a').forEach(function (chip) {
      chip.addEventListener('click', function (e) {
        e.preventDefault();
        var name = chip.textContent.trim();
        document.querySelectorAll('.chips a').forEach(function (c) { c.setAttribute('aria-current', c === chip ? 'true' : 'false'); });
        var open = 0;
        document.querySelectorAll('.job-card').forEach(function (card) {
          var ok = name === 'All stores' || card.textContent.indexOf('Prince Ebeano ' + name) !== -1;
          card.hidden = !ok;
          if (ok && !card.classList.contains('is-closed')) open++;
        });
        var meta = document.querySelector('.results-meta');
        if (meta) meta.textContent = open + (open === 1 ? ' open role' : ' open roles') + (name === 'All stores' ? '' : ' at ' + name);
      });
    });
  }

  // ---------------------------------------------------------------- staff area
  // The viewer can't show confirm() dialogs, so delete buttons go straight to the preview message.
  document.querySelectorAll('[data-confirm]').forEach(function (el) { el.removeAttribute('data-confirm'); });

  var staffName = sget('pe-name') || P.defaultName || 'Chioma';
  if (P.admin && P.role === 'staff') {
    var who = document.querySelector('.admin-who strong');
    if (who) who.textContent = staffName;
    var av = document.querySelector('.admin-who .avatar');
    if (av) av.textContent = staffName.charAt(0).toUpperCase();
  }

  var updateForm = document.querySelector('[data-update-form]');
  var rowState = function (row) {
    var checked = row.querySelector('input[type="radio"]:checked');
    var price = row.querySelector('input[data-price]');
    return (checked ? checked.value : '') + '|' + (price ? price.value.trim() : '');
  };
  if (updateForm) {
    var mine = data.stock[P.branch] || {};
    updateForm.querySelectorAll('[data-row]').forEach(function (row) {
      var radio = row.querySelector('input[type="radio"]');
      var pid = radio.name.replace(/\D/g, '');
      var o = mine[pid];
      if (!o) return;
      var target = row.querySelector('input[type="radio"][value="' + o.status + '"]');
      if (target) target.checked = true;
      var price = row.querySelector('input[data-price]');
      if (price && o.price > 0) price.value = (o.price / 100).toLocaleString('en-NG');
      var small = row.querySelector('.row-name small');
      if (small) small.textContent = 'Changed ' + timeLabel(o.ts) + ' by ' + o.name;
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'dialog') return;
    e.preventDefault();

    if (form === shopForm) { applyShopFilter(); return; }

    if (form.classList.contains('search-bar') || form.classList.contains('header-search')) {
      var q = form.querySelector('input[name="q"]').value.trim();
      if (q) sset('pe-q', q);
      location.href = form.getAttribute('action');
      return;
    }

    if (form === updateForm) {
      var changed = 0, now = Date.now();
      data.stock[P.branch] = data.stock[P.branch] || {};
      form.querySelectorAll('[data-row]').forEach(function (row) {
        if (rowState(row) === row.dataset.initial) return;
        var radio = row.querySelector('input[type="radio"]:checked');
        var pid = radio.name.replace(/\D/g, '');
        var priceIn = row.querySelector('input[data-price]');
        var kobo = Math.round(parseFloat((priceIn.value || '').replace(/[^0-9.]/g, '')) * 100) || 0;
        data.stock[P.branch][pid] = { status: radio.value, price: kobo, ts: now, name: staffName };
        row.dataset.initial = rowState(row);
        var small = row.querySelector('.row-name small');
        if (small) small.textContent = 'Changed just now by ' + staffName;
        changed++;
      });
      data.reviews[P.branch] = data.reviews[P.branch] || {};
      data.reviews[P.branch][P.cat] = { ts: now, name: staffName };
      store(data);
      form.dispatchEvent(new Event('change'));
      flashAtTop((changed ? 'Saved ' + changed + (changed === 1 ? ' change. ' : ' changes. ') : 'No changes needed. ')
        + esc(P.catName) + ' at ' + esc(P.branchName) + ' is marked as checked ' + timeLabel(now) + '. '
        + '<a href="' + P.shopLink + '">See it on the shop page →</a>');
      return;
    }

    if (P.page === 'login') {
      var user = (form.querySelector('[name="username"]').value || '').trim().toLowerCase();
      var nm = (form.querySelector('[name="staff_name"]').value || '').trim();
      if (user === 'owner') { location.href = 'admin-owner.html'; return; }
      if (!nm) { toast('Please type your name so we know who made today\'s update.'); return; }
      sset('pe-name', nm);
      location.href = 'admin-staff.html';
      return;
    }

    if (P.page === 'contact') {
      var name = form.querySelector('[name="name"]').value.trim();
      var msg = form.querySelector('[name="message"]').value.trim();
      if (!name || msg.length < 5) { toast('Please add your name and a short message.'); return; }
      form.closest('.form-card').innerHTML = '<div class="flash flash-success">' + (P.checkIcon || '')
        + '<span>Thank you, ' + esc(name) + '! Your message has been sent. On the live site it lands in the owner\'s inbox (Staff area → Messages).</span></div>';
      return;
    }

    if ((form.getAttribute('action') || '').indexOf('admin-login') !== -1) { location.href = 'admin-login.html'; return; }

    if (form.classList.contains('scope-bar') && P.page === 'morning') {
      form.reset();
      toast('This preview shows Ikeja · Drinks. On the live site the owner can switch to any store and aisle.');
      return;
    }
    if (P.page === 'product') {
      toast('Preview only: on the live site the product is saved and shows in the shop straight away.');
      return;
    }
    toast('Preview only: on the live site this saves straight away.');
  }, true);
})();

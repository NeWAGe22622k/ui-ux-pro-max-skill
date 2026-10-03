/* Refugehomes Ltd - site behaviour. No dependencies. */
(function () {
  'use strict';

  var base = document.body.getAttribute('data-base') || '';

  /* ---------- Header + mobile nav ---------- */
  var header = document.querySelector('[data-header]');
  var onScroll = function () { header && header.classList.toggle('is-scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.querySelector('[data-nav]');
  function setNav(open) {
    if (!toggle || !nav) return;
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
  }
  if (toggle) {
    toggle.addEventListener('click', function () { setNav(toggle.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { setNav(false); toggle.focus(); }
    });
    window.matchMedia('(min-width: 961px)').addEventListener('change', function (m) { if (m.matches) setNav(false); });
  }

  /* ---------- Before / after slider ---------- */
  function initCompare(el) {
    var range = el.querySelector('[data-compare-range]');
    if (!range || range._ready) return;
    range._ready = true;
    var update = function () { el.style.setProperty('--pos', range.value + '%'); };
    range.addEventListener('input', update);
    update();
  }
  document.querySelectorAll('[data-compare-inline]').forEach(initCompare);

  /* ---------- Rentals filter ---------- */
  var filter = document.querySelector('[data-filter]');
  var filterTarget = document.querySelector('[data-filter-target]');
  if (filter && filterTarget) {
    filter.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-filter-value]');
      if (!btn) return;
      var value = btn.getAttribute('data-filter-value');
      filter.querySelectorAll('[data-filter-value]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      filterTarget.querySelectorAll('[data-status]').forEach(function (card) {
        card.hidden = value !== 'all' && card.getAttribute('data-status') !== value;
      });
    });
  }

  /* ---------- Contact form ---------- */
  var focusMe = document.querySelector('[data-autofocus]');
  if (focusMe) focusMe.focus();
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('[data-submit]');
      if (btn) { btn.disabled = true; btn.firstChild.textContent = 'Sending… '; }
    });
  });

  /* ---------- Listing modal (gallery + details) ---------- */
  var modal = document.querySelector('[data-modal]');
  if (!modal) return;

  var data = {};
  document.querySelectorAll('script[data-collection]').forEach(function (s) {
    try { data[s.getAttribute('data-collection')] = JSON.parse(s.textContent); } catch (err) { /* ignore bad data */ }
  });

  var stageImg = modal.querySelector('[data-stage-img]');
  var compare = modal.querySelector('[data-compare]');
  var cmpBefore = modal.querySelector('[data-compare-before]');
  var cmpAfter = modal.querySelector('[data-compare-after]');
  var cmpRange = modal.querySelector('[data-compare-range]');
  var thumbs = modal.querySelector('[data-thumbs]');
  var tabs = modal.querySelector('[data-modal-tabs]');
  var counter = modal.querySelector('[data-counter]');
  var prev = modal.querySelector('[data-prev]');
  var next = modal.querySelector('[data-next]');
  var details = modal.querySelector('[data-details]');
  initCompare(compare);

  var state = { item: null, mode: 'images', list: [], index: 0, trigger: null };

  var I = {
    pin: '<svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>',
    bed: '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>',
    bath: '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="2" x2="22" y1="12" y2="12"/><line x1="7" x2="7" y1="19" y2="21"/><line x1="17" x2="17" y1="19" y2="21"/></svg>',
    home: '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
    check: '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    external: '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
    arrow: '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function paragraphs(text) {
    return String(text || '').split(/\n\s*\n/).filter(Boolean).map(function (p) {
      return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>';
    }).join('');
  }
  function safeUrl(u) { return /^https?:\/\//i.test(u || '') ? u : null; }

  function metaHtml(it) {
    var bits = [];
    if (it.bedrooms) bits.push(I.bed + ' ' + esc(it.bedrooms) + ' bed');
    if (it.bathrooms) bits.push(I.bath + ' ' + esc(it.bathrooms) + ' bath');
    if (it.type) bits.push(I.home + ' ' + esc(it.type));
    return bits.length ? '<ul class="card-meta"><li>' + bits.join('</li><li>') + '</li></ul>' : '';
  }

  function renderDetails(it, collection) {
    var h = '';
    if (collection === 'rentals') {
      var let_ = it.status === 'let_agreed';
      h += '<span class="badge modal-status ' + (let_ ? 'badge-let' : 'badge-available') + '">' + (let_ ? 'Let agreed' : 'Available') + '</span>';
    }
    if (it.sample) h += '<span class="badge badge-sample modal-sample">Sample listing</span>';
    if (collection === 'rentals' && it.price) h += '<p class="card-price">' + esc(it.price) + '</p>';
    h += '<h2 id="modal-title">' + esc(it.title) + '</h2>';
    if (it.location) h += '<p class="card-location">' + I.pin + ' ' + esc(it.location) + '</p>';
    h += metaHtml(it);

    var facts = [];
    if (collection === 'rentals') {
      if (it.available) facts.push(['Available', it.available]);
      if (it.furnishing) facts.push(['Furnishing', it.furnishing]);
      if (it.deposit) facts.push(['Deposit', it.deposit]);
    }
    if (collection === 'flips' && it.completed) facts.push(['Completed', it.completed]);
    if (facts.length) {
      h += '<dl class="facts">' + facts.map(function (f) { return '<div><dt>' + esc(f[0]) + '</dt><dd>' + esc(f[1]) + '</dd></div>'; }).join('') + '</dl>';
    }

    h += '<div class="desc">' + paragraphs(it.description || it.summary) + '</div>';

    var list = collection === 'rentals' ? it.features : collection === 'flips' ? it.works : null;
    if (list && list.length) {
      h += '<h3>' + (collection === 'flips' ? 'Work carried out' : 'Key features') + '</h3>';
      h += '<ul class="check-list">' + list.map(function (f) { return '<li>' + I.check + ' ' + esc(f) + '</li>'; }).join('') + '</ul>';
    }

    if (collection === 'rentals') {
      var links = (it.links || []).filter(function (l) { return safeUrl(l.url); });
      if (links.length && it.status !== 'let_agreed') {
        h += '<h3>Also advertised on</h3><div class="listing-links">' + links.map(function (l) {
          return '<a href="' + esc(l.url) + '" target="_blank" rel="noopener">' + esc(l.label || 'View listing') + ' <span class="sr-only">(opens in a new tab)</span>' + I.external + '</a>';
        }).join('') + '</div>';
      }
      var q = 'contact?type=renting&property=' + encodeURIComponent(it.title + (it.location ? ', ' + it.location : ''));
      h += '<div class="modal-actions"><a class="btn btn-primary" href="' + esc(base + '/' + q) + '">' + (it.status === 'let_agreed' ? 'Ask about similar homes ' : 'Enquire about this home ') + I.arrow + '</a></div>';
    } else {
      h += '<div class="modal-actions"><a class="btn btn-secondary" href="' + esc(base + '/contact?type=' + (collection === 'flips' ? 'selling' : 'landlord')) + '">' +
        (collection === 'flips' ? 'Selling a property? Talk to us ' : 'Talk to us about management ') + I.arrow + '</a></div>';
    }
    details.innerHTML = h;
  }

  function setMode(mode) {
    var it = state.item;
    state.mode = mode;
    if (mode === 'compare') {
      var pairs = Math.min(it.before.length, it.after.length);
      state.list = it.after.slice(0, pairs).map(function (src, i) { return { before: it.before[i], after: src }; });
    } else {
      state.list = (it[mode] || []).map(function (src) { return { src: src }; });
    }
    tabs.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-mode') === mode)); });
    renderThumbs();
    show(0);
  }

  function renderThumbs() {
    thumbs.innerHTML = '';
    thumbs.hidden = state.list.length < 2;
    state.list.forEach(function (entry, i) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('aria-label', 'Show photo ' + (i + 1));
      var im = document.createElement('img');
      im.src = entry.src || entry.after;
      im.alt = '';
      im.loading = 'lazy';
      b.appendChild(im);
      b.addEventListener('click', function () { show(i); });
      li.appendChild(b);
      thumbs.appendChild(li);
    });
  }

  function show(i) {
    var n = state.list.length;
    if (!n) { stageImg.hidden = true; compare.hidden = true; counter.textContent = 'No photos yet'; prev.hidden = next.hidden = true; return; }
    state.index = (i + n) % n;
    var entry = state.list[state.index];
    var label = state.item.title + ', photo ' + (state.index + 1) + ' of ' + n;
    if (state.mode === 'compare') {
      stageImg.hidden = true;
      compare.hidden = false;
      cmpBefore.src = entry.before; cmpBefore.alt = state.item.title + ', before';
      cmpAfter.src = entry.after; cmpAfter.alt = state.item.title + ', after';
      cmpRange.value = 50; compare.style.setProperty('--pos', '50%');
    } else {
      compare.hidden = true;
      stageImg.hidden = false;
      stageImg.src = entry.src;
      stageImg.alt = (state.mode === 'before' ? 'Before: ' : state.mode === 'after' ? 'After: ' : '') + label;
    }
    counter.textContent = (state.index + 1) + ' / ' + n;
    prev.hidden = next.hidden = n < 2;
    thumbs.querySelectorAll('button').forEach(function (b, k) {
      b.setAttribute('aria-current', String(k === state.index));
      if (k === state.index && b.scrollIntoView) b.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
    // Warm the cache for the next photo.
    var nxt = state.list[(state.index + 1) % n];
    if (nxt) { var pre = new Image(); pre.src = nxt.src || nxt.after; }
  }

  function open(collection, id, trigger) {
    var it = (data[collection] || []).filter(function (x) { return x.id === id; })[0];
    if (!it) return;
    state.item = it;
    state.trigger = trigger || null;
    renderDetails(it, collection);

    tabs.innerHTML = '';
    if (collection === 'flips') {
      var modes = [];
      if (it.before.length && it.after.length) modes.push(['compare', 'Compare']);
      if (it.before.length) modes.push(['before', 'Before (' + it.before.length + ')']);
      if (it.after.length) modes.push(['after', 'After (' + it.after.length + ')']);
      modes.forEach(function (m) {
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('data-mode', m[0]);
        b.textContent = m[1];
        b.addEventListener('click', function () { setMode(m[0]); });
        tabs.appendChild(b);
      });
      tabs.hidden = modes.length < 2;
      setMode(modes.length ? modes[0][0] : 'after');
    } else {
      tabs.hidden = true;
      setMode('images');
    }

    if (!modal.open) {
      modal.showModal();
      document.documentElement.style.overflow = 'hidden';
    }
    modal.querySelector('[data-modal-close]').focus();
    if (history.replaceState) history.replaceState(null, '', '#' + id);
  }

  function close() { if (modal.open) modal.close(); }

  modal.addEventListener('close', function () {
    document.documentElement.style.overflow = '';
    if (history.replaceState) history.replaceState(null, '', location.pathname + location.search);
    if (state.trigger && document.contains(state.trigger)) state.trigger.focus();
  });
  modal.querySelector('[data-modal-close]').addEventListener('click', close);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  prev.addEventListener('click', function () { show(state.index - 1); });
  next.addEventListener('click', function () { show(state.index + 1); });
  modal.addEventListener('keydown', function (e) {
    if (e.target && (e.target.type === 'range' || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName))) return;
    if (e.key === 'ArrowLeft') { show(state.index - 1); e.preventDefault(); }
    if (e.key === 'ArrowRight') { show(state.index + 1); e.preventDefault(); }
  });

  // Swipe between photos on touch screens (not while dragging the compare slider).
  var stage = modal.querySelector('[data-stage]');
  var startX = null;
  stage.addEventListener('touchstart', function (e) { startX = state.mode === 'compare' ? null : e.touches[0].clientX; }, { passive: true });
  stage.addEventListener('touchend', function (e) {
    if (startX === null) return;
    var dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) show(state.index + (dx < 0 ? 1 : -1));
    startX = null;
  });

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open]');
    if (!t) return;
    open(t.getAttribute('data-collection'), t.getAttribute('data-open'), t);
  });

  // Deep links: /rentals#sample-rental-1 opens that listing directly.
  var hash = decodeURIComponent(location.hash.slice(1));
  if (hash) {
    Object.keys(data).forEach(function (c) {
      if ((data[c] || []).some(function (x) { return x.id === hash; })) {
        var trigger = document.querySelector('[data-open="' + CSS.escape(hash) + '"]');
        open(c, hash, trigger);
      }
    });
  }
})();

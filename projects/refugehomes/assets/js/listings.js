/* Refugehomes — property & rental listings: filters, galleries, before/after, rental detail */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function payloadOf(el) {
    var host = el.closest('[data-payload]');
    try { return host ? JSON.parse(host.getAttribute('data-payload')) : null; } catch (e) { return null; }
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Reusable gallery (stage + thumbs + arrows + swipe + keys) ---------- */
  function Gallery(root) {
    this.root = root;
    this.img = $('[data-g-img]', root);
    this.prev = $('[data-g-prev]', root);
    this.next = $('[data-g-next]', root);
    this.count = $('[data-g-count]', root);
    this.thumbs = $('[data-g-thumbs]', root);
    this.images = [];
    this.index = 0;
    this.label = '';
    var self = this;
    this.prev.addEventListener('click', function () { self.go(self.index - 1); });
    this.next.addEventListener('click', function () { self.go(self.index + 1); });
    this.thumbs.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-i]');
      if (b) self.go(parseInt(b.getAttribute('data-i'), 10));
    });
    // swipe
    var stage = this.img.parentNode, x0 = null;
    stage.addEventListener('pointerdown', function (e) { x0 = e.clientX; });
    stage.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) self.go(self.index + (dx < 0 ? 1 : -1));
    });
  }
  Gallery.prototype.set = function (images, label) {
    this.images = images || [];
    this.label = label || '';
    this.thumbs.innerHTML = this.images.map(function (src, i) {
      return '<button type="button" data-i="' + i + '" aria-label="Show photo ' + (i + 1) + '"><img src="' + esc(src) + '" alt="" loading="lazy"></button>';
    }).join('');
    var multi = this.images.length > 1;
    this.prev.hidden = !multi; this.next.hidden = !multi; this.thumbs.hidden = !multi;
    this.go(0);
  };
  Gallery.prototype.go = function (i) {
    var n = this.images.length;
    if (!n) { this.img.removeAttribute('src'); this.count.textContent = ''; return; }
    this.index = (i + n) % n;
    var img = this.img, src = this.images[this.index];
    img.classList.add('is-loading');
    img.onload = function () { img.classList.remove('is-loading'); };
    img.src = src;
    img.alt = (this.label ? this.label + ' – ' : '') + 'photo ' + (this.index + 1) + ' of ' + n;
    this.count.textContent = (this.index + 1) + ' / ' + n;
    var cur = this.index;
    $$('button', this.thumbs).forEach(function (b, k) {
      b.setAttribute('aria-current', k === cur ? 'true' : 'false');
      if (k === cur && b.scrollIntoView) b.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
    // preload neighbour
    if (n > 1) { var pre = new Image(); pre.src = this.images[(this.index + 1) % n]; }
  };

  /* ---------- Dialog helpers ---------- */
  var lastFocus = null;
  function openDialog(d) {
    lastFocus = document.activeElement;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    document.body.style.overflow = 'hidden';
  }
  function wireDialog(d, onClose) {
    if (!d) return;
    $$('[data-close]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); // backdrop
    d.addEventListener('close', function () {
      document.body.style.overflow = '';
      if (onClose) onClose();
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    });
  }
  function keyNav(d, gallery) {
    d.addEventListener('keydown', function (e) {
      if (e.target.closest('input, textarea, select')) return;
      if (e.key === 'ArrowRight') { gallery.go(gallery.index + 1); e.preventDefault(); }
      if (e.key === 'ArrowLeft') { gallery.go(gallery.index - 1); e.preventDefault(); }
    });
  }

  /* ---------- Property gallery modal ---------- */
  var gModal = $('#gallery-modal');
  if (gModal) {
    var gGallery = new Gallery($('[data-gallery]', gModal));
    var tabs = $('[data-g-tabs]', gModal);
    var current = null;
    var showSet = function (set) {
      $$('button', tabs).forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-set') === set ? 'true' : 'false'); });
      gGallery.set(current[set], current.title + ' (' + set + ')');
    };
    tabs.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-set]');
      if (b) showSet(b.getAttribute('data-set'));
    });
    wireDialog(gModal);
    keyNav(gModal, gGallery);

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-open-gallery]');
      if (!trigger) return;
      var p = payloadOf(trigger);
      if (!p) return;
      current = p;
      $('[data-g-title]', gModal).textContent = p.title;
      $('[data-g-location]', gModal).textContent = p.location;
      $('[data-g-summary]', gModal).textContent = p.summary || '';
      var isFlip = p.type === 'flip' && p.before.length && p.after.length;
      tabs.hidden = !isFlip;
      if (isFlip) showSet('after'); else gGallery.set(p.images.length ? p.images : p.after.concat(p.before), p.title);
      openDialog(gModal);
    });
  }

  /* ---------- Rental detail modal ---------- */
  var rModal = $('#rental-modal');
  if (rModal) {
    var rGallery = new Gallery($('[data-gallery]', rModal));
    wireDialog(rModal, function () {
      if (location.hash.indexOf('#home-') === 0) history.replaceState(null, '', location.pathname + location.search);
    });
    keyNav(rModal, rGallery);

    var openRental = function (r) {
      $('[data-r-title]', rModal).textContent = r.title;
      $('[data-r-location]', rModal).textContent = r.location;
      $('[data-r-price]', rModal).innerHTML = esc(r.price) + ' <span>pcm</span>';
      var st = $('[data-r-status]', rModal);
      st.textContent = r.status === 'let' ? 'Let agreed' : 'Available';
      st.className = 'status ' + (r.status === 'let' ? 'status-let' : 'status-available');

      var spec = [
        ['Bedrooms', r.bedrooms || ''], ['Bathrooms', r.bathrooms || ''],
        ['Property type', r.property_type], ['Furnishing', r.furnished],
        ['Available', r.available_from], ['Deposit', r.deposit]
      ].filter(function (s) { return s[1] !== '' && s[1] != null; });
      $('[data-r-spec]', rModal).innerHTML = spec.map(function (s) {
        return '<div><dt>' + esc(s[0]) + '</dt><dd>' + esc(s[1]) + '</dd></div>';
      }).join('');

      $('[data-r-desc]', rModal).innerHTML = String(r.description || '').split(/\n{2,}/).filter(Boolean).map(function (p) {
        return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>';
      }).join('');

      var fw = $('[data-r-features-wrap]', rModal);
      fw.hidden = !r.features.length;
      $('[data-r-features]', rModal).innerHTML = r.features.map(function (f) {
        return '<li><svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>' + esc(f) + '</li>';
      }).join('');

      var lw = $('[data-r-links-wrap]', rModal);
      lw.hidden = !r.links.length;
      $('[data-r-links]', rModal).innerHTML = r.links.map(function (l) {
        return '<a href="' + esc(l.url) + '" target="_blank" rel="noopener">' + esc(l.platform || 'View listing') +
          ' <svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>' +
          '<span class="sr-only"> (opens in a new tab)</span></a>';
      }).join('');

      var enq = $('[data-r-enquire]', rModal);
      enq.href = 'contact.php?enquiry=' + encodeURIComponent(r.title + ' – ' + r.location);
      enq.hidden = r.status === 'let';

      rGallery.set(r.images, r.title);
      openDialog(rModal);
      if (r.id) history.replaceState(null, '', '#home-' + r.id);
    };

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-open-rental]');
      if (!trigger) return;
      var r = payloadOf(trigger);
      if (r) openRental(r);
    });

    // Deep link: rentals.php#home-<id> opens that listing
    if (location.hash.indexOf('#home-') === 0) {
      var card = document.getElementById(location.hash.slice(1));
      if (card) { var r = payloadOf(card); if (r) openRental(r); }
    }
  }

  /* ---------- Before / after compare slider ---------- */
  $$('[data-compare]').forEach(function (c) {
    var range = $('.compare-range', c);
    var set = function (v) { c.style.setProperty('--pos', v + '%'); };
    range.addEventListener('input', function () { set(range.value); });
    set(range.value);
  });

  /* ---------- Filter tabs (properties page) ---------- */
  var filters = $('[data-filters]');
  if (filters) {
    var cards = $$('[data-type]', document.querySelector('[data-filter-grid]'));
    var apply = function (type) {
      $$('button[data-filter]', filters).forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-filter') === type ? 'true' : 'false'); });
      cards.forEach(function (c) {
        var show = type === 'all' || c.getAttribute('data-type') === type;
        c.hidden = !show;
        if (show) {
          c.classList.add('is-in');
          var m = c.querySelector('.mr');
          if (m) m.classList.add('mr-in');
        }
      });
      var empty = $('[data-filter-empty]');
      if (empty) empty.hidden = cards.some(function (c) { return !c.hidden; });
    };
    filters.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-filter]');
      if (!b) return;
      var t = b.getAttribute('data-filter');
      apply(t);
      history.replaceState(null, '', t === 'all' ? location.pathname : '?show=' + t);
    });
    var initial = new URLSearchParams(location.search).get('show');
    if (initial === 'managed' || initial === 'flip') apply(initial);
  }
})();

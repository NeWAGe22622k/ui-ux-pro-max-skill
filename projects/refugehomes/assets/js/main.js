/* Refugehomes — header, mobile menu, reveal-on-scroll */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  // Header border once the page scrolls
  var header = document.querySelector('[data-header]');
  if (header) {
    var scrolled = null;
    var onScroll = function () {
      var now = window.scrollY > 8;
      if (now !== scrolled) { scrolled = now; header.classList.toggle('is-scrolled', now); }
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Mobile menu
  var toggle = document.querySelector('[data-nav-toggle]');
  if (toggle) {
    var setOpen = function (open) {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setOpen(false); toggle.focus(); }
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 960) setOpen(false); });
  }

  /* ---------- Motion on scroll ---------- */
  var each = function (sel, fn) { Array.prototype.forEach.call(document.querySelectorAll(sel), fn); };
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!('IntersectionObserver' in window) || reduce) {
    each('.reveal', function (el) { el.classList.add('is-in'); });
    return;
  }

  // Delay for an element: data-delay="ms", else a small stagger among siblings
  var delayOf = function (el) {
    if (el.hasAttribute('data-delay')) return parseInt(el.getAttribute('data-delay'), 10) || 0;
    var idx = Array.prototype.indexOf.call(el.parentNode.children, el);
    return Math.min(idx, 4) * 90;
  };
  each('.reveal', function (el) { el.style.transitionDelay = delayOf(el) + 'ms'; });
  var baseDelay = function (el) {
    var r = el.closest('.reveal');
    return r ? (parseInt(r.style.transitionDelay, 10) || 0) : 0;
  };

  // Headings: wrap each word so it can rise into place
  var splitWords = function (el) {
    var label = (el.innerText || el.textContent).replace(/\s+/g, ' ').trim();
    var start = baseDelay(el) + 80, i = 0;
    (function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            var w = document.createElement('span'), inner = document.createElement('span');
            w.className = 'w';
            inner.textContent = part;
            inner.style.transitionDelay = (start + Math.min(i++, 14) * 55) + 'ms';
            w.appendChild(inner);
            frag.appendChild(w);
          });
          node.replaceChild(frag, n);
        } else if (n.nodeType === 1 && n.tagName !== 'BR') {
          walk(n);
        }
      });
    })(el);
    if (/^H[1-6]$/.test(el.tagName)) {
      // screen readers read the heading as one phrase, not word by word
      el.setAttribute('aria-label', label);
      Array.prototype.forEach.call(el.querySelectorAll('.w'), function (w) { w.setAttribute('aria-hidden', 'true'); });
    }
    el.setAttribute('data-split', '');
  };
  each('.display, .h1, .h2, .mission blockquote', function (el) {
    if (!el.closest('.modal')) splitWords(el);
  });

  // Photos: unveil upwards
  var media = [];
  each('.hero-media, .banner, .feature-media, .card-media', function (el) {
    el.classList.add('mr');
    el.style.setProperty('--mr-delay', baseDelay(el) + 'ms');
    media.push(el);
  });

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var el = entry.target;
      io.unobserve(el);
      if (el.__mr) {
        el.__mr.forEach(function (m) {
          m.classList.add('mr-in', 'is-in');
          // hand back to normal styles once done (keeps hover zoom on cards working)
          setTimeout(function () { m.classList.remove('mr', 'mr-in'); }, (parseInt(m.style.getPropertyValue('--mr-delay'), 10) || 0) + 1900);
        });
      }
      if (el.classList.contains('reveal') || el.hasAttribute('data-split')) el.classList.add('is-in');
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  each('.reveal, [data-split]', function (el) { io.observe(el); });
  // A fully clipped photo doesn't count as "on screen", so watch its parent instead
  media.forEach(function (el) {
    var t = el.parentElement;
    (t.__mr = t.__mr || []).push(el);
    io.observe(t);
  });

  /* ---------- Gentle parallax on large photos (desktop only) ---------- */
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    var layers = [];
    each('.hero-media img, .banner img, .feature-media img', function (img) {
      img.style.scale = '1.12';
      layers.push(img);
    });
    var ticking = false;
    var update = function () {
      ticking = false;
      var vh = window.innerHeight;
      layers.forEach(function (img) {
        var r = img.parentNode.getBoundingClientRect();
        if (r.bottom < 0 || r.top > vh) return;
        var p = (r.top + r.height / 2 - vh / 2) / (vh / 2 + r.height / 2); // -1 … 1 as it passes
        p = Math.max(-1, Math.min(1, p));
        img.style.translate = '0 ' + (-p * 5).toFixed(2) + '%'; // drifts slower than the page
      });
    };
    if (layers.length) {
      window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
      }, { passive: true });
      window.addEventListener('resize', update);
      update();
    }
  }
})();

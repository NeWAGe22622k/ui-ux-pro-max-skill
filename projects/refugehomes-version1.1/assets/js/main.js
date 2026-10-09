/* Refugehomes — header, mobile menu, reveal-on-scroll */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  // Header border once the page scrolls
  var header = document.querySelector('[data-header]');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Mobile menu: side drawer
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('primary-nav');
  if (toggle && nav) {
    var isOpen = function () { return document.body.classList.contains('nav-open'); };
    var setOpen = function (open, returnFocus) {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      if (open) {
        var first = nav.querySelector('a');
        if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 60);
      } else if (returnFocus) {
        toggle.focus();
      }
    };
    toggle.addEventListener('click', function () { setOpen(!isOpen()); });
    // tap the dimmed page to close
    Array.prototype.forEach.call(document.querySelectorAll('[data-nav-close]'), function (el) {
      el.addEventListener('click', function () { setOpen(false); });
    });
    // close after choosing a link (matters for links to a section on the same page)
    nav.addEventListener('click', function (e) { if (e.target.closest('a') && isOpen()) setOpen(false); });
    // swipe the drawer to the right to close it
    var x0 = null, y0 = 0;
    nav.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    nav.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      x0 = null;
      if (dx > 60 && Math.abs(dy) < 60) setOpen(false);
    }, { passive: true });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) setOpen(false, true);
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 960 && isOpen()) setOpen(false); });
  }

  // Reveal on scroll
  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el, i) {
      // small stagger for siblings in the same grid
      var idx = Array.prototype.indexOf.call(el.parentNode.children, el);
      el.style.transitionDelay = Math.min(idx, 4) * 80 + 'ms';
      io.observe(el);
    });
  } else {
    items.forEach(function (el) { el.classList.add('is-in'); });
  }
})();

// Prince Ebeano — small progressive enhancements. Every page works without this file.
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.addEventListener('DOMContentLoaded', function () {
    // Header shadow once the page scrolls.
    const header = document.querySelector('[data-header]');
    if (header) {
      const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }

    // Mobile menu.
    const toggle = document.querySelector('[data-nav-toggle]');
    const mobileNav = document.getElementById('mobile-nav');
    if (toggle && mobileNav) {
      toggle.addEventListener('click', function () {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        mobileNav.hidden = open;
      });
    }

    // Branch picker sheet.
    const sheet = document.getElementById('branch-sheet');
    if (sheet && typeof sheet.showModal === 'function') {
      document.querySelectorAll('[data-open-branch]').forEach(function (btn) {
        btn.addEventListener('click', function () { sheet.showModal(); });
      });
      sheet.addEventListener('click', function (e) {
        if (e.target === sheet) sheet.close(); // click on the backdrop
      });
      if (sheet.hasAttribute('data-auto-open')) sheet.showModal();
    }

    // Reveal-on-scroll.
    const revealables = document.querySelectorAll('[data-reveal]');
    if (!reduceMotion && 'IntersectionObserver' in window) {
      const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -40px 0px' });
      revealables.forEach(function (el, i) {
        el.style.transitionDelay = Math.min(i % 8, 6) * 40 + 'ms';
        io.observe(el);
      });
    } else {
      revealables.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // Shop filters: submit instantly when "hide out of stock" changes.
    document.querySelectorAll('[data-autosubmit]').forEach(function (input) {
      input.addEventListener('change', function () { if (input.form.requestSubmit) input.form.requestSubmit(); else input.form.submit(); });
    });

    // Photo preview before upload.
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
      const target = document.getElementById(input.dataset.preview);
      input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file || !target) return;
        const img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(file);
        target.replaceChildren(img);
      });
    });

    // Confirm destructive actions.
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!window.confirm(el.dataset.confirm)) e.preventDefault();
      });
    });

    // Morning update: quick filter by product name.
    const rowFilter = document.querySelector('[data-row-filter]');
    if (rowFilter) {
      rowFilter.addEventListener('input', function () {
        const term = rowFilter.value.trim().toLowerCase();
        document.querySelectorAll('[data-row]').forEach(function (row) {
          row.hidden = term !== '' && row.dataset.name.indexOf(term) === -1;
        });
      });
    }

    // Morning update: highlight changed rows, count them, warn before leaving unsaved.
    const updateForm = document.querySelector('[data-update-form]');
    if (updateForm) {
      const rows = updateForm.querySelectorAll('[data-row]');
      const counter = updateForm.querySelector('[data-changed-count]');
      let dirty = false;

      const rowState = function (row) {
        const checked = row.querySelector('input[type="radio"]:checked');
        const price = row.querySelector('input[data-price]');
        return (checked ? checked.value : '') + '|' + (price ? price.value.trim() : '');
      };
      rows.forEach(function (row) { row.dataset.initial = rowState(row); });

      const refresh = function () {
        let changed = 0;
        rows.forEach(function (row) {
          const status = row.querySelector('input[type="radio"]:checked');
          const price = row.querySelector('input[data-price]');
          if (price) price.disabled = status && status.value === 'none';
          const isChanged = rowState(row) !== row.dataset.initial;
          row.classList.toggle('is-changed', isChanged);
          if (isChanged) changed++;
        });
        dirty = changed > 0;
        if (counter) counter.textContent = changed === 0 ? 'No changes yet' : changed + (changed === 1 ? ' product changed' : ' products changed');
      };
      updateForm.addEventListener('change', refresh);
      updateForm.addEventListener('input', refresh);
      refresh();

      window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
      });
      updateForm.addEventListener('submit', function () { dirty = false; });
    }
  });
})();

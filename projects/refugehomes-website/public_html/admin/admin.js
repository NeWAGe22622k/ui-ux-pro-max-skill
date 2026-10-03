/* Refugehomes admin: photo ordering, upload previews, link rows, confirmations. */
(function () {
  'use strict';

  // Confirm before destructive actions.
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Prevent double submits and show progress while photos upload.
  var dirty = false;
  document.querySelectorAll('[data-edit-form]').forEach(function (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function (e) {
      dirty = false;
      var clicked = e.submitter;
      form.querySelectorAll('[data-submit]').forEach(function (b) { b.classList.add('is-busy'); });
      if (clicked) clicked.textContent = 'Saving…';
      // Keep the clicked button's name/value (e.g. save_and_close) by disabling only after submit starts.
      setTimeout(function () { form.querySelectorAll('[data-submit]').forEach(function (b) { b.disabled = true; }); }, 0);
    });
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- Photo galleries ---------- */
  document.querySelectorAll('[data-gallery]').forEach(function (g) {
    var list = g.querySelector('[data-gallery-list]');
    var input = g.querySelector('[data-gallery-input]');
    var pending = g.querySelector('[data-gallery-pending]');
    var zone = g.querySelector('[data-dropzone]');
    var count = g.querySelector('[data-gallery-count]');

    function updateCount() {
      var n = list.children.length;
      var p = input.files ? input.files.length : 0;
      count.textContent = '(' + n + (p ? ' + ' + p + ' new' : '') + ')';
    }

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      var item = btn.closest('.gallery-item');
      if (btn.hasAttribute('data-remove')) {
        item.remove();
      } else if (btn.hasAttribute('data-move')) {
        if (btn.getAttribute('data-move') === '-1' && item.previousElementSibling) {
          list.insertBefore(item, item.previousElementSibling);
        } else if (btn.getAttribute('data-move') === '1' && item.nextElementSibling) {
          list.insertBefore(item.nextElementSibling, item);
        }
        btn.focus();
      }
      dirty = true;
      updateCount();
    });

    function preview() {
      pending.innerHTML = '';
      Array.prototype.forEach.call(input.files || [], function (file) {
        var li = document.createElement('li');
        li.className = 'gallery-item';
        var img = document.createElement('img');
        img.alt = '';
        if (/^image\/(jpeg|png|webp)$/.test(file.type)) {
          img.src = URL.createObjectURL(file);
        }
        li.appendChild(img);
        pending.appendChild(li);
      });
      updateCount();
    }
    input.addEventListener('change', preview);

    ['dragenter', 'dragover'].forEach(function (t) {
      zone.addEventListener(t, function () { zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      zone.addEventListener(t, function () { zone.classList.remove('is-over'); });
    });
    updateCount();
  });

  /* ---------- Listing links ---------- */
  document.querySelectorAll('[data-links]').forEach(function (box) {
    var rows = box.querySelector('[data-link-rows]');
    var tpl = box.querySelector('[data-link-template]');
    box.querySelector('[data-add-link]').addEventListener('click', function () {
      rows.appendChild(tpl.content.cloneNode(true));
      rows.lastElementChild.querySelector('input').focus();
    });
    rows.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove-link]');
      if (!btn) return;
      var row = btn.closest('.link-row');
      if (rows.children.length > 1) {
        row.remove();
      } else {
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      }
      dirty = true;
    });
  });
})();

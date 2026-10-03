/* Refugehomes admin — confirmations, photo sorting, previews, client-side resize */
(function () {
  'use strict';
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  // Confirm destructive actions
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // Managed vs before/after photo groups
  var radios = $$('[data-type-switch]');
  function syncType() {
    var checked = radios.filter(function (r) { return r.checked; })[0];
    var t = checked ? checked.value : 'managed';
    radios.forEach(function (r) { r.closest('label').classList.toggle('is-checked', r.checked); });
    $$('[data-for-type]').forEach(function (el) { el.hidden = el.getAttribute('data-for-type') !== t; });
  }
  radios.forEach(function (r) { r.addEventListener('change', syncType); });
  if (radios.length) syncType();

  /* ---------- Existing photos: remove, drag to reorder, move buttons ---------- */
  function addMoveButtons(li) {
    if (li.querySelector('.photo-move')) return;
    var wrap = document.createElement('div');
    wrap.className = 'photo-move';
    wrap.innerHTML = '<button type="button" data-move="-1" aria-label="Move earlier">‹</button><button type="button" data-move="1" aria-label="Move later">›</button>';
    li.appendChild(wrap);
  }
  $$('[data-sortable]').forEach(function (list) {
    $$('.photo', list).forEach(addMoveButtons);
    var dragging = null;
    list.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove]');
      if (rm) { rm.closest('li').remove(); return; }
      var mv = e.target.closest('[data-move]');
      if (mv) {
        var li = mv.closest('li'), dir = parseInt(mv.getAttribute('data-move'), 10);
        if (dir < 0 && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
        if (dir > 0 && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
      }
    });
    list.addEventListener('dragstart', function (e) {
      dragging = e.target.closest('.photo');
      if (dragging) { dragging.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
    });
    list.addEventListener('dragend', function () { if (dragging) dragging.classList.remove('dragging'); dragging = null; });
    list.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      var over = e.target.closest('.photo');
      if (!over || over === dragging) return;
      var r = over.getBoundingClientRect();
      var after = (e.clientX - r.left) > r.width / 2;
      list.insertBefore(dragging, after ? over.nextSibling : over);
    });
  });

  /* ---------- Small preview thumbnails ----------
     Phone photos are often 12+ megapixels. Showing them full size as previews
     uses a lot of memory and makes typing in the form laggy on iPads/phones,
     so each preview is shrunk to a ~320px thumbnail, one photo at a time. */
  var thumbCache = typeof WeakMap === 'function' ? new WeakMap() : null;
  var thumbQueue = Promise.resolve();
  function thumbnail(file) {
    if (thumbCache && thumbCache.has(file)) return Promise.resolve(thumbCache.get(file));
    var job = thumbQueue.then(function () {
      return new Promise(function (resolve) {
        var src = URL.createObjectURL(file);
        var im = new Image();
        im.onload = function () {
          var s = Math.min(1, 320 / Math.max(im.naturalWidth, im.naturalHeight));
          var c = document.createElement('canvas');
          c.width = Math.max(1, Math.round(im.naturalWidth * s));
          c.height = Math.max(1, Math.round(im.naturalHeight * s));
          c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
          im.onload = im.onerror = null;
          URL.revokeObjectURL(src);
          c.toBlob(function (b) { resolve(b ? URL.createObjectURL(b) : ''); }, 'image/jpeg', 0.8);
        };
        im.onerror = function () { URL.revokeObjectURL(src); resolve(''); };
        im.src = src;
      });
    });
    thumbQueue = job.catch(function () {});
    if (thumbCache) job.then(function (url) { thumbCache.set(file, url); });
    return job;
  }

  /* ---------- New photos: accumulate selections + previews ---------- */
  var canDT = (function () { try { return !!new DataTransfer(); } catch (e) { return false; } })();
  $$('[data-photos]').forEach(function (group) {
    var input = group.querySelector('[data-file]');
    var previews = group.querySelector('[data-new-previews]');
    var drop = group.querySelector('.drop');
    var files = [];
    function render() {
      previews.innerHTML = '';
      files.forEach(function (f, i) {
        var li = document.createElement('li');
        li.className = 'photo';
        var img = document.createElement('img');
        img.alt = '';
        img.decoding = 'async';
        thumbnail(f).then(function (url) { img.src = url; });
        li.appendChild(img);
        var tag = document.createElement('span'); tag.className = 'photo-tag'; tag.textContent = 'New';
        li.appendChild(tag);
        if (canDT) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'photo-remove'; b.setAttribute('aria-label', 'Remove photo'); b.textContent = '×';
          b.addEventListener('click', function () { files.splice(i, 1); sync(); });
          li.appendChild(b);
        }
        previews.appendChild(li);
      });
    }
    function sync() {
      if (canDT) {
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
      }
      render();
    }
    function add(list) {
      Array.prototype.forEach.call(list, function (f) { if (/^image\//.test(f.type)) files.push(f); });
      sync();
    }
    input.addEventListener('change', function () {
      if (canDT) add(input.files); else { files = Array.prototype.slice.call(input.files); render(); }
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { if (e.dataTransfer && e.dataTransfer.types.indexOf('Files') > -1) { e.preventDefault(); drop.classList.add('over'); } });
    });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      e.preventDefault();
      if (canDT) add(e.dataTransfer.files);
    });
  });

  /* ---------- Links ---------- */
  var links = document.querySelector('[data-links]');
  var addLink = document.querySelector('[data-link-add]');
  if (links && addLink) {
    addLink.addEventListener('click', function () {
      var rows = $$('[data-link-row]', links);
      var row = rows[rows.length - 1].cloneNode(true);
      $$('input', row).forEach(function (i) { i.value = ''; });
      links.appendChild(row);
      row.querySelector('input').focus();
    });
    links.addEventListener('click', function (e) {
      var b = e.target.closest('[data-link-remove]');
      if (!b) return;
      var rows = $$('[data-link-row]', links);
      if (rows.length > 1) b.closest('[data-link-row]').remove();
      else $$('input', rows[0]).forEach(function (i) { i.value = ''; });
    });
  }

  /* ---------- Submit: shrink photos in the browser before uploading ---------- */
  var MAX = 2000;
  function shrink(file) {
    if (!window.createImageBitmap || !/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.resolve(file);
    return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      var scale = Math.min(1, MAX / Math.max(bmp.width, bmp.height));
      if (scale === 1 && file.size < 1.5e6) return file;
      var c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (res) {
        c.toBlob(function (b) { res(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.86);
      });
    }).catch(function () { return file; });
  }

  var editor = document.querySelector('[data-editor]');
  if (editor && window.fetch && window.FormData) {
    editor.addEventListener('submit', function (e) {
      var inputs = $$('input[type=file]', editor).filter(function (i) { return i.files && i.files.length; });
      if (!inputs.length) return; // nothing to upload, normal submit
      e.preventDefault();
      var status = editor.querySelector('[data-upload-status]');
      var btn = editor.querySelector('[data-save]');
      btn.disabled = true;
      status.textContent = 'Preparing photos…';
      var fd = new FormData(editor);
      var jobs = inputs.map(function (inp) {
        fd.delete(inp.name);
        return Promise.all(Array.prototype.map.call(inp.files, shrink)).then(function (list) {
          list.forEach(function (f) { fd.append(inp.name, f, f.name); });
        });
      });
      Promise.all(jobs).then(function () {
        status.textContent = 'Uploading…';
        return fetch(editor.action || location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
      }).then(function (res) {
        // Show the page the server answered with (keeps the "saved" message, which is shown only once)
        return res.text().then(function (html) {
          if (res.redirected) history.replaceState(null, '', res.url);
          document.open(); document.write(html); document.close();
        });
      }).catch(function () {
        status.textContent = 'Upload failed. Please check your connection and try again.';
        btn.disabled = false;
      });
    });
  }
})();

(function () {
  // Guard: bail if WP didn't localize data
  if (typeof window.AskAdamLite !== 'object' || !AskAdamLite.restUrl || !AskAdamLite.nonce) {
    console.warn('[Ask Adam Lite] Missing localized data (restUrl/nonce).');
  }

  // Utilities
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  // Auto-expand textarea height
  function autoGrow(ta) {
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(160, Math.max(42, ta.scrollHeight)) + 'px';
  }

  // Image upload constraints (must match server)
  const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5MB raw
  const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  // REST call to chat endpoint
  async function sendChat(prompt, image) {
    const url = (window.AskAdamLite && AskAdamLite.restUrl) || '';
    const nonce = (window.AskAdamLite && AskAdamLite.nonce) || '';
    const payload = { prompt: String(prompt || '').slice(0, 2000) };
    if (image) {
      payload.image = image;
    }
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: JSON.stringify(payload)
    });
    let data = null;
    try { data = await res.json(); } catch (_e) {}
    if (!res.ok || !data || !data.success) {
      const msg = (data && (data.data?.message || data.data?.error)) || ('HTTP ' + res.status);
      throw new Error(msg);
    }
    return data.data; // { answer, sources, meta }
  }

  // Read a File to a base64 data URL
  function readFileAsDataURL(file) {
    return new Promise(function (resolve, reject) {
      const r = new FileReader();
      r.onload = function () { resolve(String(r.result || '')); };
      r.onerror = function () { reject(new Error('Failed to read file.')); };
      r.readAsDataURL(file);
    });
  }

  // Simple escaping for HTML text nodes
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  // Linkify bare URLs
  function linkify(html) {
    return html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
  }

  // Append a message bubble
  function appendMsg(bodyEl, type, html) {
    const div = document.createElement('div');
    div.className = 'aa-msg ' + type;
    div.innerHTML = html;
    bodyEl.appendChild(div);
  }

  // Ensure scroll to bottom
  function scrollToBottom(el) {
    if (!el) return;
    el.scrollTop = el.scrollHeight;
  }

  // Bind one widget root (floating or embed)
  function bindWidget(root) {
    if (!root || root.__aaliteBound) return;
    root.__aaliteBound = true;

    const panel = root.querySelector('.aalite-panel');
    const body = root.querySelector('.aalite-body');
    const form = root.querySelector('.aalite-form');
    const ta = form ? form.querySelector('textarea') : null;
    const attachBtn = form ? form.querySelector('.aalite-attach') : null;
    const fileInput = form ? form.querySelector('.aalite-file') : null;
    const previewWrap = form ? form.querySelector('.aalite-image-preview') : null;
    const previewImg = previewWrap ? previewWrap.querySelector('.aalite-image-preview-img') : null;
    const previewRemove = previewWrap ? previewWrap.querySelector('.aalite-image-remove') : null;

    // FAB toggle is wired by the inline script in class-widget.php
    // (window.AALiteToggle + delegated click on .aalite-btn).

    // Holds the base64 data URL for the currently-staged image.
    let pendingImage = '';

    function clearPendingImage() {
      pendingImage = '';
      if (fileInput) fileInput.value = '';
      if (previewImg) previewImg.src = '';
      if (previewWrap) previewWrap.setAttribute('hidden', '');
    }

    // Attach button → open file dialog
    if (attachBtn && fileInput) {
      attachBtn.addEventListener('click', function (e) {
        e.preventDefault();
        fileInput.click();
      });
    }

    // File chosen → validate, encode, preview
    if (fileInput) {
      fileInput.addEventListener('change', async function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        const l10n = window.AskAdamLite || {};
        if (ALLOWED_IMAGE_TYPES.indexOf(file.type) === -1) {
          appendMsg(body, 'err', esc(l10n.imageInvalidType || 'Unsupported image type.'));
          scrollToBottom(body);
          fileInput.value = '';
          return;
        }
        if (file.size > MAX_IMAGE_BYTES) {
          appendMsg(body, 'err', esc(l10n.imageTooLarge || 'Image too large.'));
          scrollToBottom(body);
          fileInput.value = '';
          return;
        }

        try {
          const dataUrl = await readFileAsDataURL(file);
          pendingImage = dataUrl;
          if (previewImg) previewImg.src = dataUrl;
          if (previewWrap) previewWrap.removeAttribute('hidden');
        } catch (err) {
          appendMsg(body, 'err', 'Error: ' + esc(err.message || String(err)));
          fileInput.value = '';
        }
      });
    }

    // Remove preview button
    if (previewRemove) {
      previewRemove.addEventListener('click', function (e) {
        e.preventDefault();
        clearPendingImage();
      });
    }

    // Textarea UX
    if (ta) {
      ta.addEventListener('input', function () { autoGrow(ta); }, { passive: true });
      // initial grow
      setTimeout(function(){ autoGrow(ta); }, 0);
    }

    // Submit handler
    if (form && body && ta) {
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        e.stopPropagation();

        const q = (ta.value || '').trim();
        if (!q && !pendingImage) return;

        ta.disabled = true;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        if (attachBtn) attachBtn.disabled = true;

        const sentImage = pendingImage;

        let userHtml = esc(q);
        if (sentImage) {
          // Show the attached image inline above the user text.
          userHtml = '<img class="aa-msg-img" src="' + esc(sentImage) + '" alt="">' + (q ? '<br>' + userHtml : '');
        }
        appendMsg(body, 'user', userHtml);

        const typing = document.createElement('div');
        typing.className = 'aa-msg bot aa-typing';
        body.appendChild(typing);
        scrollToBottom(body);

        try {
          const out = await sendChat(q, sentImage);
          // remove typing
          if (typing.parentNode) typing.parentNode.removeChild(typing);

          let answer = esc(out.answer || '').replace(/\n/g, '<br>');
          answer = linkify(answer);
          appendMsg(body, 'bot', answer);

          if (Array.isArray(out.sources) && out.sources.length) {
            const wrap = document.createElement('div');
            wrap.className = 'aa-sources';
            wrap.innerHTML = 'Sources: ' + out.sources.map(function (s) {
              const title = esc(s.title || s.url || 'source');
              const url = esc(s.url || '#');
              return '<a href="' + url + '" target="_blank" rel="noopener">' + title + '</a>';
            }).join(' • ');
            body.appendChild(wrap);
          }
        } catch (err) {
          if (typing.parentNode) typing.parentNode.removeChild(typing);
          appendMsg(body, 'err', 'Error: ' + esc(err.message || String(err)));
        } finally {
          ta.value = '';
          ta.disabled = false;
          if (btn) btn.disabled = false;
          if (attachBtn) attachBtn.disabled = false;
          clearPendingImage();
          autoGrow(ta);
          scrollToBottom(body);
        }
      }, false);
    }
  }

  // Initial bind for existing nodes
  function bindAll() {
    $all('#aalite-widget, .aalite-embed').forEach(bindWidget);
  }

  // Observe DOM for late inserts (some builders/themes delay render)
  const mo = new MutationObserver(function (muts) {
    for (var i = 0; i < muts.length; i++) {
      const m = muts[i];
      if (m.addedNodes && m.addedNodes.length) {
        for (var j = 0; j < m.addedNodes.length; j++) {
          const n = m.addedNodes[j];
          if (!(n instanceof HTMLElement)) continue;
          if (n.matches && (n.matches('#aalite-widget') || n.matches('.aalite-embed'))) {
            bindWidget(n);
          } else {
            // check descendants
            $all('#aalite-widget, .aalite-embed', n).forEach(bindWidget);
          }
        }
      }
    }
  });

  // Start once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bindAll();
      mo.observe(document.documentElement, { childList: true, subtree: true });
    });
  } else {
    bindAll();
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }
})();

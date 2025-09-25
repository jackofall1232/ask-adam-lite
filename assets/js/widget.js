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

  // REST call to chat endpoint
  async function sendChat(prompt) {
    const url = (window.AskAdamLite && AskAdamLite.restUrl) || '';
    const nonce = (window.AskAdamLite && AskAdamLite.nonce) || '';
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: JSON.stringify({ prompt: String(prompt || '').slice(0, 8000) })
    });
    let data = null;
    try { data = await res.json(); } catch (_e) {}
    if (!res.ok || !data || !data.success) {
      const msg = (data && (data.data?.message || data.data?.error)) || ('HTTP ' + res.status);
      throw new Error(msg);
    }
    return data.data; // { answer, sources, meta }
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

    // Handle open/close buttons
    const openBtn = root.querySelector('.aalite-btn');
    const closeBtn = root.querySelector('.aalite-close');
    
    if (openBtn) {
      openBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (panel) {
          // Show the panel by setting display to flex (matching your CSS)
          panel.style.display = 'flex';
          panel.removeAttribute('hidden');
        }
      });
    }
    
    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (panel) {
          // Hide the panel by setting display to none
          panel.style.display = 'none';
          panel.setAttribute('hidden', '');
        }
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
        if (!q) return;

        ta.disabled = true;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        appendMsg(body, 'user', esc(q));
        const typing = document.createElement('div');
        typing.className = 'aa-msg bot aa-typing';
        body.appendChild(typing);
        scrollToBottom(body);

        try {
          const out = await sendChat(q);
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

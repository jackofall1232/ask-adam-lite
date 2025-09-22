/* assets/js/frontend.js
 * Ask Adam Lite — frontend bootstrap
 * Consumes window.ADAM_LITE_Q (seeded by the shortcode) and mounts each instance.
 * No frameworks. CSS lives in assets/css/frontend.css.
 */

(function () {
  'use strict';

  // ---- Utilities ----------------------------------------------------------

  function $(sel, root) { return (root || document).querySelector(sel); }
  function el(tag, className, props) {
    const n = document.createElement(tag);
    if (className) n.className = className;
    if (props) Object.assign(n, props);
    return n;
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  // Client-side downscale to keep payloads small (no server storage)
  async function downscaleImageFile(file, maxW = 1024, maxH = 1024, quality = 0.82) {
    if (!file || !/^image\//.test(file.type)) throw new Error('Please choose an image file.');
    const buf = await file.arrayBuffer();
    const blobUrl = URL.createObjectURL(new Blob([buf]));
    try {
      const img = await new Promise((resolve, reject) => {
        const i = new Image();
        i.onload = () => resolve(i);
        i.onerror = () => reject(new Error('Invalid image'));
        i.src = blobUrl;
      });
      let { width: w, height: h } = img;
      const scale = Math.min(1, maxW / w, maxH / h);
      const cw = Math.max(1, Math.round(w * scale));
      const ch = Math.max(1, Math.round(h * scale));
      const canvas = document.createElement('canvas');
      canvas.width = cw; canvas.height = ch;
      const ctx = canvas.getContext('2d');
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, 0, 0, cw, ch);
      return canvas.toDataURL('image/jpeg', quality);
    } finally {
      URL.revokeObjectURL(blobUrl);
    }
  }

  // Attempt Shadow DOM for isolation (falls back to light DOM)
  function getMount(root) {
    if (!root) return null;
    try { if (root.attachShadow) return root.attachShadow({ mode: 'open' }); } catch (e) {}
    return root;
  }

  // Render helpers
  function addRow(chat, html) {
    const wrap = el('div');
    wrap.innerHTML = html.trim();
    const node = wrap.firstElementChild;
    chat.appendChild(node);
    chat.scrollTop = chat.scrollHeight;
    return node;
  }
  function addUser(chat, text) {
    return addRow(chat, `<div class="message user"><div class="bubble"><strong>You:</strong> ${esc(text)}</div></div>`);
  }
  function addAI(chat, who, text) {
    return addRow(chat, `<div class="message"><div class="bubble"><strong>${esc(who)}:</strong> ${esc(text)}</div></div>`);
  }
  function typingStart(chat) {
    return addRow(chat, `<div class="message"><div class="bubble"><span class="typing" aria-live="polite">...</span></div></div>`);
  }
  function typingStop(n) { if (n && n.parentNode) n.parentNode.remove(); }

  // ---- Mount one instance -------------------------------------------------

  async function mountOne(cfg) {
    const root = document.getElementById(cfg.rootId);
    if (!root || root.dataset.adamLiteMounted === '1') return;

    const mount = getMount(root);
    root.dataset.adamLiteMounted = '1';

    // Container that matches frontend.css classnames
    const scope = el('div', 'adam-lite-scope');

    // Optional: expose CSS vars on scope for theming via cfg.colors
    scope.style.setProperty('--aa-primary', cfg.colors?.primary || '#667eea');
    scope.style.setProperty('--aa-secondary', cfg.colors?.secondary || '#764ba2');
    scope.style.setProperty('--aa-text', cfg.colors?.text || '#1f2937');
    scope.style.setProperty('--aa-bg', cfg.colors?.background || '#ffffff');

    // Panel
    const panel = el('div', 'panel');
    panel.style.minHeight = (parseInt(cfg.height, 10) || 300) + 'px';

    // Header
    const header = el('div', 'header');
    const avatar = el('div', 'avatar');
    if (cfg.avatarUrl) {
      const img = el('img', '', { alt: cfg.title });
      img.src = cfg.avatarUrl;
      img.width = 40; img.height = 40;
      img.style.cssText = 'width:40px;height:40px;object-fit:cover;border-radius:50%;';
      avatar.appendChild(img);
    }
    const titleEl = el('div', 'title');
    titleEl.textContent = cfg.title || 'Adam';
    const status = el('div', 'status');
    const dot = el('span', 'dot'); dot.setAttribute('aria-hidden', 'true');
    status.appendChild(dot);
    status.appendChild(document.createTextNode('Online'));
    header.appendChild(avatar);
    header.appendChild(titleEl);
    header.appendChild(status);

    // Chat
    const chat = el('div', 'chat');
    chat.id = cfg.rootId + '-chat';

    // Attachments
    const attach = el('div', 'attach');
    const file = el('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.id = cfg.rootId + '-file';

    const pick = el('label', 'pick', { title: 'Attach image' });
    pick.setAttribute('for', file.id);
    pick.textContent = 'Add image';

    const previewRow = el('div', 'previewRow');
    previewRow.id = cfg.rootId + '-previewRow';
    previewRow.hidden = true;

    const preview = el('div', 'preview');
    const previewImg = el('img', '', { alt: 'Attached image preview' });
    const previewRemove = el('button', 'previewRemove', { type: 'button' });
    previewRemove.setAttribute('aria-label', 'Remove attached image');
    previewRemove.textContent = '✖';
    preview.appendChild(previewImg);
    previewRow.appendChild(preview);
    previewRow.appendChild(previewRemove);

    attach.appendChild(file);
    attach.appendChild(pick);
    attach.appendChild(previewRow);

    // Input row
    const inputRow = el('div', 'inputrow');
    const inp = el('input', 'inp', { type: 'text' });
    inp.placeholder = cfg.placeholder || 'Ask me anything…';
    inp.setAttribute('aria-label', 'Type your message');

    const send = el('button', 'send', { type: 'button' });
    send.setAttribute('aria-label', 'Send message');
    send.textContent = 'Ask Me';

    inputRow.appendChild(inp);
    inputRow.appendChild(send);

    // Compose
    panel.appendChild(header);
    panel.appendChild(chat);
    panel.appendChild(attach);
    panel.appendChild(inputRow);
    scope.appendChild(panel);
    mount.appendChild(scope);

    // Behavior
    let sending = false;
    let imageDataURL = null;

    file.addEventListener('change', async () => {
      const f = file.files && file.files[0];
      if (!f) return;
      if (f.size > 5 * 1024 * 1024) {
        alert('Image too large (max 5MB).');
        file.value = '';
        return;
      }
      try {
        imageDataURL = await downscaleImageFile(f, 1024, 1024, 0.82);
        if (imageDataURL) {
          previewImg.src = imageDataURL;
          previewRow.hidden = false;
        } else {
          previewImg.removeAttribute('src');
          previewRow.hidden = true;
        }
      } catch (e) {
        alert('Could not process the image. Please try a different file.');
        imageDataURL = null;
        file.value = '';
        previewImg.removeAttribute('src');
        previewRow.hidden = true;
      }
    });

    previewRemove.addEventListener('click', () => {
      imageDataURL = null;
      file.value = '';
      previewImg.removeAttribute('src');
      previewRow.hidden = true;
    });

    async function sendMsg() {
      const q = inp.value.trim();
      if (!q && !imageDataURL) return;
      if (imageDataURL && !q) {
        addAI(chat, cfg.title, 'Please add a brief question with your image (e.g., “What does this diagram show?”).');
        inp.focus();
        return;
      }
      if (sending) return;

      sending = true; send.disabled = true; inp.blur();
      addUser(chat, q || '(Image attached)');
      const typing = typingStart(chat);

      try {
        const body = { prompt: q, channel: 'shortcode', kb: !!cfg.kb };
        if (imageDataURL) body.image_data_url = imageDataURL;
        if (cfg.model) body.model = cfg.model;
        if (Number(cfg.maxTokens)) body.max_tokens = Number(cfg.maxTokens);
        if (typeof cfg.temperature === 'number' && !isNaN(cfg.temperature)) body.temperature = cfg.temperature;

        const res = await fetch(cfg.restUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.restNonce
          },
          body: JSON.stringify(body),
        });

        let data = null;
        try { data = await res.json(); } catch (_) {}

        typingStop(typing);

        if (!res.ok || !data || !data.success) {
          const err = (data && data.data && data.data.error) ? data.data.error : ('HTTP ' + res.status);
          addAI(chat, cfg.title, 'Sorry, there was a problem: ' + esc(err));
        } else {
          addAI(chat, cfg.title, data.data.answer || '(no answer)');
          // clear image so it won't resend
          if (imageDataURL) {
            imageDataURL = null;
            file.value = '';
            previewImg.removeAttribute('src');
            previewRow.hidden = true;
          }
        }
      } catch (e) {
        typingStop(typing);
        addAI(chat, cfg.title, 'Network error: ' + esc(e && e.message ? e.message : e));
      } finally {
        sending = false; send.disabled = false; inp.value = '';
      }
    }

    send.addEventListener('click', (e) => { e.preventDefault(); sendMsg(); });
    inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); sendMsg(); } });

    // Initial greeting
    addAI(chat, cfg.title || 'Adam', cfg.welcome || "Hi! I'm Adam — ask me anything.");
  }

  // ---- Bootstrap: consume the queue --------------------------------------

  function start() {
    const Q = (window.ADAM_LITE_Q || []);
    for (const cfg of Q) mountOne(cfg);
    // Keep queue for potential late mounts; do not clear.
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

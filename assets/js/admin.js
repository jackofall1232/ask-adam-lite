// Ask Adam Lite — admin tabs (accessible, tiny, dependency-free)
(function () {
  const LS_KEY = 'aalite_admin_tab';

  function qs(scope, sel) { return (scope || document).querySelector(sel); }
  function qsa(scope, sel) { return Array.from((scope || document).querySelectorAll(sel)); }

  function activateTab(wrap, tabId, pushHash = true) {
    if (!wrap) return;
    const tabs   = qsa(wrap, '.adam-tab');
    const panels = qsa(wrap, '.adam-tabpanel');

    let targetBtn = tabs.find(t => t.getAttribute('data-tab') === tabId && !t.classList.contains('is-disabled'));
    if (!targetBtn) targetBtn = tabs[0]; // fallback to first

    const targetId = targetBtn ? targetBtn.getAttribute('data-tab') : null;

    tabs.forEach(btn => {
      const isActive = btn === targetBtn;
      btn.classList.toggle('is-active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      btn.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    panels.forEach(p => {
      const match = p.getAttribute('data-panel') === targetId;
      p.hidden = !match;
      p.setAttribute('aria-hidden', match ? 'false' : 'true');
    });

    if (targetId) {
      try { localStorage.setItem(LS_KEY, targetId); } catch (e) {}
      if (pushHash) {
        const url = new URL(location.href);
        url.hash = `aalite=${encodeURIComponent(targetId)}`;
        history.replaceState(null, '', url.toString());
      }
    }

    // Move focus to active tab if we changed via keyboard
    if (document.activeElement && document.activeElement.classList.contains('adam-tab') === false) {
      targetBtn && targetBtn.focus();
    }
  }

  function init() {
    const wrap = qs(document, '.wrap.adam-admin');
    if (!wrap) return;

    // ARIA roles (no HTML change needed)
    const tablist = qs(wrap, '.adam-tablist');
    if (tablist) tablist.setAttribute('role', 'tablist');

    qsa(wrap, '.adam-tab').forEach(btn => {
      btn.setAttribute('role', 'tab');
      btn.setAttribute('tabindex', btn.classList.contains('is-active') ? '0' : '-1');
      btn.setAttribute('aria-selected', btn.classList.contains('is-active') ? 'true' : 'false');
    });

    qsa(wrap, '.adam-tabpanel').forEach(p => {
      p.setAttribute('role', 'tabpanel');
      p.setAttribute('aria-hidden', p.hidden ? 'true' : 'false');
    });

    // Resolve initial tab: hash > localStorage > current .is-active > first
    const hashMatch = location.hash.match(/aalite=([^&]+)/i);
    const fromHash = hashMatch ? decodeURIComponent(hashMatch[1]) : null;
    let initial = fromHash;
    if (!initial) {
      try { initial = localStorage.getItem(LS_KEY) || null; } catch (e) {}
    }
    if (!initial) {
      const current = qs(wrap, '.adam-tab.is-active');
      initial = current ? current.getAttribute('data-tab') : (qs(wrap, '.adam-tab')?.getAttribute('data-tab') || null);
    }
    activateTab(wrap, initial, /*pushHash*/ false);

    // Click to switch
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.adam-tab');
      if (!btn || btn.classList.contains('is-disabled')) return;
      if (!wrap.contains(btn)) return;
      activateTab(wrap, btn.getAttribute('data-tab'));
    });

    // Keyboard navigation (Left/Right, Home/End)
    tablist && tablist.addEventListener('keydown', (e) => {
      const tabs = qsa(wrap, '.adam-tab:not(.is-disabled)');
      if (!tabs.length) return;

      const current = document.activeElement.closest('.adam-tab');
      const i = Math.max(0, tabs.indexOf(current));
      let ni = i;

      switch (e.key) {
        case 'ArrowRight': ni = (i + 1) % tabs.length; break;
        case 'ArrowLeft':  ni = (i - 1 + tabs.length) % tabs.length; break;
        case 'Home':       ni = 0; break;
        case 'End':        ni = tabs.length - 1; break;
        case 'Enter':
        case ' ':
          // already handled by click handler via activateTab when focused + click;
          // we manually activate here for keyboards
          activateTab(wrap, current?.getAttribute('data-tab'));
          e.preventDefault();
          return;
        default: return;
      }
      tabs[ni].focus();
      activateTab(wrap, tabs[ni].getAttribute('data-tab'));
      e.preventDefault();
    });

    // Back/forward hash support
    window.addEventListener('popstate', () => {
      const hm = location.hash.match(/aalite=([^&]+)/i);
      const tab = hm ? decodeURIComponent(hm[1]) : null;
      if (tab) activateTab(wrap, tab, /*pushHash*/ false);
    });
  }

  // DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

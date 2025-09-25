// Simple tab toggling for the admin screen
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.adam-tab');
  if (!btn || btn.classList.contains('is-disabled')) return;
  const wrap = document.querySelector('.wrap.adam-admin');
  if (!wrap) return;

  // switch tab
  wrap.querySelectorAll('.adam-tab').forEach(t => t.classList.remove('is-active'));
  btn.classList.add('is-active');

  const panel = btn.getAttribute('data-tab');
  wrap.querySelectorAll('.adam-tabpanel').forEach(p => {
    p.hidden = p.getAttribute('data-panel') !== panel;
  });
});

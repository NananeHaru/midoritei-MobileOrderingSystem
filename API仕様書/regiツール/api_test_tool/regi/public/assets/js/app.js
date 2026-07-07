window.openModal = (id) => {
  const el = document.getElementById(id);
  if (el) el.classList.add('show');
};
window.closeModal = (id) => {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
};

document.addEventListener('click', (e) => {
  const bd = e.target.closest('.backdrop');
  if (!bd) return;
  if (e.target === bd) bd.classList.remove('show');
});

window.bindTabs = (rootId) => {
  const root = document.getElementById(rootId);
  if (!root) return;
  const tabs = root.querySelectorAll('[data-tab]');
  const panes = root.querySelectorAll('[data-pane]');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      const name = btn.dataset.tab;
      tabs.forEach(x => x.classList.toggle('active', x === btn));
      panes.forEach(p => p.style.display = (p.dataset.pane === name) ? 'block' : 'none');
    });
  });
};

window.bindKeypad = ({ keypadId, inputId, maxLen = 9 }) => {
  const keypad = document.getElementById(keypadId);
  const input = document.getElementById(inputId);
  if (!keypad || !input) return;

  keypad.addEventListener('click', (e) => {
    const key = e.target.closest('[data-key]');
    if (!key) return;
    const v = key.dataset.key;

    if (v === 'clear') { input.value = ''; input.dispatchEvent(new Event('input')); return; }
    if (v === 'back')  { input.value = input.value.slice(0, -1); input.dispatchEvent(new Event('input')); return; }
    if (!/^\d$/.test(v)) return;

    if (input.value.length >= maxLen) return;
    input.value += v;
    input.dispatchEvent(new Event('input'));
  });
};

window.addEventListener('DOMContentLoaded', () => {
  const operatorName = document.getElementById('operatorName');
  const registerStartAmount = document.getElementById('registerStartAmount');
  const expectedCashSales = document.getElementById('expectedCashSales');

  const countedAmount = document.getElementById('countedAmount');
  const expectedLabel = document.getElementById('expectedLabel');
  const diffValue = document.getElementById('diffValue');
  const diffNote = document.getElementById('diffNote');
  const diffBox = document.getElementById('diffBox');

  const storeForm = document.getElementById('storeForm');
  const storePayload = document.getElementById('storePayload');

  const keypad = document.getElementById('keypad');
  const keypadDisplay = document.getElementById('keypadDisplay');
  const keypadTitle = document.getElementById('keypadTitle');

  const targets = Array.from(document.querySelectorAll('.js-keypad-target'));
  const denomInputs = Array.from(document.querySelectorAll('.denom-count'));

  let currentInput = null;
  let buffer = '0';

  const yen = (n) => '¥' + Number(n || 0).toLocaleString('ja-JP');

  const toInt = (v) => {
    const s = String(v ?? '').replace(/[^\d]/g, '');
    return s ? parseInt(s, 10) : 0;
  };

  const fieldLabelMap = {
    start: 'レジ開始金額',
    '10000': '10000円札の枚数',
    '5000': '5000円札の枚数',
    '1000': '1000円札の枚数',
    '500': '500円玉の枚数',
    '100': '100円玉の枚数',
    '50': '50円玉の枚数',
    '10': '10円玉の枚数',
    '5': '5円玉の枚数',
    '1': '1円玉の枚数',
  };

  const setKeypadTargetLabel = (input) => {
    const field = input?.dataset.field || '';
    const label = fieldLabelMap[field] || '入力';

    if (keypadTitle) keypadTitle.textContent = label;
  };

  const openKeypad = (input) => {
    currentInput = input;
    buffer = String(toInt(input.value || '0'));
    keypadDisplay.textContent = buffer;
    setKeypadTargetLabel(input);
    keypad.classList.add('is-open');
    keypad.setAttribute('aria-hidden', 'false');
  };

  const closeKeypad = () => {
    keypad.classList.remove('is-open');
    keypad.setAttribute('aria-hidden', 'true');
    currentInput = null;

    if (keypadTitle) keypadTitle.textContent = '入力';
  };

  const setDiffStyle = (diff) => {
    diffBox.classList.remove('is-plus', 'is-minus', 'is-equal');

    if (diff > 0) {
      diffBox.classList.add('is-plus');
      diffNote.textContent = '期待額より多いです。';
    } else if (diff < 0) {
      diffBox.classList.add('is-minus');
      diffNote.textContent = '期待額より少ないです。';
    } else {
      diffBox.classList.add('is-equal');
      diffNote.textContent = '差額はありません。';
    }
  };

  const updateSubtotals = () => {
    let counted = 0;

    denomInputs.forEach((input) => {
      const field = input.dataset.field;
      const count = toInt(input.value);
      const denom = toInt(field);
      const subtotal = count * denom;

      const subEl = document.querySelector(`[data-subtotal-for="${field}"]`);
      if (subEl) {
        subEl.textContent = yen(subtotal);
      }

      counted += subtotal;
    });

    const expected = toInt(registerStartAmount.value) + toInt(expectedCashSales.value);
    const diff = counted - expected;

    countedAmount.textContent = yen(counted);
    expectedLabel.textContent = yen(expected);
    diffValue.textContent = yen(diff);
    setDiffStyle(diff);
  };

  const applyBufferToInput = () => {
    if (!currentInput) return;
    currentInput.value = String(toInt(buffer));
    updateSubtotals();
  };

  const moveToNextInput = () => {
    if (!currentInput) return;

    const idx = targets.indexOf(currentInput);
    if (idx >= 0 && idx < targets.length - 1) {
      currentInput = targets[idx + 1];
      buffer = String(toInt(currentInput.value || '0'));
      keypadDisplay.textContent = buffer;
      setKeypadTargetLabel(currentInput);
    } else {
      closeKeypad();
    }
  };

  const buildPayload = () => {
    const counts = {};
    denomInputs.forEach((input) => {
      counts[input.dataset.field] = toInt(input.value);
    });

    const registerStart = toInt(registerStartAmount?.value);
    const cashSales = toInt(expectedCashSales?.value);

    let actualCash = 0;
    Object.keys(counts).forEach((k) => {
      actualCash += toInt(k) * toInt(counts[k]);
    });

    const expectedCash = registerStart + cashSales;
    const cashDiff = actualCash - expectedCash;

    return {
      operatorName: (operatorName?.value || '').trim(),
      registerStartAmount: registerStart,
      expectedCashSales: cashSales,
      expectedCash,
      actualCash,
      cashDiff,
      counts
    };
  };

  targets.forEach((input) => {
    input.addEventListener('click', () => openKeypad(input));
  });

  keypad.addEventListener('click', (e) => {
    const btn = e.target.closest('.kp');
    if (!btn) return;

    const key = btn.dataset.k;
    const action = btn.dataset.action;

    if (key != null) {
      if (buffer === '0') {
        buffer = key;
      } else {
        buffer += key;
      }
      keypadDisplay.textContent = buffer;
      return;
    }

    if (action === 'bs') {
      buffer = buffer.length > 1 ? buffer.slice(0, -1) : '0';
      keypadDisplay.textContent = buffer;
      return;
    }

    if (action === 'clear') {
      buffer = '0';
      keypadDisplay.textContent = buffer;
      return;
    }

    if (action === 'close') {
      closeKeypad();
      return;
    }

    if (action === 'enter') {
      applyBufferToInput();
      moveToNextInput();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (!keypad.classList.contains('is-open')) return;

    if (/^\d$/.test(e.key)) {
      e.preventDefault();
      if (buffer === '0') {
        buffer = e.key;
      } else {
        buffer += e.key;
      }
      keypadDisplay.textContent = buffer;
      return;
    }

    if (e.key === 'Backspace') {
      e.preventDefault();
      buffer = buffer.length > 1 ? buffer.slice(0, -1) : '0';
      keypadDisplay.textContent = buffer;
      return;
    }

    if (e.key === 'Enter') {
      e.preventDefault();
      applyBufferToInput();
      moveToNextInput();
      return;
    }

    if (e.key === 'Escape') {
      e.preventDefault();
      closeKeypad();
    }
  });

  storeForm?.addEventListener('submit', (e) => {
    const name = (operatorName?.value || '').trim();
    if (!name) {
      e.preventDefault();
      alert('レジ締担当を入力してください。');
      operatorName?.focus();
      return;
    }

    const payload = buildPayload();
    storePayload.value = JSON.stringify(payload);
  });

  updateSubtotals();
});
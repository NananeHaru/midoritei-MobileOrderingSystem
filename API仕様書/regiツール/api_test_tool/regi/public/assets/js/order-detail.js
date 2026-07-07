(() => {
  const LEN = 7;
  const modal = document.getElementById('addCustomerModal');
  const openBtn = document.getElementById('btnAddCustomer');
  const closeBtn = document.getElementById('btnCloseAddCustomer');
  const cancelBtn = document.getElementById('btnCancelAddCustomer');

  const pinText = document.getElementById('pinText');
  const hidden = document.getElementById('addCustomerId');
  const applyBtn = document.getElementById('btnApplyAddCustomer');

  if (!modal || !pinText || !hidden || !applyBtn) {
    return;
  }

  let value = '';

  const render = () => {
    pinText.textContent = value || '-------';
    hidden.value = value;
    applyBtn.disabled = value.length !== LEN;
  };

  const open = () => {
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    value = '';
    render();
  };

  const close = () => {
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  };

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  cancelBtn?.addEventListener('click', close);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) close();
  });

  modal.querySelectorAll('.od-key').forEach((btn) => {
    btn.addEventListener('click', () => {
      const k = btn.dataset.key;
      const act = btn.dataset.action;

      if (k) {
        if (value.length < LEN) value += k;
      } else if (act === 'back') {
        value = value.slice(0, -1);
      } else if (act === 'clear') {
        value = '';
      }

      render();
    });
  });

  render();
})();

(() => {
  const openModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.setAttribute('aria-hidden', 'false');
  };

  const closeModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
  };

  // ===== 割引モーダル =====
  const discountModal = document.getElementById('discountModal');
  const btnDiscount = document.getElementById('btnDiscount');

  btnDiscount?.addEventListener('click', () => openModal('discountModal'));

  discountModal?.addEventListener('click', (e) => {
    if (e.target === discountModal) closeModal('discountModal');
  });

  document.querySelectorAll('[data-close="discount"]').forEach((b) => {
    b.addEventListener('click', () => closeModal('discountModal'));
  });

  const dtype = document.getElementById('dtype');
  const p = document.getElementById('dpercent');
  const a = document.getElementById('damount');
  const percentField = document.getElementById('percentField');
  const amountField = document.getElementById('amountField');

  const setDiscountType = (t) => {
    if (dtype) dtype.value = t;

    document.querySelectorAll('#discountModal [data-dtype]').forEach((x) => {
      x.classList.toggle('active', x.dataset.dtype === t);
    });

    if (t === 'percent') {
      if (percentField) percentField.style.display = '';
      if (amountField) amountField.style.display = 'none';

      if (p) {
        p.disabled = false;
      }

      if (a) {
        a.disabled = true;
        a.value = '';
      }
    } else {
      if (percentField) percentField.style.display = 'none';
      if (amountField) amountField.style.display = '';

      if (p) {
        p.disabled = true;
        p.value = '';
      }

      if (a) {
        a.disabled = false;
      }
    }
  };

  document.querySelectorAll('#discountModal [data-dtype]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const t = btn.dataset.dtype || 'percent';
      setDiscountType(t);
    });
  });

  if (discountModal) {
    discountModal.setAttribute('aria-hidden', 'true');
  }

  setDiscountType(dtype?.value || 'percent');
})();

(() => {
  // ===== splitModal がある画面だけ動かす =====
  const splitModal = document.getElementById('splitModal');
  const btnSplit = document.getElementById('btnSplit');

  if (!splitModal || !btnSplit) {
    return;
  }

  const openModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.setAttribute('aria-hidden', 'false');
  };

  const closeModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
  };

  btnSplit.addEventListener('click', () => openModal('splitModal'));

  splitModal.addEventListener('click', (e) => {
    if (e.target === splitModal) closeModal('splitModal');
  });

  document.querySelectorAll('[data-close="split"]').forEach((b) => {
    b.addEventListener('click', () => closeModal('splitModal'));
  });

  const smode = document.getElementById('smode');
  const people = document.getElementById('speople');
  const rows = document.getElementById('srows');
  const sumEl = document.getElementById('ssum');
  const diffEl = document.getElementById('sdiff');
  const amounts = document.getElementById('samounts');
  const apply = document.getElementById('sapply');

  if (!smode || !people || !rows || !sumEl || !diffEl || !amounts || !apply) {
    return;
  }

  const totalValue = Number(window.TOTAL || 0);
  const yen = (n) => '¥' + Math.round(n).toLocaleString('ja-JP');

  const setSplitMode = (m) => {
    smode.value = m;
    document.querySelectorAll('#splitModal [data-smode]').forEach((b) => {
      b.classList.toggle('active', b.dataset.smode === m);
    });
    renderSplit();
  };

  document.querySelectorAll('#splitModal [data-smode]').forEach((btn) => {
    btn.addEventListener('click', () => setSplitMode(btn.dataset.smode));
  });

  const equalArr = (pCount) => {
    const base = Math.floor(totalValue / pCount);
    const rem = totalValue - base * pCount;
    return Array.from({ length: pCount }, (_, i) => base + (i === 0 ? rem : 0));
  };

  const renderSplit = () => {
    const pCount = Math.max(2, parseInt(people.value || '2', 10));
    const mode = smode.value || 'equal';
    const vals = [];

    rows.innerHTML = '';

    if (mode === 'equal') {
      const arr = equalArr(pCount);
      arr.forEach((v, i) => {
        vals.push(v);
        rows.insertAdjacentHTML(
          'beforeend',
          `<div class="split-row"><span>${i + 1}人目</span><strong>${yen(v)}</strong></div>`
        );
      });
      amounts.value = arr.join(',');
    } else {
      for (let i = 0; i < pCount; i++) {
        const n = document.createElement('input');
        n.type = 'number';
        n.min = '0';
        n.step = '1';
        n.className = 'input';
        n.placeholder = `${i + 1}人目の金額`;
        n.dataset.idx = String(i);
        n.addEventListener('input', () => {
          const arr = Array.from(rows.querySelectorAll('input')).map((x) => Number(x.value || 0));
          amounts.value = arr.join(',');
          updateTotals(arr);
        });

        const wrap = document.createElement('div');
        wrap.className = 'field';
        wrap.innerHTML = `<div class="label">${i + 1}人目</div>`;
        wrap.appendChild(n);
        rows.appendChild(wrap);
      }
      amounts.value = '';
      updateTotals([]);
      return;
    }

    updateTotals(vals);
  };

  const updateTotals = (arr) => {
    const s = arr.reduce((a, b) => a + b, 0);
    const d = totalValue - s;
    sumEl.textContent = yen(s);
    diffEl.textContent = yen(d);
    apply.disabled = d !== 0;
  };

  people.addEventListener('input', renderSplit);

  if (splitModal) {
    splitModal.setAttribute('aria-hidden', 'true');
  }

  setSplitMode(smode.value || 'equal');
})();
window.addEventListener('DOMContentLoaded', () => {
  const BASE = document.querySelector('meta[name="base-url"]')?.content ?? '';

  const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '');
  const yen = (n) => '¥' + Math.round(Number(n || 0)).toLocaleString('ja-JP');

  const escapeHtml = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

  const pad = (n) => String(n).padStart(2, '0');

  const toLocalDatetimeValue = (date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

  const toIsoSeconds = (value) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  };

  const billStatusLabel = (v) => {
    switch (Number(v)) {
      case 1: return '受付中';
      case 2: return '会計済み';
      case 4: return '未収金';
      case 8: return '会計中';
      default: return `不明(${v})`;
    }
  };

  // =========================
  // タブ
  // =========================
  const tabLink = document.getElementById('tab-link');
  const tabOrder = document.getElementById('tab-order');
  const tabManual = document.getElementById('tab-manual');

  const paneLink = document.getElementById('pane-link');
  const paneOrder = document.getElementById('pane-order');
  const paneManual = document.getElementById('pane-manual');

  const customerId = document.getElementById('customerId');

  const setTab = (mode) => {
    const isLink = mode === 'link';
    const isOrder = mode === 'order';
    const isManual = mode === 'manual';

    tabLink?.classList.toggle('active', isLink);
    tabOrder?.classList.toggle('active', isOrder);
    tabManual?.classList.toggle('active', isManual);

    if (paneLink) paneLink.style.display = isLink ? 'block' : 'none';
    if (paneOrder) paneOrder.style.display = isOrder ? 'block' : 'none';
    if (paneManual) paneManual.style.display = isManual ? 'block' : 'none';

    if (isLink) customerId?.focus();
  };

  tabLink?.addEventListener('click', () => setTab('link'));
  tabOrder?.addEventListener('click', () => setTab('order'));
  tabManual?.addEventListener('click', () => setTab('manual'));

  // =========================
  // 客番号入力
  // =========================
  const customerIdHidden = document.getElementById('customerIdHidden');
  const submitBtn = document.getElementById('submitBtn');
  const customerSelectForm = document.getElementById('customerSelectForm');
  const customerClearBtn = document.getElementById('customerClearBtn');
  const keypad = document.getElementById('customerKeypad');

  const syncCustomer = () => {
    if (!customerId) return;

    customerId.value = onlyDigits(customerId.value).slice(0, 7);

    if (customerIdHidden) customerIdHidden.value = customerId.value;

    const ok = customerId.value.length === 7;
    if (submitBtn) {
      submitBtn.disabled = !ok;
      submitBtn.classList.toggle('btn-disabled', !ok);
    }
  };

  const submitCustomerIfValid = () => {
    syncCustomer();
    if (customerId && customerId.value.length === 7) {
      customerSelectForm?.submit();
    }
  };

  customerId?.addEventListener('input', syncCustomer);

  customerId?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      submitCustomerIfValid();
    }
  });

  customerClearBtn?.addEventListener('click', () => {
    if (!customerId) return;
    customerId.value = '';
    syncCustomer();
    customerId.focus();
  });

  keypad?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-key]');
    if (!btn || !customerId) return;

    const key = btn.dataset.key;
    let value = customerId.value;

    if (key === 'back') {
      value = value.slice(0, -1);
    } else if (key === 'clear') {
      value = '';
    } else if (/^\d$/.test(key || '') && value.length < 7) {
      value += key;
    }

    customerId.value = value;
    syncCustomer();
  });

  customerSelectForm?.addEventListener('submit', (e) => {
    syncCustomer();
    if (!customerIdHidden?.value || customerIdHidden.value.length !== 7) {
      e.preventDefault();
    }
  });

  syncCustomer();

  // =========================
  // 注文選択
  // =========================
  const orderSearchForm = document.getElementById('orderSearchForm');
  const orderSearchClearBtn = document.getElementById('orderSearchClearBtn');
  const orderStoreId = document.getElementById('orderStoreId');
  const fromTime = document.getElementById('fromTime');
  const toTime = document.getElementById('toTime');
  const orderTbody = document.getElementById('orderTbody');

  const now = new Date();
  const from = new Date();
  from.setHours(5, 0, 0, 0);

  if (fromTime && !fromTime.value) fromTime.value = toLocalDatetimeValue(from);
  if (toTime && !toTime.value) toTime.value = toLocalDatetimeValue(now);

  const calcBillStatus = () => {
    const checked = Array.from(document.querySelectorAll('input[name="billStates[]"]:checked'))
      .map((el) => Number(el.value))
      .filter((v) => !Number.isNaN(v));

    if (checked.length === 0) return null;
    return checked.reduce((sum, v) => sum | v, 0);
  };

  const renderOrderEmpty = (message) => {
    if (!orderTbody) return;
    orderTbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align:center; color:#64748b; padding:24px;">
          ${escapeHtml(message)}
        </td>
      </tr>
    `;
  };

  const renderOrderTable = (orders) => {
    if (!orderTbody) return;

    if (!Array.isArray(orders) || orders.length === 0) {
      renderOrderEmpty('該当する注文がありません');
      return;
    }

    orderTbody.innerHTML = orders.map((order) => {
      const itemsHtml = (order.items || []).length
        ? order.items.map((item) =>
            `<div>${escapeHtml(item.menuName)} ×${Number(item.orderQty || 0)}</div>`
          ).join('')
        : '<div>-</div>';

      return `
        <tr>
          <td>${escapeHtml(order.customerId)}</td>
          <td>${escapeHtml(billStatusLabel(order.billStatus))}</td>
          <td>${escapeHtml(order.entryTime)}</td>
          <td>${itemsHtml}</td>
          <td class="t-right">
            <form method="post" action="${BASE}/customer/select-order" style="margin:0;">
              <input type="hidden" name="customerId" value="${escapeHtml(order.customerId)}">
              <input type="hidden" name="hash" value="${escapeHtml(order.hash)}">
              <button type="submit" class="btn btn-primary">選択</button>
            </form>
          </td>
        </tr>
      `;
    }).join('');
  };

  orderSearchClearBtn?.addEventListener('click', () => {
    document.querySelectorAll('input[name="billStates[]"]').forEach((el) => {
      el.checked = Number(el.value) === 1;
    });

    if (orderStoreId) orderStoreId.value = '';
    if (fromTime) fromTime.value = toLocalDatetimeValue(from);
    if (toTime) toTime.value = toLocalDatetimeValue(new Date());

    renderOrderEmpty('条件を指定して検索してください');
  });

  orderSearchForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      billStatus: calcBillStatus(),
      storeId: orderStoreId?.value ?? '',
      fromTime: toIsoSeconds(fromTime?.value ?? ''),
      toTime: toIsoSeconds(toTime?.value ?? '')
    };

    console.log('注文検索 payload:', payload);

    renderOrderEmpty('検索中です...');

    try {
      const url = `${BASE}/customer/search-orders`;
      console.log('注文検索 URL:', url);

      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const raw = await res.text();

      console.log('注文検索 HTTP status:', res.status);
      console.log('注文検索 raw response:', raw);

      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (jsonErr) {
        renderOrderEmpty(
          `注文検索に失敗しました。JSONではないレスポンスです。HTTP ${res.status} / ${raw.slice(0, 300)}`
        );
        return;
      }

      if (!res.ok || !data?.ok) {
        renderOrderEmpty(
          data?.message || `注文検索に失敗しました。HTTP ${res.status}`
        );
        return;
      }

      renderOrderTable(data.orders || []);
    } catch (err) {
      console.error('注文検索 fetch error:', err);
      renderOrderEmpty(`通信エラーが発生しました: ${err?.message || err}`);
    }
  });

  // =========================
  // 手動金額入力
  // =========================
  const mPrice = document.getElementById('mPrice');
  const mQty = document.getElementById('mQty');
  const mTax = document.getElementById('mTax');

  const priceDisplay = document.getElementById('priceDisplay');
  const qtyDisplay = document.getElementById('qtyDisplay');
  const priceDisplayBtn = document.getElementById('priceDisplayBtn');
  const qtyDisplayBtn = document.getElementById('qtyDisplayBtn');

  const manualKeypad = document.getElementById('manualKeypad');
  const addRowBtn = document.getElementById('addRowBtn');
  const tbody = document.querySelector('#manualTable tbody');
  const sumEx = document.getElementById('sumEx');
  const sumTax = document.getElementById('sumTax');
  const sumIn = document.getElementById('sumIn');
  const manualClear = document.getElementById('manualClear');
  const itemsJson = document.getElementById('itemsJson');
  const manualCheckoutForm = document.getElementById('manualCheckoutForm');

  let activeManualField = 'price';

  const MANUAL_LIMITS = {
    price: 999999,
    qty: 99,
  };

  const normalizeManualPrice = (value) => {
    let num = parseInt(String(value ?? '0'), 10);
    if (Number.isNaN(num) || num < 0) num = 0;
    if (num > MANUAL_LIMITS.price) num = MANUAL_LIMITS.price;
    return num;
  };

  const normalizeManualQty = (value) => {
    let num = parseInt(String(value ?? '1'), 10);
    if (Number.isNaN(num) || num < 1) num = 1;
    if (num > MANUAL_LIMITS.qty) num = MANUAL_LIMITS.qty;
    return num;
  };

  const syncManualDisplay = () => {
    const safePrice = normalizeManualPrice(mPrice?.value || '0');
    const safeQty = normalizeManualQty(mQty?.value || '1');

    if (mPrice) mPrice.value = String(safePrice);
    if (mQty) mQty.value = String(safeQty);

    if (priceDisplay) priceDisplay.textContent = safePrice.toLocaleString('ja-JP');
    if (qtyDisplay) qtyDisplay.textContent = safeQty.toLocaleString('ja-JP');

    priceDisplayBtn?.classList.toggle('is-active', activeManualField === 'price');
    qtyDisplayBtn?.classList.toggle('is-active', activeManualField === 'qty');
  };

  priceDisplayBtn?.addEventListener('click', () => {
    activeManualField = 'price';
    syncManualDisplay();
  });

  qtyDisplayBtn?.addEventListener('click', () => {
    activeManualField = 'qty';
    syncManualDisplay();
  });

  manualKeypad?.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;

    const key = btn.dataset.key;
    const action = btn.dataset.action;
    const target = activeManualField === 'price' ? mPrice : mQty;
    if (!target) return;

    const isQty = activeManualField === 'qty';
    const fallback = isQty ? '1' : '0';
    const maxValue = isQty ? MANUAL_LIMITS.qty : MANUAL_LIMITS.price;

    let value = String(parseInt(target.value || fallback, 10));
    if (!value || Number.isNaN(parseInt(value, 10))) {
      value = fallback;
    }

    if (key != null) {
      if (isQty) {
        value = value === '1' ? key : value + key;
      } else {
        value = value === '0' ? key : value + key;
      }
    }

    if (action === 'clear') {
      value = fallback;
    }

    if (action === 'back') {
      value = value.length <= 1 ? fallback : value.slice(0, -1);
    }

    let num = parseInt(value, 10);
    if (Number.isNaN(num)) {
      num = isQty ? 1 : 0;
    }

    if (isQty && num < 1) {
      num = 1;
    }

    if (num > maxValue) {
      num = maxValue;
    }

    target.value = String(num);
    syncManualDisplay();
  });

  const getItems = () => {
    const items = [];
    tbody?.querySelectorAll('tr').forEach((tr) => {
      const price = parseInt(tr.dataset.price, 10);
      const qty = parseInt(tr.dataset.qty, 10);
      const taxRate = parseInt(tr.dataset.tax, 10);

      if (Number.isNaN(price) || Number.isNaN(qty) || Number.isNaN(taxRate)) {
        return;
      }

      items.push({
        name: '手入力商品',
        price: price,
        qty: qty,
        tax_rate: taxRate,
        category_name: '手入力'
      });
    });
    return items;
  };

  const recalc = () => {
    let ex = 0;
    let tax = 0;
    let inc = 0;

    tbody?.querySelectorAll('tr').forEach((tr) => {
      const p = parseInt(tr.dataset.price, 10);
      const q = parseInt(tr.dataset.qty, 10);
      const t = parseInt(tr.dataset.tax, 10);

      const lineEx = p * q;
      const lineTax = Math.floor(lineEx * (t / 100));
      const lineIn = lineEx + lineTax;

      ex += lineEx;
      tax += lineTax;
      inc += lineIn;

      const subtotalCell = tr.querySelector('[data-cell="subtotal"]');
      if (subtotalCell) {
        subtotalCell.textContent = yen(lineIn);
      }
    });

    if (sumEx) sumEx.textContent = yen(ex);
    if (sumTax) sumTax.textContent = yen(tax);
    if (sumIn) sumIn.textContent = yen(inc);
    if (itemsJson) itemsJson.value = JSON.stringify(getItems());
  };

  const addRow = (price, qty, taxRate) => {
    if (!tbody) return;

    const safePrice = normalizeManualPrice(price);
    const safeQty = normalizeManualQty(qty);

    const tr = document.createElement('tr');
    tr.dataset.price = String(safePrice);
    tr.dataset.qty = String(safeQty);
    tr.dataset.tax = String(taxRate);

    tr.innerHTML = `
      <td>${yen(safePrice)}</td>
      <td class="t-right">${safeQty}</td>
      <td class="t-right">${taxRate}%</td>
      <td class="t-right" data-cell="subtotal">${yen(0)}</td>
      <td class="t-right"><button class="icon-btn icon-trash" type="button" title="削除">🗑</button></td>
    `;

    tr.querySelector('button')?.addEventListener('click', () => {
      tr.remove();
      recalc();
    });

    tbody.appendChild(tr);
    recalc();
  };

  const restoreManualItems = () => {
    const initialItems = Array.isArray(window.__INITIAL_MANUAL_ITEMS__)
      ? window.__INITIAL_MANUAL_ITEMS__
      : [];

    if (!initialItems.length) return;

    if (tbody) tbody.innerHTML = '';

    initialItems.forEach((item) => {
      const price = normalizeManualPrice(item.price ?? '0');
      const qty = normalizeManualQty(item.qty ?? '1');
      const taxRate = Math.max(0, parseInt(item.tax_rate ?? '10', 10));

      if (price > 0 && qty > 0) {
        addRow(price, qty, taxRate);
      }
    });
  };

  addRowBtn?.addEventListener('click', () => {
    const price = normalizeManualPrice(mPrice?.value || '0');
    const qty = normalizeManualQty(mQty?.value || '1');
    const taxRate = Math.max(0, parseInt(mTax?.value || '10', 10));

    if (price <= 0) {
      alert('単価は1〜999999の範囲で入力してください。');
      activeManualField = 'price';
      syncManualDisplay();
      return;
    }

    if (qty < 1 || qty > MANUAL_LIMITS.qty) {
      alert('個数は1〜99の範囲で入力してください。');
      activeManualField = 'qty';
      syncManualDisplay();
      return;
    }

    addRow(price, qty, taxRate);

    if (mPrice) mPrice.value = '0';
    if (mQty) mQty.value = '1';
    activeManualField = 'price';
    syncManualDisplay();
  });

  manualClear?.addEventListener('click', () => {
    if (tbody) tbody.innerHTML = '';
    if (mPrice) mPrice.value = '0';
    if (mQty) mQty.value = '1';
    if (mTax) mTax.value = '10';
    activeManualField = 'price';
    syncManualDisplay();
    recalc();
  });

  manualCheckoutForm?.addEventListener('submit', (e) => {
    const items = getItems();

    if (items.length === 0) {
      e.preventDefault();
      alert('商品を1件以上追加してください。');
      return;
    }

    const invalidItem = items.find((item) => {
      const price = parseInt(item.price ?? '0', 10);
      const qty = parseInt(item.qty ?? '0', 10);
      return Number.isNaN(price) || price < 0 || price > 999999
          || Number.isNaN(qty) || qty < 1 || qty > 99;
    });

    if (invalidItem) {
      e.preventDefault();
      alert('単価は999999以下、個数は99以下で入力してください。');
      return;
    }

    if (itemsJson) {
      itemsJson.value = JSON.stringify(items);
    }
  });

  syncManualDisplay();
  recalc();
  restoreManualItems();

  const initialTab = window.__INITIAL_ACTIVE_TAB__ || 'link';
  if (initialTab === 'manual') {
    setTab('manual');
  } else if (initialTab === 'order') {
    setTab('order');
  } else {
    setTab('link');
  }
});
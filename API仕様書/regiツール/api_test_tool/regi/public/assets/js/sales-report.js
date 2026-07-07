(() => {
  const BASE = document.querySelector('meta[name="base-url"]')?.content ?? '';

  const storeEl = document.getElementById('srStore');
  const dateEl = document.getElementById('srDate');
  const tabs = Array.from(document.querySelectorAll('.sr-tab'));

  const kpiTotal = document.getElementById('kpiTotal');
  const kpiMode = document.getElementById('kpiMode');

  const chartEl = document.getElementById('srChart');
  const tbodyEl = document.getElementById('srTbody');

  const btnReload = document.getElementById('btnReload');
  const btnWeekPrev = document.getElementById('btnWeekPrev');
  const btnWeekNext = document.getElementById('btnWeekNext');
  const weekShiftWrap = document.getElementById('srWeekShift');

  let mode = tabs.find(t => t.classList.contains('is-active'))?.dataset.mode || 'daily';
  let weekOffset = 0;

  const yen = (n) => '¥' + Number(n || 0).toLocaleString('ja-JP');

  const escapeHtml = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

  const setActiveTab = (nextMode) => {
    mode = nextMode;

    tabs.forEach((tab) => {
      const active = tab.dataset.mode === nextMode;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    if (weekShiftWrap) {
      weekShiftWrap.style.display = nextMode === 'weekly' ? 'flex' : 'none';
    }

    if (nextMode !== 'weekly') {
      weekOffset = 0;
    }
  };

  const buildUrl = (path, params) => {
    const url = new URL(BASE + path, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.set(key, String(value));
    });
    return url.toString();
  };

  const getBarSizeClass = (count) => {
    if (count <= 7) return 'bars-large';
    if (count <= 12) return 'bars-medium';
    return 'bars-normal';
  };

  const formatChartLabel = (label, currentMode) => {
    const raw = String(label ?? '');

    if (currentMode === 'monthly') {
      const m = raw.match(/^(\d+日)\((.)\)$/);
      if (m) {
        return `${escapeHtml(m[1])}<br><span class="sr-bar__label-sub">(${escapeHtml(m[2])})</span>`;
      }
    }

    return escapeHtml(raw);
  };

  const render = (data) => {
    kpiTotal.textContent = yen(data.totalSales ?? 0);
    kpiMode.textContent = data.modeLabel ?? '-';

    renderChart(data.chart ?? { labels: [], values: [] }, data.mode ?? mode);
    renderTable(data.rows ?? []);

    if (mode === 'weekly') {
      if (btnWeekPrev) btnWeekPrev.disabled = !(data.canShiftPrev ?? false);
      if (btnWeekNext) btnWeekNext.disabled = !(data.canShiftNext ?? false);
    }
  };

  const renderChart = (chart, currentMode) => {
    const labels = Array.isArray(chart.labels) ? chart.labels : [];
    const values = Array.isArray(chart.values) ? chart.values : [];

    if (!labels.length || !values.length) {
      chartEl.innerHTML = `<div class="sr-empty">データがありません</div>`;
      return;
    }

    const count = labels.length;
    const sizeClass = getBarSizeClass(count);
    const numericValues = values.map(v => Number(v || 0));
    const max = Math.max(...numericValues, 1);

    const bars = labels.map((label, index) => {
      const value = Number(values[index] ?? 0);
      const heightPercent = value > 0 ? Math.max(6, Math.round((value / max) * 100)) : 0;
      const isMax = value === max && max > 0;

      return `
        <div class="sr-bar-item" title="${escapeHtml(label)} : ${escapeHtml(yen(value))}">
          <div class="sr-bar-track">
            <div class="sr-bar-value">${value > 0 ? escapeHtml(yen(value)) : ''}</div>
            <div class="sr-bar__col ${isMax ? 'is-max' : ''}" style="height:${heightPercent}%"></div>
          </div>
          <div class="sr-bar__label">${formatChartLabel(label, currentMode)}</div>
        </div>
      `;
    }).join('');

    chartEl.innerHTML = `<div class="sr-bars ${sizeClass}">${bars}</div>`;
  };

  const renderTable = (rows) => {
    if (!rows.length) {
      tbodyEl.innerHTML = `
        <tr>
          <td colspan="2" class="sr-empty-cell">データがありません</td>
        </tr>
      `;
      return;
    }

    tbodyEl.innerHTML = rows.map((row) => `
      <tr>
        <td>${escapeHtml(row.label)}</td>
        <td class="r">${escapeHtml(yen(row.sales))}</td>
      </tr>
    `).join('');
  };

  const showError = (message) => {
    chartEl.innerHTML = `<div class="sr-empty">${escapeHtml(message)}</div>`;
    tbodyEl.innerHTML = `
      <tr>
        <td colspan="2" class="sr-empty-cell">${escapeHtml(message)}</td>
      </tr>
    `;
    kpiTotal.textContent = '¥0';
    kpiMode.textContent = '-';
  };

  const load = async () => {
    try {
      const store = storeEl?.value ?? 'all';
      const date = dateEl?.value ?? '';

      const url = buildUrl('/sales/report/data', {
        store,
        mode,
        date,
        week_offset: weekOffset
      });

      const res = await fetch(url, { method: 'GET' });
      const json = await res.json();

      if (!res.ok || !json.ok) {
        throw new Error(json?.message || '売上レポートの取得に失敗しました。');
      }

      render(json.data);
    } catch (error) {
      console.error(error);
      showError(error?.message || 'データの読み込みに失敗しました。');
    }
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      setActiveTab(tab.dataset.mode || 'daily');
      load();
    });
  });

  // 左へ行くほど過去を含むようにする
  btnWeekPrev?.addEventListener('click', () => {
    if (weekOffset < 6) {
      weekOffset += 1;
      load();
    }
  });

  // 右へ行くほど未来側へ戻す
  btnWeekNext?.addEventListener('click', () => {
    if (weekOffset > 0) {
      weekOffset -= 1;
      load();
    }
  });

  storeEl?.addEventListener('change', load);
  dateEl?.addEventListener('change', () => {
    weekOffset = 0;
    load();
  });
  btnReload?.addEventListener('click', load);

  setActiveTab(mode);
  load();
})();
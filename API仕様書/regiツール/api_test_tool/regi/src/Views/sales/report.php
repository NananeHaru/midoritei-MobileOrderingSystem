<?php
$BASE = '/regi/public';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="base-url" content="<?= htmlspecialchars($BASE, ENT_QUOTES, 'UTF-8') ?>">

  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/sales-report.css">
  <script defer src="<?= $BASE ?>/assets/js/sales-report.js?v=2"></script>

  <title><?= htmlspecialchars($title ?? '売上レポート', ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body class="sales-report-page">
<header class="app-header">
  <div class="app-header__left">
    <a class="app-back" href="<?= $BASE ?>/home" aria-label="戻る">←</a>
    <h1 class="app-title">売上レポート</h1>
  </div>
</header>

<main class="sr-main">
  <section class="sr-top">
    <div class="sr-controls">
      <select id="srStore" class="sr-select" aria-label="店舗選択">
        <?php foreach ($stores as $s): ?>
          <option value="<?= htmlspecialchars($s['id']) ?>" <?= $s['id'] === $storeId ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input id="srDate" class="sr-date" type="date" value="<?= htmlspecialchars($date) ?>">

      <button type="button" class="sr-btn sr-btn--primary sr-btn--top" id="btnReload">更新</button>
    </div>
  </section>

  <section class="sr-tabs" role="tablist" aria-label="集計単位">
    <?php
      $tabs = [
        ['k'=>'daily','label'=>'日次'],
        ['k'=>'weekly','label'=>'週次'],
        ['k'=>'monthly','label'=>'月次'],
        ['k'=>'yearly','label'=>'年次']
      ];
    ?>
    <?php foreach ($tabs as $t): ?>
      <button
        type="button"
        class="sr-tab <?= $t['k'] === $mode ? 'is-active' : '' ?>"
        data-mode="<?= htmlspecialchars($t['k']) ?>"
        role="tab"
        aria-selected="<?= $t['k'] === $mode ? 'true' : 'false' ?>"
      ><?= htmlspecialchars($t['label']) ?></button>
    <?php endforeach; ?>
  </section>

  <section class="sr-week-shift" id="srWeekShift" style="display:none;">
    <button type="button" class="sr-btn sr-btn--ghost" id="btnWeekPrev">←</button>
    <div class="sr-week-shift__label">週の並びをずらす</div>
    <button type="button" class="sr-btn sr-btn--ghost" id="btnWeekNext">→</button>
  </section>

  <section class="sr-kpis">
    <div class="sr-kpi">
      <div class="sr-kpi__label">総売上</div>
      <div class="sr-kpi__value" id="kpiTotal">-</div>
    </div>
    <div class="sr-kpi sr-kpi--muted" hidden>
      <div class="sr-kpi__label">集計</div>
      <div class="sr-kpi__value" id="kpiMode">-</div>
    </div>
  </section>

  <section class="sr-card">
    <div class="sr-card__head">
      <div class="sr-card__title">売上推移</div>
    </div>
    <div class="sr-chart" id="srChart" aria-label="売上推移グラフ"></div>
  </section>

  <section class="sr-card">
    <div class="sr-card__head">
      <div class="sr-card__title">内訳</div>
    </div>

    <div class="sr-tablewrap">
      <table class="sr-table">
        <thead>
          <tr>
            <th>区分</th>
            <th class="r">売上金額</th>
          </tr>
        </thead>
        <tbody id="srTbody"></tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
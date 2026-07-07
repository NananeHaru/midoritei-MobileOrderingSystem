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
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/closing.css">
  <script defer src="<?= $BASE ?>/assets/js/closing.js"></script>

  <title><?= htmlspecialchars($title ?? 'レジ締め', ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body class="close-page">
<header class="app-header">
  <div class="app-header__left">
    <a class="app-back" href="<?= $BASE ?>/home" aria-label="戻る">←</a>
    <h1 class="app-title">レジ締</h1>
  </div>
</header>

<main class="page page-closing">
  <?php if (!empty($_SESSION['flash_success'] ?? '')): ?>
    <div class="flash flash-success">
      <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_error'] ?? '')): ?>
    <div class="flash flash-error">
      <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <section class="top-info-card card close-top">
    <div class="close-top__left">
      <div class="close-top__date"><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="close-top__right">
      <label class="close-top__label" for="operatorName">レジ締担当</label>
      <div class="close-top__operator">
        <input
          id="operatorName"
          name="operatorName"
          class="input close-top__input"
          type="text"
          value="<?= htmlspecialchars($operatorName, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="例：山田太郎"
        >
      </div>
    </div>
  </section>

  <section class="closing-grid">
    <div class="closing-col-left">
      <div class="card closing-left">
        <h2 class="card-title">売上集計</h2>

        <div class="closing-total">
          <div class="closing-total__amount">¥<?= number_format((int)$salesTotal) ?></div>
          <div class="closing-total__meta">会計済み件数：<?= (int)$paidCount ?>件</div>
        </div>

        <div class="table-like">
          <div class="table-like__head">
            <div>決済方法</div>
            <div class="t-right">金額</div>
            <div class="t-right">件数</div>
          </div>
          <?php foreach ($paymentRows as $r): ?>
            <div class="table-like__row">
              <div><?= htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="t-right">¥<?= number_format((int)$r['amount']) ?></div>
              <div class="t-right"><?= (int)$r['count'] ?>件</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card closing-ar">
        <h2 class="card-title">未会計</h2>

        <div class="ar-summary">
          <div class="ar-summary__amount">¥<?= number_format((int)$arTotal) ?></div>
          <div class="ar-summary__meta">未収件数：<?= (int)$arCount ?>件</div>
        </div>

        <div class="ar-simple-box">
          <?php if (!empty($arRows)): ?>
            <div class="table-like">
              <div class="table-like__head">
                <div>顧客ID</div>
                <div>入店日時</div>
                <div class="t-right">合計金額</div>
              </div>
              <?php foreach ($arRows as $row): ?>
                <div class="table-like__row">
                  <div><?= htmlspecialchars((string)$row['customer_id'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div><?= htmlspecialchars((string)$row['entry_time'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="t-right">¥<?= number_format((int)$row['total_amount']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="ar-simple-box__text">未会計はありません</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card closing-right">
      <h2 class="card-title">レジ金管理</h2>
      <div class="closing-right__scroll">

        <div class="form-row">
          <label class="label">レジ開始金額</label>
          <div class="input-with-btn">
            <input
              id="registerStartAmount"
              class="input js-keypad-target"
              type="text"
              inputmode="none"
              readonly
              value="<?= (int)$registerStartAmount ?>"
              data-field="start"
              placeholder="0"
            >
          </div>
        </div>

        <div class="divider"></div>
        <div class="sub-title">紙幣・硬貨の枚数</div>

        <div class="denoms">
          <?php
            $denomList = [
              ['k' => '10000', 'label' => '10000円札'],
              ['k' => '5000',  'label' => '5000円札'],
              ['k' => '1000',  'label' => '1000円札'],
              ['k' => '500',   'label' => '500円玉'],
              ['k' => '100',   'label' => '100円玉'],
              ['k' => '50',    'label' => '50円玉'],
              ['k' => '10',    'label' => '10円玉'],
              ['k' => '5',     'label' => '5円玉'],
              ['k' => '1',     'label' => '1円玉'],
            ];
            foreach ($denomList as $d):
          ?>
            <div class="denom-row">
              <div class="denom-label"><?= htmlspecialchars($d['label'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="input-with-btn">
                <input
                  id="denom-<?= htmlspecialchars($d['k'], ENT_QUOTES, 'UTF-8') ?>"
                  class="input js-keypad-target denom-count"
                  type="text"
                  inputmode="none"
                  readonly
                  value="0"
                  data-field="<?= htmlspecialchars($d['k'], ENT_QUOTES, 'UTF-8') ?>"
                  placeholder="0"
                >
                <div class="yen-mini" data-subtotal-for="<?= htmlspecialchars($d['k'], ENT_QUOTES, 'UTF-8') ?>">¥0</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="divider"></div>

        <div class="form-row">
          <label class="label">レジ締め金額</label>
          <div class="big-amount" id="countedAmount">¥0</div>
        </div>

        <div class="diff-box" id="diffBox">
          <div class="diff-box__label">差額</div>
          <div class="diff-box__value" id="diffValue">¥0</div>
          <div class="diff-box__note" id="diffNote"></div>
        </div>

        <div class="expect-box">
          <div>現金売上： <span id="cashSalesLabel">¥<?= number_format((int)$expectedCashSales) ?></span></div>
          <div>期待レジ金額： <strong id="expectedLabel">¥<?= number_format((int)$expectedRegisterAmount) ?></strong></div>
        </div>

        <input type="hidden" id="expectedCashSales" value="<?= (int)$expectedCashSales ?>">
      </div>
    </div>
  </section>

  <section class="bottom-actions">
    <div class="bottom-actions__right">
      <form method="post" action="<?= $BASE ?>/close/store" id="storeForm">
        <input type="hidden" name="payload" id="storePayload" value="">
        <button class="btn btn-orange" id="btnStore" type="submit">レジ締め実行</button>
      </form>
    </div>
  </section>
</main>

<div class="keypad" id="keypad" aria-hidden="true">
  <div class="keypad__panel">
    <div class="keypad__title" id="keypadTitle">入力</div>
    <div class="keypad__display" id="keypadDisplay">0</div>

    <div class="keypad__grid">
      <button type="button" class="kp" data-k="7">7</button>
      <button type="button" class="kp" data-k="8">8</button>
      <button type="button" class="kp" data-k="9">9</button>
      <button type="button" class="kp kp-sub" data-action="bs">⌫</button>

      <button type="button" class="kp" data-k="4">4</button>
      <button type="button" class="kp" data-k="5">5</button>
      <button type="button" class="kp" data-k="6">6</button>
      <button type="button" class="kp kp-sub" data-action="clear">C</button>

      <button type="button" class="kp" data-k="1">1</button>
      <button type="button" class="kp" data-k="2">2</button>
      <button type="button" class="kp" data-k="3">3</button>
      <button type="button" class="kp kp-enter" data-action="enter">Enter</button>

      <button type="button" class="kp kp-zero" data-k="0">0</button>
      <button type="button" class="kp kp-sub" data-action="close">閉じる</button>
    </div>
  </div>
</div>

</body>
</html>
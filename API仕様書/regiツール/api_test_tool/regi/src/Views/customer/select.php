<?php
$title = '会計入力';
require dirname(__DIR__) . '/layout/base.php';

$manualItems = $manualItems ?? [];
$activeTab = $activeTab ?? 'link';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/select.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/checkout.css">
<script src="<?= $BASE_URL ?>/assets/js/select.js" defer></script>

<div class="page checkout-input">
  <header class="app-header">
    <div class="app-header__left">
      <a class="app-back" href="<?= $BASE_URL ?>/home" aria-label="戻る">←</a>
      <h1 class="app-title">会計入力</h1>
    </div>
  </header>

  <div class="tabbar">
    <button id="tab-link" class="active" type="button">客番号入力</button>
    <button id="tab-order" type="button">注文選択</button>
    <button id="tab-manual" type="button">手動金額入力</button>
  </div>

  <div class="center-wrap">

    <!-- ===== 注文連携（客番号入力） ===== -->
    <div id="pane-link" class="panel">
      <div class="help-text">7桁の客番号を入力またはバーコード読取してください</div>

      <input
        class="input"
        id="customerId"
        name="customerId"
        type="text"
        inputmode="none"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        maxlength="7"
        placeholder="0000000"
        aria-label="客番号"
        style="max-width:420px;margin:0 auto;display:block;text-align:center;font-size:22px;font-weight:1000;letter-spacing:.18em;"
      >

      <div class="keypad big" id="customerKeypad" style="margin-top:16px">
        <?php foreach ([7, 8, 9, 4, 5, 6, 1, 2, 3] as $n): ?>
          <button class="key" type="button" data-key="<?= $n ?>"><?= $n ?></button>
        <?php endforeach; ?>
        <button class="key danger" type="button" data-key="back">⌫</button>
        <button class="key" type="button" data-key="0">0</button>
        <button class="key orange" type="button" data-key="clear">C</button>
      </div>

      <div class="help-text" style="margin-top:12px;">
        ※ バーコードリーダ、または画面上のテンキーで客番号を入力できます。<br>
        ※ 客番号は7桁の数字のみ受け付けます。
      </div>

      <div class="bottom-actions" style="gap:12px;justify-content:center;">
        <button class="btn btn-outline btn-lg" type="button" id="customerClearBtn">クリア</button>

        <form method="post" action="<?= $BASE_URL ?>/customer/select" id="customerSelectForm" style="margin:0">
          <input type="hidden" name="customerId" id="customerIdHidden">
          <button class="btn btn-primary btn-lg btn-disabled" type="submit" id="submitBtn" disabled>確定</button>
        </form>
      </div>
    </div>

    <!-- ===== 注文選択 ===== -->
    <div id="pane-order" class="panel" style="display:none">
      <div class="manual-title">注文選択</div>

      <section class="manual-block" style="max-width:1100px; margin:0 auto;">
        <div class="manual-block__title">検索条件</div>

        <form id="orderSearchForm" class="order-search-form">
          <div class="order-search-grid">
            <div class="field">
              <label class="label">会計状況</label>
              <div class="check-group">
                <label><input type="checkbox" name="billStates[]" value="1" checked> 受付中</label>
                <label><input type="checkbox" name="billStates[]" value="8"> 会計中</label>
                <label><input type="checkbox" name="billStates[]" value="4"> 未収金</label>
              </div>
            </div>

            <div class="field">
              <label for="orderStoreId" class="label">店舗</label>
              <select id="orderStoreId" name="storeId" class="input">
                <option value="">全店舗</option>
                <?php foreach (($stores ?? []) as $store): ?>
                  <option value="<?= h($store['store_id'] ?? '') ?>">
                    <?= h($store['store_name'] ?? '') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="fromTime" class="label">日時 From</label>
              <input type="datetime-local" id="fromTime" name="fromTime" class="input">
            </div>

            <div class="field">
              <label for="toTime" class="label">日時 To</label>
              <input type="datetime-local" id="toTime" name="toTime" class="input">
            </div>
          </div>

          <div class="bottom-actions" style="justify-content:flex-end; margin-top:16px;">
            <button type="button" class="btn btn-outline btn-lg" id="orderSearchClearBtn">条件クリア</button>
            <button type="submit" class="btn btn-primary btn-lg" id="orderSearchBtn">検索</button>
          </div>
        </form>
      </section>

      <section class="manual-block" style="max-width:1100px; margin:20px auto 0;">
        <div class="manual-block__title">注文一覧</div>

        <div class="table-wrap">
          <table class="table-mini" id="orderTable">
            <thead>
              <tr>
                <th>客番号</th>
                <th>注文状況</th>
                <th>入店日時</th>
                <th>注文内容</th>
                <th class="t-right">操作</th>
              </tr>
            </thead>
            <tbody id="orderTbody">
              <tr>
                <td colspan="5" style="text-align:center; color:#64748b; padding:24px;">
                  条件を指定して検索してください
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- ===== 手動入力 ===== -->
    <div id="pane-manual" class="panel" style="display:none">
      <div class="manual-title">商品情報入力</div>

      <div class="manual-layout">
        <!-- 左：出力箱 -->
        <section class="manual-block manual-block--output">
          <div class="manual-block__title">登録内容・会計</div>

          <div class="table-wrap">
            <table class="table-mini" id="manualTable">
              <thead>
                <tr>
                  <th>単価（税抜）</th>
                  <th class="t-right">数量</th>
                  <th class="t-right">税率</th>
                  <th class="t-right">小計（税込）</th>
                  <th class="t-right">操作</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="total-card">
            <div class="head">合計</div>
            <div class="body">
              <div class="total-line muted">
                <span>小計（税抜）：</span><span id="sumEx">¥0</span>
              </div>
              <div class="total-line muted">
                <span>消費税：</span><span id="sumTax">¥0</span>
              </div>
              <div class="total-line big">
                <span>合計：</span><span id="sumIn">¥0</span>
              </div>
            </div>
          </div>

          <div class="bottom-actions manual-output-actions">
            <button class="btn btn-outline btn-lg" type="button" id="manualClear">クリア</button>

            <form method="post" action="<?= $BASE_URL ?>/manual/checkout" style="margin:0" id="manualCheckoutForm">
              <input type="hidden" name="items_json" id="itemsJson" value="[]">
              <button class="btn btn-green btn-lg" type="submit">会計へ進む</button>
            </form>
          </div>
        </section>

        <!-- 右：入力箱 -->
        <section class="manual-block manual-block--input">
          <div class="manual-block__title">商品登録</div>

          <div class="manual-entry-card">
            <div class="manual-main-row">
              <div class="manual-box">
                <div class="manual-box__label">単価（税抜）</div>
                <button type="button" class="manual-display is-active" id="priceDisplayBtn">
                  <span id="priceDisplay">0</span>
                </button>
              </div>

              <div class="manual-times">×</div>

              <div class="manual-box qty-box">
                <div class="manual-box__label">数量</div>
                <button type="button" class="manual-display" id="qtyDisplayBtn">
                  <span id="qtyDisplay">1</span>
                </button>
              </div>
            </div>

            <div class="manual-sub-row">
              <div class="manual-tax">
                <label for="mTax" class="label">税率（%）</label>
                <select id="mTax" class="input">
                  <option value="10" selected>10%</option>
                  <option value="8">8%</option>
                  <option value="0">0%</option>
                </select>
              </div>

              <button class="btn btn-primary btn-lg" type="button" id="addRowBtn">明細へ追加</button>
            </div>

            <div class="manual-keypad" id="manualKeypad">
              <button type="button" class="mk" data-key="7">7</button>
              <button type="button" class="mk" data-key="8">8</button>
              <button type="button" class="mk" data-key="9">9</button>

              <button type="button" class="mk" data-key="4">4</button>
              <button type="button" class="mk" data-key="5">5</button>
              <button type="button" class="mk" data-key="6">6</button>

              <button type="button" class="mk" data-key="1">1</button>
              <button type="button" class="mk" data-key="2">2</button>
              <button type="button" class="mk" data-key="3">3</button>

              <button type="button" class="mk sub" data-action="clear">C</button>
              <button type="button" class="mk" data-key="0">0</button>
              <button type="button" class="mk sub" data-action="back">⌫</button>
            </div>
          </div>
        </section>
      </div>

      <input type="hidden" id="mPrice" value="0">
      <input type="hidden" id="mQty" value="1">
    </div>
  </div>
</div>
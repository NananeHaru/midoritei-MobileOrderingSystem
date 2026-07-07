<?php
$title = '注文詳細';
require dirname(__DIR__) . '/layout/base.php';

$customerId  = $customerId ?? ($_SESSION['customerId'] ?? '');
$customerIds = $customerIds ?? ($_SESSION['customer_ids'] ?? []);
$startTime   = $startTime ?? ($_SESSION['start_time'] ?? ($_SESSION['manual_started_at'] ?? ''));
$items       = $items ?? ($_SESSION['order_items'] ?? []);
$discount    = $discount ?? ($_SESSION['discount'] ?? null);
$isManualCheckout = $isManualCheckout ?? !empty($_SESSION['manual_checkout_items']);
$flashError  = $flashError ?? ($_SESSION['flash_error'] ?? null);
$taxBreakdown = $taxBreakdown ?? [];

unset($_SESSION['flash_error']);

if (empty($customerIds) && $customerId !== '') {
    $customerIds = [$customerId];
}

$subtotal = (int)($subtotal ?? 0);
$discountAmount = (int)($discountAmount ?? 0);
$tax = (int)($taxAmount ?? 0);
$total = (int)($totalAmount ?? 0);

$discountLabel = '';
if (!empty($discount) && !empty($discount['type'])) {
    if ($discount['type'] === 'percent') {
        $percent = max(0, min(100, (int)($discount['percent'] ?? 0)));
        $discountLabel = $percent . '%割引';
    } elseif ($discount['type'] === 'amount') {
        $discountLabel = '円引き';
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/order-detail.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/mordal.css">
<script src="<?= $BASE_URL ?>/assets/js/order-detail.js" defer></script>

<div class="screen order-detail">

  <header class="app-header">
    <div class="app-header__left">
      <a href="/regi/public/checkout/order-back" class="app-back" aria-label="注文選択画面へ戻る">←</a>
      <h1 class="app-title">注文詳細</h1>
    </div>
  </header>

  <div class="od-wrap">
    <div class="od-card">

      <?php if ($flashError): ?>
        <div style="margin:16px 16px 0; padding:12px 14px; border-radius:12px; background:#fef2f2; color:#b91c1c; font-size:13px;">
          <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div style="padding:16px 16px 0; color:var(--muted); font-size:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <span>
          開始時間: <?= htmlspecialchars($startTime !== '' ? $startTime : '未設定', ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span>
          <?= $isManualCheckout ? '手入力会計' : '注文連携会計' ?>
        </span>
      </div>

      <?php if (!$isManualCheckout): ?>
        <div style="padding:12px 16px 0;">
          <div class="od-customer-box">
            <div class="od-customer-label">客番号</div>
            <div class="od-customer-list">
              <?php if (!empty($customerIds)): ?>
                <?php foreach ($customerIds as $cid): ?>
                  <span class="summary-chip"><?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="summary-chip">未設定</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div style="padding:12px 16px 0;">
          <div class="od-customer-box">
            <div class="od-customer-label">会計種別</div>
            <div class="od-customer-list">
              <span class="summary-chip">手入力会計</span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div style="padding:16px;">
        <table class="od-table">
          <thead>
            <tr>
              <th>商品名</th>
              <th class="od-right">単価</th>
              <th class="od-right">数量</th>
              <th class="od-right">小計</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($items)): ?>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?= htmlspecialchars((string)($it['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="od-right">¥<?= number_format((int)($it['price'] ?? 0)) ?></td>
                  <td class="od-right"><?= number_format((int)($it['qty'] ?? 0)) ?></td>
                  <td class="od-right">¥<?= number_format((int)($it['price'] ?? 0) * (int)($it['qty'] ?? 0)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="od-right" style="text-align:center; color:var(--muted); padding:20px;">
                  明細がありません
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="od-summary">
        <div class="od-sumrow">
          <span>小計</span>
          <span>¥<?= number_format($subtotal) ?></span>
        </div>

        <?php if ($discountAmount > 0): ?>
          <div class="od-sumrow" style="color:#d97706;">
            <span>
              割引（<?= htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8') ?>
              <?php if (!empty($discount['note'])): ?>
                / <?= htmlspecialchars((string)$discount['note'], ENT_QUOTES, 'UTF-8') ?>
              <?php endif; ?>）
            </span>
            <span>- ¥<?= number_format($discountAmount) ?></span>
          </div>

          <form method="post" action="<?= $BASE_URL ?>/discount/clear" style="margin-top:8px;">
            <button class="btn btn-outline" type="submit">割引解除</button>
          </form>
        <?php endif; ?>

        <?php if (!empty($taxBreakdown)): ?>
          <?php foreach ($taxBreakdown as $row): ?>
            <div class="od-sumrow muted">
              <span>
                消費税（<?= number_format((int)($row['tax_rate'] ?? 0)) ?>%）
              </span>
              <span>
                ¥<?= number_format((int)($row['tax_amount'] ?? 0)) ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="od-sumrow muted">
            <span>消費税</span>
            <span>¥<?= number_format($tax) ?></span>
          </div>
        <?php endif; ?>

        <div class="od-total">
          <span>合計</span>
          <span class="big">¥<?= number_format($total) ?></span>
        </div>
      </div>

    </div>
  </div>

  <div class="od-footer">
    <button class="btn btn-warn btn-lg btn-block" type="button" id="btnDiscount">割引適用</button>

    <?php if (!$isManualCheckout): ?>
      <button class="btn btn-green btn-lg btn-block" type="button" id="btnAddCustomer">客番号追加</button>
    <?php endif; ?>

    <a class="btn btn-primary btn-lg btn-block" href="<?= $BASE_URL ?>/settlement">会計へ進む</a>
  </div>

  <!-- 割引適用モーダル -->
  <div class="od-backdrop" id="discountModal" aria-hidden="true">
    <div class="od-modal" role="dialog" aria-modal="true" aria-label="割引適用">
      <div class="od-modal-head">
        <div>割引適用</div>
      </div>

      <div class="od-modal-body">
        <div style="color:var(--muted); font-size:12px; margin-bottom:10px;">
          割引の種類を選んで入力してください
        </div>

        <div class="tabs" style="margin:0 0 12px">
          <button class="tab active" type="button" data-dtype="percent">％割引</button>
          <button class="tab" type="button" data-dtype="amount">円引き</button>
        </div>

        <form method="post" action="<?= $BASE_URL ?>/discount/apply" id="discountForm">
          <input type="hidden" name="type" id="dtype" value="percent">

          <div class="field" id="percentField">
            <div class="label">割引率（%）</div>
            <input class="input" name="percent" id="dpercent" inputmode="numeric" placeholder="例: 10">
          </div>

          <div class="field" id="amountField" style="display:none;">
            <div class="label">割引額（円）</div>
            <input class="input" name="amount" id="damount" inputmode="numeric" placeholder="例: 500" disabled>
          </div>

          <div class="field">
            <div class="label">メモ（任意）</div>
            <input class="input" name="note" placeholder="例: クーポン、学割 など">
          </div>

          <div class="od-modal-foot">
            <button class="btn btn-outline btn-lg" type="button" data-close="discount">キャンセル</button>
            <button class="btn btn-primary btn-lg btn-block" type="submit">適用</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (!$isManualCheckout): ?>
    <!-- 客番号追加モーダル -->
    <div class="od-backdrop" id="addCustomerModal" aria-hidden="true">
      <div class="od-modal" role="dialog" aria-modal="true" aria-label="客番号追加">
        <div class="od-modal-head">
          <div>客番号追加</div>
        </div>

        <div class="od-modal-body">
          <div style="color:var(--muted); font-size:12px; margin-bottom:10px;">
            追加する客番号を入力してください
          </div>

          <div class="od-pin" id="pinText"></div>

          <div class="od-keypad">
            <?php foreach ([7,8,9,4,5,6,1,2,3] as $n): ?>
              <button class="od-key" type="button" data-key="<?= $n ?>"><?= $n ?></button>
            <?php endforeach; ?>
            <button class="od-key danger" type="button" data-action="back">⌫</button>
            <button class="od-key" type="button" data-key="0">0</button>
            <button class="od-key orange" type="button" data-action="clear">クリア</button>
          </div>
        </div>

        <div class="od-modal-foot">
          <button class="btn btn-outline btn-lg" type="button" id="btnCancelAddCustomer">キャンセル</button>

          <form method="post" action="<?= $BASE_URL ?>/customer/add" style="flex:1;">
            <input type="hidden" name="addCustomerId" id="addCustomerId">
            <button class="btn btn-primary btn-lg btn-block" type="submit" id="btnApplyAddCustomer" disabled>
              追加
            </button>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>
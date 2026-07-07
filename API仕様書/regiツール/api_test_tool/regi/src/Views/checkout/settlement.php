<?php
$title = '会計';
require dirname(__DIR__) . '/layout/base.php';

/** @var string $customerId */
/** @var array $customerIds */
/** @var string $startTime */
/** @var array $details */
/** @var int $subtotal */
/** @var int $discountAmount */
/** @var int $taxAmount */
/** @var int $totalAmount */
/** @var array $taxBreakdown */
/** @var bool $isManual */
/** @var string|null $manualStartedAt */
/** @var string $splitMode */
/** @var int $personCount */
/** @var array $splitPayments */
/** @var int $remainingAmount */
/** @var array $personSplitAmounts */
/** @var string|null $flashWarning */
/** @var array $itemRemainingDetails */
/** @var array $itemPaidIndexes */
/** @var int $itemSelectedTotal */
/** @var int $itemSelectedCount */

$customerId = $customerId ?? '';
$customerIds = $customerIds ?? [];
$startTime = $startTime ?? '';
$details = $details ?? [];
$subtotal = (int)($subtotal ?? 0);
$discountAmount = (int)($discountAmount ?? 0);
$taxAmount = (int)($taxAmount ?? 0);
$totalAmount = (int)($totalAmount ?? 0);
$taxBreakdown = $taxBreakdown ?? [];
$isManual = (bool)($isManual ?? false);
$manualStartedAt = $manualStartedAt ?? null;

$splitMode = $splitMode ?? 'NONE';
$personCount = (int)($personCount ?? 0);
$splitPayments = $splitPayments ?? [];
$remainingAmount = (int)($remainingAmount ?? $totalAmount);
$personSplitAmounts = $personSplitAmounts ?? [];
$flashWarning = $flashWarning ?? null;

$itemRemainingDetails = $itemRemainingDetails ?? [];
$itemPaidIndexes = $itemPaidIndexes ?? [];
$itemSelectedTotal = (int)($itemSelectedTotal ?? 0);
$itemSelectedCount = (int)($itemSelectedCount ?? 0);

$BASE_URL = '/regi/public';

$formAction = $BASE_URL . '/checkout/execute';
$backUrl = $BASE_URL . '/checkout/back';
$storeId = $_SESSION['store_id'] ?? '01';

if (empty($customerIds) && $customerId !== '') {
    $customerIds = [$customerId];
}

$paidAmount = 0;
foreach ($splitPayments as $payment) {
    $paidAmount += (int)($payment['pay_amount'] ?? 0);
}

$isPaymentSplitMode = in_array($splitMode, ['PERSON', 'AMOUNT'], true);
$isItemSplitMode = ($splitMode === 'ITEM');
$isSplitMode = $isPaymentSplitMode || $isItemSplitMode;
$isCompleted = ($remainingAmount === 0);
$hasConfirmedPayment = ($paidAmount > 0) || !empty($splitPayments);

$currentPersonIndex = count($splitPayments);
$currentPersonAmount = 0;
if ($splitMode === 'PERSON' && isset($personSplitAmounts[$currentPersonIndex])) {
    $currentPersonAmount = (int)$personSplitAmounts[$currentPersonIndex];
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('yen')) {
    function yen($value): string
    {
        return '¥' . number_format((int)$value);
    }
}

if (!function_exists('payMethodLabel')) {
    function payMethodLabel(string $method): string
    {
        return match (strtoupper($method)) {
            'CASH' => '現金',
            'CARD' => 'カード',
            'ELECTRONIC_MONEY' => '電子マネー',
            default => $method,
        };
    }
}

if (!function_exists('refundPolicyLabel')) {
    function refundPolicyLabel(string $method): string
    {
        return match (strtoupper($method)) {
            'CASH' => '返金時は現金返金で対応',
            'CARD' => '取消不可',
            'ELECTRONIC_MONEY' => '取消不可',
            default => '取消不可',
        };
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/mordal.css">
<link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/payment.css">

<div
  id="settlementPage"
  data-total-amount="<?= (int)$totalAmount ?>"
  data-split-mode="<?= h($splitMode) ?>"
  data-is-split-mode="<?= $isSplitMode ? '1' : '0' ?>"
  data-is-item-split-mode="<?= $isItemSplitMode ? '1' : '0' ?>"
  data-remaining-amount="<?= (int)$remainingAmount ?>"
  data-current-person-amount="<?= (int)$currentPersonAmount ?>"
></div>

<div class="screen payment">

  <header class="app-header">
    <div class="app-header__left">
      <a class="app-back" href="<?= h($backUrl) ?>" aria-label="戻る">←</a>
      <h1 class="app-title">会計</h1>
    </div>
  </header>

  <div class="payment-wrap">
    <div class="payment-cols <?= $isPaymentSplitMode ? 'payment-cols--triple' : '' ?>">

      <div class="payment-card payment-summary-card">
        <div class="payment-card-title">注文概要</div>

        <?php if ($flashWarning): ?>
          <div style="margin-bottom:12px; padding:12px 14px; border-radius:12px; background:#fef2f2; color:#b91c1c; font-size:13px;">
            <?= h($flashWarning) ?>
          </div>
        <?php endif; ?>

        <?php if ($isSplitMode && $hasConfirmedPayment): ?>
          <div style="margin-bottom:12px; padding:12px 14px; border-radius:12px; background:#fff7ed; color:#9a3412; font-size:13px; line-height:1.6;">
            すでに確定済みの支払いがあります。確定済みのカード決済・電子マネー決済は画面上から取消できません。
            返金が必要な場合は、店舗運用として現金返金で対応してください。
          </div>
        <?php endif; ?>

        <div class="payment-summary-head">
          <?php if ($isManual): ?>
            <span class="summary-chip">手入力会計</span>
            <?php if (!empty($manualStartedAt)): ?>
              <span class="summary-chip">開始: <?= h((string)$manualStartedAt) ?></span>
            <?php elseif (!empty($startTime)): ?>
              <span class="summary-chip">開始: <?= h((string)$startTime) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <?php if (!empty($customerIds)): ?>
              <?php foreach ($customerIds as $cid): ?>
                <span class="summary-chip">客番号 <?= h((string)$cid) ?></span>
              <?php endforeach; ?>
            <?php elseif ($customerId !== ''): ?>
              <span class="summary-chip">客番号 <?= h((string)$customerId) ?></span>
            <?php endif; ?>

            <?php if (!empty($startTime)): ?>
              <span class="summary-chip">開始: <?= h((string)$startTime) ?></span>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($splitMode === 'PERSON'): ?>
            <span class="summary-chip">人数分割</span>
          <?php elseif ($splitMode === 'AMOUNT'): ?>
            <span class="summary-chip">金額分割</span>
          <?php elseif ($splitMode === 'ITEM'): ?>
            <span class="summary-chip">商品別分割</span>
          <?php endif; ?>
        </div>

        <?php if (!$isItemSplitMode): ?>
          <div class="payment-order-scroll">
            <div class="payment-list payment-order-list">
              <?php if (empty($details)): ?>
                <div class="payment-order-item empty">
                  <div class="order-main">
                    <div class="order-name">会計対象データがありません</div>
                  </div>
                  <div class="order-amount">-</div>
                </div>
              <?php else: ?>
                <?php foreach ($details as $detailIndex => $it): ?>
                  <?php
                    $name = h((string)($it['menu_name'] ?? ''));
                    $qty = (int)($it['qty'] ?? 0);
                    $price = (int)($it['unit_price'] ?? 0);
                    $lineTotal = $price * $qty;
                    $taxRate = (int)($it['tax_rate'] ?? 0);
                  ?>

                  <div class="payment-order-item">
                    <div class="order-main">
                      <div class="order-name">
                        <?= $name ?>
                        <span style="margin-left:8px; font-size:12px; color:#64748b;">
                          <?= h($taxRate) ?>%
                        </span>
                      </div>

                      <div class="order-sub">
                        <span>単価 <?= yen($price) ?></span>
                        <span>× <?= number_format($qty) ?></span>
                      </div>
                    </div>

                    <div class="order-amount"><?= yen($lineTotal) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($isItemSplitMode): ?>
          <div class="payment-hr"></div>

          <div class="payment-card-title" style="margin-bottom:12px;">商品選択</div>

          <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#334155; font-size:13px; line-height:1.6; margin-bottom:12px;">
            未会計の商品を選択し、選択した商品分だけ1決済として確定します。
            会計後は残りの商品から続けて選べます。
          </div>

          <?php if (empty($itemRemainingDetails)): ?>
  <div style="padding:12px; border-radius:12px; background:#ecfdf5; color:#166534; font-size:13px; margin-bottom:12px;">
    すべての商品が会計済みです。右下の「決済完了」ボタンで会計を完了してください。
  </div>
<?php endif; ?>

<form method="post" action="<?= h($BASE_URL . '/checkout/item-split/execute') ?>" id="itemSplitForm">
  <div style="display:grid; gap:10px;">

    <?php
      $renderedUnitKeys = [];
    ?>

    <?php foreach ($itemRemainingDetails as $detailIndex => $it): ?>
      <?php
        $price = (int)($it['unit_price'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        $taxRate = (int)($it['tax_rate'] ?? 10);
        $menuName = h((string)($it['menu_name'] ?? ''));

        $remainingUnitIndexes = $it['remaining_unit_indexes'] ?? [];
        if (empty($remainingUnitIndexes)) {
            $remainingUnitIndexes = range(1, max(1, $qty));
        }

        $unitTax = (int)ceil($price * $taxRate / 100);
        $unitTotalWithTax = $price + $unitTax;
      ?>

      <?php foreach ($remainingUnitIndexes as $unitIndex): ?>
        <?php
          $unitKey = (int)$detailIndex . ':' . (int)$unitIndex;
          $renderedUnitKeys[$unitKey] = true;
        ?>

        <label style="display:block; border:1px solid #e5e7eb; border-radius:12px; padding:12px; cursor:pointer; background:#ffffff;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div style="display:flex; gap:10px; align-items:flex-start;">
              <input
                type="checkbox"
                name="selected_units[]"
                value="<?= (int)$detailIndex ?>:<?= (int)$unitIndex ?>"
                class="item-split-checkbox"
                data-amount="<?= (int)$price ?>"
                data-tax-rate="<?= (int)$taxRate ?>"
                style="margin-top:4px;"
              >

              <div>
                <div style="font-weight:700; margin-bottom:4px; color:#0f172a;">
                  <?= $menuName ?>

                  <span style="margin-left:8px; font-size:12px; color:#64748b;">
                    <?= (int)$taxRate ?>%
                  </span>

                  <?php if ($qty >= 2): ?>
                    <span style="margin-left:8px; font-size:12px; color:#2563eb;">
                      <?= (int)$unitIndex ?>点目
                    </span>
                  <?php endif; ?>
                </div>

                <div style="font-size:12px; color:#64748b;">
                  単価 <?= yen($price) ?>
                  <?php if ($qty >= 2): ?>
                    ／ 全<?= number_format($qty) ?>点中の<?= (int)$unitIndex ?>点目
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div style="text-align:right;">
              <div style="font-weight:700; color:#0f172a;"><?= yen($unitTotalWithTax) ?></div>
              <div style="font-size:12px; color:#64748b;">税込</div>
            </div>
          </div>
        </label>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <?php foreach ($details as $detailIndex => $it): ?>
      <?php
        $price = (int)($it['unit_price'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        $taxRate = (int)($it['tax_rate'] ?? 10);
        $menuName = h((string)($it['menu_name'] ?? ''));

        $unitTax = (int)ceil($price * $taxRate / 100);
        $unitTotalWithTax = $price + $unitTax;

        $allUnitIndexes = range(1, max(1, $qty));
      ?>

      <?php foreach ($allUnitIndexes as $unitIndex): ?>
        <?php
          $unitKey = (int)$detailIndex . ':' . (int)$unitIndex;

          if (isset($renderedUnitKeys[$unitKey])) {
              continue;
          }

          $isPaidItem = in_array((int)$detailIndex, $itemPaidIndexes, true);

          if (!$isPaidItem) {
              continue;
          }
        ?>

        <label style="display:block; border:1px solid #e2e8f0; border-radius:12px; padding:12px; cursor:not-allowed; background:#f1f5f9; color:#94a3b8;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div style="display:flex; gap:10px; align-items:flex-start;">
              <input
                type="checkbox"
                class="item-split-checkbox"
                style="margin-top:4px;"
                disabled
              >

              <div>
                <div style="font-weight:700; margin-bottom:4px; color:#64748b;">
                  <?= $menuName ?>

                  <span style="margin-left:8px; font-size:12px; color:#94a3b8;">
                    <?= (int)$taxRate ?>%
                  </span>

                  <?php if ($qty >= 2): ?>
                    <span style="margin-left:8px; font-size:12px; color:#94a3b8;">
                      <?= (int)$unitIndex ?>点目
                    </span>
                  <?php endif; ?>

                  <span
                    style="
                      margin-left:8px;
                      display:inline-block;
                      padding:2px 8px;
                      border-radius:999px;
                      background:#e2e8f0;
                      color:#64748b;
                      font-size:12px;
                      font-weight:700;
                    "
                  >
                    会計済み
                  </span>
                </div>

                <div style="font-size:12px; color:#94a3b8;">
                  単価 <?= yen($price) ?>
                  <?php if ($qty >= 2): ?>
                    ／ 全<?= number_format($qty) ?>点中の<?= (int)$unitIndex ?>点目
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div style="text-align:right;">
              <div style="font-weight:700; color:#64748b;"><?= yen($unitTotalWithTax) ?></div>
              <div style="font-size:12px; color:#94a3b8;">税込</div>
            </div>
          </div>
        </label>
      <?php endforeach; ?>
    <?php endforeach; ?>

  </div>

  <div style="margin-top:16px;">
    <div class="payment-row muted">
      <div>選択商品数</div>
      <div class="r" id="itemSelectedCount"><?= number_format($itemSelectedCount) ?>件</div>
    </div>

    <div class="payment-row muted">
      <div>税抜小計</div>
      <div class="r" id="itemSelectedSubtotal"><?= yen(0) ?></div>
    </div>

    <div class="payment-row muted">
      <div>消費税</div>
      <div class="r" id="itemSelectedTax"><?= yen(0) ?></div>
    </div>

    <div class="payment-row">
      <div><strong>今回会計額（税込）</strong></div>
      <div class="r"><strong id="itemSelectedTotal"><?= yen($itemSelectedTotal) ?></strong></div>
    </div>
  </div>
</form>
          <?php endif; ?>


        <div class="payment-hr"></div>

        <div class="payment-row muted">
          <div>小計</div>
          <div class="r"><?= yen($subtotal) ?></div>
        </div>

        <div class="payment-row muted">
          <div>割引</div>
          <div class="r">- <?= yen($discountAmount) ?></div>
        </div>

        <?php if (!empty($taxBreakdown)): ?>
          <?php foreach ($taxBreakdown as $row): ?>
            <div class="payment-row muted">
              <div>消費税（<?= number_format((int)($row['tax_rate'] ?? 0)) ?>%）</div>
              <div class="r"><?= yen((int)($row['tax_amount'] ?? 0)) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="payment-row muted">
            <div>消費税</div>
            <div class="r"><?= yen($taxAmount) ?></div>
          </div>
        <?php endif; ?>

        <div class="payment-hr"></div>

        <div class="payment-total">
          <div>合計</div>
          <div class="sum"><?= yen($totalAmount) ?></div>
        </div>

        <?php if ($isPaymentSplitMode): ?>
          <div class="payment-hr"></div>

          <div class="payment-row muted">
            <div>支払済み</div>
            <div class="r"><?= yen($paidAmount) ?></div>
          </div>

          <div class="payment-row">
            <div><strong>残額</strong></div>
            <div class="r"><strong id="remainingAmountValue"><?= yen($remainingAmount) ?></strong></div>
          </div>

          <?php if ($splitMode === 'PERSON' && !empty($personSplitAmounts)): ?>
            <div style="margin-top:14px;">
              <div style="font-size:13px; font-weight:700; margin-bottom:8px;">人数分割金額</div>
              <div style="display:grid; gap:8px;">
                <?php foreach ($personSplitAmounts as $idx => $amount): ?>
                  <div class="payment-row muted" style="background:#f8fafc; border-radius:10px; padding:8px 10px;">
                    <div><?= ($idx + 1) ?>人目</div>
                    <div class="r"><?= yen((int)$amount) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:6px; font-size:12px; color:#64748b;">端数は先頭の人に加算しています。</div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($isItemSplitMode): ?>
          <div class="payment-hr"></div>

          <div style="font-size:13px; font-weight:700; margin-bottom:10px;">商品別分割の進捗</div>

          <div class="payment-row muted">
            <div>未会計商品数</div>
            <div class="r"><?= number_format(count($itemRemainingDetails)) ?>件</div>
          </div>

          <div class="payment-row muted">
            <div>今回選択中</div>
            <div class="r"><?= number_format($itemSelectedCount) ?>件</div>
          </div>

          <div class="payment-row">
            <div><strong>今回選択小計</strong></div>
            <div class="r"><strong><?= yen($itemSelectedTotal) ?></strong></div>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$isSplitMode): ?>
        <div class="payment-card payment-method-card">
          <div class="payment-card-title">支払方法</div>

          <form method="post" action="<?= h($formAction) ?>" id="paymentForm">
            <input type="hidden" name="is_manual" value="<?= $isManual ? '1' : '0' ?>">
            <input type="hidden" name="store_id" value="<?= h((string)$storeId) ?>">
            <input type="hidden" name="discount_amount" value="<?= $discountAmount ?>">

            <div class="payment-paylist">
              <label class="payment-payitem active">
                <input type="radio" name="pay_method" value="CASH" checked>
                <span class="icon">💵</span>
                <span>現金</span>
              </label>

              <label class="payment-payitem">
                <input type="radio" name="pay_method" value="CARD">
                <span class="icon">💳</span>
                <span>カード</span>
              </label>

              <label class="payment-payitem">
                <input type="radio" name="pay_method" value="ELECTRONIC_MONEY">
                <span class="icon">📱</span>
                <span>電子マネー</span>
              </label>
            </div>

            <div class="field payment-received-field">
              <div class="label">受領金額</div>
              <input
                class="input payment-received-input"
                name="received_amount"
                id="received_amount"
                placeholder="受領金額を入力"
                inputmode="numeric"
                autocomplete="off"
              >
            </div>

            <div class="field" id="providerField" style="display:none; margin-top:12px;">
              <div class="label">決済事業者名</div>
              <input
                class="input"
                name="provider"
                id="provider"
                placeholder="例: VISA / PayPay"
                autocomplete="off"
              >
            </div>

            <div class="payment-shortcuts">
              <button type="button" class="quick-amount-btn" data-amount="<?= $totalAmount ?>">ちょうど</button>
            </div>

            <div class="payment-keypad" id="paymentKeypad">
              <button type="button" class="key" data-key="7">7</button>
              <button type="button" class="key" data-key="8">8</button>
              <button type="button" class="key" data-key="9">9</button>
              <button type="button" class="key key-action" data-key="clear">C</button>

              <button type="button" class="key" data-key="4">4</button>
              <button type="button" class="key" data-key="5">5</button>
              <button type="button" class="key" data-key="6">6</button>
              <button type="button" class="key key-action" data-key="back">⌫</button>

              <button type="button" class="key" data-key="1">1</button>
              <button type="button" class="key" data-key="2">2</button>
              <button type="button" class="key" data-key="3">3</button>
              <button type="button" class="key key-action" data-key="00">00</button>

              <button type="button" class="key key-wide" data-key="0">0</button>
            </div>

            <div class="payment-change-box">
              <div class="change-label">おつり</div>
              <div class="change-value" id="changeAmount">¥0</div>
            </div>

            <button type="submit" class="payment-hidden-submit">submit</button>
          </form>
        </div>

      <?php elseif ($isPaymentSplitMode): ?>
        <div class="payment-card payment-input-card">
          <div class="payment-card-title">支払い確定</div>

          <?php if ($isCompleted): ?>
            <div style="padding:12px; border-radius:12px; background:#ecfdf5; color:#166534; font-size:13px; line-height:1.6; margin-bottom:14px;">
              残額が0円になりました。右下の「決済完了」ボタンで会計を完了してください。
            </div>
          <?php else: ?>
            <form method="post" action="<?= h($BASE_URL . '/checkout/split/add') ?>" id="splitPaymentForm">
              <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#334155; font-size:13px; line-height:1.6; margin-bottom:12px;">
                1支払いごとに決済を確定します。この支払いを確定すると、支払い情報が登録されます。
              </div>

              <div class="payment-paylist">
                <label class="payment-payitem active">
                  <input type="radio" name="pay_method" value="CASH" checked>
                  <span class="icon">💵</span>
                  <span>現金</span>
                </label>

                <label class="payment-payitem">
                  <input type="radio" name="pay_method" value="CARD">
                  <span class="icon">💳</span>
                  <span>カード</span>
                </label>

                <label class="payment-payitem">
                  <input type="radio" name="pay_method" value="ELECTRONIC_MONEY">
                  <span class="icon">📱</span>
                  <span>電子マネー</span>
                </label>
              </div>

              <div class="field split-amount-field" style="margin-top:12px;">
                <div class="label">
                  支払額
                  <span class="split-input-guide">テンキー入力</span>
                </div>

                <div class="split-amount-input-row">
                  <input
                    class="input split-keypad-input is-keypad-target"
                    name="pay_amount"
                    id="split_pay_amount"
                    type="text"
                    placeholder="支払額を入力"
                    inputmode="none"
                    autocomplete="off"
                    readonly
                    aria-label="支払額。画面のテンキーで入力してください"
                    value="<?= $splitMode === 'PERSON'
                      && !empty($personSplitAmounts)
                      && isset($personSplitAmounts[count($splitPayments)])
                        ? (int)$personSplitAmounts[count($splitPayments)]
                        : '' ?>"
                  >

                  <button
                    type="button"
                    class="quick-amount-btn split-exact-btn"
                    id="splitExactPayBtn"
                    data-remaining-amount="<?= (int)$remainingAmount ?>"
                  >
                    ちょうど支払
                  </button>
                </div>
              </div>

              <div class="field payment-received-field split-amount-field">
                <div class="label">
                  受領金額
                  <span class="split-input-guide">テンキー入力</span>
                </div>

                <div class="split-amount-input-row">
                  <input
                    class="input payment-received-input split-keypad-input"
                    name="received_amount"
                    id="split_received_amount"
                    type="text"
                    placeholder="受領金額を入力"
                    inputmode="none"
                    autocomplete="off"
                    readonly
                    aria-label="受領金額。画面のテンキーで入力してください"
                  >

                  <button
                    type="button"
                    class="quick-amount-btn split-exact-btn"
                    id="splitExactReceivedBtn"
                  >
                    ちょうど受領
                  </button>
                </div>
              </div>

              <div class="field" id="splitProviderField" style="display:none; margin-top:12px;">
                <div class="label">決済事業者名</div>
                <input
                  class="input"
                  name="provider"
                  id="split_provider"
                  placeholder="例: VISA / PayPay"
                  autocomplete="off"
                >
              </div>

              <div class="payment-change-box" style="margin-top:14px;">
                <div class="change-label">おつり</div>
                <div class="change-value" id="splitChangeAmount">¥0</div>
              </div>

              <div style="display:flex; gap:12px; margin-top:16px;">
                <button type="submit" class="btn btn-green" style="flex:1; height:52px; border-radius:14px;">
                  この支払いを確定
                </button>
                <a href="<?= h($BASE_URL . '/checkout/settlement') ?>" class="btn btn-outline" style="flex:1; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; text-align:center; text-decoration:none;">
                  再表示
                </a>
              </div>
            </form>
          <?php endif; ?>

          <div class="payment-hr"></div>

          <div style="font-size:14px; font-weight:700; margin-bottom:10px;">支払い済み一覧</div>

          <?php if (empty($splitPayments)): ?>
            <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#64748b; font-size:13px;">
              まだ確定済みの支払いはありません。
            </div>
          <?php else: ?>
            <div style="display:grid; gap:10px;">
              <?php foreach ($splitPayments as $index => $payment): ?>
                <?php
                  $method = strtoupper((string)($payment['pay_method'] ?? ''));
                ?>
                <div style="border:1px solid #e5e7eb; border-radius:14px; padding:12px;">
                  <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                    <div>
                      <div style="font-weight:700;">
                        <?= h(payMethodLabel($method)) ?>
                        / <?= yen((int)($payment['pay_amount'] ?? 0)) ?>
                      </div>

                      <div style="font-size:12px; color:#64748b; margin-top:4px;">
                        確定時刻: <?= h((string)($payment['pay_time'] ?? $payment['paid_at'] ?? '')) ?>
                      </div>

                      <?php if (($payment['received_amount'] ?? null) !== null): ?>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                          受領: <?= yen((int)$payment['received_amount']) ?>
                        </div>
                      <?php endif; ?>

                      <?php if (($payment['change_amount'] ?? null) !== null): ?>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                          おつり: <?= yen((int)$payment['change_amount']) ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div style="text-align:right;">
                      <div style="display:inline-block; padding:6px 10px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700;">
                        <?= h(refundPolicyLabel($method)) ?>
                      </div>

                      <?php if ($method !== 'CASH'): ?>
                        <div style="font-size:11px; color:#94a3b8; margin-top:6px;">
                          画面上からの取消不可
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="payment-card payment-keypad-card">
          <div class="payment-card-title">テンキー</div>

          <?php if (!$isCompleted): ?>
            <div class="split-keypad-status" aria-live="polite">
              <div class="split-keypad-status__label">現在のテンキー入力先</div>
              <div class="split-keypad-status__value" id="splitKeypadCurrentLabel">支払額</div>
            </div>

            <div class="payment-keypad" id="splitPaymentKeypad" style="margin-top:12px;">
              <button type="button" class="key" data-key="7">7</button>
              <button type="button" class="key" data-key="8">8</button>
              <button type="button" class="key" data-key="9">9</button>
              <button type="button" class="key key-action" data-key="clear">C</button>

              <button type="button" class="key" data-key="4">4</button>
              <button type="button" class="key" data-key="5">5</button>
              <button type="button" class="key" data-key="6">6</button>
              <button type="button" class="key key-action" data-key="back">⌫</button>

              <button type="button" class="key" data-key="1">1</button>
              <button type="button" class="key" data-key="2">2</button>
              <button type="button" class="key" data-key="3">3</button>
              <button type="button" class="key key-action" data-key="00">00</button>

              <button type="button" class="key key-wide" data-key="0">0</button>
            </div>
          <?php else: ?>
            <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#64748b; font-size:13px;">
              残額が0円のため、追加の支払い入力は不要です。
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="payment-card payment-keypad-card">
          <div class="payment-card-title">支払い入力</div>

          <?php if (!empty($itemRemainingDetails)): ?>
            <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#334155; font-size:13px; line-height:1.6; margin-bottom:12px;">
              選択した商品分を1決済として確定します。
            </div>

            <div class="payment-paylist" id="itemPayList">
              <label class="payment-payitem active">
                <input form="itemSplitForm" type="radio" name="pay_method" value="CASH" checked>
                <span class="icon">💵</span>
                <span>現金</span>
              </label>

              <label class="payment-payitem">
                <input form="itemSplitForm" type="radio" name="pay_method" value="CARD">
                <span class="icon">💳</span>
                <span>カード</span>
              </label>

              <label class="payment-payitem">
                <input form="itemSplitForm" type="radio" name="pay_method" value="ELECTRONIC_MONEY">
                <span class="icon">📱</span>
                <span>電子マネー</span>
              </label>
            </div>

            <div class="field payment-received-field">
              <div class="label">受領金額</div>
              <input
                form="itemSplitForm"
                class="input payment-received-input"
                name="received_amount"
                id="item_received_amount"
                placeholder="受領金額を入力"
                inputmode="numeric"
                autocomplete="off"
              >
            </div>

            <div class="field" id="itemProviderField" style="display:none; margin-top:12px;">
              <div class="label">決済事業者名</div>
              <input
                form="itemSplitForm"
                class="input"
                name="provider"
                id="item_provider"
                placeholder="例: VISA / PayPay"
                autocomplete="off"
              >
            </div>

            <div class="payment-shortcuts">
              <button type="button" class="quick-amount-btn item-received-btn" data-amount="exact">ちょうど</button>
            </div>

            <div class="payment-keypad" id="itemPaymentKeypad" style="margin-top:12px;">
              <button type="button" class="key" data-key="7">7</button>
              <button type="button" class="key" data-key="8">8</button>
              <button type="button" class="key" data-key="9">9</button>
              <button type="button" class="key key-action" data-key="clear">C</button>

              <button type="button" class="key" data-key="4">4</button>
              <button type="button" class="key" data-key="5">5</button>
              <button type="button" class="key" data-key="6">6</button>
              <button type="button" class="key key-action" data-key="back">⌫</button>

              <button type="button" class="key" data-key="1">1</button>
              <button type="button" class="key" data-key="2">2</button>
              <button type="button" class="key" data-key="3">3</button>
              <button type="button" class="key key-action" data-key="00">00</button>

              <button type="button" class="key key-wide" data-key="0">0</button>
            </div>

            <div class="payment-change-box" style="margin-top:14px;">
              <div class="change-label">おつり</div>
              <div class="change-value" id="itemChangeAmount">¥0</div>
            </div>
          <?php else: ?>
            <div style="padding:12px; border-radius:12px; background:#f8fafc; color:#64748b; font-size:13px;">
              すべての商品が会計済みのため、追加の支払い入力は不要です。
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <div class="payment-footer">
    <?php if (!$isSplitMode): ?>
      <button class="btn btn-outline btn-lg" type="button" id="openSplitModal">会計分割</button>
      <button class="btn btn-green btn-lg" type="button" onclick="document.getElementById('paymentForm').requestSubmit()">決済完了</button>
    <?php else: ?>
      <?php if ($hasConfirmedPayment): ?>
        <button
          class="btn btn-outline btn-lg"
          type="button"
          disabled
          title="確定済みの支払いがあるため、通常会計には戻せません。"
        >
          通常会計に戻せません
        </button>
      <?php else: ?>
        <form method="post" action="<?= h($BASE_URL . '/checkout/split-mode') ?>" style="display:inline;">
          <input type="hidden" name="split_mode" value="NONE">
          <button class="btn btn-outline btn-lg" type="submit">通常会計に戻す</button>
        </form>
      <?php endif; ?>

      <?php if ($isPaymentSplitMode): ?>
        <form method="post" action="<?= h($formAction) ?>" style="display:inline;">
          <button class="btn btn-green btn-lg" type="submit" <?= $isCompleted ? '' : 'disabled' ?>>決済完了</button>
        </form>
      <?php elseif ($isItemSplitMode): ?>
        <?php if (empty($itemRemainingDetails)): ?>
          <form method="post" action="<?= h($formAction) ?>" style="display:inline;">
            <button class="btn btn-green btn-lg" type="submit">決済完了</button>
          </form>
        <?php else: ?>
          <button class="btn btn-green btn-lg" type="button" onclick="document.getElementById('itemSplitForm')?.requestSubmit()">
            選択商品を決済確定
          </button>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<div class="split-modal-backdrop" id="splitModal" hidden>
  <div class="split-modal">
    <div class="split-modal-header">
      <h2>会計分割</h2>
      <button type="button" class="split-close" id="closeSplitModal">×</button>
    </div>

    <div class="split-total-box">
      合計金額 <strong><?= yen($totalAmount) ?></strong>
    </div>

    <div class="split-tabs">
      <button type="button" class="split-tab active" data-tab="people">人数分割</button>
      <button type="button" class="split-tab" data-tab="amount">金額分割</button>
      <button type="button" class="split-tab" data-tab="item">商品別分割</button>
    </div>

    <div class="split-panel active" id="splitPanelPeople">
      <form method="post" action="<?= h($BASE_URL . '/checkout/split-mode') ?>">
        <input type="hidden" name="split_mode" value="PERSON">

        <div class="split-field">
          <label for="split_people">人数</label>
          <input type="number" id="split_people" name="person_count" min="2" value="2">
        </div>

        <div class="split-result" id="splitPeopleResult">
          1人あたり: <?= yen((int)ceil($totalAmount / 2)) ?>
        </div>

        <div style="padding:12px; border-radius:12px; background:#f8fafc; font-size:13px; color:#334155; line-height:1.6; margin-bottom:12px;">
          人数分割では、1人分の支払いごとに決済を確定します。
        </div>

        <button type="submit" class="btn btn-green split-apply-btn">人数分割を適用</button>
      </form>
    </div>

    <div class="split-panel" id="splitPanelAmount">
      <form method="post" action="<?= h($BASE_URL . '/checkout/split-mode') ?>">
        <input type="hidden" name="split_mode" value="AMOUNT">

        <div style="padding:12px; border-radius:12px; background:#f8fafc; font-size:13px; color:#334155; line-height:1.6;">
          金額分割は、任意金額を1支払いごとに決済確定します。
        </div>

        <div class="split-result" id="splitAmountResult" style="margin-top:12px;">
          最初の支払い金額は次画面で入力します
        </div>

        <button type="submit" class="btn btn-green split-apply-btn">金額分割を適用</button>
      </form>
    </div>

    <div class="split-panel" id="splitPanelItem">
      <form method="post" action="<?= h($BASE_URL . '/checkout/split-mode') ?>">
        <input type="hidden" name="split_mode" value="ITEM">

        <div style="padding:12px; border-radius:12px; background:#f8fafc; font-size:13px; color:#334155; line-height:1.6;">
          未会計の商品を選び、選択した商品分を1決済として確定します。
        </div>

        <div class="split-result" style="margin-top:12px;">
          次画面で商品を選択して決済します
        </div>

        <button type="submit" class="btn btn-green split-apply-btn">商品別分割を適用</button>
      </form>
    </div>
  </div>
</div>

<script src="<?= $BASE_URL ?>/assets/js/settlement.js" defer></script>
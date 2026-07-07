<?php
declare(strict_types=1);

/** @var array $data */

$d = $data['detail'] ?? [];
$items = is_array($d['items'] ?? null) ? $d['items'] : [];
$payments = is_array($data['payments'] ?? null) ? $data['payments'] : [];

$billId = (string)($d['billId'] ?? '');

$storeName = (string)(
    $d['storeName']
    ?? $d['store_name']
    ?? $d['storeId']
    ?? ''
);

$customerIdText = (string)(
    $d['customerIdText']
    ?? $d['customerId']
    ?? ''
);

$paidAt = (string)(
    $d['paidAt']
    ?? $d['datetime']
    ?? ''
);

$payMethod = (string)(
    $d['payLabel']
    ?? $d['payMethod']
    ?? ''
);

$subtotal = (int)($d['subtotal'] ?? 0);
$tax = (int)($d['tax'] ?? 0);
$discount = (int)($d['discount'] ?? 0);
$total = (int)($d['total'] ?? 0);

$BASE_URL = '/regi/public';

/**
 * HTMLエスケープ
 */
function historyDetailH(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * 支払IDを取得する
 */
function historyDetailPaymentId(array $payment): string
{
    return (string)(
        $payment['billPaymentId']
        ?? $payment['bill_payment_id']
        ?? ''
    );
}

/**
 * 支払方法を日本語表示する
 */
function historyDetailPaymentLabel(array $payment): string
{
    $label = trim((string)(
        $payment['payLabel']
        ?? $payment['pay_label']
        ?? ''
    ));

    if ($label !== '') {
        return $label;
    }

    $method = strtoupper(trim((string)(
        $payment['payMethod']
        ?? $payment['pay_method']
        ?? ''
    )));

    return match ($method) {
        'CASH' => '現金',
        'CARD', 'CREDIT_CARD' => 'カード',
        'ELECTRONIC_MONEY' => '電子マネー',
        'QR_PAYMENT' => 'QRコード決済',
        default => $method !== '' ? $method : '不明',
    };
}

/**
 * 支払金額を取得する
 *
 * DB側のカラム名が異なる場合にも対応する。
 */
function historyDetailPaymentAmount(array $payment): int
{
    return (int)(
        $payment['paymentAmount']
        ?? $payment['payAmount']
        ?? $payment['pay_amount']
        ?? $payment['payment_amount']
        ?? $payment['paid_amount']
        ?? $payment['amount']
        ?? 0
    );
}

/**
 * 支払日時を取得する
 */
function historyDetailPaymentTime(array $payment): string
{
    return (string)(
        $payment['payTime']
        ?? $payment['paidAt']
        ?? $payment['pay_time']
        ?? $payment['paid_at']
        ?? ''
    );
}
?>

<div class="history-detail">

  <!-- 会計全体の基本情報 -->
  <div class="kv">

    <div class="row">
      <div class="k">会計ID:</div>
      <div class="v">
        <?= historyDetailH($billId) ?>
      </div>
    </div>

    <div class="row">
      <div class="k">店舗名:</div>
      <div class="v">
        <?= historyDetailH($storeName) ?>
      </div>
    </div>

    <div class="row">
      <div class="k">客番号:</div>
      <div class="v">
        <?= historyDetailH($customerIdText) ?>
      </div>
    </div>

    <div class="row">
      <div class="k">会計日時:</div>
      <div class="v">
        <?= historyDetailH($paidAt) ?>
      </div>
    </div>

    <?php if (empty($payments)): ?>
      <div class="row">
        <div class="k">支払方法:</div>
        <div class="v">
          <?= historyDetailH($payMethod) ?>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <div class="history-hr"></div>

  <!-- 注文明細 -->
  <div class="sec-title">注文明細</div>

  <div class="detail-list">

    <?php if (!empty($items)): ?>

      <?php foreach ($items as $it): ?>

        <?php
        $menuName = (string)(
            $it['name']
            ?? $it['menu_name']
            ?? ''
        );

        $qty = (int)($it['qty'] ?? 0);
        $amount = (int)($it['amount'] ?? 0);
        ?>

        <div class="line">
          <div>
            <?= historyDetailH($menuName) ?>
            ×
            <?= $qty ?>
          </div>

          <div class="r">
            ¥<?= number_format($amount) ?>
          </div>
        </div>

      <?php endforeach; ?>

    <?php else: ?>

      <div class="line empty">
        <div>明細がありません</div>
      </div>

    <?php endif; ?>

  </div>

  <div class="history-hr"></div>

  <!-- 会計全体の金額 -->
  <div class="sum">

    <div class="line">
      <div>小計:</div>
      <div class="r">
        ¥<?= number_format($subtotal) ?>
      </div>
    </div>

    <div class="line">
      <div>消費税:</div>
      <div class="r">
        ¥<?= number_format($tax) ?>
      </div>
    </div>

    <?php if ($discount > 0): ?>

      <div class="line discount">
        <div>割引:</div>
        <div class="r">
          -¥<?= number_format($discount) ?>
        </div>
      </div>

    <?php endif; ?>

    <div class="line total">
      <div>会計合計:</div>
      <div class="r big">
        ¥<?= number_format($total) ?>
      </div>
    </div>

  </div>

  <div class="history-hr"></div>

  <!-- 支払い履歴 -->
  <div class="sec-title">支払い履歴</div>

  <?php if (!empty($payments)): ?>

    <div class="history-payment-help">
      再印刷する支払いを選択してください。
    </div>

    <div class="history-payment-list">

      <?php foreach ($payments as $index => $payment): ?>

        <?php
        $paymentId = historyDetailPaymentId($payment);
        $paymentLabel = historyDetailPaymentLabel($payment);
        $paymentAmount = historyDetailPaymentAmount($payment);
        $paymentTime = historyDetailPaymentTime($payment);
        ?>

        <label class="history-payment-row">

          <div class="history-payment-radio">
            <input
              type="radio"
              name="history_bill_payment_id"
              value="<?= historyDetailH($paymentId) ?>"
              <?= $index === 0 ? 'checked' : '' ?>
            >
          </div>

          <div class="history-payment-info">

            <div class="history-payment-main">
              <span class="history-payment-method">
                <?= historyDetailH($paymentLabel) ?>
              </span>

              <span class="history-payment-amount">
                ¥<?= number_format($paymentAmount) ?>
              </span>
            </div>

            <div class="history-payment-sub">

              <?php if ($paymentId !== ''): ?>
                <span>
                  支払ID:
                  <?= historyDetailH($paymentId) ?>
                </span>
              <?php endif; ?>

              <?php if ($paymentTime !== ''): ?>
                <span>
                  <?= historyDetailH($paymentTime) ?>
                </span>
              <?php endif; ?>

            </div>

          </div>

        </label>

      <?php endforeach; ?>

    </div>

    <div class="history-actions">

      <button
        id="historyReceiptReprintButton"
        class="btn btn-primary btn-lg btn-block"
        type="button"
        data-base-url="<?= historyDetailH($BASE_URL) ?>"
      >
        選択した支払いのレシートを再印刷
      </button>

      <button
        id="historyInvoiceReprintButton"
        class="btn btn-outline btn-lg btn-block"
        type="button"
        data-base-url="<?= historyDetailH($BASE_URL) ?>"
      >
        選択した支払いの領収書を再印刷
      </button>

    </div>

  <?php else: ?>

    <div class="history-payment-empty">
      支払い情報が見つかりません。
    </div>

    <!--
      過去データなどでBILL_PAYMENTが存在しない場合は、
      従来どおりbill_id単位で再印刷する。
    -->
    <div class="history-actions">

      <button
        class="btn btn-primary btn-lg btn-block"
        type="button"
        onclick="location.href='<?= historyDetailH(
            $BASE_URL
            . '/history/receipt?bill_id='
            . rawurlencode($billId)
        ) ?>'"
      >
        レシート再印刷
      </button>

      <button
        class="btn btn-outline btn-lg btn-block"
        type="button"
        onclick="location.href='<?= historyDetailH(
            $BASE_URL
            . '/history/invoice?bill_id='
            . rawurlencode($billId)
        ) ?>'"
      >
        領収書再印刷
      </button>

    </div>

  <?php endif; ?>

</div>

<script>
(() => {
  const receiptButton = document.getElementById(
    'historyReceiptReprintButton'
  );

  const invoiceButton = document.getElementById(
    'historyInvoiceReprintButton'
  );

  /**
   * 選択中の支払IDを取得する。
   *
   * @returns {string}
   */
  const getSelectedPaymentId = () => {
    const selected = document.querySelector(
      'input[name="history_bill_payment_id"]:checked'
    );

    return selected ? String(selected.value || '').trim() : '';
  };

  /**
   * 選択中の支払いを帳票画面で開く。
   *
   * @param {string} type
   * @param {HTMLButtonElement} button
   */
  const openPrintPage = (type, button) => {
    const paymentId = getSelectedPaymentId();

    if (!paymentId) {
      alert('再印刷する支払いを選択してください。');
      return;
    }

    const baseUrl = String(
      button.dataset.baseUrl || '/regi/public'
    );

    const url =
      baseUrl
      + '/history/'
      + type
      + '?bill_payment_id='
      + encodeURIComponent(paymentId);

    window.location.href = url;
  };

  if (receiptButton) {
    receiptButton.addEventListener('click', () => {
      openPrintPage('receipt', receiptButton);
    });
  }

  if (invoiceButton) {
    invoiceButton.addEventListener('click', () => {
      openPrintPage('invoice', invoiceButton);
    });
  }
})();
</script>
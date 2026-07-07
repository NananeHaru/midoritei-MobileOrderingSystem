<?php
declare(strict_types=1);

/**
 * 分割会計：1支払いごとの完了画面
 *
 * 人数分割・金額分割：
 *   - 領収書のみ発行可能
 *   - レシートは最終精算完了画面で発行
 *
 * 商品別分割：
 *   - レシート・領収書の両方を発行可能
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * ブラウザバックで古い注文詳細画面がキャッシュ表示されることを防ぐ。
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$BASE_URL = '/regi/public';

$result = $_SESSION['last_split_payment_result'] ?? [];

if (empty($result)) {
    header('Location: ' . $BASE_URL . '/checkout/settlement');
    exit;
}

$splitType = strtoupper((string)($result['split_type'] ?? ''));
$printType = strtoupper((string)($result['print_type'] ?? 'INVOICE_ONLY'));
$isFinal = (bool)($result['is_final'] ?? false);

$bill = $result['bill'] ?? [];
$payment = $result['payment'] ?? [];
$summary = $result['summary'] ?? [];
$details = $result['details'] ?? [];

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('yen')) {
    function yen($value): string
    {
        return '¥' . number_format((int)$value);
    }
}

if (!function_exists('pay_label')) {
    function pay_label(string $method): string
    {
        return match (strtoupper($method)) {
            'CASH' => '現金',
            'CARD' => 'カード',
            'ELECTRONIC_MONEY' => '電子マネー',
            default => $method !== '' ? $method : '-',
        };
    }
}

if (!function_exists('split_label')) {
    function split_label(string $splitType): string
    {
        return match (strtoupper($splitType)) {
            'PERSON' => '人数分割',
            'AMOUNT' => '金額分割',
            'ITEM' => '商品別分割',
            default => '会計分割',
        };
    }
}

$billId = (int)($bill['bill_id'] ?? 0);
$payMethod = strtoupper((string)($payment['pay_method'] ?? ''));
$payAmount = (int)($payment['pay_amount'] ?? 0);
$receivedAmount = $payment['received_amount'] ?? null;
$changeAmount = $payment['change_amount'] ?? null;
$provider = $payment['provider'] ?? null;
$payTime = (string)($payment['pay_time'] ?? '');

$totalAmount = (int)(
    $summary['total_amount']
    ?? ($bill['total_amount'] ?? 0)
);
$paidAmount = (int)($summary['paid_amount'] ?? 0);
$remainingAmount = (int)($summary['remaining_amount'] ?? 0);
$paymentCount = (int)($summary['payment_count'] ?? 1);

$canPrintReceipt = (
    $splitType === 'ITEM'
    && $printType === 'RECEIPT_AND_INVOICE'
);
$canPrintInvoice = true;

$titleText = '支払い確定完了';
$subText = $isFinal
    ? 'すべての支払いが完了しました'
    : '次の支払いへ進めます';

$settlementUrl = $BASE_URL . '/checkout/settlement';

$nextUrl = $isFinal
    ? $BASE_URL . '/checkout/complete'
    : $settlementUrl;

$nextLabel = $isFinal
    ? '精算完了画面へ'
    : (
        $splitType === 'ITEM'
            ? '残りの商品を選択'
            : '次の支払いへ'
    );

$receiptUrl = $BASE_URL . '/receipt';
$invoiceUrl = $BASE_URL . '/invoice';
?>

<link
  rel="stylesheet"
  href="<?= h($BASE_URL) ?>/assets/css/app.css"
>
<link
  rel="stylesheet"
  href="<?= h($BASE_URL) ?>/assets/css/payment.css"
>

<div class="screen payment">

  <header class="app-header">
    <div class="app-header__left">
      <!--
        支払い後は注文詳細画面へ戻さず、
        必ず会計入力画面へ戻す。
      -->
      <a
        class="app-back"
        href="<?= h($settlementUrl) ?>"
        aria-label="会計入力画面に戻る"
      >
        ←
      </a>

      <h1 class="app-title">支払い完了</h1>
    </div>
  </header>

  <div
    style="
      max-width:760px;
      margin:24px auto;
      padding:0 16px 120px;
      box-sizing:border-box;
    "
  >

    <div
      style="
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:22px;
        padding:28px;
        box-shadow:0 18px 40px rgba(25,55,90,.08);
      "
    >

      <div style="text-align:center; margin-bottom:24px;">
        <div
          style="
            width:56px;
            height:56px;
            margin:0 auto 12px;
            border-radius:12px;
            background:#52c782;
            color:#fff;
            font-size:38px;
            display:flex;
            align-items:center;
            justify-content:center;
          "
        >
          ✓
        </div>

        <h2 style="margin:0; font-size:24px; color:#0f2544;">
          <?= h($titleText) ?>
        </h2>

        <div
          style="
            margin-top:6px;
            color:#64748b;
            font-size:14px;
          "
        >
          <?= h($subText) ?>
        </div>
      </div>

      <div
        style="
          margin-bottom:18px;
          padding:12px 14px;
          border-radius:14px;
          background:#f8fafc;
          border:1px solid #e5edf6;
        "
      >
        <div
          style="
            display:flex;
            justify-content:space-between;
            gap:12px;
            align-items:center;
          "
        >
          <div style="font-size:13px; color:#64748b;">
            分割種別
          </div>

          <div style="font-weight:800; color:#0f172a;">
            <?= h(split_label($splitType)) ?>
          </div>
        </div>
      </div>

      <div style="display:grid; gap:10px; margin-bottom:20px;">

        <div class="payment-row">
          <div>会計番号</div>
          <div class="r">
            <?= $billId > 0 ? h((string)$billId) : '-' ?>
          </div>
        </div>

        <div class="payment-row">
          <div>支払い番号</div>
          <div class="r"><?= h((string)$paymentCount) ?></div>
        </div>

        <div class="payment-row">
          <div>支払方法</div>
          <div class="r">
            <?= h(pay_label($payMethod)) ?>
          </div>
        </div>

        <?php if ($provider !== null && $provider !== ''): ?>
          <div class="payment-row">
            <div>決済事業者</div>
            <div class="r"><?= h((string)$provider) ?></div>
          </div>
        <?php endif; ?>

        <div class="payment-row">
          <div>今回支払額</div>
          <div class="r">
            <strong><?= yen($payAmount) ?></strong>
          </div>
        </div>

        <?php if ($payMethod === 'CASH'): ?>
          <div class="payment-row">
            <div>受領金額</div>
            <div class="r">
              <?= $receivedAmount !== null
                  ? yen((int)$receivedAmount)
                  : '-' ?>
            </div>
          </div>

          <div class="payment-row">
            <div>おつり</div>
            <div class="r" style="color:#16a34a;">
              <?= $changeAmount !== null
                  ? yen((int)$changeAmount)
                  : yen(0) ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="payment-row">
          <div>支払時刻</div>
          <div class="r">
            <?= $payTime !== '' ? h($payTime) : '-' ?>
          </div>
        </div>

        <?php if ($splitType !== 'ITEM'): ?>
          <div class="payment-hr"></div>

          <div class="payment-row">
            <div>請求額</div>
            <div class="r"><?= yen($totalAmount) ?></div>
          </div>

          <div class="payment-row">
            <div>支払済み合計</div>
            <div class="r"><?= yen($paidAmount) ?></div>
          </div>

          <div class="payment-row">
            <div><strong>残額</strong></div>
            <div class="r">
              <strong><?= yen($remainingAmount) ?></strong>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <?php if ($splitType === 'ITEM' && !empty($details)): ?>
        <div style="margin-top:22px;">
          <div style="font-weight:800; margin-bottom:10px;">
            今回会計した商品
          </div>

          <div style="display:grid; gap:10px;">
            <?php foreach ($details as $detail): ?>
              <?php
                $menuName = (string)(
                    $detail['menu_name'] ?? ''
                );
                $qty = (int)($detail['qty'] ?? 0);
                $unitPrice = (int)(
                    $detail['unit_price'] ?? 0
                );
                $amount = (int)(
                    $detail['amount']
                    ?? ($unitPrice * $qty)
                );
                $taxRate = (int)(
                    $detail['tax_rate'] ?? 0
                );
              ?>

              <div
                style="
                  border:1px solid #e5e7eb;
                  border-radius:14px;
                  padding:12px;
                "
              >
                <div
                  style="
                    display:flex;
                    justify-content:space-between;
                    gap:12px;
                    align-items:flex-start;
                  "
                >
                  <div>
                    <div style="font-weight:700;">
                      <?= h($menuName) ?>
                    </div>

                    <div
                      style="
                        font-size:12px;
                        color:#64748b;
                        margin-top:4px;
                      "
                    >
                      単価 <?= yen($unitPrice) ?>
                      × <?= number_format($qty) ?>

                      <?php if ($taxRate > 0): ?>
                        <span style="margin-left:6px;">
                          <?= h((string)$taxRate) ?>%
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div
                    style="
                      font-weight:800;
                      white-space:nowrap;
                    "
                  >
                    <?= yen($amount) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div style="margin-top:24px; display:grid; gap:12px;">

        <?php if ($canPrintReceipt): ?>
          <a
            href="<?= h($receiptUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-green btn-lg"
            style="
              display:flex;
              align-items:center;
              justify-content:center;
              text-decoration:none;
              width:100%;
            "
          >
            レシート印刷
          </a>
        <?php endif; ?>

        <?php if ($canPrintInvoice): ?>
          <a
            href="<?= h($invoiceUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-green btn-lg"
            style="
              display:flex;
              align-items:center;
              justify-content:center;
              text-decoration:none;
              width:100%;
            "
          >
            領収書印刷
          </a>
        <?php endif; ?>

        <a
          href="<?= h($nextUrl) ?>"
          class="btn btn-outline btn-lg"
          style="
            display:flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
            width:100%;
          "
        >
          <?= h($nextLabel) ?>
        </a>
      </div>

      <?php if ($splitType !== 'ITEM'): ?>
        <div
          style="
            margin-top:18px;
            padding:12px 14px;
            border-radius:12px;
            background:#f8fafc;
            color:#64748b;
            font-size:13px;
            line-height:1.6;
          "
        >
          人数分割・金額分割では、商品明細が支払いごとに
          分かれないため、この画面では領収書のみ発行できます。
          レシートは最終的な精算完了画面で発行してください。
        </div>
      <?php endif; ?>

      <?php if ($payMethod !== 'CASH'): ?>
        <div
          style="
            margin-top:12px;
            padding:12px 14px;
            border-radius:12px;
            background:#fff7ed;
            color:#9a3412;
            font-size:13px;
            line-height:1.6;
          "
        >
          カード・電子マネーで確定した支払いは、
          画面上から取消できません。
          返金が必要な場合は、店舗運用として
          現金返金で対応してください。
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
(() => {
  const settlementUrl = <?= json_encode(
      $settlementUrl,
      JSON_UNESCAPED_SLASHES
      | JSON_UNESCAPED_UNICODE
  ) ?>;

  /*
   * ブラウザの戻るボタンを押した場合も、
   * 注文詳細ではなく会計入力画面へ戻す。
   */
  history.replaceState(
    { splitPaymentComplete: true },
    '',
    window.location.href
  );

  history.pushState(
    { splitPaymentCompleteGuard: true },
    '',
    window.location.href
  );

  window.addEventListener('popstate', () => {
    window.location.replace(settlementUrl);
  });
})();
</script>

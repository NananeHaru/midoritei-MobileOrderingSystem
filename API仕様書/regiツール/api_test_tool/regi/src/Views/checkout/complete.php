<?php
$title = '精算完了';
require dirname(__DIR__) . '/layout/base.php';

/** @var array|null $result */
$result = $result ?? ($_SESSION['last_checkout_result'] ?? null);

$BASE_URL = '/regi/public';

$bill = $result['bill'] ?? [];
$summary = $result['summary'] ?? [];
$payments = $result['payments'] ?? [];

$billId = $bill['bill_id'] ?? '';
$totalAmount = (int)($summary['total_amount'] ?? 0);
$paidAmount = (int)($summary['paid_amount'] ?? 0);
$subtotalAmount = (int)($summary['subtotal_amount'] ?? 0);
$discountAmount = (int)($summary['discount_amount'] ?? 0);
$taxAmount = (int)($summary['tax_amount'] ?? 0);
$taxBreakdown = $summary['tax_breakdown'] ?? [];
$splitMode = strtoupper((string)($bill['split_mode'] ?? 'NONE'));

$totalChange = 0;
foreach ($payments as $payment) {
    $totalChange += (int)($payment['change_amount'] ?? 0);
}

$payMethodMap = [
    'CASH' => '現金',
    'CARD' => 'カード',
    'ELECTRONIC_MONEY' => '電子マネー',
];

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

if (!function_exists('split_mode_label')) {
    function split_mode_label(string $mode): string
    {
        return match (strtoupper($mode)) {
            'PERSON' => '人数分割',
            'AMOUNT' => '金額分割',
            'ITEM' => '商品別分割',
            'SPLIT' => '会計分割',
            default => '通常会計',
        };
    }
}
?>

<div class="container">
  <div class="card" style="max-width:720px">
    <div class="card-body" style="text-align:center">

      <div style="font-size:46px">✅</div>
      <div style="font-size:22px; font-weight:1100; margin-top:8px">精算完了</div>
      <div style="color:var(--muted); font-size:12px; margin-top:6px">
        すべての支払いが完了しました
      </div>

      <?php if (!$result): ?>
        <div class="pill" style="margin:16px auto 0; max-width:520px; text-align:left">
          <div style="color:#b91c1c;">会計結果が見つかりません。</div>
        </div>

        <div style="margin-top:18px; display:grid; gap:12px">
          <a class="btn btn-primary btn-lg btn-block" href="<?= h($BASE_URL) ?>/customer/select">
            客番号入力へ戻る
          </a>
        </div>
      <?php else: ?>

        <div class="pill" style="margin:16px auto 0; max-width:520px; text-align:left">
          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">会計番号</span>
            <strong><?= h((string)$billId) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">会計種別</span>
            <strong><?= h(split_mode_label($splitMode)) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">小計</span>
            <strong><?= yen($subtotalAmount) ?></strong>
          </div>

          <?php if ($discountAmount > 0): ?>
            <div style="display:flex; justify-content:space-between; margin:6px 0">
              <span style="color:var(--muted)">割引</span>
              <strong>- <?= yen($discountAmount) ?></strong>
            </div>
          <?php endif; ?>

          <?php if (!empty($taxBreakdown)): ?>
            <?php foreach ($taxBreakdown as $row): ?>
              <div style="display:flex; justify-content:space-between; margin:6px 0">
                <span style="color:var(--muted)">
                  消費税（<?= number_format((int)($row['tax_rate'] ?? 0)) ?>%）
                </span>
                <strong><?= yen((int)($row['tax_amount'] ?? 0)) ?></strong>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="display:flex; justify-content:space-between; margin:6px 0">
              <span style="color:var(--muted)">消費税</span>
              <strong><?= yen($taxAmount) ?></strong>
            </div>
          <?php endif; ?>

          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">請求額</span>
            <strong><?= yen($totalAmount) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">支払合計</span>
            <strong><?= yen($paidAmount) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; margin:6px 0">
            <span style="color:var(--muted)">おつり合計</span>
            <strong style="color:var(--accent)"><?= yen($totalChange) ?></strong>
          </div>
        </div>

        <div class="pill" style="margin:16px auto 0; max-width:520px; text-align:left">
          <div style="font-weight:700; margin-bottom:10px;">支払い明細</div>

          <?php if (empty($payments)): ?>
            <div style="color:var(--muted); font-size:13px;">支払い明細はありません。</div>
          <?php else: ?>
            <div style="display:grid; gap:10px;">
              <?php foreach ($payments as $index => $payment): ?>
                <?php
                  $methodCode = (string)($payment['pay_method'] ?? '');
                  $payMethod = $payMethodMap[$methodCode] ?? $methodCode;
                ?>

                <div style="border:1px solid #e5e7eb; border-radius:12px; padding:12px;">
                  <div style="display:flex; justify-content:space-between; margin:4px 0;">
                    <span style="color:var(--muted)">支払い<?= $index + 1 ?></span>
                    <strong><?= h((string)$payMethod) ?></strong>
                  </div>

                  <div style="display:flex; justify-content:space-between; margin:4px 0;">
                    <span style="color:var(--muted)">支払額</span>
                    <strong><?= yen((int)($payment['pay_amount'] ?? 0)) ?></strong>
                  </div>

                  <?php if (($payment['received_amount'] ?? null) !== null): ?>
                    <div style="display:flex; justify-content:space-between; margin:4px 0;">
                      <span style="color:var(--muted)">受領額</span>
                      <strong><?= yen((int)$payment['received_amount']) ?></strong>
                    </div>
                  <?php endif; ?>

                  <?php if (($payment['change_amount'] ?? null) !== null): ?>
                    <div style="display:flex; justify-content:space-between; margin:4px 0;">
                      <span style="color:var(--muted)">おつり</span>
                      <strong style="color:var(--accent)"><?= yen((int)$payment['change_amount']) ?></strong>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($payment['provider'])): ?>
                    <div style="display:flex; justify-content:space-between; margin:4px 0;">
                      <span style="color:var(--muted)">決済事業者</span>
                      <strong><?= h((string)$payment['provider']) ?></strong>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($payment['pay_time'])): ?>
                    <div style="display:flex; justify-content:space-between; margin:4px 0;">
                      <span style="color:var(--muted)">支払時刻</span>
                      <strong><?= h((string)$payment['pay_time']) ?></strong>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (in_array($splitMode, ['PERSON', 'AMOUNT', 'SPLIT'], true)): ?>
          <div class="pill" style="margin:16px auto 0; max-width:520px; text-align:left; background:#f8fafc;">
            <div style="font-size:13px; color:#64748b; line-height:1.7;">
              人数分割・金額分割では、途中支払いごとの領収書のみ発行できます。<br>
              この画面では、会計全体のレシート・領収書を発行できます。
            </div>
          </div>
        <?php endif; ?>

        <div style="margin-top:18px; display:grid; gap:12px">

          <a class="btn btn-primary btn-lg btn-block"
             href="<?= h($BASE_URL) ?>/receipt?bill_id=<?= urlencode((string)$billId) ?>"
             target="_blank">
            レシート印刷
          </a>

          <a class="btn btn-primary btn-lg btn-block"
             href="<?= h($BASE_URL) ?>/invoice?bill_id=<?= urlencode((string)$billId) ?>"
             target="_blank">
            領収書印刷
          </a>

          <form method="post" action="<?= h($BASE_URL) ?>/checkout/finish">
            <button class="btn btn-primary btn-lg btn-block" type="submit">
              終了
            </button>
          </form>

        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
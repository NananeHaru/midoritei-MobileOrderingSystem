<?php
/** @var array $data */
/** @var array $result */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$source = $data ?? ($result ?? ($_SESSION['last_checkout_result'] ?? []));

$bill = $source['bill'] ?? [];
$details = $source['details'] ?? [];
$payments = $source['payments'] ?? [];
$summary = $source['summary'] ?? [];
$returnUrl = $source['return_url'] ?? '';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('print_yen')) {
    function print_yen($v): string
    {
        return '¥' . number_format((int)$v);
    }
}

$store = $source['store'] ?? [];

$storeName = $store['store_name']
    ?? $store['storeName']
    ?? $bill['store_name']
    ?? $bill['storeName']
    ?? '居酒屋みどり亭';

$storeAddress = $store['store_address']
    ?? $store['storeAddress']
    ?? $bill['store_address']
    ?? $bill['storeAddress']
    ?? '';

$storePhone = $store['store_phone']
    ?? $store['storePhone']
    ?? $bill['store_phone']
    ?? $bill['storePhone']
    ?? '';

$storeTno = $store['store_tno']
    ?? $store['storeTno']
    ?? $bill['store_tno']
    ?? $bill['storeTno']
    ?? 'T2420655498022';

$payMethodMap = [
    'CASH' => '現金',
    'CARD' => 'カード',
    'ELECTRONIC_MONEY' => '電子マネー',
];

$billId = $bill['bill_id'] ?? '';
$billTime = $bill['bill_time'] ?? '';

$subtotalAmount = (int)($summary['subtotal_amount'] ?? ($bill['subtotal_amount'] ?? 0));
$discountAmount = (int)($summary['discount_amount'] ?? ($bill['discount_amount'] ?? 0));
$taxAmount = (int)($summary['tax_amount'] ?? ($bill['tax_amount'] ?? 0));
$totalAmount = (int)($summary['total_amount'] ?? ($bill['total_amount'] ?? 0));
$taxBreakdown = $summary['tax_breakdown'] ?? [];

$totalReceivedAmount = 0;
$totalChangeAmount = 0;
foreach ($payments as $payment) {
    $totalReceivedAmount += (int)($payment['received_amount'] ?? 0);
    $totalChangeAmount += (int)($payment['change_amount'] ?? 0);
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>レシート</title>
<style>
body {
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
    font-family: "MS Gothic", monospace;
}
.receipt {
    width: 320px;
    margin: 0 auto;
    padding: 16px;
    box-sizing: border-box;
}
.center {
    text-align: center;
}
.line {
    border-top: 1px dashed #000;
    margin: 8px 0;
}
.row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 14px;
    line-height: 1.6;
}
.item-name {
    margin-top: 6px;
    font-size: 14px;
    word-break: break-word;
}
.item-sub {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    padding-left: 8px;
    gap: 8px;
}
.total-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    line-height: 1.8;
    gap: 8px;
}
.bold {
    font-weight: bold;
}
.blank {
    height: 16px;
}
.no-print {
    text-align: center;
    margin: 12px 0;
}
.no-print button {
    margin: 0 6px;
    padding: 10px 16px;
    font-size: 14px;
}
.payment-box {
    margin-top: 6px;
    padding: 6px 0;
}
.payment-title {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 2px;
}
.tax-note {
    font-size: 12px;
    color: #333;
    margin-top: 2px;
}
@media print {
    .no-print {
        display: none;
    }
    body {
        margin: 0;
    }
    .receipt {
        width: auto;
    }
}
</style>
</head>
<body onload="window.print()">

<div class="receipt">
    <div class="center"><?= h($storeName) ?></div>

    <?php if ($storeAddress !== ''): ?>
        <div class="center"><?= h($storeAddress) ?></div>
    <?php endif; ?>

    <?php if ($storePhone !== ''): ?>
        <div class="center">TEL <?= h($storePhone) ?></div>
    <?php endif; ?>

    <?php if ($storeTno !== ''): ?>
        <div class="center">T番号 <?= h($storeTno) ?></div>
    <?php endif; ?>

    <div class="line"></div>

    <div class="line"></div>

    <?php if ($billTime !== ''): ?>
        <div class="row">
            <span>会計日時</span>
            <span><?= h($billTime) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($billId !== ''): ?>
        <div class="row">
            <span>会計番号</span>
            <span><?= h($billId) ?></span>
        </div>
    <?php endif; ?>

    <div class="line"></div>

    <?php if (!empty($details)): ?>
        <?php foreach ($details as $d): ?>
            <?php $detailTaxRate = (int)($d['tax_rate'] ?? 0); ?>
            <div class="item-name">
                <?= h($d['menu_name'] ?? '') ?>
                <span class="tax-note">(<?= $detailTaxRate ?>%)</span>
            </div>
            <div class="item-sub">
                <span><?= (int)($d['qty'] ?? 0) ?> × <?= print_yen($d['unit_price'] ?? 0) ?></span>
                <span><?= print_yen($d['amount'] ?? 0) ?></span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="center">明細がありません</div>
    <?php endif; ?>

    <div class="line"></div>

    <div class="total-row">
        <span>小計</span>
        <span><?= print_yen($subtotalAmount) ?></span>
    </div>

    <?php if ($discountAmount > 0): ?>
        <div class="total-row">
            <span>割引</span>
            <span>-<?= print_yen($discountAmount) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($taxBreakdown)): ?>
        <?php foreach ($taxBreakdown as $row): ?>
            <div class="total-row">
                <span>税額（<?= (int)($row['tax_rate'] ?? 0) ?>%）</span>
                <span><?= print_yen((int)($row['tax_amount'] ?? 0)) ?></span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="total-row">
            <span>税額</span>
            <span><?= print_yen($taxAmount) ?></span>
        </div>
    <?php endif; ?>

    <div class="total-row bold">
        <span>合計</span>
        <span><?= print_yen($totalAmount) ?></span>
    </div>

    <div class="line"></div>

    <?php if (!empty($payments)): ?>
        <?php foreach ($payments as $index => $payment): ?>
            <?php
                $rawPayMethod = $payment['pay_method'] ?? '';
                $payMethod = $payMethodMap[$rawPayMethod] ?? $rawPayMethod;
                $provider = $payment['provider'] ?? null;
                $payAmount = (int)($payment['pay_amount'] ?? 0);
                $receivedAmount = $payment['received_amount'] ?? null;
                $changeAmount = $payment['change_amount'] ?? null;
            ?>
            <div class="payment-box">
                <div class="payment-title">支払い<?= $index + 1 ?></div>

                <div class="total-row">
                    <span>支払方法</span>
                    <span><?= h($payMethod) ?></span>
                </div>

                <div class="total-row">
                    <span>支払額</span>
                    <span><?= print_yen($payAmount) ?></span>
                </div>

                <?php if (!empty($provider)): ?>
                    <div class="total-row">
                        <span>事業者</span>
                        <span><?= h($provider) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($rawPayMethod === 'CASH'): ?>
                    <div class="total-row">
                        <span>預かり金額</span>
                        <span><?= print_yen($receivedAmount ?? 0) ?></span>
                    </div>
                    <div class="total-row">
                        <span>お釣り</span>
                        <span><?= print_yen($changeAmount ?? 0) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($index < count($payments) - 1): ?>
                <div class="line"></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($totalChangeAmount > 0): ?>
            <div class="line"></div>
            <div class="total-row bold">
                <span>お釣り合計</span>
                <span><?= print_yen($totalChangeAmount) ?></span>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="center">支払い情報がありません</div>
    <?php endif; ?>

    <div class="blank"></div>
    <div class="center">ありがとうございました</div>
</div>

<div class="no-print">
    <button type="button" onclick="window.print()">印刷</button>
    <button
        type="button"
        onclick="handleReceiptClose()"
    >
        閉じる
    </button>
</div>

<script>
function handleReceiptClose() {
    const returnUrl = <?= json_encode($returnUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    if (window.opener && !window.opener.closed) {
        window.close();
        return;
    }

    if (returnUrl) {
        location.href = returnUrl;
        return;
    }

    if (document.referrer) {
        location.href = document.referrer;
        return;
    }

    history.back();
}
</script>

</body>
</html>
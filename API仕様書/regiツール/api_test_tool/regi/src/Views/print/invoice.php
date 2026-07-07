<?php
declare(strict_types=1);

/** @var array $data */
/** @var array $result */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * データ取得元
 *
 * 1. Controllerから渡された$data
 * 2. $result
 * 3. 会計直後のセッション情報
 */
$source = $data ?? ($result ?? ($_SESSION['last_checkout_result'] ?? []));

$bill = is_array($source['bill'] ?? null)
    ? $source['bill']
    : [];

$store = is_array($source['store'] ?? null)
    ? $source['store']
    : [];

$summary = is_array($source['summary'] ?? null)
    ? $source['summary']
    : [];

$payments = is_array($source['payments'] ?? null)
    ? $source['payments']
    : [];

/*
 * 履歴から支払単位で再発行する場合は、
 * HistoryControllerからpaymentが1件渡される。
 */
$payment = is_array($source['payment'] ?? null)
    ? $source['payment']
    : [];

$returnUrl = (string)($source['return_url'] ?? '');

$isReissue = (bool)($source['is_reissue'] ?? false);

if (!function_exists('invoice_h')) {
    function invoice_h(mixed $value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('invoice_yen')) {
    function invoice_yen(mixed $value): string
    {
        return '¥' . number_format((int)$value);
    }
}

/**
 * 配列から最初に存在する値を取得する。
 *
 * @param array<string, mixed> $source
 * @param array<int, string> $keys
 */
if (!function_exists('invoice_first_value')) {
    function invoice_first_value(
        array $source,
        array $keys,
        mixed $default = ''
    ): mixed {
        foreach ($keys as $key) {
            if (
                array_key_exists($key, $source)
                && $source[$key] !== null
                && $source[$key] !== ''
            ) {
                return $source[$key];
            }
        }

        return $default;
    }
}

/**
 * 支払方法を日本語へ変換する。
 */
if (!function_exists('invoice_payment_label')) {
    function invoice_payment_label(string $method): string
    {
        return match (strtoupper(trim($method))) {
            'CASH' => '現金',

            'CARD',
            'CREDIT_CARD' => 'カード',

            'ELECTRONIC_MONEY' => '電子マネー',

            'QR',
            'QR_PAYMENT' => 'QRコード決済',

            default => $method,
        };
    }
}

/*
 * 店舗情報
 */
$storeName = (string)invoice_first_value(
    $store,
    [
        'store_name',
        'storeName',
    ],
    invoice_first_value(
        $bill,
        [
            'store_name',
            'storeName',
        ],
        '居酒屋みどり亭'
    )
);

$storeAddress = (string)invoice_first_value(
    $store,
    [
        'store_address',
        'storeAddress',
    ],
    invoice_first_value(
        $bill,
        [
            'store_address',
            'storeAddress',
        ],
        ''
    )
);

$storePhone = (string)invoice_first_value(
    $store,
    [
        'store_phone',
        'storePhone',
    ],
    invoice_first_value(
        $bill,
        [
            'store_phone',
            'storePhone',
        ],
        ''
    )
);

$storeTno = (string)invoice_first_value(
    $store,
    [
        'store_tno',
        'storeTno',
    ],
    invoice_first_value(
        $bill,
        [
            'store_tno',
            'storeTno',
        ],
        'T2420655498022'
    )
);

$stampText = 'みどり亭';

/*
 * 会計情報
 */
$billId = (string)invoice_first_value(
    $bill,
    [
        'bill_id',
        'billId',
    ],
    ''
);

$billTime = (string)invoice_first_value(
    $bill,
    [
        'bill_time',
        'billTime',
        'paid_at',
        'paidAt',
    ],
    ''
);

/*
 * 選択された支払情報
 */
$billPaymentId = (string)invoice_first_value(
    $payment,
    [
        'bill_payment_id',
        'billPaymentId',
    ],
    ''
);

$selectedPayMethod = (string)invoice_first_value(
    $payment,
    [
        'pay_method',
        'payMethod',
        'payment_method',
        'paymentMethod',
    ],
    ''
);

$selectedProvider = (string)invoice_first_value(
    $payment,
    [
        'provider',
        'payment_provider',
        'paymentProvider',
    ],
    ''
);

$selectedPayTime = (string)invoice_first_value(
    $payment,
    [
        'pay_time',
        'payTime',
        'paid_at',
        'paidAt',
        'payment_time',
        'paymentTime',
    ],
    ''
);

$selectedPaymentAmount = (int)invoice_first_value(
    $payment,
    [
        'pay_amount',
        'payAmount',
        'payment_amount',
        'paymentAmount',
        'paid_amount',
        'paidAmount',
        'amount',
    ],
    0
);

/*
 * 発行日には、選択した支払日時を優先する。
 */
$issueTime = $selectedPayTime !== ''
    ? $selectedPayTime
    : $billTime;

/*
 * 領収書に表示する金額
 *
 * paymentが渡された場合:
 *   選択した支払金額
 *
 * paymentがない場合:
 *   従来どおり会計全体の合計金額
 */
if ($payment !== [] && $selectedPaymentAmount > 0) {
    $displayAmount = $selectedPaymentAmount;
} else {
    $displayAmount = (int)invoice_first_value(
        $summary,
        [
            'total_amount',
            'totalAmount',
        ],
        invoice_first_value(
            $bill,
            [
                'total_amount',
                'totalAmount',
            ],
            0
        )
    );
}

/*
 * 支払方法表示
 */
$paymentSummary = '';

if ($payment !== []) {
    /*
     * 履歴から支払単位で再発行する場合
     */
    $paymentSummary = invoice_payment_label($selectedPayMethod);

    if ($selectedProvider !== '') {
        $paymentSummary .= '（' . $selectedProvider . '）';
    }
} else {
    /*
     * 会計直後など、複数支払いをまとめて表示する場合
     */
    $paymentLabels = [];

    foreach ($payments as $paymentRow) {
        if (!is_array($paymentRow)) {
            continue;
        }

        $rawMethod = (string)invoice_first_value(
            $paymentRow,
            [
                'pay_method',
                'payMethod',
                'payment_method',
                'paymentMethod',
            ],
            ''
        );

        $label = invoice_payment_label($rawMethod);

        $provider = (string)invoice_first_value(
            $paymentRow,
            [
                'provider',
                'payment_provider',
                'paymentProvider',
            ],
            ''
        );

        if ($provider !== '') {
            $label .= '（' . $provider . '）';
        }

        if ($label !== '') {
            $paymentLabels[] = $label;
        }
    }

    $paymentLabels = array_values(array_unique($paymentLabels));
    $paymentSummary = implode(' / ', $paymentLabels);
}

/*
 * 会計全体の金額
 *
 * 支払単位の再発行時に補足表示するため保持する。
 */
$billTotalAmount = (int)invoice_first_value(
    $summary,
    [
        'bill_total_amount',
        'total_amount',
        'totalAmount',
    ],
    invoice_first_value(
        $bill,
        [
            'total_amount',
            'totalAmount',
        ],
        0
    )
);

/*
 * 宛名・但し書き
 *
 * 将来Controllerや入力画面から渡せるようにしている。
 */
$addressee = (string)($source['addressee'] ?? '');
$proviso = (string)($source['proviso'] ?? '飲食代として');
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>領収書</title>

<style>
body {
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
    font-family: "MS Mincho", "Yu Mincho", serif;
}

.invoice {
    width: 700px;
    margin: 24px auto;
    padding: 32px;
    border: 1px solid #000;
    box-sizing: border-box;
}

.center {
    text-align: center;
}

.right {
    text-align: right;
}

.title {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 16px;
    letter-spacing: 0.18em;
}

.reissue-label {
    width: fit-content;
    margin: 0 auto 16px;
    padding: 4px 16px;
    border: 2px solid #000;
    font-size: 15px;
    font-weight: bold;
    letter-spacing: 0.12em;
}

.row {
    margin-bottom: 16px;
    font-size: 18px;
}

.amount-box {
    border-top: 1px solid #000;
    border-bottom: 3px double #000;
    padding: 14px 0;
    font-size: 30px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 24px;
}

.name-box {
    border-bottom: 1px solid #000;
    min-height: 32px;
    margin-bottom: 24px;
    padding: 0 8px 6px;
    font-size: 20px;
    text-align: center;
}

.note-box {
    border-bottom: 1px solid #000;
    padding: 0 8px 6px;
    margin-bottom: 24px;
    min-height: 24px;
}

.meta {
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.8;
}

.sub-meta {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.7;
}

.payment-note {
    margin: 18px 0 0;
    padding: 10px 12px;
    border: 1px solid #777;
    font-size: 13px;
    line-height: 1.7;
}

.store-area {
    margin-top: 40px;
    text-align: right;
    line-height: 1.8;
}

.stamp {
    display: inline-block;
    margin-top: 16px;
    padding: 16px 20px;
    border: 2px solid #c00;
    color: #c00;
    font-weight: bold;
    transform: rotate(-8deg);
}

.no-print {
    text-align: center;
    margin: 12px 0 24px;
}

.no-print button {
    margin: 0 6px;
    padding: 10px 16px;
    font-size: 14px;
    cursor: pointer;
}

@media print {
    @page {
        margin: 12mm;
    }

    .no-print {
        display: none;
    }

    .invoice {
        border: none;
        margin: 0 auto;
        width: 100%;
        max-width: 700px;
    }
}
</style>
</head>

<body onload="window.print()">

<div class="invoice">

    <div class="title center">
        領収書
    </div>

    <?php if ($isReissue): ?>
        <div class="reissue-label">
            再発行
        </div>
    <?php endif; ?>

    <div class="meta right">

        <?php if ($issueTime !== ''): ?>
            <div>
                発行日:
                <?= invoice_h($issueTime) ?>
            </div>
        <?php endif; ?>

        <?php if ($billId !== ''): ?>
            <div>
                会計番号:
                <?= invoice_h($billId) ?>
            </div>
        <?php endif; ?>

        <?php if ($billPaymentId !== ''): ?>
            <div>
                支払番号:
                <?= invoice_h($billPaymentId) ?>
            </div>
        <?php endif; ?>

        <?php if ($paymentSummary !== ''): ?>
            <div class="sub-meta">
                支払方法:
                <?= invoice_h($paymentSummary) ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="row">
        宛名
    </div>

    <div class="name-box">
        <?php if ($addressee !== ''): ?>
            <?= invoice_h($addressee) ?> 様
        <?php endif; ?>
    </div>

    <div class="row">
        金額
    </div>

    <div class="amount-box">
        <?= invoice_yen($displayAmount) ?>
    </div>

    <div class="row">
        但し書き
    </div>

    <div class="note-box">
        <?= invoice_h($proviso) ?>
    </div>

    <?php if (
        $payment !== []
        && $billTotalAmount > 0
        && $billTotalAmount !== $displayAmount
    ): ?>
        <div class="payment-note">
            本領収書は、会計金額
            <?= invoice_yen($billTotalAmount) ?>
            のうち、今回お支払いいただいた
            <?= invoice_yen($displayAmount) ?>
            に対して発行したものです。
        </div>
    <?php endif; ?>

    <div class="store-area">

        <div>
            <?= invoice_h($storeName) ?>
        </div>

        <?php if ($storeAddress !== ''): ?>
            <div>
                <?= invoice_h($storeAddress) ?>
            </div>
        <?php endif; ?>

        <?php if ($storePhone !== ''): ?>
            <div>
                TEL <?= invoice_h($storePhone) ?>
            </div>
        <?php endif; ?>

        <?php if ($storeTno !== ''): ?>
            <div>
                登録番号 <?= invoice_h($storeTno) ?>
            </div>
        <?php endif; ?>

        <div class="stamp">
            <?= invoice_h($stampText) ?>
        </div>

    </div>

</div>

<div class="no-print">

    <button
        type="button"
        onclick="window.print()"
    >
        印刷
    </button>

    <button
        type="button"
        onclick="handleInvoiceClose()"
    >
        閉じる
    </button>

</div>

<script>
function handleInvoiceClose() {
    const returnUrl = <?= json_encode(
        $returnUrl,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) ?>;

    /*
     * 別ウィンドウで開かれた場合
     */
    if (window.opener && !window.opener.closed) {
        window.close();
        return;
    }

    /*
     * Controllerから戻り先が指定されている場合
     */
    if (returnUrl) {
        window.location.href = returnUrl;
        return;
    }

    /*
     * 直前のページへ戻れる場合
     */
    if (document.referrer) {
        window.location.href = document.referrer;
        return;
    }

    window.history.back();
}
</script>

</body>
</html>
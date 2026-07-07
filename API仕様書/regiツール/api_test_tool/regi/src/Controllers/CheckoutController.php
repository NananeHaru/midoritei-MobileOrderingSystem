<?php

namespace App\Controllers;

use App\Services\BillingService;
use App\Lib\MosApiClient;
use App\Lib\MosOrdersApi;
use Throwable;

class CheckoutController
{
    private BillingService $billingService;

    public function __construct()
    {
        $this->billingService = new BillingService();
    }

    public function show(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        /*
         * ブラウザバックで支払い前の注文詳細画面が
         * キャッシュ表示されることを防ぐ。
         */
        $this->disableBrowserCache();

        /*
         * 分割会計で1件以上の支払いが確定している場合は、
         * 注文詳細画面を表示せず会計入力画面へ戻す。
         *
         * URL直接入力やブラウザバックによる再表示も防止する。
         */
        if ($this->hasConfirmedSplitPayment()) {
            $_SESSION['flash_warning']
                = '分割会計の支払いが開始されているため、会計入力画面に戻りました。';

            header('Location: /regi/public/checkout/settlement');
            exit;
        }

        $customerId = $_SESSION['customerId'] ?? '0000001';

        if (empty($_SESSION['customer_ids']) && $customerId !== '') {
            $_SESSION['customer_ids'] = [$customerId];
        }

        $customerIds = $_SESSION['customer_ids'] ?? [];
        $startTime   = $_SESSION['start_time'] ?? ($_SESSION['manual_started_at'] ?? date('Y-m-d H:i:s'));

        $context = $this->buildCheckoutContext();

        $details          = $context['details'];
        $subtotal         = (int)$context['subtotal'];
        $discountAmount   = (int)$context['discount_amount'];
        $taxAmount        = (int)$context['tax_amount'];
        $totalAmount      = (int)$context['total_amount'];
        $taxBreakdown     = $context['tax_breakdown'] ?? [];
        $isManualCheckout = (bool)$context['is_manual'];

        $items = [];
        foreach ($details as $detail) {
            $items[] = [
                'name'     => $detail['menu_name'] ?? '',
                'qty'      => (int)($detail['qty'] ?? 0),
                'price'    => (int)($detail['unit_price'] ?? 0),
                'tax_rate' => (int)($detail['tax_rate'] ?? 0),
            ];
        }

        $discount = $_SESSION['discount'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        require dirname(__DIR__) . '/Views/checkout/index.php';
    }

    public function settlement(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        /*
         * 支払い状況を常に最新表示するため、
         * 会計入力画面もブラウザキャッシュを使用しない。
         */
        $this->disableBrowserCache();

        try {
            $context = $this->buildCheckoutContext();

            $details        = $context['details'];
            $subtotal       = (int)$context['subtotal'];
            $discountAmount = (int)$context['discount_amount'];
            $taxAmount      = (int)$context['tax_amount'];
            $totalAmount    = (int)$context['total_amount'];
            $taxBreakdown   = $context['tax_breakdown'] ?? [];
            $isManual       = (bool)$context['is_manual'];

            $customerId      = $_SESSION['customerId'] ?? '';
            $customerIds     = $_SESSION['customer_ids'] ?? ($customerId !== '' ? [$customerId] : []);
            $startTime       = $_SESSION['start_time'] ?? ($_SESSION['manual_started_at'] ?? date('Y-m-d H:i:s'));
            $manualStartedAt = $_SESSION['manual_started_at'] ?? null;

            $splitMode     = $_SESSION['split_mode'] ?? 'NONE';
            $personCount   = (int)($_SESSION['split_person_count'] ?? 0);
            $splitPayments = $_SESSION['split_payments'] ?? [];

            $paidAmount = 0;
            foreach ($splitPayments as $payment) {
                $paidAmount += (int)($payment['pay_amount'] ?? 0);
            }

            $remainingAmount = max(0, $totalAmount - $paidAmount);

            $splitBillId = (int)($_SESSION['split_bill_id'] ?? 0);

            if (in_array($splitMode, ['PERSON', 'AMOUNT'], true) && $splitBillId > 0) {
                $progress = $this->billingService->getSplitPaymentProgress($splitBillId);

                $splitPayments = $progress['payments'] ?? [];
                $_SESSION['split_payments'] = $splitPayments;

                $paidAmount = (int)($progress['summary']['paid_amount'] ?? 0);
                $remainingAmount = (int)($progress['summary']['remaining_amount'] ?? max(0, $totalAmount - $paidAmount));
            }

            $personSplitAmounts = [];
            if ($splitMode === 'PERSON' && $personCount > 0) {
                $personSplitAmounts = $this->buildPersonSplitAmounts($totalAmount, $personCount);
            }

            $itemPaidUnits = $_SESSION['item_split_paid_units'] ?? [];
            $itemRemainingDetails = [];

            foreach ($details as $index => $detail) {
                $index = (int)$index;
                $qty = max(0, (int)($detail['qty'] ?? 0));

                if ($qty <= 0) {
                    continue;
                }

                $paidUnits = $itemPaidUnits[$index] ?? [];
                $paidUnits = array_map('intval', (array)$paidUnits);

                $remainingUnitIndexes = [];

                for ($unitIndex = 1; $unitIndex <= $qty; $unitIndex++) {
                    if (!in_array($unitIndex, $paidUnits, true)) {
                        $remainingUnitIndexes[] = $unitIndex;
                    }
                }

                if (!empty($remainingUnitIndexes)) {
                    $detail['remaining_unit_indexes'] = $remainingUnitIndexes;
                    $detail['remaining_unit_count'] = count($remainingUnitIndexes);
                    $itemRemainingDetails[$index] = $detail;
                }
            }

            $itemSelectedTotal = 0;
            $itemSelectedCount = 0;

            $selectedUnits = $_POST['selected_units'] ?? [];
            if (is_array($selectedUnits)) {
                foreach ($selectedUnits as $value) {
                    $parts = explode(':', (string)$value);
                    if (count($parts) !== 2) {
                        continue;
                    }

                    $detailIndex = (int)$parts[0];

                    if (!isset($details[$detailIndex])) {
                        continue;
                    }

                    $price = (int)($details[$detailIndex]['unit_price'] ?? 0);
                    $taxRate = (int)($details[$detailIndex]['tax_rate'] ?? 10);
                    $unitTax = (int)floor($price * $taxRate / 100);

                    $itemSelectedTotal += $price + $unitTax;
                    $itemSelectedCount++;
                }
            }

            $flashWarning = $_SESSION['flash_warning'] ?? null;
            unset($_SESSION['flash_warning']);

            require dirname(__DIR__) . '/Views/checkout/settlement.php';
        } catch (Throwable $e) {
            http_response_code(400);
            echo '精算画面の表示に失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public function setSplitMode(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'POSTでアクセスしてください。';
                return;
            }

            $mode = strtoupper(trim((string)($_POST['split_mode'] ?? 'NONE')));
            $allowedModes = ['NONE', 'PERSON', 'AMOUNT', 'ITEM'];

            if (!in_array($mode, $allowedModes, true)) {
                $mode = 'NONE';
            }

            $currentMode = strtoupper((string)($_SESSION['split_mode'] ?? 'NONE'));

            /*
            * 1回でも分割会計の支払いが確定している場合は、
            * 通常会計に戻すことも、別の分割方法へ変更することも禁止する。
            *
            * 対象:
            * - 人数分割
            * - 金額分割
            * - 商品別分割
            */
            if ($this->hasConfirmedSplitPayment()) {
                if ($mode !== $currentMode) {
                    $_SESSION['flash_warning'] = 'すでに確定済みの支払いがあるため、通常会計や別の分割方法には変更できません。';
                    header('Location: /regi/public/checkout/settlement');
                    exit;
                }

                $_SESSION['flash_warning'] = 'すでに確定済みの支払いがあるため、現在の分割方法を継続してください。';
                header('Location: /regi/public/checkout/settlement');
                exit;
            }

            $_SESSION['split_mode'] = $mode;

            unset($_SESSION['split_person_count']);
            unset($_SESSION['item_split_paid_indexes']);
            unset($_SESSION['item_split_paid_units']);
            unset($_SESSION['last_split_payment_result']);
            unset($_SESSION['last_split_payment_type']);
            unset($_SESSION['last_split_is_final']);

            if ($mode === 'NONE') {
                unset($_SESSION['split_bill_id']);
                unset($_SESSION['split_order_bill_id']);
                unset($_SESSION['split_payments']);
                unset($_SESSION['split_started_result']);

                header('Location: /regi/public/checkout/settlement');
                exit;
            }

            if (in_array($mode, ['PERSON', 'AMOUNT'], true)) {
                $_SESSION['split_payments'] = [];

                if ($mode === 'PERSON') {
                    $personCount = max(2, (int)($_POST['person_count'] ?? 2));
                    $_SESSION['split_person_count'] = $personCount;
                }

                unset($_SESSION['split_bill_id']);
                unset($_SESSION['split_order_bill_id']);
                unset($_SESSION['split_started_result']);
            }

            if ($mode === 'ITEM') {
                unset($_SESSION['split_bill_id']);
                unset($_SESSION['split_order_bill_id']);
                unset($_SESSION['split_started_result']);
                $_SESSION['split_payments'] = [];
                $_SESSION['item_split_paid_units'] = [];
            }

            unset($_SESSION['flash_warning']);

            header('Location: /regi/public/checkout/settlement');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_warning'] = $e->getMessage();
            header('Location: /regi/public/checkout/settlement');
            exit;
        }
    }

    private function hasConfirmedSplitPayment(): bool
    {
        /*
        * 人数分割・金額分割:
        * DB上の分割会計IDがあり、支払い件数が1件以上あれば確定済み。
        */
        $currentSplitBillId = (int)($_SESSION['split_bill_id'] ?? 0);

        if ($currentSplitBillId > 0) {
            try {
                $progress = $this->billingService->getSplitPaymentProgress($currentSplitBillId);
                $paymentCount = (int)($progress['summary']['payment_count'] ?? 0);

                if ($paymentCount > 0) {
                    return true;
                }
            } catch (Throwable $e) {
                /*
                * DB確認に失敗しても、セッション側の情報で後続判定する。
                */
            }
        }

        /*
        * 人数分割・金額分割:
        * セッション上に支払い済みデータが残っていれば確定済み。
        */
        $splitPayments = $_SESSION['split_payments'] ?? [];

        if (is_array($splitPayments) && !empty($splitPayments)) {
            return true;
        }

        /*
        * 商品別分割:
        * item_split_paid_units に1つでも支払い済みの商品単位があれば確定済み。
        */
        $paidUnits = $_SESSION['item_split_paid_units'] ?? [];

        if (is_array($paidUnits)) {
            foreach ($paidUnits as $units) {
                if (is_array($units) && !empty($units)) {
                    return true;
                }
            }
        }

        /*
        * 念のため、直前の分割支払い結果が残っている場合も確定済み扱い。
        */
        if (!empty($_SESSION['last_split_payment_result'])) {
            return true;
        }

        return false;
    }

    public function addSplitPayment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'POSTでアクセスしてください。';
                return;
            }

            $context = $this->buildCheckoutContext();
            $totalAmount = (int)$context['total_amount'];

            $splitMode = $_SESSION['split_mode'] ?? 'NONE';
            if (!in_array($splitMode, ['PERSON', 'AMOUNT'], true)) {
                throw new \RuntimeException('分割会計モードではありません。');
            }

            $payMethod = strtoupper(trim((string)($_POST['pay_method'] ?? '')));
            $payAmount = (int)($_POST['pay_amount'] ?? 0);

            $allowedMethods = ['CASH', 'CARD', 'ELECTRONIC_MONEY'];
            if (!in_array($payMethod, $allowedMethods, true)) {
                throw new \RuntimeException('支払方法が不正です。');
            }

            $splitPayments = $_SESSION['split_payments'] ?? [];

            $paidAmount = 0;
            foreach ($splitPayments as $payment) {
                $paidAmount += (int)($payment['pay_amount'] ?? 0);
            }

            $remainingAmount = max(0, $totalAmount - $paidAmount);

            if ($remainingAmount <= 0) {
                throw new \RuntimeException('残額がありません。');
            }

            if ($splitMode === 'PERSON' && $payAmount <= 0) {
                $personCount = (int)($_SESSION['split_person_count'] ?? 0);
                $personSplitAmounts = $this->buildPersonSplitAmounts($totalAmount, $personCount);
                $currentIndex = count($splitPayments);

                if (isset($personSplitAmounts[$currentIndex])) {
                    $payAmount = (int)$personSplitAmounts[$currentIndex];
                }
            }

            if ($payAmount <= 0) {
                throw new \RuntimeException('支払額が不正です。');
            }

            if ($payAmount > $remainingAmount) {
                throw new \RuntimeException('残額を超える金額は登録できません。');
            }

            $receivedAmount = ($_POST['received_amount'] ?? '') !== ''
                ? (int)$_POST['received_amount']
                : null;

            $provider = ($_POST['provider'] ?? '') !== ''
                ? trim((string)$_POST['provider'])
                : null;

            if (in_array($payMethod, ['CARD', 'ELECTRONIC_MONEY'], true) && $provider === null) {
                throw new \RuntimeException('カード・電子マネーの場合は決済事業者名を入力してください。');
            }

            if ($payMethod === 'CASH') {
              /*
              * 現金の場合、受領金額が未入力なら支払額ちょうどとして扱う。
              * 人数分割では支払額が自動入力されるため、
              * 受領金額だけ空になって不足扱いになることを防ぐ。
              */
              if ($receivedAmount === null) {
                  $receivedAmount = $payAmount;
              }

              if ($receivedAmount < $payAmount) {
                  throw new \RuntimeException('現金受領額が支払額未満です。');
              }

              $changeAmount = $receivedAmount - $payAmount;
          } else {
              $receivedAmount = null;
              $changeAmount = null;
          }

            $splitBillId = (int)($_SESSION['split_bill_id'] ?? 0);

            if ($splitBillId <= 0) {
                $startResult = $this->billingService->beginSplitCheckout(
                    $this->buildBillingInputForCurrentCheckout($context, $splitMode, [])
                );

                $splitBillId = (int)($startResult['bill']['bill_id'] ?? 0);
                $splitOrderBillId = (int)($startResult['order_bill']['order_bill_id'] ?? 0);

                if ($splitBillId <= 0) {
                    throw new \RuntimeException('分割会計の開始に失敗しました。');
                }

                $_SESSION['split_bill_id'] = $splitBillId;
                $_SESSION['split_order_bill_id'] = $splitOrderBillId;
                $_SESSION['split_started_result'] = $startResult;
            }

            $paymentResult = $this->billingService->addPaymentToExistingBill($splitBillId, [
                'pay_method'      => $payMethod,
                'pay_amount'      => $payAmount,
                'pay_time'        => date('Y-m-d H:i:s'),
                'received_amount' => $receivedAmount,
                'change_amount'   => $changeAmount,
                'provider'        => $provider,
            ]);

            $progress = $this->billingService->getSplitPaymentProgress($splitBillId);

            $_SESSION['split_payments'] = $progress['payments'] ?? [];

            $remainingAfter = (int)($progress['summary']['remaining_amount'] ?? 0);
            $isFinal = ($remainingAfter === 0);

            $currentPayment = $paymentResult['payment'] ?? [
                'pay_method'      => $payMethod,
                'pay_amount'      => $payAmount,
                'pay_time'        => date('Y-m-d H:i:s'),
                'received_amount' => $receivedAmount,
                'change_amount'   => $changeAmount,
                'provider'        => $provider,
            ];

            $_SESSION['last_split_payment_result'] = [
                'split_type' => $splitMode,
                'print_type' => 'INVOICE_ONLY',
                'is_final' => $isFinal,
                'bill' => [
                    'bill_id' => $splitBillId,
                    'total_amount' => $totalAmount,
                ],
                'payment' => $currentPayment,
                'summary' => [
                    'total_amount' => $totalAmount,
                    'paid_amount' => (int)($progress['summary']['paid_amount'] ?? 0),
                    'remaining_amount' => $remainingAfter,
                    'payment_count' => (int)($progress['summary']['payment_count'] ?? count($_SESSION['split_payments'])),
                ],
            ];

            $_SESSION['last_split_payment_type'] = $splitMode;
            $_SESSION['last_split_is_final'] = $isFinal;

            $_SESSION['last_print_result'] = $this->buildSinglePaymentPrintResult(
                $_SESSION['last_split_payment_result']
            );

            if ($isFinal) {
                $finalResult = $this->billingService->completeExistingSplitCheckout($splitBillId);

                if (!(bool)$context['is_manual']) {
                    $this->updateMosStatusToPaid();
                }

                $_SESSION['last_checkout_result'] = $finalResult;
            }

            unset($_SESSION['flash_warning']);

            header('Location: /regi/public/checkout/split/payment-complete');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_warning'] = $e->getMessage();
            header('Location: /regi/public/checkout/settlement');
            exit;
        }
    }

    public function splitPaymentComplete(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $result = $_SESSION['last_split_payment_result'] ?? null;

        if (empty($result)) {
            $_SESSION['flash_warning'] = '直前の支払い情報が見つかりません。';
            header('Location: /regi/public/checkout/settlement');
            exit;
        }

        require dirname(__DIR__) . '/Views/checkout/split_payment_complete.php';
    }

    public function removeSplitPayment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flash_warning'] = '確定済みの支払いは画面上から削除できません。返金が必要な場合は現金返金で対応してください。';

        header('Location: /regi/public/checkout/settlement');
        exit;
    }

    public function executeItemSplit(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'POSTでアクセスしてください。';
                return;
            }

            $context = $this->buildCheckoutContext();

            $isManual   = (bool)$context['is_manual'];
            $allDetails = $context['details'];
            $splitMode  = $_SESSION['split_mode'] ?? 'NONE';

            if ($splitMode !== 'ITEM') {
                throw new \RuntimeException('商品別分割モードではありません。');
            }

            $paidUnits = $_SESSION['item_split_paid_units'] ?? [];

            $selectedUnitsRaw = $_POST['selected_units'] ?? [];
            if (!is_array($selectedUnitsRaw) || empty($selectedUnitsRaw)) {
                throw new \RuntimeException('会計する商品を選択してください。');
            }

            $selectedQtyByDetail = [];
            $selectedUnitMap = [];

            foreach ($selectedUnitsRaw as $rawValue) {
                $value = trim((string)$rawValue);
                $parts = explode(':', $value);

                if (count($parts) !== 2) {
                    throw new \RuntimeException('選択商品の形式が不正です。');
                }

                $detailIndex = (int)$parts[0];
                $unitIndex = (int)$parts[1];

                if (!isset($allDetails[$detailIndex])) {
                    throw new \RuntimeException('選択した商品が見つかりません。');
                }

                $qty = (int)($allDetails[$detailIndex]['qty'] ?? 0);
                if ($unitIndex < 1 || $unitIndex > $qty) {
                    throw new \RuntimeException('選択した商品の数量が不正です。');
                }

                $alreadyPaidUnits = $paidUnits[$detailIndex] ?? [];
                $alreadyPaidUnits = array_map('intval', (array)$alreadyPaidUnits);

                if (in_array($unitIndex, $alreadyPaidUnits, true)) {
                    throw new \RuntimeException('すでに会計済みの商品が含まれています。');
                }

                $uniqueKey = $detailIndex . ':' . $unitIndex;
                if (isset($selectedUnitMap[$uniqueKey])) {
                    throw new \RuntimeException('同じ商品が重複して選択されています。');
                }

                $selectedUnitMap[$uniqueKey] = true;

                if (!isset($selectedQtyByDetail[$detailIndex])) {
                    $selectedQtyByDetail[$detailIndex] = 0;
                }

                $selectedQtyByDetail[$detailIndex]++;
            }

            if (empty($selectedQtyByDetail)) {
                throw new \RuntimeException('会計する商品を選択してください。');
            }

            $selectedDetails = [];
            $selectedSubtotal = 0;

            foreach ($selectedQtyByDetail as $detailIndex => $selectedQty) {
                $detail = $allDetails[$detailIndex];

                $unitPrice = (int)($detail['unit_price'] ?? 0);
                $selectedQty = (int)$selectedQty;

                if ($unitPrice <= 0 || $selectedQty <= 0) {
                    continue;
                }

                $detail['qty'] = $selectedQty;

                $selectedDetails[] = $detail;
                $selectedSubtotal += $unitPrice * $selectedQty;
            }

            if (empty($selectedDetails)) {
                throw new \RuntimeException('選択した商品が見つかりません。');
            }

            $allSubtotal = (int)$context['subtotal'];
            $allDiscount = (int)$context['discount_amount'];

            $selectedDiscount = 0;
            if ($allDiscount > 0 && $allSubtotal > 0) {
                $selectedDiscount = (int)floor($allDiscount * ($selectedSubtotal / $allSubtotal));
            }

            $payMethod = strtoupper(trim((string)($_POST['pay_method'] ?? '')));

            $receivedAmount = ($_POST['received_amount'] ?? '') !== ''
                ? (int)$_POST['received_amount']
                : null;

            $provider = ($_POST['provider'] ?? '') !== ''
                ? trim((string)($_POST['provider']))
                : null;

            $allowedMethods = ['CASH', 'CARD', 'ELECTRONIC_MONEY'];
            if (!in_array($payMethod, $allowedMethods, true)) {
                throw new \RuntimeException('支払方法を選択してください。');
            }

            if (in_array($payMethod, ['CARD', 'ELECTRONIC_MONEY'], true) && $provider === null) {
                throw new \RuntimeException('カード・電子マネーの場合は決済事業者名を入力してください。');
            }

            $billingInput = [
                'is_manual'       => $isManual,
                'store_id'        => $_SESSION['account']['store_id'] ?? $_SESSION['storeId'] ?? $_SESSION['store_id'] ?? '',
                'discount_amount' => $selectedDiscount,
                'details'         => $selectedDetails,
                'split_mode'      => 'ITEM',
                'payments'        => [[
                    'pay_method'      => $payMethod,
                    'pay_amount'      => 0,
                    'pay_time'        => date('Y-m-d H:i:s'),
                    'received_amount' => $receivedAmount,
                    'change_amount'   => null,
                    'provider'        => $provider,
                ]],
            ];

            if (!$isManual) {
                $billingInput['customer_id'] = $_SESSION['customerId'] ?? '';
                $billingInput['entry_time'] = $_SESSION['start_time'] ?? date('Y-m-d H:i:s');
                $billingInput['order_header_ids'] = [];
                $billingInput['customer_ids'] = $_SESSION['customer_ids'] ?? [];
                $billingInput['checkout_hash'] = $_SESSION['checkout_hash'] ?? null;
                $billingInput['checkout_bill_status'] = $_SESSION['checkout_bill_status'] ?? null;
            }

            $preview = $this->billingService->previewTotal($billingInput);
            $selectedTotal = (int)($preview['total_amount'] ?? 0);

            $billingInput['payments'][0]['pay_amount'] = $selectedTotal;

            if ($payMethod === 'CASH') {
              /*
              * 商品別分割では、選択商品の合計額をサーバ側で計算する。
              * 受領金額が未入力の場合は「ちょうど」として扱う。
              */
              if ($receivedAmount === null) {
                  $receivedAmount = $selectedTotal;
              }

              if ($receivedAmount < $selectedTotal) {
                  throw new \RuntimeException('現金受領額が不足しています。');
              }

              $billingInput['payments'][0]['received_amount'] = $receivedAmount;
              $billingInput['payments'][0]['change_amount'] = $receivedAmount - $selectedTotal;
          } else {
              $billingInput['payments'][0]['received_amount'] = null;
              $billingInput['payments'][0]['change_amount'] = null;
          }

            $result = $this->billingService->checkout($billingInput);

            foreach ($selectedUnitMap as $key => $_) {
                [$detailIndex, $unitIndex] = explode(':', $key);

                $detailIndex = (int)$detailIndex;
                $unitIndex = (int)$unitIndex;

                if (!isset($paidUnits[$detailIndex])) {
                    $paidUnits[$detailIndex] = [];
                }

                $paidUnits[$detailIndex][] = $unitIndex;
                $paidUnits[$detailIndex] = array_values(array_unique(array_map('intval', $paidUnits[$detailIndex])));
                sort($paidUnits[$detailIndex]);
            }

            $_SESSION['item_split_paid_units'] = $paidUnits;

            $remainingExists = false;

            foreach ($allDetails as $detailIndex => $detail) {
                $detailIndex = (int)$detailIndex;
                $qty = (int)($detail['qty'] ?? 0);

                $paidCount = isset($paidUnits[$detailIndex])
                    ? count(array_unique(array_map('intval', (array)$paidUnits[$detailIndex])))
                    : 0;

                if ($paidCount < $qty) {
                    $remainingExists = true;
                    break;
                }
            }

            $_SESSION['last_split_payment_result'] = [
                'split_type' => 'ITEM',
                'print_type' => 'RECEIPT_AND_INVOICE',
                'is_final' => !$remainingExists,
                'bill' => $result['bill'] ?? [],
                'payment' => $result['payments'][0] ?? [],
                'details' => $result['details'] ?? [],
                'summary' => $result['summary'] ?? [],
            ];

            $_SESSION['last_split_payment_type'] = 'ITEM';
            $_SESSION['last_split_is_final'] = !$remainingExists;

            $_SESSION['last_print_result'] = $result;
            $_SESSION['last_checkout_result'] = $result;

            unset($_SESSION['flash_warning']);

            if (!$remainingExists && !$isManual) {
                $this->updateMosStatusToPaid();
            }

            header('Location: /regi/public/checkout/split/payment-complete');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_warning'] = $e->getMessage();
            header('Location: /regi/public/checkout/settlement');
            exit;
        }
    }

    public function execute(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo 'POSTでアクセスしてください。';
                return;
            }

            $context = $this->buildCheckoutContext();

            $isManual       = (bool)$context['is_manual'];
            $details        = $context['details'];
            $discountAmount = (int)$context['discount_amount'];
            $totalAmount    = (int)$context['total_amount'];

            $splitMode = $_SESSION['split_mode'] ?? 'NONE';

            if ($splitMode === 'ITEM') {
                throw new \RuntimeException('商品別分割は専用の確定処理を使用してください。');
            }

            if (in_array($splitMode, ['PERSON', 'AMOUNT'], true)) {
                $splitBillId = (int)($_SESSION['split_bill_id'] ?? 0);

                if ($splitBillId <= 0) {
                    throw new \RuntimeException('分割会計の支払い情報がありません。');
                }

                $progress = $this->billingService->getSplitPaymentProgress($splitBillId);
                $paidAmount = (int)($progress['summary']['paid_amount'] ?? 0);

                if ($paidAmount !== $totalAmount) {
                    throw new \RuntimeException('支払合計が請求額と一致していません。');
                }

                $result = $this->billingService->completeExistingSplitCheckout($splitBillId);

                if (!$isManual) {
                    $this->updateMosStatusToPaid();
                }

                $_SESSION['last_checkout_result'] = $result;
                $_SESSION['last_print_result'] = $result;
                unset($_SESSION['flash_warning']);

                $this->clearCheckoutSession(false);

                header('Location: /regi/public/checkout/complete');
                exit;
            }

            $payMethod = strtoupper(trim((string)($_POST['pay_method'] ?? '')));

            $receivedAmount = ($_POST['received_amount'] ?? '') !== ''
                ? (int)$_POST['received_amount']
                : null;

            $provider = ($_POST['provider'] ?? '') !== ''
                ? trim((string)($_POST['provider']))
                : null;

            $allowedMethods = ['CASH', 'CARD', 'ELECTRONIC_MONEY'];
            if (!in_array($payMethod, $allowedMethods, true)) {
                throw new \RuntimeException('支払方法を選択してください。');
            }

            if (in_array($payMethod, ['CARD', 'ELECTRONIC_MONEY'], true) && $provider === null) {
                throw new \RuntimeException('カード・電子マネーの場合は決済事業者名を入力してください。');
            }

            $changeAmount = null;

            if ($payMethod === 'CASH') {
                if ($receivedAmount === null || $receivedAmount < $totalAmount) {
                    throw new \RuntimeException('現金受領額が不足しています。');
                }

                $changeAmount = $receivedAmount - $totalAmount;
            } else {
                $receivedAmount = null;
                $changeAmount = null;
            }

            $payments = [[
                'pay_method'      => $payMethod,
                'pay_amount'      => $totalAmount,
                'pay_time'        => date('Y-m-d H:i:s'),
                'received_amount' => $receivedAmount,
                'change_amount'   => $changeAmount,
                'provider'        => $provider,
            ]];

            $billingInput = [
                'is_manual'       => $isManual,
                'store_id'        => $_SESSION['account']['store_id'] ?? $_SESSION['storeId'] ?? $_SESSION['store_id'] ?? '',
                'discount_amount' => $discountAmount,
                'details'         => $details,
                'payments'        => $payments,
                'split_mode'      => 'NONE',
            ];

            if (!$isManual) {
                $billingInput['customer_id'] = $_SESSION['customerId'] ?? '';
                $billingInput['entry_time'] = $_SESSION['start_time'] ?? date('Y-m-d H:i:s');
                $billingInput['order_header_ids'] = $_SESSION['order_header_ids'] ?? [];
                $billingInput['customer_ids'] = $_SESSION['customer_ids'] ?? [];
                $billingInput['checkout_hash'] = $_SESSION['checkout_hash'] ?? null;
                $billingInput['checkout_bill_status'] = $_SESSION['checkout_bill_status'] ?? null;
            }

            $result = $this->billingService->checkout($billingInput);

            if (!$isManual) {
                $this->updateMosStatusToPaid();
            }

            $_SESSION['last_checkout_result'] = $result;
            $_SESSION['last_print_result'] = $result;
            unset($_SESSION['flash_warning']);

            $this->clearCheckoutSession(false);

            header('Location: /regi/public/checkout/complete');
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            echo '会計確定に失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public function complete(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $result = $_SESSION['last_checkout_result'] ?? null;

        if ($result !== null) {
            $_SESSION['last_print_result'] = $result;
        }

        require dirname(__DIR__) . '/Views/checkout/complete.php';
    }

    public function finish(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['last_checkout_result']);
        unset($_SESSION['last_print_result']);
        unset($_SESSION['last_split_payment_result']);
        unset($_SESSION['last_split_payment_type']);
        unset($_SESSION['last_split_is_final']);

        $this->clearCheckoutSession();

        header('Location: /regi/public/customer/select');
        exit;
    }

    /**
     * 注文詳細画面から注文選択画面へ戻る。
     *
     * 現在表示している注文・割引・分割会計の一時情報を削除する。
     */
    public function orderBack(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->disableBrowserCache();
        $this->clearCheckoutSession();

        header('Location: /regi/public/customer/select');
        exit;
    }

    public function back(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->disableBrowserCache();

        /*
         * 分割会計で1件以上の支払いが確定している場合:
         *   注文詳細画面には戻さず、客番号入力画面へ戻す。
         *
         * 途中会計はDBへ保存済みなので、
         * 画面用セッションはここで削除する。
         *
         * 同じ客番号を再入力した場合は、
         * CustomerControllerがDBから途中会計を復元する。
         *
         * 支払い開始前:
         *   注文詳細画面へ戻す。
         */
        /*
         * 分割会計で1件以上の支払いが確定している場合:
         *   途中会計はDBに保存済みなのでセッションを削除し、
         *   注文選択画面へ戻す。
         *
         * 支払い開始前・通常会計:
         *   セッションを保持したまま注文詳細画面へ戻す。
         */
        if ($this->hasConfirmedSplitPayment()) {
            $this->clearCheckoutSession();

            header('Location: /regi/public/customer/select');
            exit;
        }

        header('Location: /regi/public/checkout');
        exit;
    }

    private function buildSinglePaymentPrintResult(array $splitResult): array
    {
        $payment = $splitResult['payment'] ?? [];
        $summary = $splitResult['summary'] ?? [];
        $bill = $splitResult['bill'] ?? [];

        $payAmount = (int)($payment['pay_amount'] ?? 0);
        $receivedAmount = $payment['received_amount'] ?? null;
        $changeAmount = $payment['change_amount'] ?? null;

        return [
            'order_bill' => [
                'order_bill_id' => (int)($bill['order_bill_id'] ?? 0),
                'created_at' => (string)($payment['pay_time'] ?? date('Y-m-d H:i:s')),
            ],
            'bill' => [
                'bill_id' => (int)($bill['bill_id'] ?? 0),
                'order_bill_id' => (int)($bill['order_bill_id'] ?? 0),
                'store_id' => $_SESSION['account']['store_id'] ?? $_SESSION['storeId'] ?? $_SESSION['store_id'] ?? '',
                'bill_time' => (string)($payment['pay_time'] ?? date('Y-m-d H:i:s')),
                'subtotal_amount' => $payAmount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $payAmount,
                'split_mode' => (string)($splitResult['split_type'] ?? 'SPLIT'),
            ],
            'details' => [[
                'bill_id' => (int)($bill['bill_id'] ?? 0),
                'menu_name' => '分割支払い',
                'category_name' => '会計分割',
                'qty' => 1,
                'unit_price' => $payAmount,
                'amount' => $payAmount,
                'tax_rate' => 0,
            ]],
            'payments' => [[
                'bill_id' => (int)($bill['bill_id'] ?? 0),
                'pay_method' => (string)($payment['pay_method'] ?? ''),
                'pay_amount' => $payAmount,
                'pay_time' => (string)($payment['pay_time'] ?? date('Y-m-d H:i:s')),
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'provider' => $payment['provider'] ?? null,
            ]],
            'summary' => [
                'subtotal_amount' => $payAmount,
                'discount_amount' => 0,
                'subtotal_after_discount' => $payAmount,
                'tax_amount' => 0,
                'total_amount' => $payAmount,
                'tax_breakdown' => [],
                'paid_amount' => $payAmount,
                'payment_count' => 1,
                'remaining_amount' => (int)($summary['remaining_amount'] ?? 0),
            ],
        ];
    }

    private function buildBillingInputForCurrentCheckout(array $context, string $splitMode, array $payments = []): array
    {
        $isManual = (bool)$context['is_manual'];

        $billingInput = [
            'is_manual'       => $isManual,
            'store_id'        => $_SESSION['account']['store_id'] ?? $_SESSION['storeId'] ?? $_SESSION['store_id'] ?? '',
            'discount_amount' => (int)$context['discount_amount'],
            'details'         => $context['details'],
            'payments'        => $payments,
            'split_mode'      => $splitMode,
        ];

        if (!$isManual) {
            $billingInput['customer_id'] = $_SESSION['customerId'] ?? '';
            $billingInput['entry_time'] = $_SESSION['start_time'] ?? date('Y-m-d H:i:s');
            $billingInput['order_header_ids'] = $_SESSION['order_header_ids'] ?? [];
            $billingInput['customer_ids'] = $_SESSION['customer_ids'] ?? [];
            $billingInput['checkout_hash'] = $_SESSION['checkout_hash'] ?? null;
            $billingInput['checkout_bill_status'] = $_SESSION['checkout_bill_status'] ?? null;
        }

        return $billingInput;
    }

    private function buildCheckoutContext(): array
    {
        $mosOrders   = $_SESSION['checkout_orders'] ?? [];
        $manualItems = $_SESSION['manual_checkout_items'] ?? [];
        $isManual    = !empty($manualItems);

        if (empty($mosOrders) && empty($manualItems)) {
            throw new \RuntimeException('注文データがありません。');
        }

        $details = [];
        $subtotal = 0;

        if ($isManual) {
            foreach ($manualItems as $item) {
                $qty = (int)($item['qty'] ?? 0);
                $unitPrice = (int)($item['price'] ?? 0);
                $menuName = trim((string)($item['name'] ?? ''));
                $categoryName = (string)($item['category_name'] ?? '手入力');
                $taxRate = (int)($item['tax_rate'] ?? 10);

                if ($menuName === '' || $qty <= 0 || $unitPrice <= 0) {
                    continue;
                }

                $details[] = [
                    'menu_name'     => $menuName,
                    'category_name' => $categoryName,
                    'qty'           => $qty,
                    'unit_price'    => $unitPrice,
                    'tax_rate'      => $taxRate,
                ];

                $subtotal += $qty * $unitPrice;
            }
        } else {
            foreach ($mosOrders as $order) {
                foreach (($order['items'] ?? []) as $item) {
                    $qty = (int)($item['offerQty'] ?? $item['orderQty'] ?? 0);
                    $unitPrice = (int)($item['unitPrice'] ?? 0);
                    $menuName = trim((string)($item['menuName'] ?? ''));
                    $categoryName = $item['categoryName'] ?? null;
                    $taxRate = (int)($item['taxRate'] ?? 10);

                    if ($menuName === '' || $qty <= 0 || $unitPrice <= 0) {
                        continue;
                    }

                    $details[] = [
                        'menu_name'     => $menuName,
                        'category_name' => $categoryName,
                        'qty'           => $qty,
                        'unit_price'    => $unitPrice,
                        'tax_rate'      => $taxRate,
                    ];

                    $subtotal += $qty * $unitPrice;
                }
            }
        }

        if (empty($details)) {
            throw new \RuntimeException('会計対象の明細がありません。');
        }

        $discount = $_SESSION['discount'] ?? null;
        $discountAmount = 0;

        if (!empty($discount) && !empty($discount['type'])) {
            if ($discount['type'] === 'percent') {
                $percent = max(0, min(100, (int)($discount['percent'] ?? 0)));
                $discountAmount = (int)floor($subtotal * ($percent / 100));
            } elseif ($discount['type'] === 'amount') {
                $amount = max(0, (int)($discount['amount'] ?? 0));
                $discountAmount = min($subtotal, $amount);
            }
        }

        $_SESSION['discount_amount'] = $discountAmount;

        $preview = $this->billingService->previewTotal([
            'is_manual'       => $isManual,
            'store_id'        => $_SESSION['store_id'] ?? ($_SESSION['checkout_store_id'] ?? '01'),
            'discount_amount' => $discountAmount,
            'details'         => $details,
        ]);

        $taxAmount    = (int)($preview['tax_amount'] ?? 0);
        $totalAmount  = (int)($preview['total_amount'] ?? 0);
        $taxBreakdown = $preview['tax_breakdown'] ?? [];

        return [
            'is_manual'       => $isManual,
            'details'         => $details,
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount'      => $taxAmount,
            'total_amount'    => $totalAmount,
            'tax_breakdown'   => $taxBreakdown,
        ];
    }

    private function buildPersonSplitAmounts(int $totalAmount, int $personCount): array
    {
        if ($personCount <= 0) {
            return [];
        }

        $base = intdiv($totalAmount, $personCount);
        $remainder = $totalAmount % $personCount;

        $amounts = [];
        for ($i = 0; $i < $personCount; $i++) {
            $amounts[] = $base + ($i === 0 ? $remainder : 0);
        }

        return $amounts;
    }

    private function updateMosStatusToPaid(): void
    {
        $orders = $_SESSION['checkout_orders'] ?? [];

        if (empty($orders) || !is_array($orders)) {
            return;
        }

        $client = new MosApiClient('http://localhost:8080');
        $mosApi = new MosOrdersApi($client);

        $updatedCustomerIds = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $customerId = trim((string)($order['customerId'] ?? ''));

            if ($customerId === '') {
                continue;
            }

            if (in_array($customerId, $updatedCustomerIds, true)) {
                continue;
            }

            $hash = $order['hash'] ?? null;
            if (!is_string($hash) || trim($hash) === '') {
                $hash = null;
            }

            $mosApi->updateStatus(
                $customerId,
                $hash,
                MosOrdersApi::BILL_STATUS_PAID
            );

            $updatedCustomerIds[] = $customerId;
        }
    }

    /**
     * ブラウザの戻る操作で古い会計画面が表示されることを防ぐ。
     */
    private function disableBrowserCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function clearCheckoutSession(bool $withResult = true): void
    {
        unset($_SESSION['checkout_orders']);
        unset($_SESSION['manual_checkout_items']);
        unset($_SESSION['customerId']);
        unset($_SESSION['customer_ids']);
        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['start_time']);
        unset($_SESSION['order_header_ids']);
        unset($_SESSION['manual_started_at']);
        unset($_SESSION['checkout_hash']);
        unset($_SESSION['checkout_store_id']);
        unset($_SESSION['checkout_bill_status']);

        unset($_SESSION['split_mode']);
        unset($_SESSION['split_person_count']);
        unset($_SESSION['split_payments']);
        unset($_SESSION['split_bill_id']);
        unset($_SESSION['split_order_bill_id']);
        unset($_SESSION['split_started_result']);

        unset($_SESSION['item_split_paid_indexes']);
        unset($_SESSION['item_split_paid_units']);
        unset($_SESSION['flash_warning']);

        unset($_SESSION['last_split_payment_result']);
        unset($_SESSION['last_split_payment_type']);
        unset($_SESSION['last_split_is_final']);

        if ($withResult) {
            unset($_SESSION['last_checkout_result']);
            unset($_SESSION['last_print_result']);
        }
    }
}
<?php

namespace App\Controllers;

use App\Services\BillingService;
use Throwable;

class BillingController
{
    private BillingService $billingService;

    public function __construct()
    {
        $this->billingService = new BillingService();
    }

    /**
     * 会計確定処理
     */
    public function checkout(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                $this->json([
                    'ok' => false,
                    'message' => 'POSTメソッドでアクセスしてください。',
                ]);
                return;
            }

            $input = $this->buildInputFromPost();
            $result = $this->billingService->checkout($input);

            http_response_code(200);
            $this->json([
                'ok' => true,
                'message' => '会計を確定しました。',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            http_response_code(400);
            $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POSTデータから会計入力データを組み立てる
     */
    private function buildInputFromPost(): array
    {
        $details = $_POST['details'] ?? [];
        $isManual = $this->toBool($_POST['is_manual'] ?? false);

        $splitMode = strtoupper(trim((string)($_POST['split_mode'] ?? 'NONE')));
        $allowedModes = ['NONE', 'PERSON', 'AMOUNT', 'ITEM'];
        if (!in_array($splitMode, $allowedModes, true)) {
            $splitMode = 'NONE';
        }

        $input = [
            'is_manual' => $isManual,
            'store_id' => trim((string)($_POST['store_id'] ?? ($_SESSION['store_id'] ?? '01'))),
            'discount_amount' => isset($_POST['discount_amount'])
                ? (int)$_POST['discount_amount']
                : 0,
            'details' => $this->normalizeDetails(is_array($details) ? $details : []),
            'payments' => $this->buildPaymentsFromPost(),
            'split_mode' => $splitMode,
        ];

        // 注文連携会計のときだけ必要になりうる項目
        if (!$isManual) {
            if (isset($_POST['order_bill_id']) && $_POST['order_bill_id'] !== '') {
                $input['order_bill_id'] = trim((string)$_POST['order_bill_id']);
            }

            if (isset($_POST['order_id']) && $_POST['order_id'] !== '') {
                $input['order_id'] = trim((string)$_POST['order_id']);
            }

            if (isset($_POST['customer_id']) && trim((string)$_POST['customer_id']) !== '') {
                $input['customer_id'] = trim((string)$_POST['customer_id']);
            }

            if (isset($_POST['entry_time']) && trim((string)$_POST['entry_time']) !== '') {
                $input['entry_time'] = trim((string)$_POST['entry_time']);
            }

            if (isset($_POST['order_header_ids']) && is_array($_POST['order_header_ids'])) {
                $input['order_header_ids'] = array_values(array_filter(
                    array_map(
                        static fn($v) => trim((string)$v),
                        $_POST['order_header_ids']
                    ),
                    static fn($v) => $v !== ''
                ));
            }

            if (isset($_POST['customer_ids']) && is_array($_POST['customer_ids'])) {
                $input['customer_ids'] = array_values(array_filter(
                    array_map(
                        static fn($v) => trim((string)$v),
                        $_POST['customer_ids']
                    ),
                    static fn($v) => $v !== ''
                ));
            }

            if (isset($_POST['checkout_hash']) && trim((string)$_POST['checkout_hash']) !== '') {
                $input['checkout_hash'] = trim((string)$_POST['checkout_hash']);
            }

            if (isset($_POST['checkout_bill_status']) && trim((string)$_POST['checkout_bill_status']) !== '') {
                $input['checkout_bill_status'] = trim((string)$_POST['checkout_bill_status']);
            }
        }

        return $input;
    }

    /**
     * POSTデータから支払い配列を組み立てる
     *
     * 1) payments[][pay_method] ... の複数入力
     * 2) pay_method / received_amount / provider の単一入力
     * の両方に対応
     */
    private function buildPaymentsFromPost(): array
    {
        $payments = $_POST['payments'] ?? null;

        if (is_array($payments) && !empty($payments)) {
            return $this->normalizePayments($payments);
        }

        $payMethod = strtoupper(trim((string)($_POST['pay_method'] ?? '')));
        if ($payMethod === '') {
            return [];
        }

        $payAmount = isset($_POST['pay_amount']) && $_POST['pay_amount'] !== ''
            ? (int)$_POST['pay_amount']
            : null;

        $receivedAmount = isset($_POST['received_amount']) && $_POST['received_amount'] !== ''
            ? (int)$_POST['received_amount']
            : null;

        $provider = isset($_POST['provider']) && trim((string)$_POST['provider']) !== ''
            ? trim((string)$_POST['provider'])
            : null;

        $payTime = isset($_POST['pay_time']) && trim((string)$_POST['pay_time']) !== ''
            ? trim((string)$_POST['pay_time'])
            : date('Y-m-d H:i:s');

        $changeAmount = null;
        if ($payMethod === 'CASH' && $receivedAmount !== null && $payAmount !== null && $receivedAmount >= $payAmount) {
            $changeAmount = $receivedAmount - $payAmount;
        }

        return [[
            'pay_method'      => $payMethod,
            'pay_amount'      => $payAmount,
            'pay_time'        => $payTime,
            'received_amount' => $receivedAmount,
            'change_amount'   => $changeAmount,
            'provider'        => $provider,
        ]];
    }

    /**
     * 支払い配列を整形する
     */
    private function normalizePayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $payMethod = strtoupper(trim((string)($payment['pay_method'] ?? '')));
            $payAmount = isset($payment['pay_amount']) && $payment['pay_amount'] !== ''
                ? (int)$payment['pay_amount']
                : 0;

            $payTime = isset($payment['pay_time']) && trim((string)$payment['pay_time']) !== ''
                ? trim((string)$payment['pay_time'])
                : date('Y-m-d H:i:s');

            $receivedAmount = isset($payment['received_amount']) && $payment['received_amount'] !== ''
                ? (int)$payment['received_amount']
                : null;

            $changeAmount = isset($payment['change_amount']) && $payment['change_amount'] !== ''
                ? (int)$payment['change_amount']
                : null;

            $provider = isset($payment['provider']) && trim((string)$payment['provider']) !== ''
                ? trim((string)$payment['provider'])
                : null;

            if ($payMethod === '' || $payAmount <= 0) {
                continue;
            }

            if ($payMethod === 'CASH') {
                if ($receivedAmount === null || $receivedAmount < $payAmount) {
                    throw new \RuntimeException('現金支払いの受領額が不正です。');
                }

                if ($changeAmount === null) {
                    $changeAmount = $receivedAmount - $payAmount;
                }
            } else {
                $changeAmount = null;
            }

            $normalized[] = [
                'pay_method'      => $payMethod,
                'pay_amount'      => $payAmount,
                'pay_time'        => $payTime,
                'received_amount' => $receivedAmount,
                'change_amount'   => $changeAmount,
                'provider'        => $provider,
            ];
        }

        return $normalized;
    }

    /**
     * 明細配列を整形する
     */
    private function normalizeDetails(array $details): array
    {
        $normalized = [];

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $menuName = trim((string)($detail['menu_name'] ?? ''));
            $categoryName = isset($detail['category_name']) && $detail['category_name'] !== ''
                ? trim((string)$detail['category_name'])
                : null;
            $qty = isset($detail['qty']) ? (int)$detail['qty'] : 0;
            $unitPrice = isset($detail['unit_price']) ? (int)$detail['unit_price'] : 0;
            $taxRate = isset($detail['tax_rate']) ? (int)$detail['tax_rate'] : 0;

            if ($menuName === '' || $qty <= 0 || $unitPrice <= 0) {
                continue;
            }

            $normalized[] = [
                'menu_name' => $menuName,
                'category_name' => $categoryName,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
            ];
        }

        return $normalized;
    }

    /**
     * truthy文字列をbool化
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * JSONレスポンス出力
     */
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
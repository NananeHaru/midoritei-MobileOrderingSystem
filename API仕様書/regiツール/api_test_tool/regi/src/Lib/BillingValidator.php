<?php

namespace App\Lib;

use InvalidArgumentException;

class BillingValidator
{
    private const PAY_METHODS = [
        'CASH',
        'CARD',
        'ELECTRONIC_MONEY',
    ];

    /**
     * 会計入力全体のバリデーション
     *
     * 想定:
     * [
     *   'is_manual'        => true|false,
     *   'store_id'         => 'AA',
     *   'customer_id'      => '0000001',
     *   'entry_time'       => '2026-03-21 18:00:00',
     *   'order_header_ids' => [1,2],
     *   'discount_amount'  => 100,
     *   'split_mode'       => 'NONE'|'PERSON'|'AMOUNT'|'ITEM',
     *   'details'          => [
     *     [
     *       'menu_name'     => '瓶ビール',
     *       'category_name' => 'ビール',
     *       'qty'           => 2,
     *       'unit_price'    => 600,
     *       'tax_rate'      => 10
     *     ]
     *   ],
     *   'payments'         => [
     *     [
     *       'pay_method'      => 'CASH',
     *       'pay_amount'      => 5000,
     *       'received_amount' => 6000,
     *       'change_amount'   => 1000,
     *       'provider'        => null,
     *       'pay_time'        => '2026-03-23 18:00:00'
     *     ]
     *   ]
     * ]
     */
    public function validateInput(array $input): void
    {
        $this->validateRequired($input);

        $discountAmount = $this->normalizeInt($input['discount_amount'] ?? 0, '割引額');

        $this->validateDiscountAmount($discountAmount);
        $this->validateDetails($input['details'] ?? []);
        $this->validatePayments($input['payments'] ?? []);
    }

    /**
     * 単一支払情報のバリデーション
     *
     * 既存互換用
     */
    public function validatePayment(
        string $payMethod,
        int $totalAmount,
        ?int $receivedAmount
    ): void {
        $payMethod = $this->normalizePayMethod($payMethod);
        $this->validatePayMethod($payMethod);

        if ($totalAmount < 0) {
            throw new InvalidArgumentException('合計金額が不正です。');
        }

        if ($payMethod === 'CASH') {
            if ($receivedAmount === null) {
                throw new InvalidArgumentException('現金支払いでは受領金額が必要です。');
            }

            if ($receivedAmount < 0) {
                throw new InvalidArgumentException('受領金額は0以上で入力してください。');
            }

            if ($receivedAmount < $totalAmount) {
                throw new InvalidArgumentException('受領金額が不足しています。');
            }
        }
    }

    /**
     * 支払い配列全体のバリデーション
     */
    public function validatePayments(array $payments): void
    {
        if (empty($payments)) {
            throw new InvalidArgumentException('支払い情報がありません。');
        }

        foreach ($payments as $index => $payment) {
            $rowNo = $index + 1;

            if (!is_array($payment)) {
                throw new InvalidArgumentException("支払い{$rowNo}の形式が不正です。");
            }

            $payMethod = $this->normalizePayMethod((string)($payment['pay_method'] ?? ''));
            $this->validatePayMethod($payMethod);

            if (!array_key_exists('pay_amount', $payment) || !is_numeric($payment['pay_amount'])) {
                throw new InvalidArgumentException("支払い{$rowNo}の支払額が不正です。");
            }

            $payAmount = (int)$payment['pay_amount'];
            if ($payAmount <= 0) {
                throw new InvalidArgumentException("支払い{$rowNo}の支払額は1以上で入力してください。");
            }

            $receivedAmount = array_key_exists('received_amount', $payment)
                ? $this->normalizeNullableInt($payment['received_amount'], "支払い{$rowNo}の受領金額")
                : null;

            $changeAmount = array_key_exists('change_amount', $payment)
                ? $this->normalizeNullableInt($payment['change_amount'], "支払い{$rowNo}のお釣り")
                : null;

            if ($payMethod === 'CASH') {
                if ($receivedAmount === null) {
                    throw new InvalidArgumentException("支払い{$rowNo}は現金のため受領金額が必要です。");
                }

                if ($receivedAmount < $payAmount) {
                    throw new InvalidArgumentException("支払い{$rowNo}の受領金額が不足しています。");
                }

                if ($changeAmount !== null && $changeAmount < 0) {
                    throw new InvalidArgumentException("支払い{$rowNo}のお釣りが不正です。");
                }
            }

            if ($payMethod !== 'CASH' && $changeAmount !== null) {
                if ($changeAmount !== 0) {
                    throw new InvalidArgumentException("支払い{$rowNo}は現金以外のためお釣りは設定できません。");
                }
            }

            if (array_key_exists('provider', $payment) && $payment['provider'] !== null && $payment['provider'] !== '') {
                if (!is_string($payment['provider'])) {
                    throw new InvalidArgumentException("支払い{$rowNo}の決済事業者名が不正です。");
                }
            }

            if (array_key_exists('pay_time', $payment) && $payment['pay_time'] !== null && $payment['pay_time'] !== '') {
                $payTime = trim((string)$payment['pay_time']);
                if (
                    !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $payTime) &&
                    !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $payTime)
                ) {
                    throw new InvalidArgumentException("支払い{$rowNo}の支払時刻が不正です。");
                }
            }
        }
    }

    /**
     * discount_amount: 0以上、かつ discount_amount <= subtotal
     */
    public function validateDiscountAgainstSubtotal(int $discountAmount, int $subtotal): void
    {
        if ($discountAmount < 0) {
            throw new InvalidArgumentException('割引額は0以上で入力してください。');
        }

        if ($subtotal < 0) {
            throw new InvalidArgumentException('小計金額が不正です。');
        }

        if ($discountAmount > $subtotal) {
            throw new InvalidArgumentException('割引額は小計以下で入力してください。');
        }
    }

    /**
     * 明細から小計を算出し、割引額との整合を確認
     */
    public function validateDiscountWithDetails(int $discountAmount, array $details): void
    {
        $subtotal = 0;

        foreach ($details as $index => $detail) {
            $rowNo = $index + 1;
            $qty = $this->resolveQty($detail, $rowNo);
            $unitPrice = $this->normalizeInt($detail['unit_price'] ?? 0, "明細{$rowNo}の単価");

            if ($qty < 0 || $unitPrice < 0) {
                throw new InvalidArgumentException("明細{$rowNo}の金額計算に必要な値が不正です。");
            }

            $subtotal += ($qty * $unitPrice);
        }

        $this->validateDiscountAgainstSubtotal($discountAmount, $subtotal);
    }

    /**
     * 必須項目のバリデーション
     *
     * ルール:
     * - MANUAL会計:
     *   - store_id 必須
     *   - customer_id 不要
     *   - entry_time 不要
     * - ORDER_LINKED会計:
     *   - order_header_ids があれば customer_id / entry_time は必須でなくてよい
     *   - order_header_ids が無ければ customer_id / entry_time が必要
     */
    private function validateRequired(array $input): void
    {
        $isManual = $this->toBool($input['is_manual'] ?? false);
        $hasOrderHeaderIds = !empty($input['order_header_ids']) && is_array($input['order_header_ids']);
        $hasCustomerId = array_key_exists('customer_id', $input) && trim((string)$input['customer_id']) !== '';
        $hasEntryTime = array_key_exists('entry_time', $input) && trim((string)$input['entry_time']) !== '';

        if (empty($input['store_id'])) {
            throw new InvalidArgumentException('store_id が必要です。');
        }

        $storeId = trim((string)$input['store_id']);
        if (!preg_match('/^[A-Z]{2}$|^[0-9A-Za-z_-]{1,10}$/', $storeId)) {
            throw new InvalidArgumentException('store_id が不正です。');
        }

        if ($hasOrderHeaderIds) {
            foreach ($input['order_header_ids'] as $id) {
                if (!is_numeric($id) || (int)$id <= 0) {
                    throw new InvalidArgumentException('order_header_ids に不正な値があります。');
                }
            }
        }

        if ($isManual) {
            return;
        }

        if ($hasOrderHeaderIds) {
            return;
        }

        if (!$hasCustomerId) {
            throw new InvalidArgumentException('customer_id が必要です。');
        }

        $customerId = trim((string)$input['customer_id']);
        if (!preg_match('/^[0-9]{1,7}$/', $customerId) && !preg_match('/^[A-Za-z0-9_-]{1,20}$/', $customerId)) {
            throw new InvalidArgumentException('customer_id が不正です。');
        }

        if (!$hasEntryTime) {
            throw new InvalidArgumentException('entry_time が必要です。');
        }

        $entryTime = trim((string)$input['entry_time']);
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $entryTime) &&
            !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entryTime)
        ) {
            throw new InvalidArgumentException('entry_time が不正です。');
        }
    }

    private function validateDiscountAmount(int $discountAmount): void
    {
        if ($discountAmount < 0) {
            throw new InvalidArgumentException('割引額は0以上で入力してください。');
        }
    }

    private function validatePayMethod(string $payMethod): void
    {
        if (!in_array($payMethod, self::PAY_METHODS, true)) {
            throw new InvalidArgumentException('支払方法が不正です。');
        }
    }

    private function validateDetails(array $details): void
    {
        if (empty($details)) {
            throw new InvalidArgumentException('会計明細がありません。');
        }

        foreach ($details as $index => $detail) {
            $rowNo = $index + 1;

            if (empty($detail['menu_name']) || !is_string($detail['menu_name'])) {
                throw new InvalidArgumentException("明細{$rowNo}のメニュー名がありません。");
            }

            $qty = $this->resolveQty($detail, $rowNo);
            if ($qty <= 0 || $qty > 9999) {
                throw new InvalidArgumentException("明細{$rowNo}の数量が不正です。");
            }

            if (!isset($detail['unit_price']) || !is_numeric($detail['unit_price'])) {
                throw new InvalidArgumentException("明細{$rowNo}の単価が不正です。");
            }

            $unitPrice = (int)$detail['unit_price'];
            if ($unitPrice < 0 || $unitPrice > 99999999) {
                throw new InvalidArgumentException("明細{$rowNo}の単価は0以上で入力してください。");
            }

            $taxRateRaw = $detail['tax_rate'] ?? null;
            if ($taxRateRaw === null || !is_numeric($taxRateRaw)) {
                throw new InvalidArgumentException("明細{$rowNo}の税率が不正です。");
            }

            $taxRate = (int)$taxRateRaw;
            if ($taxRate < 0 || $taxRate > 100) {
                throw new InvalidArgumentException("明細{$rowNo}の税率は0〜100で入力してください。");
            }

            if (
                array_key_exists('category_name', $detail) &&
                $detail['category_name'] !== null &&
                !is_string($detail['category_name'])
            ) {
                throw new InvalidArgumentException("明細{$rowNo}のカテゴリ名が不正です。");
            }
        }
    }

    /**
     * 新設計では qty をそのまま会計対象数量として扱う
     */
    private function resolveQty(array $detail, int $rowNo): int
    {
        if (!array_key_exists('qty', $detail)) {
            throw new InvalidArgumentException("明細{$rowNo}の数量(qty)がありません。");
        }

        if (!is_numeric($detail['qty'])) {
            throw new InvalidArgumentException("明細{$rowNo}の数量(qty)が不正です。");
        }

        return (int)$detail['qty'];
    }

    private function normalizePayMethod(string $payMethod): string
    {
        return strtoupper(trim($payMethod));
    }

    private function normalizeInt(mixed $value, string $label): int
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("{$label}は数値で入力してください。");
        }

        return (int)$value;
    }

    private function normalizeNullableInt(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("{$label}は数値で入力してください。");
        }

        return (int)$value;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
<?php

namespace App\Lib;

use InvalidArgumentException;

class BillingCalculator
{
    /**
     * 会計金額を計算する
     *
     * 想定明細:
     * [
     *   [
     *     'menu_name'     => '焼き鳥',
     *     'category_name' => '串物',
     *     'qty'           => 2,
     *     'unit_price'    => 300,
     *     'tax_rate'      => 10,
     *   ],
     *   ...
     * ]
     *
     * 戻り値:
     * [
     *   'details' => [...],
     *   'subtotal_amount' => 0,
     *   'discount_amount' => 0,
     *   'subtotal_after_discount' => 0,
     *   'tax_amount' => 0,
     *   'total_amount' => 0,
     *   'tax_breakdown' => [
     *     [
     *       'tax_rate' => 8,
     *       'taxable_amount' => 1000,
     *       'tax_amount' => 80,
     *     ],
     *     [
     *       'tax_rate' => 10,
     *       'taxable_amount' => 2000,
     *       'tax_amount' => 200,
     *     ],
     *   ],
     * ]
     */
    public function calculate(array $details, int $discountTotal): array
    {
        if (empty($details)) {
            throw new InvalidArgumentException('会計明細がありません。');
        }

        $calculatedDetails = [];
        $subtotal = 0;

        foreach ($details as $index => $detail) {
            $menuName = trim((string)($detail['menu_name'] ?? ''));
            $categoryName = isset($detail['category_name']) && $detail['category_name'] !== ''
                ? (string)$detail['category_name']
                : null;
            $qty = $this->sanitizePositiveInt($detail['qty'] ?? 0);
            $unitPrice = $this->sanitizeNonNegativeInt($detail['unit_price'] ?? 0);
            $taxRate = $this->sanitizeTaxRate($detail['tax_rate'] ?? 0);

            if ($menuName === '') {
                throw new InvalidArgumentException('明細[' . $index . '] の menu_name が未指定です。');
            }

            if ($qty <= 0) {
                throw new InvalidArgumentException('明細[' . $index . '] の qty が不正です。');
            }

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('明細[' . $index . '] の unit_price が不正です。');
            }

            $lineAmount = $unitPrice * $qty;

            $calculatedDetails[] = [
                'menu_name'             => $menuName,
                'category_name'         => $categoryName,
                'qty'                   => $qty,
                'unit_price'            => $unitPrice,
                'tax_rate'              => $taxRate,
                'amount'                => $lineAmount, // BILL_DETAIL.amount
                'discount_allocated'    => 0,
                'amount_after_discount' => $lineAmount,
                'tax_amount'            => 0,
                'including_tax'         => 0,
            ];

            $subtotal += $lineAmount;
        }

        $discountTotal = $this->sanitizeNonNegativeInt($discountTotal);

        if ($discountTotal > $subtotal) {
            throw new InvalidArgumentException('割引額が小計を超えています。');
        }

        $subtotalAfterDiscount = max(0, $subtotal - $discountTotal);

        $taxResult = $this->calculateTaxByDetails($calculatedDetails, $discountTotal);
        $taxAmount = (int)$taxResult['tax_amount'];
        $detailsAfterDiscount = $taxResult['details'];
        $taxBreakdown = $taxResult['tax_breakdown'];

        $totalAmount = max(0, $subtotalAfterDiscount + $taxAmount);

        return [
            'details' => $detailsAfterDiscount,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discountTotal,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'tax_breakdown' => $taxBreakdown,
        ];
    }

    /**
     * 割引按分後の税額計算
     *
     * 各明細に対して割引を按分し、
     * ceil(amount_after_discount × tax_rate / 100) で税額を求める。
     *
     * あわせて税率別内訳 tax_breakdown も返す。
     */
    private function calculateTaxByDetails(array $details, int $discountTotal): array
    {
        if (empty($details)) {
            return [
                'tax_amount' => 0,
                'details' => [],
                'tax_breakdown' => [],
            ];
        }

        $subtotal = 0;
        foreach ($details as $detail) {
            $subtotal += (int)$detail['amount'];
        }

        if ($subtotal <= 0) {
            foreach ($details as &$detail) {
                $detail['discount_allocated'] = 0;
                $detail['amount_after_discount'] = 0;
                $detail['tax_amount'] = 0;
                $detail['including_tax'] = 0;
            }
            unset($detail);

            return [
                'tax_amount' => 0,
                'details' => $details,
                'tax_breakdown' => [],
            ];
        }

        $allocatedDiscountTotal = 0;
        $lastIndex = array_key_last($details);

        foreach ($details as $i => &$detail) {
            $lineAmount = (int)$detail['amount'];

            if ($i === $lastIndex) {
                $allocatedDiscount = $discountTotal - $allocatedDiscountTotal;
            } else {
                $allocatedDiscount = (int) floor($discountTotal * $lineAmount / $subtotal);
                $allocatedDiscountTotal += $allocatedDiscount;
            }

            if ($allocatedDiscount < 0) {
                $allocatedDiscount = 0;
            }
            if ($allocatedDiscount > $lineAmount) {
                $allocatedDiscount = $lineAmount;
            }

            $amountAfterDiscount = $lineAmount - $allocatedDiscount;
            if ($amountAfterDiscount < 0) {
                $amountAfterDiscount = 0;
            }

            $taxRate = $this->sanitizeTaxRate($detail['tax_rate'] ?? 0);
            $detailTax = (int) ceil($amountAfterDiscount * $taxRate / 100);

            $includingTax = $amountAfterDiscount + $detailTax;
            if ($includingTax < 0) {
                $includingTax = 0;
            }

            $detail['discount_allocated'] = $allocatedDiscount;
            $detail['amount_after_discount'] = $amountAfterDiscount;
            $detail['tax_amount'] = $detailTax;
            $detail['including_tax'] = $includingTax;
        }
        unset($detail);

        $taxAmount = 0;
        $taxBreakdownMap = [];

        foreach ($details as $detail) {
            $taxRate = (int)($detail['tax_rate'] ?? 0);
            $amountAfterDiscount = (int)($detail['amount_after_discount'] ?? 0);
            $detailTax = (int)($detail['tax_amount'] ?? 0);

            $taxAmount += $detailTax;

            if (!isset($taxBreakdownMap[$taxRate])) {
                $taxBreakdownMap[$taxRate] = [
                    'tax_rate' => $taxRate,
                    'taxable_amount' => 0,
                    'tax_amount' => 0,
                ];
            }

            $taxBreakdownMap[$taxRate]['taxable_amount'] += $amountAfterDiscount;
            $taxBreakdownMap[$taxRate]['tax_amount'] += $detailTax;
        }

        ksort($taxBreakdownMap);

        return [
            'tax_amount' => $taxAmount,
            'details' => $details,
            'tax_breakdown' => array_values($taxBreakdownMap),
        ];
    }

    private function sanitizePositiveInt(mixed $value): int
    {
        $intValue = (int)$value;
        return max(0, $intValue);
    }

    private function sanitizeNonNegativeInt(mixed $value): int
    {
        $intValue = (int)$value;
        return max(0, $intValue);
    }

    /**
     * taxRate は 0～100 の範囲を許容する（整数値）
     */
    private function sanitizeTaxRate(mixed $value): int
    {
        $taxRate = (int)$value;

        if ($taxRate < 0) {
            return 0;
        }

        if ($taxRate > 100) {
            return 100;
        }

        return $taxRate;
    }
}
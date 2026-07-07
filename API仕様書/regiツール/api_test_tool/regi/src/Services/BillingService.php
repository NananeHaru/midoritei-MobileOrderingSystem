<?php

namespace App\Services;

use App\Lib\BillingCalculator;
use App\Lib\BillingValidator;
use App\Repositories\BillDetailRepository;
use App\Repositories\BillPaymentRepository;
use App\Repositories\BillRepository;
use App\Repositories\OrderBillRepository;
use App\Repositories\OrderHeaderRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

class BillingService
{
    private PDO $pdo;
    private BillingValidator $validator;
    private BillingCalculator $calculator;
    private BillRepository $billRepository;
    private BillDetailRepository $billDetailRepository;
    private BillPaymentRepository $billPaymentRepository;
    private OrderHeaderRepository $orderHeaderRepository;
    private OrderBillRepository $orderBillRepository;

    public function __construct(
        ?PDO $pdo = null,
        ?BillingValidator $validator = null,
        ?BillingCalculator $calculator = null,
        ?BillRepository $billRepository = null,
        ?BillDetailRepository $billDetailRepository = null,
        ?BillPaymentRepository $billPaymentRepository = null,
        ?OrderHeaderRepository $orderHeaderRepository = null,
        ?OrderBillRepository $orderBillRepository = null
    ) {
        $this->pdo = $pdo ?? require dirname(__DIR__) . '/Database/db.php';
        $this->validator = $validator ?? new BillingValidator();
        $this->calculator = $calculator ?? new BillingCalculator();
        $this->billRepository = $billRepository ?? new BillRepository();
        $this->billDetailRepository = $billDetailRepository ?? new BillDetailRepository();
        $this->billPaymentRepository = $billPaymentRepository ?? new BillPaymentRepository();
        $this->orderHeaderRepository = $orderHeaderRepository ?? new OrderHeaderRepository();
        $this->orderBillRepository = $orderBillRepository ?? new OrderBillRepository();
    }

    /**
     * 会計金額の試算
     * 商品別分割でグループごとの合計算出に使う
     */
    public function previewTotal(array $input): array
    {
        $normalizedInput = $this->normalizeInput($input);

        $this->validator->validateDiscountWithDetails(
            (int)$normalizedInput['discount_amount'],
            $normalizedInput['details']
        );

        return $this->calculator->calculate(
            $normalizedInput['details'],
            (int)$normalizedInput['discount_amount']
        );
    }

    /**
     * 通常会計・従来型の一括会計確定
     *
     * 通常会計、または最後にまとめて支払いを登録する方式で使う。
     */
    public function checkout(array $input): array
    {
        $normalizedInput = $this->normalizeInput($input);

        $this->validator->validateInput($normalizedInput);
        $this->validator->validateDiscountWithDetails(
            (int)$normalizedInput['discount_amount'],
            $normalizedInput['details']
        );

        $calculated = $this->calculator->calculate(
            $normalizedInput['details'],
            (int)$normalizedInput['discount_amount']
        );

        $this->validator->validateDiscountAgainstSubtotal(
            (int)$normalizedInput['discount_amount'],
            (int)$calculated['subtotal_amount']
        );

        $this->validatePayments(
            $normalizedInput['payments'],
            (int)$calculated['total_amount']
        );

        $now = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            $orderBillId = $this->orderBillRepository->insert($this->pdo, [
                'created_at' => $now,
            ]);

            $orderHeaderIds = [];
            if (!$normalizedInput['is_manual']) {
                $orderHeaderIds = $this->resolveOrderHeaderIds($normalizedInput, $orderBillId, $now);
            }

            $billId = $this->billRepository->insert($this->pdo, [
                'order_bill_id'    => $orderBillId,
                'store_id'         => (string)$normalizedInput['store_id'],
                'bill_time'        => $now,
                'subtotal_amount'  => (int)$calculated['subtotal_amount'],
                'discount_amount'  => (int)$calculated['discount_amount'],
                'tax_amount'       => (int)$calculated['tax_amount'],
                'total_amount'     => (int)$calculated['total_amount'],
                'split_mode'       => (string)$normalizedInput['split_mode'],
            ]);

            $detailRows = $this->buildBillDetailRows($billId, $calculated['details']);
            if (!empty($detailRows)) {
                $this->billDetailRepository->insertMany($this->pdo, $detailRows);
            }

            $paymentRows = $this->buildBillPaymentRows($billId, $normalizedInput['payments'], $now);
            foreach ($paymentRows as $paymentRow) {
                $this->billPaymentRepository->insert($this->pdo, $paymentRow);
            }

            $this->pdo->commit();

            return [
                'order_bill' => [
                    'order_bill_id' => $orderBillId,
                    'created_at'    => $now,
                ],
                'bill' => [
                    'bill_id'          => $billId,
                    'order_bill_id'    => $orderBillId,
                    'store_id'         => (string)$normalizedInput['store_id'],
                    'bill_time'        => $now,
                    'subtotal_amount'  => (int)$calculated['subtotal_amount'],
                    'discount_amount'  => (int)$calculated['discount_amount'],
                    'tax_amount'       => (int)$calculated['tax_amount'],
                    'total_amount'     => (int)$calculated['total_amount'],
                    'split_mode'       => $normalizedInput['split_mode'],
                ],
                'order_header_ids' => $orderHeaderIds,
                'details' => $detailRows,
                'payments' => $paymentRows,
                'summary' => [
                    'subtotal_amount'         => (int)$calculated['subtotal_amount'],
                    'discount_amount'         => (int)$calculated['discount_amount'],
                    'subtotal_after_discount' => (int)($calculated['subtotal_after_discount'] ?? 0),
                    'tax_amount'              => (int)$calculated['tax_amount'],
                    'total_amount'            => (int)$calculated['total_amount'],
                    'tax_breakdown'           => $calculated['tax_breakdown'] ?? [],
                    'paid_amount'             => $this->sumPaymentAmount($normalizedInput['payments']),
                    'payment_count'           => count($paymentRows),
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * 分割会計の開始
     *
     * 1支払いごとにDBへ登録する方式で使う。
     * BILL / BILL_DETAIL / ORDER_BILL を先に作成し、BILL_PAYMENT はまだ作成しない。
     *
     * 戻り値の bill_id をセッションに保持し、
     * 以降の支払い確定で addPaymentToExistingBill() に渡す。
     */
    public function beginSplitCheckout(array $input): array
    {
        $normalizedInput = $this->normalizeInput($input);

        if (!in_array($normalizedInput['split_mode'], ['PERSON', 'AMOUNT'], true)) {
            throw new InvalidArgumentException('分割会計モードが不正です。');
        }

        $this->validator->validateDiscountWithDetails(
            (int)$normalizedInput['discount_amount'],
            $normalizedInput['details']
        );

        $calculated = $this->calculator->calculate(
            $normalizedInput['details'],
            (int)$normalizedInput['discount_amount']
        );

        $this->validator->validateDiscountAgainstSubtotal(
            (int)$normalizedInput['discount_amount'],
            (int)$calculated['subtotal_amount']
        );

        $now = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            $orderBillId = $this->orderBillRepository->insert($this->pdo, [
                'created_at' => $now,
            ]);

            $orderHeaderIds = [];
            if (!$normalizedInput['is_manual']) {
                $orderHeaderIds = $this->resolveOrderHeaderIds($normalizedInput, $orderBillId, $now);
            }

            $billId = $this->billRepository->insert($this->pdo, [
                'order_bill_id'    => $orderBillId,
                'store_id'         => (string)$normalizedInput['store_id'],
                'bill_time'        => $now,
                'subtotal_amount'  => (int)$calculated['subtotal_amount'],
                'discount_amount'  => (int)$calculated['discount_amount'],
                'tax_amount'       => (int)$calculated['tax_amount'],
                'total_amount'     => (int)$calculated['total_amount'],
                'split_mode'       => (string)$normalizedInput['split_mode'],
            ]);

            $detailRows = $this->buildBillDetailRows($billId, $calculated['details']);
            if (!empty($detailRows)) {
                $this->billDetailRepository->insertMany($this->pdo, $detailRows);
            }

            $this->pdo->commit();

            return [
                'order_bill' => [
                    'order_bill_id' => $orderBillId,
                    'created_at'    => $now,
                ],
                'bill' => [
                    'bill_id'          => $billId,
                    'order_bill_id'    => $orderBillId,
                    'store_id'         => (string)$normalizedInput['store_id'],
                    'bill_time'        => $now,
                    'subtotal_amount'  => (int)$calculated['subtotal_amount'],
                    'discount_amount'  => (int)$calculated['discount_amount'],
                    'tax_amount'       => (int)$calculated['tax_amount'],
                    'total_amount'     => (int)$calculated['total_amount'],
                    'split_mode'       => $normalizedInput['split_mode'],
                ],
                'order_header_ids' => $orderHeaderIds,
                'details' => $detailRows,
                'payments' => [],
                'summary' => [
                    'subtotal_amount'         => (int)$calculated['subtotal_amount'],
                    'discount_amount'         => (int)$calculated['discount_amount'],
                    'subtotal_after_discount' => (int)($calculated['subtotal_after_discount'] ?? 0),
                    'tax_amount'              => (int)$calculated['tax_amount'],
                    'total_amount'            => (int)$calculated['total_amount'],
                    'tax_breakdown'           => $calculated['tax_breakdown'] ?? [],
                    'paid_amount'             => 0,
                    'remaining_amount'        => (int)$calculated['total_amount'],
                    'payment_count'           => 0,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * 既存の分割会計BILLに、1件の支払いを即時登録する。
     *
     * カード・電子マネーの取消はこのメソッドでは行わない。
     * 返金が必要な場合は現金返金で運用対応する前提。
     */
    public function addPaymentToExistingBill(int $billId, array $payment): array
    {
        if ($billId <= 0) {
            throw new InvalidArgumentException('bill_id が不正です。');
        }

        $normalizedPayments = $this->normalizePayments([$payment]);
        if (empty($normalizedPayments)) {
            throw new InvalidArgumentException('支払い情報が不正です。');
        }

        $normalizedPayment = $normalizedPayments[0];

        $payMethod = (string)$normalizedPayment['pay_method'];
        $payAmount = (int)$normalizedPayment['pay_amount'];

        if (
            in_array($payMethod, ['CARD', 'ELECTRONIC_MONEY'], true)
            && trim((string)($normalizedPayment['provider'] ?? '')) === ''
        ) {
            throw new InvalidArgumentException('カード・電子マネーの場合は決済事業者名を入力してください。');
        }

        $now = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            $bill = $this->findBillForUpdate($billId);
            if (!$bill) {
                throw new InvalidArgumentException('会計データが見つかりません。');
            }

            $totalAmount = (int)$bill['total_amount'];
            $paidAmount = $this->sumPaidAmountForBill($billId);
            $remainingAmount = $totalAmount - $paidAmount;

            if ($remainingAmount <= 0) {
                throw new InvalidArgumentException('残額がありません。');
            }

            if ($payAmount > $remainingAmount) {
                throw new InvalidArgumentException('残額を超える金額は登録できません。');
            }

            if ($payMethod === 'CASH') {
                $this->validator->validatePayment(
                    $payMethod,
                    $payAmount,
                    $normalizedPayment['received_amount'] ?? null
                );
            }

            $paymentRow = $this->buildBillPaymentRows($billId, [$normalizedPayment], $now)[0];
            $paymentId = $this->billPaymentRepository->insert($this->pdo, $paymentRow);

            if (is_int($paymentId) && $paymentId > 0) {
                $paymentRow['bill_payment_id'] = $paymentId;
            }

            $paidAmountAfter = $paidAmount + $payAmount;
            $remainingAmountAfter = max(0, $totalAmount - $paidAmountAfter);

            $this->pdo->commit();

            return [
                'bill' => [
                    'bill_id'       => $billId,
                    'order_bill_id' => (int)($bill['order_bill_id'] ?? 0),
                    'store_id'      => (string)($bill['store_id'] ?? ''),
                    'bill_time'     => (string)($bill['bill_time'] ?? ''),
                    'total_amount'  => $totalAmount,
                    'split_mode'    => (string)($bill['split_mode'] ?? 'NONE'),
                ],
                'payment' => $paymentRow,
                'summary' => [
                    'paid_amount'      => $paidAmountAfter,
                    'remaining_amount' => $remainingAmountAfter,
                    'is_completed'     => ($remainingAmountAfter === 0),
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * 分割会計の支払い進捗を取得する。
     */
    public function getSplitPaymentProgress(int $billId): array
    {
        if ($billId <= 0) {
            throw new InvalidArgumentException('bill_id が不正です。');
        }

        $bill = $this->findBill($billId);
        if (!$bill) {
            throw new InvalidArgumentException('会計データが見つかりません。');
        }

        $payments = $this->findPaymentsByBillId($billId);

        $paidAmount = 0;
        foreach ($payments as $payment) {
            $paidAmount += (int)($payment['pay_amount'] ?? 0);
        }

        $totalAmount = (int)$bill['total_amount'];
        $remainingAmount = max(0, $totalAmount - $paidAmount);

        return [
            'bill' => $bill,
            'payments' => $payments,
            'summary' => [
                'total_amount'     => $totalAmount,
                'paid_amount'      => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_count'    => count($payments),
                'is_completed'     => ($remainingAmount === 0),
            ],
        ];
    }

    /**
     * 分割会計を完了可能か確認し、完了画面で使える結果形式を返す。
     *
     * BILL自体に status カラムがない構成でも動くように、ここでは更新処理を行わず、
     * 支払合計が請求額と一致しているかだけを検証する。
     */
    public function completeExistingSplitCheckout(int $billId): array
    {
        $progress = $this->getSplitPaymentProgress($billId);

        $totalAmount = (int)$progress['summary']['total_amount'];
        $paidAmount = (int)$progress['summary']['paid_amount'];

        if ($paidAmount !== $totalAmount) {
            throw new InvalidArgumentException('支払合計が請求額と一致していません。');
        }

        $bill = $progress['bill'];
        $payments = $progress['payments'];
        $details = $this->findBillDetailsByBillId($billId);

        return [
            'order_bill' => [
                'order_bill_id' => (int)($bill['order_bill_id'] ?? 0),
                'created_at'    => (string)($bill['bill_time'] ?? ''),
            ],
            'bill' => [
                'bill_id'          => (int)($bill['bill_id'] ?? $billId),
                'order_bill_id'    => (int)($bill['order_bill_id'] ?? 0),
                'store_id'         => (string)($bill['store_id'] ?? ''),
                'bill_time'        => (string)($bill['bill_time'] ?? ''),
                'subtotal_amount'  => (int)($bill['subtotal_amount'] ?? 0),
                'discount_amount'  => (int)($bill['discount_amount'] ?? 0),
                'tax_amount'       => (int)($bill['tax_amount'] ?? 0),
                'total_amount'     => (int)($bill['total_amount'] ?? 0),
                'split_mode'       => (string)($bill['split_mode'] ?? 'NONE'),
            ],
            'order_header_ids' => [],
            'details' => $details,
            'payments' => $payments,
            'summary' => [
                'subtotal_amount'         => (int)($bill['subtotal_amount'] ?? 0),
                'discount_amount'         => (int)($bill['discount_amount'] ?? 0),
                'subtotal_after_discount' => max(
                    0,
                    (int)($bill['subtotal_amount'] ?? 0) - (int)($bill['discount_amount'] ?? 0)
                ),
                'tax_amount'              => (int)($bill['tax_amount'] ?? 0),
                'total_amount'            => (int)($bill['total_amount'] ?? 0),
                'tax_breakdown'           => [],
                'paid_amount'             => $paidAmount,
                'payment_count'           => count($payments),
            ],
        ];
    }

    private function normalizeInput(array $input): array
    {
        $splitMode = strtoupper(trim((string)($input['split_mode'] ?? 'NONE')));
        $allowedModes = ['NONE', 'PERSON', 'AMOUNT', 'ITEM'];

        if (!in_array($splitMode, $allowedModes, true)) {
            $splitMode = 'NONE';
        }

        $payments = is_array($input['payments'] ?? null)
            ? $this->normalizePayments($input['payments'])
            : [];

        return [
            'is_manual' => $this->toBool($input['is_manual'] ?? false),
            'store_id' => $this->resolveStoreId($input),
            'customer_id' => trim((string)($input['customer_id'] ?? '')),
            'entry_time' => isset($input['entry_time']) && $input['entry_time'] !== ''
                ? $this->toDbDateTime((string)$input['entry_time'])
                : null,
            'order_header_ids' => is_array($input['order_header_ids'] ?? null)
                ? array_values(array_unique(array_map('intval', $input['order_header_ids'])))
                : [],
            'discount_amount' => $this->toInt($input['discount_amount'] ?? 0),
            'split_mode' => $splitMode,
            'details' => is_array($input['details'] ?? null)
                ? $this->normalizeDetails($input['details'])
                : [],
            'payments' => $payments,
        ];
    }

    private function normalizePayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $payMethod = strtoupper(trim((string)($payment['pay_method'] ?? '')));
            $payAmount = $this->toNullableInt($payment['pay_amount'] ?? null);
            $payTime = isset($payment['pay_time']) && $payment['pay_time'] !== ''
                ? $this->toDbDateTime((string)$payment['pay_time'])
                : null;
            $receivedAmount = array_key_exists('received_amount', $payment)
                ? $this->toNullableInt($payment['received_amount'])
                : null;
            $changeAmount = array_key_exists('change_amount', $payment)
                ? $this->toNullableInt($payment['change_amount'])
                : null;
            $provider = isset($payment['provider']) && $payment['provider'] !== ''
                ? trim((string)$payment['provider'])
                : null;

            if ($payMethod === '' || $payAmount === null || $payAmount <= 0) {
                continue;
            }

            if ($payMethod === 'CASH') {
                if ($receivedAmount === null || $receivedAmount < $payAmount) {
                    throw new InvalidArgumentException('現金支払いの受領額が不足しています。');
                }

                if ($changeAmount === null) {
                    $changeAmount = $receivedAmount - $payAmount;
                }
            } else {
                $receivedAmount = null;
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

    private function validatePayments(array $payments, int $totalAmount): void
    {
        if (empty($payments)) {
            throw new InvalidArgumentException('支払い情報がありません。');
        }

        $paidAmount = 0;

        foreach ($payments as $payment) {
            $payMethod = (string)($payment['pay_method'] ?? '');
            $payAmount = (int)($payment['pay_amount'] ?? 0);
            $receivedAmount = $payment['received_amount'] ?? null;

            if ($payMethod === '' || $payAmount <= 0) {
                throw new InvalidArgumentException('支払い情報が不正です。');
            }

            if ($payMethod === 'CASH') {
                $this->validator->validatePayment(
                    $payMethod,
                    $payAmount,
                    $receivedAmount
                );
            }

            $paidAmount += $payAmount;
        }

        if ($paidAmount !== $totalAmount) {
            throw new InvalidArgumentException('支払合計が請求額と一致していません。');
        }
    }

    private function resolveOrderHeaderIds(array $input, int $orderBillId, string $now): array
    {
        $ids = $input['order_header_ids'] ?? [];

        if (!empty($ids)) {
            $existingRows = $this->orderHeaderRepository->findByIdsForUpdate($this->pdo, $ids);

            if (count($existingRows) !== count($ids)) {
                throw new InvalidArgumentException('存在しない注文が含まれています。');
            }

            foreach ($existingRows as $row) {
                if (!empty($row['order_bill_id'])) {
                    throw new InvalidArgumentException('すでに会計グループに紐づいている注文が含まれています。');
                }
            }

            $this->orderHeaderRepository->attachOrderBillId($this->pdo, $ids, $orderBillId);
            return $ids;
        }

        if ($input['customer_id'] === '') {
            throw new InvalidArgumentException('customer_id が未指定です。');
        }

        if ($input['entry_time'] === null) {
            throw new InvalidArgumentException('entry_time が未指定です。');
        }

        $orderId = $this->orderHeaderRepository->insert($this->pdo, [
            'order_bill_id'      => $orderBillId,
            'customer_id'        => (string)$input['customer_id'],
            'entry_time'         => (string)$input['entry_time'],
            'hash'               => '',
            'mos_update_status'  => null,
            'mos_error_code'     => null,
            'mos_error_message'  => null,
            'mos_updated_at'     => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return [$orderId];
    }

    private function buildBillDetailRows(int $billId, array $details): array
    {
        $rows = [];

        foreach ($details as $detail) {
            $rows[] = [
                'bill_id'        => $billId,
                'menu_name'      => (string)($detail['menu_name'] ?? ''),
                'category_name'  => $detail['category_name'] ?? null,
                'qty'            => (int)($detail['qty'] ?? 0),
                'unit_price'     => (int)($detail['unit_price'] ?? 0),
                'amount'         => (int)($detail['amount'] ?? 0),
                'tax_rate'       => (int)($detail['tax_rate'] ?? 0),
            ];
        }

        return $rows;
    }

    private function buildBillPaymentRows(int $billId, array $payments, string $defaultPayTime): array
    {
        $rows = [];

        foreach ($payments as $payment) {
            $payMethod = (string)($payment['pay_method'] ?? '');

            $rows[] = [
                'bill_id'         => $billId,
                'pay_method'      => $payMethod,
                'pay_amount'      => (int)($payment['pay_amount'] ?? 0),
                'pay_time'        => $payment['pay_time'] ?? $defaultPayTime,
                'received_amount' => $payMethod === 'CASH'
                    ? ($payment['received_amount'] ?? null)
                    : null,
                'change_amount'   => $payMethod === 'CASH'
                    ? ($payment['change_amount'] ?? null)
                    : null,
                'provider'        => $payment['provider'] ?? null,
            ];
        }

        return $rows;
    }

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
            $qty = (int)($detail['qty'] ?? 0);
            $unitPrice = (int)($detail['unit_price'] ?? 0);
            $taxRate = (int)($detail['tax_rate'] ?? 0);

            if ($menuName === '' || $qty <= 0 || $unitPrice <= 0) {
                continue;
            }

            $normalized[] = [
                'menu_name'     => $menuName,
                'category_name' => $categoryName,
                'qty'           => $qty,
                'unit_price'    => $unitPrice,
                'tax_rate'      => $taxRate,
            ];
        }

        return $normalized;
    }

    private function sumPaymentAmount(array $payments): int
    {
        $sum = 0;

        foreach ($payments as $payment) {
            $sum += (int)($payment['pay_amount'] ?? 0);
        }

        return $sum;
    }

    private function findBill(int $billId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                bill_id,
                order_bill_id,
                store_id,
                bill_time,
                subtotal_amount,
                discount_amount,
                tax_amount,
                total_amount,
                split_mode
            FROM BILL
            WHERE bill_id = :bill_id
            LIMIT 1
        ');

        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function findBillForUpdate(int $billId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                bill_id,
                order_bill_id,
                store_id,
                bill_time,
                subtotal_amount,
                discount_amount,
                tax_amount,
                total_amount,
                split_mode
            FROM BILL
            WHERE bill_id = :bill_id
            LIMIT 1
            FOR UPDATE
        ');

        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function sumPaidAmountForBill(int $billId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(pay_amount), 0) AS paid_amount
            FROM BILL_PAYMENT
            WHERE bill_id = :bill_id
        ');

        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    private function findPaymentsByBillId(int $billId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                bill_payment_id,
                bill_id,
                pay_method,
                pay_amount,
                pay_time,
                received_amount,
                change_amount,
                provider
            FROM BILL_PAYMENT
            WHERE bill_id = :bill_id
            ORDER BY pay_time ASC
        ');

        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findBillDetailsByBillId(int $billId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                bill_id,
                menu_name,
                category_name,
                qty,
                unit_price,
                amount,
                tax_rate
            FROM BILL_DETAIL
            WHERE bill_id = :bill_id
            ORDER BY bill_detail_id ASC
        ');

        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function toDbDateTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('日時が空です。');
        }

        return str_replace('T', ' ', $value);
    }

    private function toInt(mixed $value): int
    {
        return (int)$value;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
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

    private function resolveStoreId(array $input): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionStoreId = trim((string)($_SESSION['account']['store_id'] ?? $_SESSION['storeId'] ?? ''));

        if ($sessionStoreId !== '') {
            return $sessionStoreId;
        }

        return trim((string)($input['store_id'] ?? ''));
    }
}
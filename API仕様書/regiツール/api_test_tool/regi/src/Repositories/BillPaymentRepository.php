<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class BillPaymentRepository
{
    private const ALLOWED_PAYMENT_METHODS = [
        'CASH',
        'CARD',
        'ELECTRONIC_MONEY',
    ];

    /**
     * 支払い明細を1件登録する。
     *
     * @return int 登録後のbill_payment_id
     */
    public function insert(PDO $pdo, array $payment): int
    {
        $normalized = $this->normalizePayment($payment);

        $sql = <<<'SQL'
            INSERT INTO bill_payment (
                bill_id,
                pay_method,
                pay_amount,
                pay_time,
                received_amount,
                change_amount,
                provider
            ) VALUES (
                :bill_id,
                :pay_method,
                :pay_amount,
                :pay_time,
                :received_amount,
                :change_amount,
                :provider
            )
        SQL;

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':bill_id' => $normalized['bill_id'],
            ':pay_method' => $normalized['pay_method'],
            ':pay_amount' => $normalized['pay_amount'],
            ':pay_time' => $normalized['pay_time'],
            ':received_amount'
                => $normalized['received_amount'],
            ':change_amount'
                => $normalized['change_amount'],
            ':provider' => $normalized['provider'],
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * 支払い明細を複数件登録する。
     *
     * @return int[] 登録後のbill_payment_id一覧
     */
    public function insertMany(
        PDO $pdo,
        array $payments
    ): array {
        $ids = [];

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $ids[] = $this->insert($pdo, $payment);
        }

        return $ids;
    }

    /**
     * 会計IDに紐づく支払い明細一覧を取得する。
     */
    public function findByBillId(
        PDO $pdo,
        int $billId
    ): array {
        if ($billId <= 0) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT
                bill_payment_id,
                bill_id,
                pay_method,
                pay_amount,
                pay_time,
                received_amount,
                change_amount,
                provider
            FROM bill_payment
            WHERE bill_id = :bill_id
            ORDER BY bill_payment_id ASC
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            [$this, 'castPaymentRow'],
            $rows
        );
    }

    /**
     * 支払い明細IDで1件取得する。
     */
    public function findById(
        PDO $pdo,
        int $billPaymentId
    ): ?array {
        if ($billPaymentId <= 0) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT
                bill_payment_id,
                bill_id,
                pay_method,
                pay_amount,
                pay_time,
                received_amount,
                change_amount,
                provider
            FROM bill_payment
            WHERE bill_payment_id = :bill_payment_id
            LIMIT 1
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_payment_id' => $billPaymentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false
            ? $this->castPaymentRow($row)
            : null;
    }

    /**
     * 会計IDに紐づく支払合計を取得する。
     */
    public function sumPaidAmountByBillId(
        PDO $pdo,
        int $billId
    ): int {
        if ($billId <= 0) {
            return 0;
        }

        $sql = <<<'SQL'
            SELECT
                COALESCE(SUM(pay_amount), 0)
            FROM bill_payment
            WHERE bill_id = :bill_id
              AND pay_amount > 0
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * 会計IDに紐づく支払い件数を取得する。
     */
    public function countByBillId(
        PDO $pdo,
        int $billId
    ): int {
        if ($billId <= 0) {
            return 0;
        }

        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM bill_payment
            WHERE bill_id = :bill_id
              AND pay_amount > 0
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * 支払いが1件以上存在するか確認する。
     */
    public function existsByBillId(
        PDO $pdo,
        int $billId
    ): bool {
        return $this->countByBillId(
            $pdo,
            $billId
        ) > 0;
    }

    /**
     * 会計の支払い進捗を取得する。
     *
     * BILL行のロックはBillRepository::findByIdForUpdate()
     * などで先に行うこと。
     */
    public function getPaymentSummary(
        PDO $pdo,
        int $billId,
        int $totalAmount
    ): array {
        if ($billId <= 0) {
            throw new InvalidArgumentException(
                'bill_id が不正です。'
            );
        }

        if ($totalAmount < 0) {
            throw new InvalidArgumentException(
                '請求額が不正です。'
            );
        }

        $paidAmount = $this->sumPaidAmountByBillId(
            $pdo,
            $billId
        );

        $paymentCount = $this->countByBillId(
            $pdo,
            $billId
        );

        $remainingAmount = max(
            0,
            $totalAmount - $paidAmount
        );

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_count' => $paymentCount,
            'is_payment_started' => $paymentCount > 0,
            'is_completed' => $remainingAmount === 0,
        ];
    }

    /**
     * 残額を超えないことを確認して支払いを追加する。
     *
     * 呼び出し側でトランザクションを開始し、
     * BILL行をFOR UPDATEでロックしてから使用する。
     */
    public function addPaymentSafely(
        PDO $pdo,
        array $payment,
        int $totalAmount
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                '支払い追加前にトランザクションを開始してください。'
            );
        }

        $normalized = $this->normalizePayment($payment);
        $billId = (int)$normalized['bill_id'];

        $summaryBefore = $this->getPaymentSummary(
            $pdo,
            $billId,
            $totalAmount
        );

        if ($summaryBefore['is_completed']) {
            throw new RuntimeException(
                'この会計はすでに全額支払い済みです。'
            );
        }

        if (
            $normalized['pay_amount']
            > $summaryBefore['remaining_amount']
        ) {
            throw new RuntimeException(
                '残額を超える支払いは登録できません。'
            );
        }

        $paymentId = $this->insert(
            $pdo,
            $normalized
        );

        $summaryAfter = $this->getPaymentSummary(
            $pdo,
            $billId,
            $totalAmount
        );

        $savedPayment = $normalized;
        $savedPayment['bill_payment_id']
            = $paymentId;

        return [
            'payment' => $savedPayment,
            'summary' => $summaryAfter,
        ];
    }

    /**
     * 指定した支払い明細を削除する。
     *
     * 現在の運用では確定済み支払いを削除しない方針のため、
     * 管理・保守用途以外では使用しないこと。
     */
    public function deleteById(
        PDO $pdo,
        int $billPaymentId
    ): bool {
        if ($billPaymentId <= 0) {
            return false;
        }

        $sql = <<<'SQL'
            DELETE FROM bill_payment
            WHERE bill_payment_id = :bill_payment_id
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_payment_id' => $billPaymentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 登録前の支払いデータを検証・整形する。
     */
    private function normalizePayment(
        array $payment
    ): array {
        $billId = (int)($payment['bill_id'] ?? 0);

        $payMethod = strtoupper(
            trim((string)(
                $payment['pay_method'] ?? ''
            ))
        );

        $payAmount = (int)(
            $payment['pay_amount'] ?? 0
        );

        $payTime = trim((string)(
            $payment['pay_time']
            ?? date('Y-m-d H:i:s')
        ));

        $receivedAmount = array_key_exists(
            'received_amount',
            $payment
        ) && $payment['received_amount'] !== null
            && $payment['received_amount'] !== ''
                ? (int)$payment['received_amount']
                : null;

        $changeAmount = array_key_exists(
            'change_amount',
            $payment
        ) && $payment['change_amount'] !== null
            && $payment['change_amount'] !== ''
                ? (int)$payment['change_amount']
                : null;

        $provider = isset($payment['provider'])
            && trim((string)$payment['provider']) !== ''
                ? trim((string)$payment['provider'])
                : null;

        if ($billId <= 0) {
            throw new InvalidArgumentException(
                'bill_id が不正です。'
            );
        }

        if (!in_array(
            $payMethod,
            self::ALLOWED_PAYMENT_METHODS,
            true
        )) {
            throw new InvalidArgumentException(
                '支払方法が不正です。'
            );
        }

        if ($payAmount <= 0) {
            throw new InvalidArgumentException(
                '支払額が不正です。'
            );
        }

        if ($payTime === '') {
            $payTime = date('Y-m-d H:i:s');
        }

        if ($payMethod === 'CASH') {
            if ($receivedAmount === null) {
                $receivedAmount = $payAmount;
            }

            if ($receivedAmount < $payAmount) {
                throw new InvalidArgumentException(
                    '現金受領額が支払額未満です。'
                );
            }

            if ($changeAmount === null) {
                $changeAmount
                    = $receivedAmount - $payAmount;
            }

            if ($changeAmount < 0) {
                throw new InvalidArgumentException(
                    'おつりの金額が不正です。'
                );
            }

            $provider = null;
        } else {
            if ($provider === null) {
                throw new InvalidArgumentException(
                    'カード・電子マネーの場合は決済事業者名を入力してください。'
                );
            }

            $receivedAmount = null;
            $changeAmount = null;
        }

        return [
            'bill_id' => $billId,
            'pay_method' => $payMethod,
            'pay_amount' => $payAmount,
            'pay_time' => $payTime,
            'received_amount' => $receivedAmount,
            'change_amount' => $changeAmount,
            'provider' => $provider,
        ];
    }

    /**
     * DB取得値を適切な型へ変換する。
     */
    private function castPaymentRow(array $row): array
    {
        $row['bill_payment_id']
            = (int)($row['bill_payment_id'] ?? 0);

        $row['bill_id']
            = (int)($row['bill_id'] ?? 0);

        $row['pay_amount']
            = (int)($row['pay_amount'] ?? 0);

        $row['received_amount']
            = $row['received_amount'] !== null
                ? (int)$row['received_amount']
                : null;

        $row['change_amount']
            = $row['change_amount'] !== null
                ? (int)$row['change_amount']
                : null;

        return $row;
    }
}

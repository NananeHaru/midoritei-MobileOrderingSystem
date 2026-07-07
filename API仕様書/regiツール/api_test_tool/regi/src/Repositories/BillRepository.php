<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class BillRepository
{
    private const ALLOWED_SPLIT_MODES = [
        'NONE',
        'PERSON',
        'AMOUNT',
        'ITEM',
    ];

    /**
     * 会計ヘッダを1件登録する。
     *
     * @return int 登録後のbill_id
     */
    public function insert(PDO $pdo, array $bill): int
    {
        $splitMode = strtoupper(
            trim((string)($bill['split_mode'] ?? 'NONE'))
        );

        if (!in_array(
            $splitMode,
            self::ALLOWED_SPLIT_MODES,
            true
        )) {
            throw new InvalidArgumentException(
                '分割方法が不正です。'
            );
        }

        $sql = <<<'SQL'
            INSERT INTO bill (
                order_bill_id,
                store_id,
                bill_time,
                subtotal_amount,
                discount_amount,
                tax_amount,
                total_amount,
                split_mode
            ) VALUES (
                :order_bill_id,
                :store_id,
                :bill_time,
                :subtotal_amount,
                :discount_amount,
                :tax_amount,
                :total_amount,
                :split_mode
            )
        SQL;

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':order_bill_id'
                => (int)($bill['order_bill_id'] ?? 0),
            ':store_id'
                => (string)($bill['store_id'] ?? ''),
            ':bill_time'
                => (string)(
                    $bill['bill_time']
                    ?? date('Y-m-d H:i:s')
                ),
            ':subtotal_amount'
                => (int)($bill['subtotal_amount'] ?? 0),
            ':discount_amount'
                => (int)($bill['discount_amount'] ?? 0),
            ':tax_amount'
                => (int)($bill['tax_amount'] ?? 0),
            ':total_amount'
                => (int)($bill['total_amount'] ?? 0),
            ':split_mode'
                => $splitMode,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * bill_idで会計ヘッダを取得する。
     */
    public function findById(
        PDO $pdo,
        int $billId
    ): ?array {
        if ($billId <= 0) {
            return null;
        }

        $sql = <<<'SQL'
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
            FROM bill
            WHERE bill_id = :bill_id
            LIMIT 1
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * bill_idで会計ヘッダをロック付き取得する。
     *
     * 支払い追加処理のトランザクション内で使用する。
     */
    public function findByIdForUpdate(
        PDO $pdo,
        int $billId
    ): ?array {
        if ($billId <= 0) {
            return null;
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'FOR UPDATEを使用する場合はトランザクションを開始してください。'
            );
        }

        $sql = <<<'SQL'
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
            FROM bill
            WHERE bill_id = :bill_id
            LIMIT 1
            FOR UPDATE
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * ORDER_BILL単位で取得する。
     *
     * レシート再表示などに利用する。
     */
    public function findByOrderBillId(
        PDO $pdo,
        int $orderBillId
    ): array {
        if ($orderBillId <= 0) {
            return [];
        }

        $sql = <<<'SQL'
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
            FROM bill
            WHERE order_bill_id = :order_bill_id
            ORDER BY bill_id ASC
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_bill_id' => $orderBillId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ORDER_BILL単位で最新の会計を1件取得する。
     */
    public function findLatestByOrderBillId(
        PDO $pdo,
        int $orderBillId
    ): ?array {
        if ($orderBillId <= 0) {
            return null;
        }

        $sql = <<<'SQL'
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
            FROM bill
            WHERE order_bill_id = :order_bill_id
            ORDER BY bill_id DESC
            LIMIT 1
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_bill_id' => $orderBillId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * 顧客番号から、支払い途中の分割会計を1件取得する。
     *
     * 条件:
     * - split_mode が PERSON / AMOUNT / ITEM
     * - BILL_PAYMENT が1件以上存在
     * - 支払合計が請求額未満
     */
    public function findIncompleteSplitBillByCustomerId(
        PDO $pdo,
        string $customerId
    ): ?array {
        $customerId = trim($customerId);

        if (!preg_match('/^\d{7}$/', $customerId)) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode,
                COUNT(bp.bill_payment_id) AS payment_count,
                COALESCE(SUM(bp.pay_amount), 0) AS paid_amount,
                GREATEST(
                    b.total_amount
                    - COALESCE(SUM(bp.pay_amount), 0),
                    0
                ) AS remaining_amount
            FROM bill AS b
            INNER JOIN order_header AS oh
                ON oh.order_bill_id = b.order_bill_id
            INNER JOIN bill_payment AS bp
                ON bp.bill_id = b.bill_id
               AND bp.pay_amount > 0
            WHERE oh.customer_id = :customer_id
              AND b.split_mode IN (
                  'PERSON',
                  'AMOUNT',
                  'ITEM'
              )
            GROUP BY
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode
            HAVING COUNT(bp.bill_payment_id) > 0
               AND COALESCE(SUM(bp.pay_amount), 0)
                   < b.total_amount
            ORDER BY b.bill_id DESC
            LIMIT 1
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customerId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->castPaymentSummary($row);
    }

    /**
     * bill_idから支払い状況付きで会計情報を取得する。
     */
    public function findWithPaymentSummaryById(
        PDO $pdo,
        int $billId
    ): ?array {
        if ($billId <= 0) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode,
                COUNT(bp.bill_payment_id) AS payment_count,
                COALESCE(SUM(bp.pay_amount), 0) AS paid_amount,
                GREATEST(
                    b.total_amount
                    - COALESCE(SUM(bp.pay_amount), 0),
                    0
                ) AS remaining_amount
            FROM bill AS b
            LEFT JOIN bill_payment AS bp
                ON bp.bill_id = b.bill_id
               AND bp.pay_amount > 0
            WHERE b.bill_id = :bill_id
            GROUP BY
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode
            LIMIT 1
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->castPaymentSummary($row);
    }

    /**
     * 分割方法を更新する。
     *
     * 支払い開始後の変更禁止はService側で判定する。
     */
    public function updateSplitMode(
        PDO $pdo,
        int $billId,
        string $splitMode
    ): bool {
        if ($billId <= 0) {
            throw new InvalidArgumentException(
                'bill_id が不正です。'
            );
        }

        $splitMode = strtoupper(trim($splitMode));

        if (!in_array(
            $splitMode,
            self::ALLOWED_SPLIT_MODES,
            true
        )) {
            throw new InvalidArgumentException(
                '分割方法が不正です。'
            );
        }

        $sql = <<<'SQL'
            UPDATE bill
            SET split_mode = :split_mode
            WHERE bill_id = :bill_id
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':split_mode' => $splitMode,
            ':bill_id' => $billId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 支払い集計付きデータを整数型へ変換する。
     */
    private function castPaymentSummary(array $row): array
    {
        $row['bill_id']
            = (int)($row['bill_id'] ?? 0);
        $row['order_bill_id']
            = (int)($row['order_bill_id'] ?? 0);
        $row['subtotal_amount']
            = (int)($row['subtotal_amount'] ?? 0);
        $row['discount_amount']
            = (int)($row['discount_amount'] ?? 0);
        $row['tax_amount']
            = (int)($row['tax_amount'] ?? 0);
        $row['total_amount']
            = (int)($row['total_amount'] ?? 0);
        $row['payment_count']
            = (int)($row['payment_count'] ?? 0);
        $row['paid_amount']
            = (int)($row['paid_amount'] ?? 0);
        $row['remaining_amount']
            = (int)($row['remaining_amount'] ?? 0);

        $row['is_payment_started']
            = $row['payment_count'] > 0;
        $row['is_fully_paid']
            = $row['remaining_amount'] === 0;

        return $row;
    }
}

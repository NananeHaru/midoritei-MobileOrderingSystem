<?php

namespace App\Repositories;

use PDO;

class OrderHeaderRepository
{
    /**
     * 注文ヘッダを1件登録する
     *
     * @return int order_id
     */
    public function insert(PDO $pdo, array $order): int
    {
        $sql = "
            INSERT INTO order_header (
                order_bill_id,
                customer_id,
                entry_time,
                hash,
                mos_update_status,
                mos_error_code,
                mos_error_message,
                mos_updated_at,
                created_at,
                updated_at
            ) VALUES (
                :order_bill_id,
                :customer_id,
                :entry_time,
                :hash,
                :mos_update_status,
                :mos_error_code,
                :mos_error_message,
                :mos_updated_at,
                :created_at,
                :updated_at
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'order_bill_id'      => $order['order_bill_id'] ?? null,
            'customer_id'        => $order['customer_id'],
            'entry_time'         => $order['entry_time'],
            'hash'               => $order['hash'] ?? '',
            'mos_update_status'  => $order['mos_update_status'] ?? null,
            'mos_error_code'     => $order['mos_error_code'] ?? null,
            'mos_error_message'  => $order['mos_error_message'] ?? null,
            'mos_updated_at'     => $order['mos_updated_at'] ?? null,
            'created_at'         => $order['created_at'],
            'updated_at'         => $order['updated_at'],
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * order_id で取得
     */
    public function findById(PDO $pdo, int $orderId): ?array
    {
        $sql = "
            SELECT
                order_id,
                order_bill_id,
                customer_id,
                entry_time,
                hash,
                mos_update_status,
                mos_error_code,
                mos_error_message,
                mos_updated_at,
                created_at,
                updated_at
            FROM order_header
            WHERE order_id = :order_id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 複数IDをロック取得（会計時）
     */
    public function findByIdsForUpdate(PDO $pdo, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT
                order_id,
                order_bill_id,
                customer_id,
                entry_time
            FROM order_header
            WHERE order_id IN ($placeholders)
            FOR UPDATE
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_map('intval', $ids));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ORDER_BILLに紐づける（会計時）
     */
    public function attachOrderBillId(PDO $pdo, array $ids, int $orderBillId): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            UPDATE order_header
            SET order_bill_id = ?
            WHERE order_id IN ($placeholders)
        ";

        $params = array_merge([$orderBillId], array_map('intval', $ids));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * 未会計注文（customer単位）
     */
    public function findOpenByCustomerId(PDO $pdo, string $customerId): array
    {
        $sql = "
            SELECT
                order_id,
                order_bill_id,
                customer_id,
                entry_time
            FROM order_header
            WHERE customer_id = :customer_id
              AND order_bill_id IS NULL
            ORDER BY order_id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 未会計注文（全体）
     */
    public function findOpenAll(PDO $pdo): array
    {
        $sql = "
            SELECT
                order_id,
                order_bill_id,
                customer_id,
                entry_time
            FROM order_header
            WHERE order_bill_id IS NULL
            ORDER BY order_id ASC
        ";

        $stmt = $pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
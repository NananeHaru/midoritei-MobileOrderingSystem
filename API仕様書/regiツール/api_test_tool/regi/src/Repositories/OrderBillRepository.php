<?php

namespace App\Repositories;

use PDO;

class OrderBillRepository
{
    /**
     * ORDER_BILL を1件登録する
     *
     * @return int order_bill_id
     */
    public function insert(PDO $pdo, array $data): int
    {
        $sql = "
            INSERT INTO order_bill (
                created_at
            ) VALUES (
                :created_at
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * order_bill_id で取得
     */
    public function findById(PDO $pdo, int $orderBillId): ?array
    {
        $sql = "
            SELECT
                order_bill_id,
                created_at
            FROM order_bill
            WHERE order_bill_id = :order_bill_id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'order_bill_id' => $orderBillId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 最新の ORDER_BILL を取得（デバッグ・確認用）
     */
    public function findLatest(PDO $pdo): ?array
    {
        $sql = "
            SELECT
                order_bill_id,
                created_at
            FROM order_bill
            ORDER BY order_bill_id DESC
            LIMIT 1
        ";

        $stmt = $pdo->query($sql);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 一覧取得（管理画面用）
     */
    public function findAll(PDO $pdo, int $limit = 100): array
    {
        $sql = "
            SELECT
                order_bill_id,
                created_at
            FROM order_bill
            ORDER BY order_bill_id DESC
            LIMIT :limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 削除（基本使わないがテスト用）
     */
    public function delete(PDO $pdo, int $orderBillId): void
    {
        $sql = "
            DELETE FROM order_bill
            WHERE order_bill_id = :order_bill_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'order_bill_id' => $orderBillId,
        ]);
    }
}
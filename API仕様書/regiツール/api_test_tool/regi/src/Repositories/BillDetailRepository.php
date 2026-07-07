<?php

namespace App\Repositories;

use PDO;

class BillDetailRepository
{
    /**
     * 会計明細を複数件登録する
     *
     * 想定:
     * [
     *   [
     *     'bill_id' => 1,
     *     'menu_name' => '焼き鳥',
     *     'category_name' => '串物',
     *     'qty' => 2,
     *     'unit_price' => 300,
     *     'amount' => 600,
     *     'tax_rate' => 10,
     *   ],
     * ]
     */
    public function insertMany(PDO $pdo, array $details): void
    {
        if (empty($details)) {
            return;
        }

        $sql = "
            INSERT INTO bill_detail (
                bill_id,
                menu_name,
                category_name,
                qty,
                unit_price,
                amount,
                tax_rate
            ) VALUES (
                :bill_id,
                :menu_name,
                :category_name,
                :qty,
                :unit_price,
                :amount,
                :tax_rate
            )
        ";

        $stmt = $pdo->prepare($sql);

        foreach ($details as $detail) {
            $stmt->execute([
                'bill_id'       => (int)($detail['bill_id'] ?? 0),
                'menu_name'     => (string)($detail['menu_name'] ?? ''),
                'category_name' => $detail['category_name'] ?? null,
                'qty'           => (int)($detail['qty'] ?? 0),
                'unit_price'    => (int)($detail['unit_price'] ?? 0),
                'amount'        => (int)($detail['amount'] ?? 0),
                'tax_rate'      => (int)($detail['tax_rate'] ?? 0),
            ]);
        }
    }

    /**
     * 会計IDに紐づく明細一覧を取得する
     */
    public function findByBillId(PDO $pdo, int $billId): array
    {
        $sql = "
            SELECT
                bill_detail_id,
                bill_id,
                menu_name,
                category_name,
                qty,
                unit_price,
                amount,
                tax_rate
            FROM bill_detail
            WHERE bill_id = :bill_id
            ORDER BY bill_detail_id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'bill_id' => $billId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
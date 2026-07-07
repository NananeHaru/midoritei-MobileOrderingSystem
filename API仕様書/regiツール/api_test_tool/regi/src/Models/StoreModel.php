<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class StoreModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require dirname(__DIR__) . '/Database/db.php';
    }

    public function findActiveStores(): array
    {
        $sql = "
            SELECT
                store_id,
                store_name,
                store_address,
                store_phone
            FROM stores
            WHERE is_active = 1
            ORDER BY store_id ASC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStoreId(string $storeId): ?array
    {
        $storeId = trim($storeId);

        if ($storeId === '') {
            return null;
        }

        $sql = "
            SELECT
                store_id,
                store_name,
                store_address,
                store_phone
            FROM stores
            WHERE store_id = :store_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
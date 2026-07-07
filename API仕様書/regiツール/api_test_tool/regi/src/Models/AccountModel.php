<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AccountModel
{
    private function getConnection(): PDO
    {
        /** @var PDO $pdo */
        $pdo = require dirname(__DIR__) . '/Database/db.php';
        return $pdo;
    }

    public function findActiveByLoginId(string $loginId): ?array
    {
        $pdo = $this->getConnection();

        $sql = '
            SELECT
                account_id,
                login_id,
                password_hash,
                account_name,
                role_type,
                store_id,
                email,
                is_active,
                last_login_at
            FROM accounts
            WHERE login_id = :login_id
              AND is_active = 1
            LIMIT 1
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function updateLastLoginAt(int $accountId): void
    {
        $pdo = $this->getConnection();

        $sql = '
            UPDATE accounts
               SET last_login_at = NOW()
             WHERE account_id = :account_id
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
<?php

namespace App\Services;

use PDO;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

class SettingsService
{
    private PDO $pdo;

    public function __construct()
    {
        $dbPath = dirname(__DIR__) . '/Database/db.php';

        if (!file_exists($dbPath)) {
            throw new RuntimeException('DB接続ファイルが見つかりません: ' . $dbPath);
        }

        $pdo = require $dbPath;

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('DB接続ファイルがPDOを返していません。');
        }

        $this->pdo = $pdo;
    }

    public function getMasterSettingsData(): array
    {
        return [
            'accounts'         => $this->getAccounts(),
            'stores'           => $this->getStores(),
            'backupHistories'  => $this->getBackupHistories(10),
            'restoreHistories' => $this->getRestoreHistories(10),
            'systemInfo'       => $this->getSystemInfo(),
        ];
    }

    public function getAccounts(): array
    {
        $sql = "
            SELECT
                a.account_id,
                a.login_id,
                a.password_hash,
                a.account_name,
                a.role_type,
                a.store_id,
                s.store_name,
                a.email,
                a.is_active,
                a.last_login_at,
                a.created_at,
                a.updated_at
            FROM accounts a
            LEFT JOIN stores s
              ON s.store_id = a.store_id
            ORDER BY
                CASE WHEN a.role_type = 'MASTER' THEN 0 ELSE 1 END,
                a.account_id ASC
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeAccountRow'], $rows);
    }

    public function getStores(): array
    {
        $sql = "
            SELECT
                s.store_id,
                s.store_name,
                s.store_address,
                s.store_phone,
                s.is_active,
                s.created_at,
                s.updated_at,
                (
                    SELECT COUNT(*)
                    FROM accounts a
                    WHERE a.store_id = s.store_id
                      AND a.role_type = 'STAFF'
                      AND a.is_active = 1
                ) AS active_staff_count
            FROM stores s
            ORDER BY s.store_id ASC
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeStoreRow'], $rows);
    }

    public function getBackupHistories(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                b.backup_id,
                b.backup_type,
                b.backup_scope,
                b.file_name,
                b.file_path,
                b.file_size,
                b.created_by_account_id,
                b.note,
                b.status,
                b.created_at,
                a.account_name AS created_by_name,
                a.login_id AS created_by_login_id
            FROM backup_history b
            LEFT JOIN accounts a
              ON a.account_id = b.created_by_account_id
            ORDER BY b.created_at DESC, b.backup_id DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeBackupHistoryRow'], $rows);
    }

    public function getRestoreHistories(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                r.restore_id,
                r.backup_id,
                r.restore_scope,
                r.executed_by_account_id,
                r.reason,
                r.status,
                r.executed_at,
                a.account_name AS executed_by_name,
                a.login_id AS executed_by_login_id,
                b.file_name AS backup_file_name
            FROM restore_history r
            LEFT JOIN accounts a
              ON a.account_id = r.executed_by_account_id
            LEFT JOIN backup_history b
              ON b.backup_id = r.backup_id
            ORDER BY r.executed_at DESC, r.restore_id DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeRestoreHistoryRow'], $rows);
    }

    public function getSystemInfo(): array
    {
        $lastBackupSql = "
            SELECT
                b.backup_id,
                b.file_name,
                b.created_at
            FROM backup_history b
            WHERE b.status = 'SUCCESS'
            ORDER BY b.created_at DESC, b.backup_id DESC
            LIMIT 1
        ";

        $lastRestoreSql = "
            SELECT
                r.restore_id,
                r.executed_at
            FROM restore_history r
            WHERE r.status = 'SUCCESS'
            ORDER BY r.executed_at DESC, r.restore_id DESC
            LIMIT 1
        ";

        $masterCountSql = "SELECT COUNT(*) FROM accounts WHERE role_type = 'MASTER'";
        $staffCountSql  = "SELECT COUNT(*) FROM accounts WHERE role_type = 'STAFF' AND is_active = 1";
        $storeCountSql  = "SELECT COUNT(*) FROM stores WHERE is_active = 1";

        $lastBackup = $this->pdo->query($lastBackupSql)->fetch(PDO::FETCH_ASSOC) ?: null;
        $lastRestore = $this->pdo->query($lastRestoreSql)->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'version'            => 'v0.1.0',
            'environment'        => 'Development',
            'health'             => '正常',
            'last_backup'        => $lastBackup['created_at'] ?? null,
            'last_backup_id'     => isset($lastBackup['backup_id']) ? (int)$lastBackup['backup_id'] : null,
            'last_backup_file'   => $lastBackup['file_name'] ?? null,
            'last_restore'       => $lastRestore['executed_at'] ?? null,
            'last_restore_id'    => isset($lastRestore['restore_id']) ? (int)$lastRestore['restore_id'] : null,
            'master_count'       => (int)$this->pdo->query($masterCountSql)->fetchColumn(),
            'active_staff_count' => (int)$this->pdo->query($staffCountSql)->fetchColumn(),
            'active_store_count' => (int)$this->pdo->query($storeCountSql)->fetchColumn(),
        ];
    }

    public function createStore(array $data): string
    {
        /*
        * store_idが空欄なら自動採番。
        * 入力されている場合は、そのIDを使用する。
        */
        $requestedStoreId = strtoupper(
            trim((string)($data['store_id'] ?? ''))
        );

        $storeName = trim(
            (string)($data['store_name'] ?? '')
        );

        $storeAddress = trim(
            (string)($data['store_address'] ?? '')
        );

        $storePhone = trim(
            (string)($data['store_phone'] ?? '')
        );

        $isActive = (int)($data['is_active'] ?? 1);
        $isActive = $isActive === 1 ? 1 : 0;

        /*
        * 必須項目チェック
        */
        if ($storeName === '') {
            throw new InvalidArgumentException(
                '店舗名を入力してください。'
            );
        }

        if ($storeAddress === '') {
            throw new InvalidArgumentException(
                '住所を入力してください。'
            );
        }

        if ($storePhone === '') {
            throw new InvalidArgumentException(
                '電話番号を入力してください。'
            );
        }

        /*
        * 店舗IDが入力されている場合だけ形式確認する。
        */
        if (
            $requestedStoreId !== ''
            && !preg_match('/^[A-Z]{2}$/', $requestedStoreId)
        ) {
            throw new InvalidArgumentException(
                '店舗IDは半角英字2文字で入力してください。'
            );
        }

        $this->pdo->beginTransaction();

        try {
            /*
            * 店舗IDを決定する。
            *
            * 指定あり:
            *   指定された店舗IDを使用する。
            *
            * 指定なし:
            *   未使用の店舗IDを自動採番する。
            */
            if ($requestedStoreId !== '') {
                $storeId = $requestedStoreId;

                /*
                * 登録中の競合を防ぎやすくするため、
                * 対象店舗IDをロック付きで確認する。
                */
                if ($this->storeIdExistsForUpdate($storeId)) {
                    throw new InvalidArgumentException(
                        '指定された店舗ID「'
                        . $storeId
                        . '」はすでに使用されています。'
                    );
                }
            } else {
                $storeId = $this->generateNextStoreIdForUpdate();
            }

            $sql = "
                INSERT INTO stores (
                    store_id,
                    store_name,
                    store_address,
                    store_phone,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (
                    :store_id,
                    :store_name,
                    :store_address,
                    :store_phone,
                    :is_active,
                    NOW(),
                    NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(
                ':store_id',
                $storeId,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':store_name',
                $storeName,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':store_address',
                $storeAddress,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':store_phone',
                $storePhone,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':is_active',
                $isActive,
                PDO::PARAM_INT
            );

            $stmt->execute();

            $this->pdo->commit();

            return $storeId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            /*
            * DB側の主キー・UNIQUE制約で重複が検出された場合も、
            * 利用者向けのメッセージに変換する。
            */
            if (
                $e instanceof \PDOException
                && $e->getCode() === '23000'
            ) {
                throw new InvalidArgumentException(
                    '指定された店舗IDはすでに使用されています。',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    public function updateStore(array $data): void
    {
        $storeId = strtoupper(trim((string)($data['store_id'] ?? '')));
        $storeName = trim((string)($data['store_name'] ?? ''));
        $storeAddress = trim((string)($data['store_address'] ?? ''));
        $storePhone = trim((string)($data['store_phone'] ?? ''));
        $isActive = (int)($data['is_active'] ?? 0);

        if ($storeId === '' || !preg_match('/^[A-Z]{2}$/', $storeId)) {
            throw new InvalidArgumentException('店舗IDが不正です。');
        }
        if ($storeName === '') {
            throw new InvalidArgumentException('店舗名を入力してください。');
        }
        if ($storeAddress === '') {
            throw new InvalidArgumentException('住所を入力してください。');
        }
        if ($storePhone === '') {
            throw new InvalidArgumentException('電話番号を入力してください。');
        }

        $store = $this->getStoreById($storeId);
        if (!$store) {
            throw new InvalidArgumentException('対象店舗が見つかりません。');
        }

        $sql = "
            UPDATE stores
            SET
                store_name = :store_name,
                store_address = :store_address,
                store_phone = :store_phone,
                is_active = :is_active,
                updated_at = NOW()
            WHERE store_id = :store_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_STR);
        $stmt->bindValue(':store_name', $storeName, PDO::PARAM_STR);
        $stmt->bindValue(':store_address', $storeAddress, PDO::PARAM_STR);
        $stmt->bindValue(':store_phone', $storePhone, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * 指定された店舗IDがすでに存在するか確認する。
     *
     * createStore() のトランザクション内から呼び出す。
     */
    private function storeIdExistsForUpdate(string $storeId): bool
    {
        $storeId = strtoupper(trim($storeId));

        if ($storeId === '') {
            return false;
        }

        $sql = "
            SELECT store_id
            FROM stores
            WHERE store_id = :store_id
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(
            ':store_id',
            $storeId,
            PDO::PARAM_STR
        );

        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * AA～ZZの範囲から、未使用の店舗IDを自動採番する。
     *
     * createStore() のトランザクション内から呼び出す。
     */
    private function generateNextStoreIdForUpdate(): string
    {
        /*
        * 現在登録されている店舗IDをロックして取得する。
        */
        $sql = "
            SELECT store_id
            FROM stores
            ORDER BY store_id ASC
            FOR UPDATE
        ";

        $stmt = $this->pdo->query($sql);

        $existingIds = $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        $used = [];

        foreach ($existingIds as $id) {
            $id = strtoupper(
                trim((string)$id)
            );

            /*
            * AA～ZZ形式のIDだけを自動採番対象として扱う。
            */
            if (preg_match('/^[A-Z]{2}$/', $id)) {
                $used[$id] = true;
            }
        }

        /*
        * AA、AB、AC……ZZの順で確認し、
        * 最初に見つかった未使用IDを返す。
        */
        for (
            $first = ord('A');
            $first <= ord('Z');
            $first++
        ) {
            for (
                $second = ord('A');
                $second <= ord('Z');
                $second++
            ) {
                $candidate =
                    chr($first)
                    . chr($second);

                if (!isset($used[$candidate])) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException(
            '店舗IDをこれ以上採番できません。'
            . '（AAからZZまで使用済みです）'
        );
    }

    public function getStoreById(string $storeId): ?array
    {
        $sql = "
            SELECT
                s.store_id,
                s.store_name,
                s.store_address,
                s.store_phone,
                s.is_active,
                s.created_at,
                s.updated_at,
                (
                    SELECT COUNT(*)
                    FROM accounts a
                    WHERE a.store_id = s.store_id
                      AND a.role_type = 'STAFF'
                      AND a.is_active = 1
                ) AS active_staff_count
            FROM stores s
            WHERE s.store_id = :store_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeStoreRow($row);
    }

    public function isValidActiveStore(string $storeId): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM stores
            WHERE store_id = :store_id
              AND is_active = 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_STR);
        $stmt->execute();

        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function resolveBillingStoreIdFromSessionUser(array $user): ?string
    {
        $role = strtolower((string)($user['role'] ?? ''));
        $storeId = $user['store_id'] ?? null;

        if ($role === 'staff') {
            return $storeId ?: null;
        }

        if ($role === 'master') {
            return $user['operating_store_id'] ?? $storeId ?? null;
        }

        return null;
    }

    private function normalizeAccountRow(array $row): array
    {
        return [
            'account_id'    => isset($row['account_id']) ? (int)$row['account_id'] : 0,
            'login_id'      => (string)($row['login_id'] ?? ''),
            'password_hash' => (string)($row['password_hash'] ?? ''),
            'account_name'  => (string)($row['account_name'] ?? ''),
            'role_type'     => (string)($row['role_type'] ?? ''),
            'store_id'      => $row['store_id'] !== null ? (string)$row['store_id'] : null,
            'store_name'    => $row['store_name'] !== null ? (string)$row['store_name'] : null,
            'email'         => $row['email'] !== null ? (string)$row['email'] : null,
            'is_active'     => (int)($row['is_active'] ?? 0),
            'last_login_at' => $row['last_login_at'] !== null ? (string)$row['last_login_at'] : null,
            'created_at'    => $row['created_at'] !== null ? (string)$row['created_at'] : null,
            'updated_at'    => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            'role_label'    => (($row['role_type'] ?? '') === 'MASTER') ? 'マスター' : 'スタッフ',
            'status_label'  => ((int)($row['is_active'] ?? 0) === 1) ? '有効' : '無効',
            'store_label'   => (($row['role_type'] ?? '') === 'MASTER')
                ? '全店舗'
                : ((string)($row['store_name'] ?? $row['store_id'] ?? '未設定')),
        ];
    }

    private function normalizeStoreRow(array $row): array
    {
        return [
            'store_id'           => (string)($row['store_id'] ?? ''),
            'store_name'         => (string)($row['store_name'] ?? ''),
            'store_address'      => (string)($row['store_address'] ?? ''),
            'store_phone'        => (string)($row['store_phone'] ?? ''),
            'is_active'          => (int)($row['is_active'] ?? 0),
            'created_at'         => $row['created_at'] !== null ? (string)$row['created_at'] : null,
            'updated_at'         => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            'active_staff_count' => isset($row['active_staff_count']) ? (int)$row['active_staff_count'] : 0,
            'status_label'       => ((int)($row['is_active'] ?? 0) === 1) ? '有効' : '無効',
        ];
    }

    private function normalizeBackupHistoryRow(array $row): array
    {
        return [
            'backup_id'             => isset($row['backup_id']) ? (int)$row['backup_id'] : 0,
            'backup_type'           => (string)($row['backup_type'] ?? ''),
            'backup_scope'          => (string)($row['backup_scope'] ?? ''),
            'file_name'             => (string)($row['file_name'] ?? ''),
            'file_path'             => (string)($row['file_path'] ?? ''),
            'file_size'             => $row['file_size'] !== null ? (int)$row['file_size'] : null,
            'created_by_account_id' => $row['created_by_account_id'] !== null ? (int)$row['created_by_account_id'] : null,
            'created_by_name'       => $row['created_by_name'] !== null ? (string)$row['created_by_name'] : null,
            'created_by_login_id'   => $row['created_by_login_id'] !== null ? (string)$row['created_by_login_id'] : null,
            'note'                  => $row['note'] !== null ? (string)$row['note'] : null,
            'status'                => (string)($row['status'] ?? ''),
            'created_at'            => $row['created_at'] !== null ? (string)$row['created_at'] : null,
        ];
    }

    private function normalizeRestoreHistoryRow(array $row): array
    {
        return [
            'restore_id'             => isset($row['restore_id']) ? (int)$row['restore_id'] : 0,
            'backup_id'              => isset($row['backup_id']) ? (int)$row['backup_id'] : 0,
            'backup_file_name'       => $row['backup_file_name'] !== null ? (string)$row['backup_file_name'] : null,
            'restore_scope'          => (string)($row['restore_scope'] ?? ''),
            'executed_by_account_id' => $row['executed_by_account_id'] !== null ? (int)$row['executed_by_account_id'] : null,
            'executed_by_name'       => $row['executed_by_name'] !== null ? (string)$row['executed_by_name'] : null,
            'executed_by_login_id'   => $row['executed_by_login_id'] !== null ? (string)$row['executed_by_login_id'] : null,
            'reason'                 => $row['reason'] !== null ? (string)$row['reason'] : null,
            'status'                 => (string)($row['status'] ?? ''),
            'executed_at'            => $row['executed_at'] !== null ? (string)$row['executed_at'] : null,
        ];
    }

    public function createAccount(array $data): void
    {
        $loginId = trim((string)($data['login_id'] ?? ''));
        $accountName = trim((string)($data['account_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $storeId = strtoupper(trim((string)($data['store_id'] ?? '')));
        $email = trim((string)($data['email'] ?? ''));
        $isActive = (int)($data['is_active'] ?? 1);

        if ($loginId === '') {
            throw new \InvalidArgumentException('ログインIDを入力してください。');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{4,50}$/', $loginId)) {
            throw new \InvalidArgumentException('ログインIDは英数字・ハイフン・アンダーバーで4〜50文字にしてください。');
        }

        if ($accountName === '') {
            throw new \InvalidArgumentException('アカウント名を入力してください。');
        }

        if ($password === '') {
            throw new \InvalidArgumentException('パスワードを入力してください。');
        }

        if (mb_strlen($password) < 8) {
            throw new \InvalidArgumentException('パスワードは8文字以上で入力してください。');
        }

        if ($storeId === '') {
            throw new \InvalidArgumentException('所属店舗を選択してください。');
        }

        if (!$this->isValidActiveStore($storeId)) {
            throw new \InvalidArgumentException('有効な店舗を選択してください。');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('メールアドレスの形式が正しくありません。');
        }

        $sqlCheck = "
            SELECT COUNT(*)
            FROM accounts
            WHERE login_id = :login_id
        ";
        $stmtCheck = $this->pdo->prepare($sqlCheck);
        $stmtCheck->bindValue(':login_id', $loginId, \PDO::PARAM_STR);
        $stmtCheck->execute();

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new \InvalidArgumentException('そのログインIDはすでに使用されています。');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('パスワードのハッシュ化に失敗しました。');
        }

        $sql = "
            INSERT INTO accounts (
                login_id,
                password_hash,
                account_name,
                role_type,
                store_id,
                email,
                is_active,
                last_login_at,
                created_at,
                updated_at
            ) VALUES (
                :login_id,
                :password_hash,
                :account_name,
                'STAFF',
                :store_id,
                :email,
                :is_active,
                NULL,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':login_id', $loginId, \PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, \PDO::PARAM_STR);
        $stmt->bindValue(':account_name', $accountName, \PDO::PARAM_STR);
        $stmt->bindValue(':store_id', $storeId, \PDO::PARAM_STR);
        $stmt->bindValue(':email', $email !== '' ? $email : null, $email !== '' ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getAccountById(int $accountId): ?array
    {
        $sql = "
            SELECT
                a.account_id,
                a.login_id,
                a.password_hash,
                a.account_name,
                a.role_type,
                a.store_id,
                s.store_name,
                a.email,
                a.is_active,
                a.last_login_at,
                a.created_at,
                a.updated_at
            FROM accounts a
            LEFT JOIN stores s
            ON s.store_id = a.store_id
            WHERE a.account_id = :account_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeAccountRow($row);
    }

    public function updateAccount(array $data): void
    {
        $accountId = (int)($data['account_id'] ?? 0);
        $loginId = trim((string)($data['login_id'] ?? ''));
        $accountName = trim((string)($data['account_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $storeId = strtoupper(trim((string)($data['store_id'] ?? '')));
        $email = trim((string)($data['email'] ?? ''));
        $isActive = (int)($data['is_active'] ?? 0);
        $roleType = strtoupper((string)($account['role_type'] ?? ''));

        if ($accountId <= 0) {
            throw new \InvalidArgumentException('アカウントIDが不正です。');
        }

        $account = $this->getAccountById($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('対象アカウントが見つかりません。');
        }

        $roleType = strtoupper((string)($account['role_type'] ?? ''));

        if ($loginId === '') {
            throw new \InvalidArgumentException('ログインIDを入力してください。');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{4,50}$/', $loginId)) {
            throw new \InvalidArgumentException('ログインIDは英数字・ハイフン・アンダーバーで4〜50文字にしてください。');
        }

        if ($accountName === '') {
            throw new \InvalidArgumentException('アカウント名を入力してください。');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('メールアドレスの形式が正しくありません。');
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            throw new \InvalidArgumentException('パスワードを変更する場合は8文字以上で入力してください。');
        }

        /*
        * マスター管理者は全店舗扱いのため、store_id は NULL にする。
        * スタッフは所属店舗が必須。
        */
        if ($roleType === 'MASTER') {
            $storeId = '';
            $isActive = (int)($account['is_active'] ?? 1);
        } else {
            if ($storeId === '') {
                throw new \InvalidArgumentException('所属店舗を選択してください。');
            }

            if (!$this->isValidActiveStore($storeId)) {
                throw new \InvalidArgumentException('有効な店舗を選択してください。');
            }
        }

        $sqlCheck = "
            SELECT COUNT(*)
            FROM accounts
            WHERE login_id = :login_id
            AND account_id <> :account_id
        ";

        $stmtCheck = $this->pdo->prepare($sqlCheck);
        $stmtCheck->bindValue(':login_id', $loginId, \PDO::PARAM_STR);
        $stmtCheck->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmtCheck->execute();

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new \InvalidArgumentException('そのログインIDはすでに使用されています。');
        }

        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($passwordHash === false) {
                throw new \RuntimeException('パスワードのハッシュ化に失敗しました。');
            }

            $sql = "
                UPDATE accounts
                SET
                    login_id = :login_id,
                    password_hash = :password_hash,
                    account_name = :account_name,
                    store_id = :store_id,
                    email = :email,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE account_id = :account_id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':password_hash', $passwordHash, \PDO::PARAM_STR);
        } else {
            $sql = "
                UPDATE accounts
                SET
                    login_id = :login_id,
                    account_name = :account_name,
                    store_id = :store_id,
                    email = :email,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE account_id = :account_id
            ";

            $stmt = $this->pdo->prepare($sql);
        }

        $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->bindValue(':login_id', $loginId, \PDO::PARAM_STR);
        $stmt->bindValue(':account_name', $accountName, \PDO::PARAM_STR);

        if ($roleType === 'MASTER') {
            $stmt->bindValue(':store_id', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':store_id', $storeId, \PDO::PARAM_STR);
        }

        if ($email !== '') {
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':email', null, \PDO::PARAM_NULL);
        }

        $stmt->bindValue(':is_active', $isActive, \PDO::PARAM_INT);
        $stmt->execute();
    }

public function createBackup(array $data): array
{
    $backupType = strtoupper(trim((string)($data['backup_type'] ?? 'MANUAL')));
    $backupScope = strtoupper(trim((string)($data['backup_scope'] ?? 'FULL')));
    $createdByAccountId = isset($data['created_by_account_id']) ? (int)$data['created_by_account_id'] : null;
    $note = trim((string)($data['note'] ?? ''));

    if (!in_array($backupType, ['MANUAL', 'AUTO'], true)) {
        throw new InvalidArgumentException('バックアップ種別が不正です。');
    }

    if (!in_array($backupScope, ['FULL', 'MASTER_ONLY'], true)) {
        throw new InvalidArgumentException('バックアップ範囲が不正です。');
    }

    $backupDir = dirname(__DIR__, 2) . '/storage/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        throw new RuntimeException('バックアップフォルダを作成できません。');
    }

    $timestamp = date('Ymd_His');
    $fileName = 'backup_' . strtolower($backupScope) . '_' . $timestamp . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    try {
        $sqlDump = $this->buildSqlDump($backupScope);

        $written = file_put_contents($filePath, $sqlDump);
        if ($written === false) {
            throw new RuntimeException('バックアップファイルの書き込みに失敗しました。');
        }

        $fileSize = is_file($filePath) ? filesize($filePath) : null;

        $sql = "
            INSERT INTO backup_history (
                backup_type,
                backup_scope,
                file_name,
                file_path,
                file_size,
                created_by_account_id,
                note,
                status,
                created_at
            ) VALUES (
                :backup_type,
                :backup_scope,
                :file_name,
                :file_path,
                :file_size,
                :created_by_account_id,
                :note,
                'SUCCESS',
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':backup_type', $backupType, PDO::PARAM_STR);
        $stmt->bindValue(':backup_scope', $backupScope, PDO::PARAM_STR);
        $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':file_path', $filePath, PDO::PARAM_STR);
        $stmt->bindValue(':file_size', $fileSize, $fileSize === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':created_by_account_id', $createdByAccountId, $createdByAccountId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':note', $note !== '' ? $note : null, $note !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return [
            'success' => true,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
        ];
    } catch (Throwable $e) {
        $sql = "
            INSERT INTO backup_history (
                backup_type,
                backup_scope,
                file_name,
                file_path,
                file_size,
                created_by_account_id,
                note,
                status,
                created_at
            ) VALUES (
                :backup_type,
                :backup_scope,
                :file_name,
                :file_path,
                NULL,
                :created_by_account_id,
                :note,
                'FAILED',
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':backup_type', $backupType, PDO::PARAM_STR);
        $stmt->bindValue(':backup_scope', $backupScope, PDO::PARAM_STR);
        $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':file_path', $filePath, PDO::PARAM_STR);
        $stmt->bindValue(':created_by_account_id', $createdByAccountId, $createdByAccountId ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $failedNote = $note !== '' ? ($note . ' / ' . $e->getMessage()) : $e->getMessage();
        $stmt->bindValue(':note', $failedNote, PDO::PARAM_STR);
        $stmt->execute();

        throw $e;
    }
}

private function buildSqlDump(string $backupScope): string
{
    $tables = $this->getBackupTargetTables($backupScope);

    $lines = [];
    $lines[] = '-- Backup created at ' . date('Y-m-d H:i:s');
    $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $lines[] = '';

    foreach ($tables as $table) {
        $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);

        if (!$createRow || !isset($createRow['Create Table'])) {
            continue;
        }

        $lines[] = '-- ------------------------------';
        $lines[] = '-- Table: ' . $table;
        $lines[] = '-- ------------------------------';
        $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
        $lines[] = $createRow['Create Table'] . ';';
        $lines[] = '';

        $rowsStmt = $this->pdo->query("SELECT * FROM `{$table}`");
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $columns = array_map(fn($col) => "`{$col}`", array_keys($row));

            $values = [];
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : $this->pdo->quote((string)$value);
            }

            $lines[] = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");";
        }

        $lines[] = '';
    }

    $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

private function getBackupTargetTables(string $backupScope): array
{
    $stmt = $this->pdo->query('SHOW TABLES');
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($backupScope === 'MASTER_ONLY') {
        $targets = [
            'accounts',
            'stores',
            'backup_history',
            'restore_history',
        ];

        return array_values(array_filter($allTables, fn($table) => in_array($table, $targets, true)));
    }

    return $allTables;
}

public function deleteExpiredAutoBackups(int $retentionDays = 30): int
{
    $retentionDays = max(1, $retentionDays);

    $sql = "
        SELECT
            backup_id,
            file_path
        FROM backup_history
        WHERE backup_type = 'AUTO'
          AND created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(
        ':retention_days',
        $retentionDays,
        PDO::PARAM_INT
    );
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return 0;
    }

    $deletedCount = 0;

    $this->pdo->beginTransaction();

    try {
        foreach ($rows as $row) {
            $backupId = (int)($row['backup_id'] ?? 0);
            $filePath = (string)($row['file_path'] ?? '');

            /*
             * ファイルが存在する場合だけ削除する。
             */
            if ($filePath !== '' && is_file($filePath)) {
                if (!unlink($filePath)) {
                    throw new RuntimeException(
                        '古いバックアップファイルを削除できませんでした: '
                        . $filePath
                    );
                }
            }

            $deleteSql = "
                DELETE FROM backup_history
                WHERE backup_id = :backup_id
            ";

            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->bindValue(
                ':backup_id',
                $backupId,
                PDO::PARAM_INT
            );
            $deleteStmt->execute();

            $deletedCount++;
        }

        $this->pdo->commit();

        return $deletedCount;
    } catch (Throwable $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        throw $e;
    }
}
}
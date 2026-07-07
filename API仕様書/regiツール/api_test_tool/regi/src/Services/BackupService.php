<?php

namespace App\Services;

use PDO;
use Throwable;

class BackupService
{
    private PDO $db;
    private string $backupDir;

    public function __construct()
    {
        $this->db = db(); // 既存のDB接続関数を想定
        $this->backupDir = dirname(__DIR__, 2) . '/storage/backups';
    }

    public function createManualBackup(?int $accountId = null, string $scope = 'FULL', ?string $note = null): array
    {
        // ユーザ向けバックアップは全データ固定
        $scope = 'FULL';

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }

        $dbName = $this->fetchDatabaseName();
        $timestamp = date('Ymd_His');
        $fileName = "backup_{$scope}_{$timestamp}.sql";
        $filePath = $this->backupDir . '/' . $fileName;

        try {
            $sql = $this->buildSqlDump($scope);
            file_put_contents($filePath, $sql);

            $fileSize = is_file($filePath) ? filesize($filePath) : null;

            $stmt = $this->db->prepare("
                INSERT INTO backup_history
                (backup_type, backup_scope, file_name, file_path, file_size, created_by_account_id, note, status, created_at)
                VALUES
                ('MANUAL', :backup_scope, :file_name, :file_path, :file_size, :created_by_account_id, :note, 'SUCCESS', NOW())
            ");
            $stmt->execute([
                ':backup_scope' => $scope,
                ':file_name' => $fileName,
                ':file_path' => $filePath,
                ':file_size' => $fileSize,
                ':created_by_account_id' => $accountId,
                ':note' => $note,
            ]);

            return [
                'success' => true,
                'message' => 'バックアップを作成しました。',
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'database' => $dbName,
            ];
        } catch (Throwable $e) {
            $stmt = $this->db->prepare("
                INSERT INTO backup_history
                (backup_type, backup_scope, file_name, file_path, file_size, created_by_account_id, note, status, created_at)
                VALUES
                ('MANUAL', :backup_scope, :file_name, :file_path, NULL, :created_by_account_id, :note, 'FAILED', NOW())
            ");
            $stmt->execute([
                ':backup_scope' => $scope,
                ':file_name' => $fileName,
                ':file_path' => $filePath,
                ':created_by_account_id' => $accountId,
                ':note' => $note ? ($note . ' / ' . $e->getMessage()) : $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'バックアップの作成に失敗しました: ' . $e->getMessage(),
            ];
        }
    }

    public function getBackupHistories(): array
    {
        $sql = "
            SELECT
                bh.*,
                a.account_name AS created_by_name
            FROM backup_history bh
            LEFT JOIN accounts a
              ON a.account_id = bh.created_by_account_id
            ORDER BY bh.created_at DESC, bh.backup_id DESC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRestoreHistories(): array
    {
        $sql = "
            SELECT
                rh.*,
                a.account_name AS executed_by_name
            FROM restore_history rh
            LEFT JOIN accounts a
              ON a.account_id = rh.executed_by_account_id
            ORDER BY rh.executed_at DESC, rh.restore_id DESC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchDatabaseName(): string
    {
        $row = $this->db->query("SELECT DATABASE() AS db_name")->fetch(PDO::FETCH_ASSOC);
        return $row['db_name'] ?? 'unknown_db';
    }

    private function buildSqlDump(string $scope = 'FULL'): string
    {
        $tables = $this->fetchTargetTables($scope);
        $dump = [];
        $dump[] = "-- backup created at " . date('Y-m-d H:i:s');
        $dump[] = "SET FOREIGN_KEY_CHECKS = 0;";
        $dump[] = "";

        foreach ($tables as $table) {
            $create = $this->db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? '';

            $dump[] = "-- -------------------------------------";
            $dump[] = "-- table: {$table}";
            $dump[] = "-- -------------------------------------";
            $dump[] = "DROP TABLE IF EXISTS `{$table}`;";
            $dump[] = $createSql . ";";
            $dump[] = "";

            $rows = $this->db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                continue;
            }

            foreach ($rows as $row) {
                $columns = array_map(fn($c) => "`{$c}`", array_keys($row));
                $values = array_map([$this->db, 'quote'], array_map(
                    fn($v) => $v === null ? null : (string)$v,
                    array_values($row)
                ));

                $values = array_map(fn($v) => $v === null ? "NULL" : $v, $values);

                $dump[] = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");";
            }

            $dump[] = "";
        }

        $dump[] = "SET FOREIGN_KEY_CHECKS = 1;";
        $dump[] = "";

        return implode(PHP_EOL, $dump);
    }

    private function fetchTargetTables(string $scope): array
    {
        return $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
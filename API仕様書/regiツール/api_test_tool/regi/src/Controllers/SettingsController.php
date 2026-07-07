<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SettingsService;
use Throwable;

class SettingsController
{
    private SettingsService $settingsService;

    public function __construct()
    {
        $this->settingsService = new SettingsService();
    }

    private function requireMasterAccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $role = strtoupper(trim((string)($_SESSION['role'] ?? '')));

        if ($role !== 'MASTER') {
            http_response_code(403);
            exit('このページにはアクセスできません。');
        }
    }

    public function master(): void
    {
        $this->requireMasterAccess();

        try {
            $data = $this->settingsService->getMasterSettingsData();

            $accounts = $data['accounts'] ?? [];
            $stores = $data['stores'] ?? [];
            $backupHistories = $data['backupHistories'] ?? [];
            $restoreHistories = $data['restoreHistories'] ?? [];
            $systemInfo = $data['systemInfo'] ?? [];

            require dirname(__DIR__) . '/Views/settings/master.php';
        } catch (Throwable $e) {
            http_response_code(500);
            echo '設定画面の表示に失敗しました: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public function createStore(): void
    {
        $this->requireMasterAccess();

        try {
            /*
            * 店舗IDは任意入力。
            * 未入力なら空文字のままServiceへ渡し、
            * Service側で自動採番する。
            */
            $storeId = strtoupper(
                trim((string)($_POST['store_id'] ?? ''))
            );

            $storeName = trim(
                (string)($_POST['store_name'] ?? '')
            );

            $storeAddress = trim(
                (string)($_POST['store_address'] ?? '')
            );

            $storePhone = trim(
                (string)($_POST['store_phone'] ?? '')
            );

            $isActive = isset($_POST['is_active'])
                ? (int)$_POST['is_active']
                : 1;

            $isActive = $isActive === 1 ? 1 : 0;

            /*
            * 店舗IDが入力されている場合だけ形式を検証する。
            */
            if (
                $storeId !== ''
                && !preg_match('/^[A-Z]{2}$/', $storeId)
            ) {
                throw new \InvalidArgumentException(
                    '店舗IDは大文字アルファベット2文字で入力してください。'
                );
            }

            $newStoreId = $this->settingsService->createStore([
                'store_id'      => $storeId,
                'store_name'    => $storeName,
                'store_address' => $storeAddress,
                'store_phone'   => $storePhone,
                'is_active'     => $isActive,
            ]);

            $_SESSION['flash_success'] =
                '店舗を追加しました。（店舗ID: '
                . $newStoreId
                . '）';

            header(
                'Location: /regi/public/settings/master#stores'
            );
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] =
                '店舗追加に失敗しました: '
                . $e->getMessage();

            header(
                'Location: /regi/public/settings/master#stores'
            );
            exit;
        }
    }

    public function storeDetail(): void
    {
        $this->requireMasterAccess();

        try {
            $storeId = trim((string)($_GET['store_id'] ?? ''));
            if ($storeId === '') {
                http_response_code(400);
                echo '店舗IDが指定されていません。';
                return;
            }

            $store = $this->settingsService->getStoreById($storeId);
            if (!$store) {
                http_response_code(404);
                echo '店舗が見つかりません。';
                return;
            }

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'store' => $store,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function updateStore(): void
    {
        $this->requireMasterAccess();

        try {
            $storeId = trim((string)($_POST['store_id'] ?? ''));
            $storeName = trim((string)($_POST['store_name'] ?? ''));
            $storeAddress = trim((string)($_POST['store_address'] ?? ''));
            $storePhone = trim((string)($_POST['store_phone'] ?? ''));
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

            $this->settingsService->updateStore([
                'store_id'      => $storeId,
                'store_name'    => $storeName,
                'store_address' => $storeAddress,
                'store_phone'   => $storePhone,
                'is_active'     => $isActive,
            ]);

            $_SESSION['flash_success'] = '店舗情報を更新しました。';
            header('Location: /regi/public/settings/master#stores');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = '店舗更新に失敗しました: ' . $e->getMessage();
            header('Location: /regi/public/settings/master#stores');
            exit;
        }
    }

    public function createAccount(): void
    {
        $this->requireMasterAccess();

        try {
            $loginId = trim((string)($_POST['login_id'] ?? ''));
            $accountName = trim((string)($_POST['account_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $storeId = trim((string)($_POST['store_id'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            $this->settingsService->createAccount([
                'login_id'     => $loginId,
                'account_name' => $accountName,
                'password'     => $password,
                'store_id'     => $storeId,
                'email'        => $email,
                'is_active'    => $isActive,
            ]);

            $_SESSION['flash_success'] = 'アカウントを追加しました。';
            header('Location: /regi/public/settings/master#accounts');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'アカウント追加に失敗しました: ' . $e->getMessage();
            header('Location: /regi/public/settings/master#accounts');
            exit;
        }
    }

    public function accountDetail(): void
    {
        $this->requireMasterAccess();

        try {
            $accountId = (int)($_GET['account_id'] ?? 0);
            if ($accountId <= 0) {
                http_response_code(400);
                echo 'アカウントIDが指定されていません。';
                return;
            }

            $account = $this->settingsService->getAccountById($accountId);
            if (!$account) {
                http_response_code(404);
                echo 'アカウントが見つかりません。';
                return;
            }

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'account' => $account,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function updateAccount(): void
    {
        $this->requireMasterAccess();

        try {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $loginId = trim((string)($_POST['login_id'] ?? ''));
            $accountName = trim((string)($_POST['account_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $storeId = trim((string)($_POST['store_id'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

            $this->settingsService->updateAccount([
                'account_id'   => $accountId,
                'login_id'     => $loginId,
                'account_name' => $accountName,
                'password'     => $password,
                'store_id'     => $storeId,
                'email'        => $email,
                'is_active'    => $isActive,
            ]);

            $_SESSION['flash_success'] = 'アカウント情報を更新しました。';
            header('Location: /regi/public/settings/master#accounts');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'アカウント更新に失敗しました: ' . $e->getMessage();
            header('Location: /regi/public/settings/master#accounts');
            exit;
        }
    }

    public function createBackup(): void
    {
        $this->requireMasterAccess();

        try {
            // ユーザ画面では選ばせず、手動・全データで固定する
            $backupType = 'MANUAL';
            $backupScope = 'FULL';
            $note = null;

            $account = $_SESSION['account'] ?? [];
            $createdByAccountId = isset($account['account_id']) ? (int)$account['account_id'] : null;

            $result = $this->settingsService->createBackup([
                'backup_type' => $backupType,
                'backup_scope' => $backupScope,
                'created_by_account_id' => $createdByAccountId,
                'note' => $note,
            ]);

            $_SESSION['flash_success'] = 'バックアップを作成しました。（' . ($result['file_name'] ?? '') . '）';
            header('Location: /regi/public/settings/master#backup');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'バックアップ作成に失敗しました: ' . $e->getMessage();
            header('Location: /regi/public/settings/master#backup');
            exit;
        }
    }
}
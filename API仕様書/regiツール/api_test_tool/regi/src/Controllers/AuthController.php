<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccountModel;

final class AuthController
{
    private AccountModel $accounts;

    public function __construct()
    {
        $this->accounts = new AccountModel();
    }

    public function showLogin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['account'])) {
            header('Location: /regi/public/home');
            exit;
        }

        require dirname(__DIR__) . '/Views/auth/login.php';
    }

    public function login(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $loginId  = trim((string)($_POST['loginId'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($loginId === '' || $password === '') {
            $_SESSION['login_error'] = 'ログインIDとパスワードを入力してください。';
            header('Location: /regi/public/login');
            exit;
        }

        $account = $this->accounts->findActiveByLoginId($loginId);

        if ($account === null || !password_verify($password, (string)$account['password_hash'])) {
            $_SESSION['login_error'] = 'ログインIDまたはパスワードが正しくありません。';
            header('Location: /regi/public/login');
            exit;
        }

        session_regenerate_id(true);

        // 古いセッション情報を掃除
        unset($_SESSION['user']);
        unset($_SESSION['role']);
        unset($_SESSION['storeId']);
        unset($_SESSION['store_id']);
        unset($_SESSION['staffName']);
        unset($_SESSION['login_user_name']);

        $roleType = strtoupper(trim((string)($account['role_type'] ?? '')));
        $storeId  = (string)($account['store_id'] ?? '');

        $_SESSION['account'] = [
            'account_id'   => (int)$account['account_id'],
            'login_id'     => (string)$account['login_id'],
            'account_name' => (string)$account['account_name'],
            'role_type'    => $roleType,
            'store_id'     => $storeId,
            'email'        => $account['email'] ?? null,
        ];

        // 互換用
        $_SESSION['role'] = $roleType;                 // STAFF / MASTER
        $_SESSION['store_id'] = $storeId;              // AA など
        $_SESSION['staffName'] = (string)$account['account_name'];
        $_SESSION['login_user_name'] = (string)$account['account_name'];

        $this->accounts->updateLastLoginAt((int)$account['account_id']);

        header('Location: /regi/public/home');
        exit;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();

        header('Location: /regi/public/login');
        exit;
    }
}
<?php
declare(strict_types=1);

namespace App\Lib;

final class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['account'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function storeId(): ?string
    {
        return self::user()['store_id'] ?? null;
    }

    public static function isMaster(): bool
    {
        return self::role() === 'master';
    }

    public static function isStaff(): bool
    {
        return self::role() === 'staff';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /regi/public/login');
            exit;
        }
    }

    public static function requireMaster(): void
    {
        self::requireLogin();

        if (!self::isMaster()) {
            http_response_code(403);
            exit('このページへはアクセスできません。');
        }
    }

    public static function requireStaff(): void
    {
        self::requireLogin();

        if (!self::isStaff()) {
            http_response_code(403);
            exit('このページへはアクセスできません。');
        }
    }

    public static function requireAny(array $roles): void
    {
        self::requireLogin();

        $role = self::role();
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            exit('このページへはアクセスできません。');
        }
    }
}
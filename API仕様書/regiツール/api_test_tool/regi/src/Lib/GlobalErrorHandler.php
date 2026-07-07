<?php
declare(strict_types=1);

namespace App\Lib;

use ErrorException;
use PDOException;
use Throwable;
use App\Exceptions\FatalSystemException;

final class GlobalErrorHandler
{
    public static function register(): void
    {
        /*
         * PHPのWarningやNoticeを例外へ変換する。
         */
        set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file,
                int $line
            ): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                throw new ErrorException(
                    $message,
                    0,
                    $severity,
                    $file,
                    $line
                );
            }
        );

        /*
         * 捕捉されなかった例外を共通処理する。
         */
        set_exception_handler(
            static function (Throwable $e): void {
                self::handleException($e);
            }
        );

        /*
         * parse errorなど、通常の例外処理では捕捉できない
         * 致命的エラーを処理する。
         */
        register_shutdown_function(
            static function (): void {
                $error = error_get_last();

                if ($error === null) {
                    return;
                }

                $fatalTypes = [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR,
                    E_USER_ERROR,
                ];

                if (!in_array($error['type'], $fatalTypes, true)) {
                    return;
                }

                self::writeLog(
                    sprintf(
                        '[FATAL] %s in %s:%d',
                        $error['message'],
                        $error['file'],
                        $error['line']
                    )
                );

                self::renderFatalPage(
                    'SYSTEM_FATAL',
                    'システムで重大なエラーが発生しました。'
                );
            }
        );
    }

    private static function handleException(Throwable $e): void
    {
        self::writeLog(
            sprintf(
                "[%s] %s\nFile: %s:%d\nTrace:\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            )
        );

        if ($e instanceof FatalSystemException) {
            self::renderFatalPage(
                $e->getErrorCode(),
                $e->getDisplayMessage()
            );
            return;
        }

        if ($e instanceof PDOException) {
            self::renderFatalPage(
                'DATABASE_ERROR',
                'データベースに接続できません。'
            );
            return;
        }

        self::renderFatalPage(
            'SYSTEM_ERROR',
            'システムで予期しないエラーが発生しました。'
        );
    }

    private static function renderFatalPage(
        string $errorCode,
        string $message
    ): void {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header(
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
            );
        }

        $safeCode = htmlspecialchars(
            $errorCode,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeMessage = htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        );

        /*
         * ここではControllerやDBを呼ばず、
         * 単独で表示できるHTMLを出力する。
         */
        echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>システムエラー</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f1f5f9;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color: #0f172a;
        }

        .error-card {
            width: min(600px, 100%);
            padding: 36px;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .12);
            text-align: center;
        }

        .error-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 34px;
            font-weight: 800;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 26px;
        }

        p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
        }

        .error-code {
            margin-top: 16px;
            padding: 10px;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 26px;
        }

        .button {
            min-height: 50px;
            border: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .button-primary {
            background: #0f5c86;
            color: #fff;
        }

        .button-secondary {
            background: #e2e8f0;
            color: #334155;
        }

        @media (max-width: 560px) {
            .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="error-card">
        <div class="error-icon">!</div>

        <h1>処理を続行できませんでした</h1>

        <p>{$safeMessage}</p>
        <p>
            接続状態を確認してから、再度お試しください。
        </p>

        <div class="error-code">
            エラーコード: {$safeCode}
        </div>

        <div class="actions">
            <button
                type="button"
                class="button button-primary"
                onclick="window.location.reload()"
            >
                再試行
            </button>

            <a
                href="/regi/public/login"
                class="button button-secondary"
            >
                ログイン画面へ
            </a>
        </div>
    </main>
</body>
</html>
HTML;

        exit;
    }

    private static function writeLog(string $message): void
    {
        $logDirectory = dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        $logFile = $logDirectory
            . '/system-'
            . date('Y-m-d')
            . '.log';

        $line = sprintf(
            "[%s] %s%s",
            date('Y-m-d H:i:s'),
            $message,
            PHP_EOL
        );

        @file_put_contents(
            $logFile,
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
<?php
declare(strict_types=1);

/*
 * GlobalErrorHandlerから受け取る変数
 *
 * $errorCode
 * $message
 * $retryUrl
 */

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$errorCode = $errorCode ?? 'SYSTEM_FATAL';
$message = $message ?? 'システムで重大なエラーが発生しました。';
$retryUrl = $retryUrl ?? '/regi/public/';
?>
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
            padding: 24px;
            display: grid;
            place-items: center;
            background: #f1f5f9;
            color: #0f172a;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                "Noto Sans JP",
                sans-serif;
        }

        .fatal-card {
            width: min(620px, 100%);
            padding: 38px;
            border-radius: 24px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            text-align: center;
        }

        .fatal-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 36px;
            font-weight: 900;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 27px;
        }

        .fatal-message {
            margin: 0;
            color: #64748b;
            font-size: 16px;
            line-height: 1.8;
        }

        .fatal-code {
            margin-top: 20px;
            padding: 11px 14px;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
        }

        .fatal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 28px;
        }

        .fatal-button {
            min-height: 52px;
            border: 0;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .fatal-button-primary {
            background: #0f5c86;
            color: #fff;
        }

        .fatal-button-secondary {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
        }

        @media (max-width: 600px) {
            .fatal-card {
                padding: 28px 20px;
            }

            .fatal-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="fatal-card">
        <div class="fatal-icon">!</div>

        <h1>処理を続行できませんでした</h1>

        <p class="fatal-message">
            <?= $escape($message) ?>
        </p>

        <p class="fatal-message">
            接続状態を確認してから再度お試しください。
        </p>

        <div class="fatal-code">
            エラーコード：
            <?= $escape($errorCode) ?>
        </div>

        <div class="fatal-actions">
            <button
                type="button"
                class="fatal-button fatal-button-primary"
                onclick="window.location.reload()"
            >
                再試行
            </button>

            <a
                href="<?= $escape($retryUrl) ?>"
                class="fatal-button fatal-button-secondary"
            >
                ログイン画面へ
            </a>
        </div>
    </main>
</body>
</html>
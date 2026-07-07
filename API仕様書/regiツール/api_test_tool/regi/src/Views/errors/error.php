<?php
declare(strict_types=1);

/**
 * ErrorControllerから次の変数を受け取る。
 *
 * $title
 * $heading
 * $message
 * $errorCode
 * $primaryUrl
 * $primaryLabel
 * $secondaryUrl
 * $secondaryLabel
 */

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= $escape($title) ?></title>

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
            color: #0f172a;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                "Noto Sans JP",
                sans-serif;
        }

        .error-card {
            width: min(620px, 100%);
            padding: 38px;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            text-align: center;
        }

        .error-icon {
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
            line-height: 1.4;
        }

        .message {
            margin: 0;
            color: #64748b;
            font-size: 16px;
            line-height: 1.8;
        }

        .error-code {
            margin-top: 20px;
            padding: 11px 14px;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
        }

        .error-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 28px;
        }

        .error-actions.one-button {
            grid-template-columns: 1fr;
        }

        .error-button {
            min-height: 52px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
        }

        .error-button-primary {
            background: #0f5c86;
            color: #fff;
        }

        .error-button-secondary {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
        }

        @media (max-width: 600px) {
            .error-card {
                padding: 28px 20px;
            }

            .error-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="error-card">
        <div class="error-icon">!</div>

        <h1><?= $escape($heading) ?></h1>

        <p class="message">
            <?= $escape($message) ?>
        </p>

        <div class="error-code">
            エラーコード：
            <?= $escape($errorCode) ?>
        </div>

        <div
            class="error-actions
                <?= $secondaryUrl === '' ? 'one-button' : '' ?>"
        >
            <a
                href="<?= $escape($primaryUrl) ?>"
                class="error-button error-button-primary"
            >
                <?= $escape($primaryLabel) ?>
            </a>

            <?php if ($secondaryUrl !== ''): ?>
                <a
                    href="<?= $escape($secondaryUrl) ?>"
                    class="error-button error-button-secondary"
                >
                    <?= $escape($secondaryLabel) ?>
                </a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
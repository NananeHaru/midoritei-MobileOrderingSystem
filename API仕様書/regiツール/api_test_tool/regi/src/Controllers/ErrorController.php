<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * 通常のルーティング処理を経由して表示できる
 * エラー画面を担当するController。
 *
 * DB停止など、Controllerを生成できない可能性があるエラーは
 * GlobalErrorHandlerで処理する。
 */
final class ErrorController
{
    /**
     * 404：ページが見つからない
     */
    public function notFound(): void
    {
        $this->disableBrowserCache();

        http_response_code(404);

        $title = 'ページが見つかりません';
        $heading = 'お探しのページが見つかりませんでした';
        $message = 'URLが間違っているか、ページが移動または削除された可能性があります。';
        $errorCode = 'NOT_FOUND';

        $primaryUrl = '/regi/public/home';
        $primaryLabel = 'ホームへ戻る';

        $secondaryUrl = '/regi/public/';
        $secondaryLabel = 'ログイン画面へ';

        $this->renderErrorView(
            $title,
            $heading,
            $message,
            $errorCode,
            $primaryUrl,
            $primaryLabel,
            $secondaryUrl,
            $secondaryLabel
        );
    }

    /**
     * 403：アクセス権限がない
     */
    public function forbidden(): void
    {
        $this->disableBrowserCache();

        http_response_code(403);

        $title = 'アクセスできません';
        $heading = 'この画面を表示する権限がありません';
        $message = 'ログインしているアカウントの権限を確認してください。';
        $errorCode = 'FORBIDDEN';

        $primaryUrl = '/regi/public/home';
        $primaryLabel = 'ホームへ戻る';

        $secondaryUrl = '/regi/public/';
        $secondaryLabel = 'ログイン画面へ';

        $this->renderErrorView(
            $title,
            $heading,
            $message,
            $errorCode,
            $primaryUrl,
            $primaryLabel,
            $secondaryUrl,
            $secondaryLabel
        );
    }

    /**
     * MOS API接続エラー
     */
    public function mosUnavailable(): void
    {
        $this->disableBrowserCache();

        http_response_code(503);

        $title = '注文システム接続エラー';
        $heading = '注文システムに接続できません';
        $message = '通信状態を確認し、しばらくしてから再度お試しください。';
        $errorCode = 'MOS_SERVICE_UNAVAILABLE';

        $primaryUrl = '/regi/public/customer/select';
        $primaryLabel = '注文選択画面へ戻る';

        $secondaryUrl = '/regi/public/home';
        $secondaryLabel = 'ホームへ戻る';

        $this->renderErrorView(
            $title,
            $heading,
            $message,
            $errorCode,
            $primaryUrl,
            $primaryLabel,
            $secondaryUrl,
            $secondaryLabel
        );
    }

    /**
     * 一般的なシステムエラー
     *
     * この画面は、Controllerまで処理が到達できた場合に使用する。
     */
    public function system(): void
    {
        $this->disableBrowserCache();

        http_response_code(500);

        $title = 'システムエラー';
        $heading = '処理を完了できませんでした';
        $message = '時間をおいてから、もう一度お試しください。';
        $errorCode = 'SYSTEM_ERROR';

        $primaryUrl = '/regi/public/home';
        $primaryLabel = 'ホームへ戻る';

        $secondaryUrl = '/regi/public/';
        $secondaryLabel = 'ログイン画面へ';

        $this->renderErrorView(
            $title,
            $heading,
            $message,
            $errorCode,
            $primaryUrl,
            $primaryLabel,
            $secondaryUrl,
            $secondaryLabel
        );
    }

    /**
     * セッション切れ
     */
    public function sessionExpired(): void
    {
        $this->disableBrowserCache();

        http_response_code(401);

        $title = 'セッションの有効期限切れ';
        $heading = 'ログイン情報の有効期限が切れました';
        $message = '安全のため、もう一度ログインしてください。';
        $errorCode = 'SESSION_EXPIRED';

        $primaryUrl = '/regi/public/';
        $primaryLabel = 'ログイン画面へ';

        $secondaryUrl = '';
        $secondaryLabel = '';

        $this->renderErrorView(
            $title,
            $heading,
            $message,
            $errorCode,
            $primaryUrl,
            $primaryLabel,
            $secondaryUrl,
            $secondaryLabel
        );
    }

    /**
     * 共通エラー画面を読み込む。
     */
    private function renderErrorView(
        string $title,
        string $heading,
        string $message,
        string $errorCode,
        string $primaryUrl,
        string $primaryLabel,
        string $secondaryUrl = '',
        string $secondaryLabel = ''
    ): void {
        $viewPath = dirname(__DIR__) . '/Views/errors/error.php';

        if (!is_file($viewPath)) {
            /*
             * エラー画面ファイルまで存在しない場合の最終手段。
             * DBや共通レイアウトは使用しない。
             */
            $this->renderFallback(
                $heading,
                $message,
                $errorCode,
                $primaryUrl,
                $primaryLabel
            );

            return;
        }

        require $viewPath;
    }

    /**
     * エラー画面の読み込みにも失敗した場合の簡易表示。
     */
    private function renderFallback(
        string $heading,
        string $message,
        string $errorCode,
        string $primaryUrl,
        string $primaryLabel
    ): void {
        $safeHeading = htmlspecialchars(
            $heading,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeMessage = htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeErrorCode = htmlspecialchars(
            $errorCode,
            ENT_QUOTES,
            'UTF-8'
        );

        $safePrimaryUrl = htmlspecialchars(
            $primaryUrl,
            ENT_QUOTES,
            'UTF-8'
        );

        $safePrimaryLabel = htmlspecialchars(
            $primaryLabel,
            ENT_QUOTES,
            'UTF-8'
        );

        echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>エラー</title>
</head>
<body>
    <main>
        <h1>{$safeHeading}</h1>
        <p>{$safeMessage}</p>
        <p>エラーコード：{$safeErrorCode}</p>
        <p>
            <a href="{$safePrimaryUrl}">
                {$safePrimaryLabel}
            </a>
        </p>
    </main>
</body>
</html>
HTML;
    }

    /**
     * エラー画面をブラウザキャッシュに残さない。
     */
    private function disableBrowserCache(): void
    {
        if (headers_sent()) {
            return;
        }

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
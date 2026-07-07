<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * 処理を継続できない重大なシステムエラーを表す例外。
 *
 * 例：
 * ・データベース接続失敗
 * ・重要な設定ファイルの読み込み失敗
 * ・会計処理中の致命的な整合性エラー
 */
final class FatalSystemException extends RuntimeException
{
    /**
     * 利用者向けに表示するエラーコード。
     */
    private string $errorCode;

    /**
     * 利用者向けに表示するメッセージ。
     */
    private string $displayMessage;

    public function __construct(
        string $displayMessage = 'システムで重大なエラーが発生しました。',
        string $errorCode = 'SYSTEM_FATAL',
        string $logMessage = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->displayMessage = $displayMessage;
        $this->errorCode = $errorCode;

        /*
         * ログ用メッセージが指定されていなければ、
         * 利用者向けメッセージを例外メッセージとして使用する。
         */
        $exceptionMessage = $logMessage !== ''
            ? $logMessage
            : $displayMessage;

        parent::__construct(
            $exceptionMessage,
            $code,
            $previous
        );
    }

    /**
     * 利用者向けのエラーコードを返す。
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 利用者向けのメッセージを返す。
     */
    public function getDisplayMessage(): string
    {
        return $this->displayMessage;
    }
}
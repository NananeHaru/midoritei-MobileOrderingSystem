<?php
declare(strict_types=1);

namespace App\Lib;

use RuntimeException;

final class MosApiClient
{
    private string $baseUrl;
    private int $timeoutSeconds;

    public function __construct(string $baseUrl, int $timeoutSeconds = 10)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * MOS APIへJSON POSTする共通処理
     *
     * @return array{status:int, body:mixed, raw:string}
     */
    public function post(string $path, array $payload): array
    {
        // pathの先頭に / がなければ付ける
        $path = '/' . ltrim($path, '/');

        $url = $this->baseUrl . $path;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('リクエストJSONの生成に失敗しました。');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURLの初期化に失敗しました。');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new RuntimeException(
                'MOS API通信に失敗しました: ' . $error . '（errno=' . $errno . '）'
            );
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $trimmed = trim($raw);

        // updateStatus 正常系はレスポンスボディ空対応
        if ($trimmed === '') {
            return [
                'status' => $status,
                'body'   => null,
                'raw'    => $raw,
            ];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'MOS APIレスポンスJSONの解析に失敗しました。raw=' . $raw
            );
        }

        return [
            'status' => $status,
            'body'   => $decoded,
            'raw'    => $raw,
        ];
    }
}
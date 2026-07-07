<?php

namespace App\Infra;

use InvalidArgumentException;

class MosClient
{
    private string $baseUrl;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        int $connectTimeout = 3,
        int $timeout = 10
    ) {
        $baseUrl = $baseUrl ?? getenv('MOS_BASE_URL') ?? '';

        if ($baseUrl === '') {
            throw new InvalidArgumentException('MOS_BASE_URL が設定されていません。');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->connectTimeout = $connectTimeout;
        $this->timeout = $timeout;
    }

    /**
     * 注文取得API
     *
     * @param string|null $customerId 7桁数字 or null
     * @param int|null $billStatus 複合指定ビット和を許容。null は全状態
     * @param string|null $fromTime ISO8601 (YYYY-MM-DDTHH:MM:SS) or null
     * @param string|null $toTime ISO8601 (YYYY-MM-DDTHH:MM:SS) or null
     *
     * @return array{
     *   success: bool,
     *   http_status: int,
     *   orders: array,
     *   error_code: ?string,
     *   message: ?string
     * }
     */
    public function getOrders(
        ?string $customerId,
        ?int $billStatus,
        ?string $fromTime = null,
        ?string $toTime = null
    ): array {
        $this->validateCustomerIdNullable($customerId);
        $this->validateBillStatusNullable($billStatus);
        $this->validateIsoDateTimeNullable($fromTime, 'fromTime');
        $this->validateIsoDateTimeNullable($toTime, 'toTime');

        $payload = [
            'method' => 'getOrders',
            'customerId' => $customerId,
            'billStatus' => $billStatus,
            'fromTime' => $fromTime,
            'toTime' => $toTime,
        ];

        $response = $this->postJson('/api/orders', $payload);

        if ($response['success'] !== true) {
            return [
                'success' => false,
                'http_status' => $response['http_status'],
                'orders' => [],
                'error_code' => $response['error_code'],
                'message' => $response['message'],
            ];
        }

        $body = $response['body'];

        if ($body === null) {
            return [
                'success' => true,
                'http_status' => $response['http_status'],
                'orders' => [],
                'error_code' => null,
                'message' => null,
            ];
        }

        if (!is_array($body)) {
            return [
                'success' => false,
                'http_status' => $response['http_status'],
                'orders' => [],
                'error_code' => 'INVALID_RESPONSE',
                'message' => 'MOS getOrders のレスポンス形式が不正です。',
            ];
        }

        return [
            'success' => true,
            'http_status' => $response['http_status'],
            'orders' => array_values($body),
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * 会計状況更新API
     *
     * @param string $customerId 7桁数字
     * @param string|null $hash 16進文字列 or null
     * @param int $billStatus 更新後ステータス（複合指定ビット和を許容）
     *
     * @return array{
     *   success: bool,
     *   http_status: int,
     *   error_code: ?string,
     *   message: ?string
     * }
     */
    public function updateStatus(
        string $customerId,
        ?string $hash,
        int $billStatus
    ): array {
        $this->validateCustomerId($customerId);
        $this->validateHashNullable($hash);
        $this->validateBillStatus($billStatus);

        $payload = [
            'method' => 'updateStatus',
            'customerId' => $customerId,
            'hash' => $hash,
            'billStatus' => $billStatus,
        ];

        $response = $this->postJson('/api/orders', $payload);

        if ($response['success'] !== true) {
            return [
                'success' => false,
                'http_status' => $response['http_status'],
                'error_code' => $response['error_code'],
                'message' => $response['message'],
            ];
        }

        return [
            'success' => true,
            'http_status' => $response['http_status'],
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * 共通POST(JSON)
     *
     * @return array{
     *   success: bool,
     *   http_status: int,
     *   body: mixed,
     *   error_code: ?string,
     *   message: ?string
     * }
     */
    private function postJson(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'success' => false,
                'http_status' => 0,
                'body' => null,
                'error_code' => 'JSON_ENCODE_ERROR',
                'message' => 'リクエストJSONの生成に失敗しました。',
            ];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'success' => false,
                'http_status' => 0,
                'body' => null,
                'error_code' => 'CURL_INIT_ERROR',
                'message' => 'cURLの初期化に失敗しました。',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $rawBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($curlErrno !== 0) {
            return [
                'success' => false,
                'http_status' => 0,
                'body' => null,
                'error_code' => $this->mapCurlErrorToCode($curlErrno),
                'message' => $curlError !== '' ? $curlError : 'MOS通信に失敗しました。',
            ];
        }

        $decoded = null;

        if ($rawBody !== '' && $rawBody !== null) {
            $decoded = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'http_status' => $httpStatus,
                    'body' => null,
                    'error_code' => 'INVALID_RESPONSE',
                    'message' => 'MOSレスポンスのJSON解析に失敗しました。',
                ];
            }
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return [
                'success' => true,
                'http_status' => $httpStatus,
                'body' => $decoded,
                'error_code' => null,
                'message' => null,
            ];
        }

        $errorCode = null;
        $message = null;

        if (is_array($decoded)) {
            $errorCode = isset($decoded['errorCode']) ? (string) $decoded['errorCode'] : null;
            $message = isset($decoded['message']) ? (string) $decoded['message'] : null;
        }

        if ($errorCode === null) {
            $errorCode = $this->mapHttpStatusToDefaultErrorCode($httpStatus);
        }

        if ($message === null) {
            $message = 'MOS API 呼び出しに失敗しました。';
        }

        return [
            'success' => false,
            'http_status' => $httpStatus,
            'body' => $decoded,
            'error_code' => $errorCode,
            'message' => $message,
        ];
    }

    private function mapCurlErrorToCode(int $curlErrno): string
    {
        return match ($curlErrno) {
            CURLE_OPERATION_TIMEDOUT => 'TIMEOUT',
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY => 'SERVICE_UNAVAILABLE',
            default => 'NETWORK_ERROR',
        };
    }

    private function mapHttpStatusToDefaultErrorCode(int $httpStatus): string
    {
        return match ($httpStatus) {
            400 => 'INVALID_REQUEST',
            503 => 'SERVICE_UNAVAILABLE',
            504 => 'TIMEOUT',
            default => $httpStatus >= 500 ? 'SYSTEM_ERROR' : 'HTTP_ERROR',
        };
    }

    private function validateCustomerId(string $customerId): void
    {
        if (!preg_match('/^[0-9]{7}$/', $customerId)) {
            throw new InvalidArgumentException('customerId が不正です。');
        }
    }

    private function validateCustomerIdNullable(?string $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $this->validateCustomerId($customerId);
    }

    private function validateHashNullable(?string $hash): void
    {
        if ($hash === null) {
            return;
        }

        if (!preg_match('/^[0-9a-f]{8,64}$/', $hash)) {
            throw new InvalidArgumentException('hash が不正です。');
        }
    }

    private function validateBillStatus(int $billStatus): void
    {
        if ($billStatus < 1 || $billStatus > 15) {
            throw new InvalidArgumentException('billStatus は1〜15で入力してください。');
        }

        if (($billStatus & ~0b1111) !== 0) {
            throw new InvalidArgumentException('billStatus が不正です。');
        }
    }

    private function validateBillStatusNullable(?int $billStatus): void
    {
        if ($billStatus === null) {
            return;
        }

        $this->validateBillStatus($billStatus);
    }

    private function validateIsoDateTimeNullable(?string $value, string $fieldName): void
    {
        if ($value === null) {
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            throw new InvalidArgumentException($fieldName . ' が不正です。');
        }
    }
}
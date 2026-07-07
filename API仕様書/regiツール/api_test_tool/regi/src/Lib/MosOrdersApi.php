<?php
declare(strict_types=1);

namespace App\Lib;

use InvalidArgumentException;
use RuntimeException;

final class MosOrdersApi
{
    private const PATH = '/api/orders';

    // billStatus
    public const BILL_STATUS_OPEN       = 1; // 受付中
    public const BILL_STATUS_PAID       = 2; // 会計済み
    public const BILL_STATUS_RECEIVABLE = 4; // 未収金
    public const BILL_STATUS_CHECKING   = 8; // 会計中

    private MosApiClient $client;

    public function __construct(MosApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * 注文取得API
     *
     * 仕様:
     * - customerId が null でなければ、その顧客IDの注文のみ取得
     * - billStatus は複合指定可能な int。null の場合は全状態
     * - fromTime / toTime は ISO8601 文字列 or null
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(
        ?string $customerId,
        ?int $billStatus,
        ?string $fromTime,
        ?string $toTime
    ): array {
        $this->validateCustomerIdOrNull($customerId);
        $this->validateBillStatusOrNull($billStatus);
        $this->validateIsoDateTimeOrNull($fromTime, 'fromTime');
        $this->validateIsoDateTimeOrNull($toTime, 'toTime');

        $payload = [
            'method'     => 'getOrders',
            'customerId' => $customerId,
            'billStatus' => $billStatus,
            'fromTime'   => $fromTime,
            'toTime'     => $toTime,
        ];

        $response = $this->client->post(self::PATH, $payload);

        if ($response['status'] !== 200) {
            $this->throwApiError($response['status'], $response['body']);
        }

        if (!is_array($response['body'])) {
            throw new RuntimeException('getOrdersのレスポンス形式が不正です。');
        }

        return $response['body'];
    }

    /**
     * 会計状況更新API
     *
     * 仕様:
     * - hash が null の場合は同一性判定なし
     * - 正常系レスポンスボディは空
     */
    public function updateStatus(string $customerId, ?string $hash, int $billStatus): void
    {
        $this->validateCustomerIdOrNull($customerId);
        $this->validateHashOrNull($hash);
        $this->validateBillStatusOrNull($billStatus);

        $payload = [
            'method'     => 'updateStatus',
            'customerId' => $customerId,
            'hash'       => $hash,
            'billStatus' => $billStatus,
        ];

        $response = $this->client->post(self::PATH, $payload);

        if ($response['status'] !== 200) {
            $this->throwApiError($response['status'], $response['body']);
        }
    }

    /**
     * 受付中＋会計中 をまとめて取りたいときの例
     * 1 | 8 = 9
     */
    public static function combineBillStatus(int ...$statuses): int
    {
        $value = 0;
        foreach ($statuses as $status) {
            $value |= $status;
        }
        return $value;
    }

    private function validateCustomerIdOrNull(?string $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        if (!preg_match('/^[0-9]{7}$/', $customerId)) {
            throw new InvalidArgumentException('customerIdは7桁数字で指定してください。');
        }
    }

    private function validateHashOrNull(?string $hash): void
    {
        if ($hash === null) {
            return;
        }

        if (!preg_match('/^[0-9a-f]{8,64}$/', $hash)) {
            throw new InvalidArgumentException('hashの形式が不正です。');
        }
    }

    private function validateBillStatusOrNull(?int $billStatus): void
    {
        if ($billStatus === null) {
            return;
        }

        if ($billStatus < 1 || $billStatus > 15) {
            throw new InvalidArgumentException('billStatusは1～15で指定してください。');
        }
    }

    private function validateIsoDateTimeOrNull(?string $value, string $fieldName): void
    {
        if ($value === null) {
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            throw new InvalidArgumentException($fieldName . 'はISO8601形式で指定してください。');
        }
    }

    /**
     * @param mixed $body
     */
    private function throwApiError(int $status, $body): void
    {
        $errorCode = is_array($body) ? (string)($body['errorCode'] ?? 'UNKNOWN_ERROR') : 'UNKNOWN_ERROR';
        $message   = is_array($body) ? (string)($body['message'] ?? 'MOS API error') : 'MOS API error';

        throw new RuntimeException(sprintf(
            'MOS APIエラー [%d] %s: %s',
            $status,
            $errorCode,
            $message
        ));
    }
}
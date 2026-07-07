<?php

namespace App\Services;

use App\Infra\MosClient;
use App\Lib\OrderDataMapper;
use App\Lib\OrderImportValidator;
use App\Repositories\OrderHeaderRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

class CheckoutService
{
    private PDO $pdo;
    private OrderImportValidator $orderImportValidator;
    private OrderDataMapper $orderDataMapper;
    private BillingService $billingService;
    private MosClient $mosClient;
    private OrderHeaderRepository $orderHeaderRepository;

    public function __construct(
        ?PDO $pdo = null,
        ?OrderImportValidator $orderImportValidator = null,
        ?OrderDataMapper $orderDataMapper = null,
        ?BillingService $billingService = null,
        ?MosClient $mosClient = null,
        ?OrderHeaderRepository $orderHeaderRepository = null
    ) {
        $this->pdo = $pdo ?? require dirname(__DIR__) . '/Database/db.php';
        $this->orderImportValidator = $orderImportValidator ?? new OrderImportValidator();
        $this->orderDataMapper = $orderDataMapper ?? new OrderDataMapper();
        $this->billingService = $billingService ?? new BillingService($this->pdo);
        $this->orderHeaderRepository = $orderHeaderRepository ?? new OrderHeaderRepository();

        if ($mosClient === null) {
            throw new InvalidArgumentException('MosClient が必要です。');
        }

        $this->mosClient = $mosClient;
    }

    /**
     * MOS注文一覧を使って会計確定する
     *
     * 注文取得時点ではDB保存しない。
     * 会計確定時にだけ ORDER_BILL / BILL / BILL_DETAIL / BILL_PAYMENT を保存する。
     *
     * @param array $mosOrders
     * @param array $paymentInput
     * [
     *   'discount_amount' => 100,
     *   'pay_method' => 'CASH',
     *   'received_amount' => 5000,
     *   'provider' => null,
     *   'bill_status' => 2
     * ]
     *
     * @return array
     */
    public function checkout(array $mosOrders, array $paymentInput): array
    {
        if (empty($mosOrders)) {
            throw new InvalidArgumentException('注文データがありません。');
        }

        // 1. MOS注文データ検証
        $this->orderImportValidator->validateOrders($mosOrders);

        // 2. 会計入力へ変換
        $billingInput = $this->orderDataMapper->mergeOrdersToBillingInput(
            $mosOrders,
            [
                'discount_amount' => $paymentInput['discount_amount'] ?? 0,
                'pay_method' => $paymentInput['pay_method'] ?? '',
                'received_amount' => $paymentInput['received_amount'] ?? null,
                'provider' => $paymentInput['provider'] ?? null,
            ]
        );

        // 注文連携会計であることを明示
        $billingInput['is_manual'] = false;

        // 3. 会計確定（ここで初めてDB保存）
        $billingResult = $this->billingService->checkout($billingInput);

        $billId = (int)($billingResult['bill']['bill_id'] ?? 0);
        if ($billId <= 0) {
            throw new InvalidArgumentException('会計IDの取得に失敗しました。');
        }

        $orderHeaderIds = $billingResult['order_header_ids'] ?? [];
        if (!is_array($orderHeaderIds)) {
            $orderHeaderIds = [];
        }

        // 4. 会計後にMOS更新
        $billStatusForUpdate = isset($paymentInput['bill_status'])
            ? (int)$paymentInput['bill_status']
            : 2;

        $this->validateUpdateBillStatus($billStatusForUpdate);

        $mosUpdateResult = $this->updateOrdersStatus($mosOrders, $billStatusForUpdate);

        // 5. MOS更新結果を ORDER_HEADER に記録
        if (!empty($orderHeaderIds)) {
            if (($mosUpdateResult['success'] ?? false) === true) {
                $this->markMosUpdateSuccess($orderHeaderIds);
            } else {
                $this->markMosUpdateFailed(
                    $orderHeaderIds,
                    $mosUpdateResult['error_code'] ?? 'MOS_UPDATE_FAILED',
                    $mosUpdateResult['message'] ?? 'MOS updateStatus に失敗しました。'
                );
            }
        }

        return [
            'success' => true,
            'billing_success' => true,
            'mos_update_success' => (bool)($mosUpdateResult['success'] ?? false),
            'billing' => $billingResult,
            'mos_update' => $mosUpdateResult,
        ];
    }

    /**
     * 注文一覧に対して updateStatus を送る
     */
    private function updateOrdersStatus(array $mosOrders, int $billStatus): array
    {
        $results = [];
        $allSuccess = true;
        $firstErrorCode = null;
        $firstErrorMessage = null;

        foreach ($mosOrders as $index => $order) {
            $customerId = (string)($order['customerId'] ?? '');
            $hash = isset($order['hash']) ? (string)$order['hash'] : null;

            $response = $this->callUpdateStatusWithRetry($customerId, $hash, $billStatus);

            $row = [
                'order_index' => $index,
                'customer_id' => $customerId,
                'hash' => $hash,
                'success' => (bool)($response['success'] ?? false),
                'http_status' => (int)($response['http_status'] ?? 0),
                'error_code' => $response['error_code'] ?? null,
                'message' => $response['message'] ?? null,
            ];

            $results[] = $row;

            if (($response['success'] ?? false) !== true) {
                $allSuccess = false;

                if ($firstErrorCode === null) {
                    $firstErrorCode = $row['error_code'] ?? 'MOS_UPDATE_FAILED';
                }

                if ($firstErrorMessage === null) {
                    $firstErrorMessage = $row['message'] ?? 'MOS updateStatus に失敗しました。';
                }
            }
        }

        if ($allSuccess) {
            return [
                'success' => true,
                'status' => 'SUCCESS',
                'results' => $results,
            ];
        }

        return [
            'success' => false,
            'status' => 'FAILED',
            'error_code' => $firstErrorCode,
            'message' => $firstErrorMessage,
            'results' => $results,
        ];
    }

    /**
     * 初回 + リトライ2回
     * 例外は投げず結果配列で返す
     */
    private function callUpdateStatusWithRetry(
        string $customerId,
        ?string $hash,
        int $billStatus
    ): array {
        $lastError = [
            'success' => false,
            'http_status' => 0,
            'error_code' => 'UNKNOWN_ERROR',
            'message' => 'MOS updateStatus に失敗しました。',
        ];

        for ($i = 0; $i < 3; $i++) {
            try {
                $response = $this->mosClient->updateStatus($customerId, $hash, $billStatus);

                if (!is_array($response)) {
                    $lastError = [
                        'success' => false,
                        'http_status' => 0,
                        'error_code' => 'INVALID_RESPONSE',
                        'message' => 'MOS updateStatus のレスポンス形式が不正です。',
                    ];
                    continue;
                }

                if (($response['success'] ?? false) === true) {
                    return [
                        'success' => true,
                        'http_status' => (int)($response['http_status'] ?? 200),
                        'error_code' => null,
                        'message' => null,
                    ];
                }

                if (($response['error_code'] ?? null) === 'HASH_MISMATCH') {
                    $lastError = [
                        'success' => false,
                        'http_status' => (int)($response['http_status'] ?? 0),
                        'error_code' => 'HASH_MISMATCH',
                        'message' => '注文内容が変更されています。再取得してください。',
                    ];
                    break;
                }

                $lastError = [
                    'success' => false,
                    'http_status' => (int)($response['http_status'] ?? 0),
                    'error_code' => $response['error_code'] ?? 'MOS_UPDATE_FAILED',
                    'message' => $response['message'] ?? 'MOS updateStatus に失敗しました。',
                ];
            } catch (Throwable $e) {
                $lastError = [
                    'success' => false,
                    'http_status' => 0,
                    'error_code' => 'EXCEPTION',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $lastError;
    }

    /**
     * MOS更新成功を ORDER_HEADER に記録
     */
    private function markMosUpdateSuccess(array $orderHeaderIds): void
    {
        $this->orderHeaderRepository->updateMosStatus(
            $this->pdo,
            $orderHeaderIds,
            1,
            null,
            null
        );
    }

    /**
     * MOS更新失敗を ORDER_HEADER に記録
     */
    private function markMosUpdateFailed(
        array $orderHeaderIds,
        ?string $errorCode,
        ?string $errorMessage
    ): void {
        $this->orderHeaderRepository->updateMosStatus(
            $this->pdo,
            $orderHeaderIds,
            9,
            $errorCode,
            $this->truncateNullable($errorMessage, 255)
        );
    }

    /**
     * 複合指定ビット和を許容
     */
    private function validateUpdateBillStatus(int $billStatus): void
    {
        if ($billStatus < 1 || $billStatus > 15) {
            throw new InvalidArgumentException('bill_status は1〜15で入力してください。');
        }

        if (($billStatus & ~0b1111) !== 0) {
            throw new InvalidArgumentException('bill_status が不正です。');
        }
    }

    private function truncateNullable(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
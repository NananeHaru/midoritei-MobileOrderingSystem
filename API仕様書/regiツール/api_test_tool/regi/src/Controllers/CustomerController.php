<?php

namespace App\Controllers;

use App\Lib\MosApiClient;
use App\Lib\MosOrdersApi;
use App\Models\StoreModel;
use PDO;
use RuntimeException;
use Throwable;

class CustomerController
{

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        /** @var PDO $connection */
        $connection = $pdo
            ?? require dirname(__DIR__) . '/Database/db.php';

        $this->pdo = $connection;
    }

    public function showSelect(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $manualItems = $_SESSION['manual_checkout_items'] ?? [];
        $activeTab = $_GET['tab'] ?? 'link';

        $storeModel = new StoreModel();
        $stores = $storeModel->findActiveStores();

        require dirname(__DIR__) . '/Views/customer/select.php';
    }

    public function select(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $customerId = trim((string)($_POST['customerId'] ?? ''));

        if ($customerId === '') {
            $_SESSION['flash_error'] = '客番号を入力してください。';
            header('Location: /regi/public/customer/select');
            exit;
        }

        if (!preg_match('/^\d{7}$/', $customerId)) {
            $_SESSION['flash_error'] = '客番号は7桁の数字で入力してください。';
            header('Location: /regi/public/customer/select');
            exit;
        }

        try {
            /*
             * MOS APIへ問い合わせる前に、レジDB内に
             * 支払い途中の分割会計がないか確認する。
             */
            if ($this->resumeIncompleteSplitCheckout($customerId)) {
                header('Location: /regi/public/checkout/settlement');
                exit;
            }

            $mosApi = $this->createMosOrdersApi();

            $orders = $mosApi->getOrders(
                $customerId,
                null,
                null,
                null
            );

            if (empty($orders)) {
                $_SESSION['flash_error'] = '指定された客番号の注文データが見つかりません。';
                header('Location: /regi/public/customer/select');
                exit;
            }

            $firstOrder = $orders[0];
            $this->applyOrderToSession($orders, $firstOrder);

            header('Location: /regi/public/checkout');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = '注文データの取得に失敗しました。';
            header('Location: /regi/public/customer/select');
            exit;
        }
    }

    /**
     * 注文選択タブから条件検索する
     * fetch('/customer/search-orders', { method:'POST', body: JSON.stringify(...) }) を想定
     */
    public function searchOrders(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Content-Type: application/json; charset=UTF-8');

        try {
            $raw = file_get_contents('php://input');
            $req = json_decode($raw, true);

            if (!is_array($req)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'リクエスト形式が不正です。'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $billStatus = $req['billStatus'] ?? null;
            $storeId    = trim((string)($req['storeId'] ?? ''));
            $fromTime   = $req['fromTime'] ?? null;
            $toTime     = $req['toTime'] ?? null;

            if ($storeId !== '' && !preg_match('/^[A-Z]{2}$/', $storeId)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => '店舗IDの形式が不正です。'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $fromTimeIso = $this->normalizeToIso8601($fromTime);
            $toTimeIso   = $this->normalizeToIso8601($toTime);

            if ($fromTime !== null && $fromTime !== '' && $fromTimeIso === null) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'From日時の形式が不正です。'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($toTime !== null && $toTime !== '' && $toTimeIso === null) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'To日時の形式が不正です。'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($billStatus !== null && $billStatus !== '') {
                $billStatus = (int)$billStatus;
                if ($billStatus < 1 || $billStatus > 15) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => '会計状況の指定が不正です。'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
            } else {
                $billStatus = null;
            }

            $mosApi = $this->createMosOrdersApi();

            // customerId=null で条件検索
            $orders = $mosApi->getOrders(
                null,
                $billStatus,
                $fromTimeIso,
                $toTimeIso
            );

            if (!is_array($orders)) {
                $orders = [];
            }

            // API仕様上 storeId 指定はないので、レジ側で絞る
            if ($storeId !== '') {
                $orders = array_values(array_filter($orders, static function ($order) use ($storeId) {
                    return (string)($order['storeId'] ?? '') === $storeId;
                }));
            }

            $result = [];
            foreach ($orders as $order) {
                $items = is_array($order['items'] ?? null) ? $order['items'] : [];

                $result[] = [
                    'hash'       => (string)($order['hash'] ?? ''),
                    'storeId'    => (string)($order['storeId'] ?? ''),
                    'entryTime'  => (string)($order['entryTime'] ?? ''),
                    'customerId' => (string)($order['customerId'] ?? ''),
                    'billStatus' => (int)($order['billStatus'] ?? 0),
                    'items'      => array_map(static function ($item) {
                        return [
                            'orderTime'    => (string)($item['orderTime'] ?? ''),
                            'menuName'     => (string)($item['menuName'] ?? ''),
                            'unitPrice'    => (int)($item['unitPrice'] ?? 0),
                            'taxRate'      => (int)($item['taxRate'] ?? 0),
                            'orderQty'     => (int)($item['orderQty'] ?? 0),
                            'offerQty'     => (int)($item['offerQty'] ?? 0),
                            'categoryName' => $item['categoryName'] ?? null,
                        ];
                    }, $items),
                ];
            }

            echo json_encode([
                'ok' => true,
                'orders' => $result,
            ], JSON_UNESCAPED_UNICODE);

        } catch (Throwable $e) {
            http_response_code(500);

            error_log('[searchOrders] ' . $e->getMessage());

            echo json_encode([
                'ok' => false,
                'message' => '注文検索に失敗しました: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 注文一覧から選択した注文を会計へ反映する
     * POST: customerId, hash
     */
    public function selectOrder(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $customerId = trim((string)($_POST['customerId'] ?? ''));
        $hash       = trim((string)($_POST['hash'] ?? ''));

        if ($customerId === '' || !preg_match('/^\d{7}$/', $customerId)) {
            $_SESSION['flash_error'] = '選択した注文の客番号が不正です。';
            header('Location: /regi/public/customer/select');
            exit;
        }

        try {
            /*
             * 注文一覧から選択した場合でも、
             * 先にレジDBの支払い途中会計を確認する。
             */
            if ($this->resumeIncompleteSplitCheckout($customerId)) {
                header('Location: /regi/public/checkout/settlement');
                exit;
            }

            $mosApi = $this->createMosOrdersApi();

            // customerId単位で再取得
            $orders = $mosApi->getOrders(
                $customerId,
                null,
                null,
                null
            );

            if (empty($orders)) {
                $_SESSION['flash_error'] = '選択した注文データが見つかりません。';
                header('Location: /regi/public/customer/select');
                exit;
            }

            $selectedOrder = null;

            if ($hash !== '') {
                foreach ($orders as $order) {
                    if ((string)($order['hash'] ?? '') === $hash) {
                        $selectedOrder = $order;
                        break;
                    }
                }
            }

            if ($selectedOrder === null) {
                $selectedOrder = $orders[0];
            }

            $this->applyOrderToSession([$selectedOrder], $selectedOrder);

            header('Location: /regi/public/checkout');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = '選択した注文データの反映に失敗しました。';
            header('Location: /regi/public/customer/select');
            exit;
        }
    }

    public function add(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $addCustomerId = trim((string)($_POST['addCustomerId'] ?? ''));

        if ($addCustomerId === '') {
            header('Location: /regi/public/checkout');
            exit;
        }

        if (!preg_match('/^\d{7}$/', $addCustomerId)) {
            $_SESSION['flash_error'] = '追加する客番号は7桁の数字で入力してください。';
            header('Location: /regi/public/checkout');
            exit;
        }

        try {
            $mosApi = $this->createMosOrdersApi();

            $orders = $mosApi->getOrders(
                $addCustomerId,
                null,
                null,
                null
            );

            if (empty($orders)) {
                $_SESSION['flash_error'] = '追加する客番号の注文データが見つかりません。';
                header('Location: /regi/public/checkout');
                exit;
            }

            $customerIds = $_SESSION['customer_ids'] ?? [];
            $mainCustomerId = $_SESSION['customerId'] ?? '';

            if (empty($customerIds) && $mainCustomerId !== '') {
                $customerIds[] = $mainCustomerId;
            }

            if (!in_array($addCustomerId, $customerIds, true)) {
                $customerIds[] = $addCustomerId;
            }

            $_SESSION['customer_ids'] = array_values($customerIds);

            $checkoutOrders = $_SESSION['checkout_orders'] ?? [];
            foreach ($orders as $order) {
                $checkoutOrders[] = $order;
            }
            $_SESSION['checkout_orders'] = $checkoutOrders;

            header('Location: /regi/public/checkout');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = '追加注文データの取得に失敗しました。';
            header('Location: /regi/public/checkout');
            exit;
        }
    }

    private function applyOrderToSession(array $orders, array $firstOrder): void
    {
        // 手入力会計の残りをクリア
        unset($_SESSION['manual_checkout_items']);
        unset($_SESSION['manual_started_at']);

        // 会計関連の一時情報を初期化
        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['order_header_ids']);
        unset($_SESSION['last_checkout_result']);

        $_SESSION['customerId'] = (string)($firstOrder['customerId'] ?? '');
        $_SESSION['customer_ids'] = [(string)($firstOrder['customerId'] ?? '')];
        $_SESSION['checkout_orders'] = $orders;

        $_SESSION['checkout_hash'] = (string)($firstOrder['hash'] ?? '');
        $_SESSION['checkout_store_id'] = (string)($firstOrder['storeId'] ?? '');
        $_SESSION['checkout_bill_status'] = (int)($firstOrder['billStatus'] ?? 0);

        $_SESSION['start_time'] = isset($firstOrder['entryTime'])
            ? str_replace('T', ' ', (string)$firstOrder['entryTime'])
            : date('Y-m-d H:i:s');
    }

    /**
     * 顧客番号に該当する支払い途中の分割会計をDBから検索し、
     * 見つかった場合は会計セッションを復元する。
     *
     * 対象:
     * - split_mode が PERSON / AMOUNT / ITEM
     * - BILL_PAYMENT が1件以上存在
     * - 支払合計が請求額未満
     */
    private function resumeIncompleteSplitCheckout(
        string $customerId
    ): bool {
        $bill = $this->findIncompleteSplitBillByCustomerId(
            $customerId
        );

        if ($bill === null) {
            return false;
        }

        $billId = (int)$bill['bill_id'];
        $orderBillId = (int)$bill['order_bill_id'];

        $details = $this->findBillDetails($billId);
        $payments = $this->findBillPayments($billId);
        $orderHeaders = $this->findOrderHeadersByOrderBillId(
            $orderBillId
        );

        if (empty($details)) {
            throw new RuntimeException(
                '途中会計の明細データが見つかりません。'
            );
        }

        /*
         * CheckoutController::buildCheckoutContext() が
         * checkout_orders を参照するため、
         * DBのBILL_DETAILからMOS形式に近い配列を再構成する。
         */
        $items = [];

        foreach ($details as $detail) {
            $qty = (int)($detail['qty'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $items[] = [
                'orderTime'
                    => (string)($bill['bill_time'] ?? ''),
                'menuName'
                    => (string)($detail['menu_name'] ?? ''),
                'unitPrice'
                    => (int)($detail['unit_price'] ?? 0),
                'taxRate'
                    => (int)($detail['tax_rate'] ?? 0),
                'orderQty' => $qty,
                'offerQty' => $qty,
                'categoryName'
                    => $detail['category_name'] ?? null,
            ];
        }

        if (empty($items)) {
            throw new RuntimeException(
                '途中会計の有効な商品明細がありません。'
            );
        }

        $customerIds = [];
        $orderHeaderIds = [];
        $entryTime = '';

        foreach ($orderHeaders as $header) {
            $headerCustomerId = trim(
                (string)($header['customer_id'] ?? '')
            );

            if (
                $headerCustomerId !== ''
                && !in_array(
                    $headerCustomerId,
                    $customerIds,
                    true
                )
            ) {
                $customerIds[] = $headerCustomerId;
            }

            $headerId = (int)(
                $header['order_header_id'] ?? 0
            );

            if ($headerId > 0) {
                $orderHeaderIds[] = $headerId;
            }

            if (
                $entryTime === ''
                && !empty($header['entry_time'])
            ) {
                $entryTime = (string)$header['entry_time'];
            }
        }

        if (empty($customerIds)) {
            $customerIds = [$customerId];
        }

        if ($entryTime === '') {
            $entryTime = (string)(
                $bill['bill_time']
                ?? date('Y-m-d H:i:s')
            );
        }

        $primaryCustomerId = $customerIds[0];

        $checkoutOrder = [
            'hash' => '',
            'storeId' => (string)($bill['store_id'] ?? ''),
            'entryTime' => str_replace(' ', 'T', $entryTime),
            'customerId' => $primaryCustomerId,
            'billStatus' => 0,
            'items' => $items,
        ];

        /*
         * 古い会計セッションだけを削除し、
         * 復元対象のDBデータで入れ直す。
         */
        $this->clearCheckoutSessionForResume();

        $_SESSION['customerId'] = $primaryCustomerId;
        $_SESSION['customer_ids'] = $customerIds;
        $_SESSION['checkout_orders'] = [$checkoutOrder];

        $_SESSION['checkout_hash'] = '';
        $_SESSION['checkout_store_id']
            = (string)($bill['store_id'] ?? '');
        $_SESSION['checkout_bill_status'] = 0;
        $_SESSION['start_time'] = $entryTime;
        $_SESSION['order_header_ids']
            = array_values(array_unique($orderHeaderIds));

        $discountAmount = (int)(
            $bill['discount_amount'] ?? 0
        );

        if ($discountAmount > 0) {
            /*
             * CheckoutControllerは discount セッションから
             * 割引額を再計算するため、定額割引として復元する。
             */
            $_SESSION['discount'] = [
                'type' => 'amount',
                'amount' => $discountAmount,
            ];
        }

        $_SESSION['discount_amount'] = $discountAmount;

        $_SESSION['split_mode'] = strtoupper(
            (string)($bill['split_mode'] ?? 'NONE')
        );
        $_SESSION['split_bill_id'] = $billId;
        $_SESSION['split_order_bill_id'] = $orderBillId;
        $_SESSION['split_payments'] = $payments;

        $_SESSION['split_started_result'] = [
            'bill' => $bill,
            'payments' => $payments,
            'summary' => [
                'total_amount'
                    => (int)($bill['total_amount'] ?? 0),
                'paid_amount'
                    => (int)($bill['paid_amount'] ?? 0),
                'remaining_amount'
                    => (int)($bill['remaining_amount'] ?? 0),
                'payment_count'
                    => (int)($bill['payment_count'] ?? 0),
            ],
        ];

        $_SESSION['flash_warning']
            = '支払い途中の分割会計が見つかったため、途中から再開しました。';

        return true;
    }

    /**
     * 顧客番号から支払い途中の分割会計を1件取得する。
     */
    private function findIncompleteSplitBillByCustomerId(
        string $customerId
    ): ?array {
        $sql = <<<'SQL'
            SELECT
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode,
                COUNT(bp.bill_payment_id) AS payment_count,
                COALESCE(SUM(bp.pay_amount), 0) AS paid_amount,
                GREATEST(
                    b.total_amount
                    - COALESCE(SUM(bp.pay_amount), 0),
                    0
                ) AS remaining_amount
            FROM bill AS b
            INNER JOIN order_header AS oh
                ON oh.order_bill_id = b.order_bill_id
            INNER JOIN bill_payment AS bp
                ON bp.bill_id = b.bill_id
               AND bp.pay_amount > 0
            WHERE oh.customer_id = :customer_id
              AND b.split_mode IN (
                  'PERSON',
                  'AMOUNT',
                  'ITEM'
              )
            GROUP BY
                b.bill_id,
                b.order_bill_id,
                b.store_id,
                b.bill_time,
                b.subtotal_amount,
                b.discount_amount,
                b.tax_amount,
                b.total_amount,
                b.split_mode
            HAVING COUNT(bp.bill_payment_id) > 0
               AND COALESCE(SUM(bp.pay_amount), 0)
                   < b.total_amount
            ORDER BY b.bill_id DESC
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customerId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['bill_id'] = (int)$row['bill_id'];
        $row['order_bill_id']
            = (int)$row['order_bill_id'];
        $row['subtotal_amount']
            = (int)$row['subtotal_amount'];
        $row['discount_amount']
            = (int)$row['discount_amount'];
        $row['tax_amount']
            = (int)$row['tax_amount'];
        $row['total_amount']
            = (int)$row['total_amount'];
        $row['payment_count']
            = (int)$row['payment_count'];
        $row['paid_amount']
            = (int)$row['paid_amount'];
        $row['remaining_amount']
            = (int)$row['remaining_amount'];

        return $row;
    }

    /**
     * 会計明細を取得する。
     */
    private function findBillDetails(int $billId): array
    {
        $sql = <<<'SQL'
            SELECT
                bill_detail_id,
                bill_id,
                menu_name,
                category_name,
                qty,
                unit_price,
                amount,
                tax_rate
            FROM bill_detail
            WHERE bill_id = :bill_id
            ORDER BY bill_detail_id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 支払い済み情報を取得する。
     */
    private function findBillPayments(int $billId): array
    {
        $sql = <<<'SQL'
            SELECT
                bill_payment_id,
                bill_id,
                pay_method,
                pay_amount,
                pay_time,
                received_amount,
                change_amount,
                provider
            FROM bill_payment
            WHERE bill_id = :bill_id
              AND pay_amount > 0
            ORDER BY bill_payment_id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':bill_id' => $billId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['bill_payment_id']
                = (int)$row['bill_payment_id'];
            $row['bill_id'] = (int)$row['bill_id'];
            $row['pay_amount']
                = (int)$row['pay_amount'];

            $row['received_amount']
                = $row['received_amount'] !== null
                    ? (int)$row['received_amount']
                    : null;

            $row['change_amount']
                = $row['change_amount'] !== null
                    ? (int)$row['change_amount']
                    : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * ORDER_BILLに紐づく注文ヘッダーを取得する。
     */
    private function findOrderHeadersByOrderBillId(
        int $orderBillId
    ): array {
        $sql = <<<'SQL'
            SELECT
                order_header_id,
                order_bill_id,
                customer_id,
                entry_time,
                hash
            FROM order_header
            WHERE order_bill_id = :order_bill_id
            ORDER BY order_header_id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_bill_id' => $orderBillId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 途中会計を復元する前に、古い一時データだけを削除する。
     */
    private function clearCheckoutSessionForResume(): void
    {
        unset($_SESSION['manual_checkout_items']);
        unset($_SESSION['manual_started_at']);

        unset($_SESSION['checkout_orders']);
        unset($_SESSION['customerId']);
        unset($_SESSION['customer_ids']);
        unset($_SESSION['discount']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['start_time']);
        unset($_SESSION['order_header_ids']);
        unset($_SESSION['checkout_hash']);
        unset($_SESSION['checkout_store_id']);
        unset($_SESSION['checkout_bill_status']);

        unset($_SESSION['split_mode']);
        unset($_SESSION['split_person_count']);
        unset($_SESSION['split_payments']);
        unset($_SESSION['split_bill_id']);
        unset($_SESSION['split_order_bill_id']);
        unset($_SESSION['split_started_result']);

        unset($_SESSION['item_split_paid_indexes']);
        unset($_SESSION['item_split_paid_units']);

        unset($_SESSION['last_checkout_result']);
        unset($_SESSION['last_print_result']);
        unset($_SESSION['last_split_payment_result']);
        unset($_SESSION['last_split_payment_type']);
        unset($_SESSION['last_split_is_final']);
        unset($_SESSION['flash_warning']);
    }

    private function normalizeToIso8601(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d\TH:i:s', $ts);
    }

    private function createMosOrdersApi(): MosOrdersApi
    {
        /** @var array{
         *     base_url: string,
         *     timeout_seconds?: int
         * } $config
         */
        $config = require dirname(__DIR__) . '/Config/mos.php';

        $baseUrl = trim((string)($config['base_url'] ?? ''));
        $timeoutSeconds = (int)($config['timeout_seconds'] ?? 10);

        if ($baseUrl === '') {
            throw new RuntimeException(
                'MOS APIの接続先が設定されていません。'
            );
        }

        $client = new MosApiClient(
            $baseUrl,
            $timeoutSeconds
        );

        return new MosOrdersApi($client);
    }
}
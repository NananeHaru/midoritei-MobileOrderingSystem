<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Throwable;

final class HistoryController
{
    private const HISTORY_URL = '/regi/public/history';

    /**
     * 会計履歴一覧を表示する。
     *
     * 一覧はBILL単位で1行ずつ表示する。
     */
    public function index(): void
    {
        $this->startSession();

        try {
            $pdo = $this->getConnection();
            [$role, $sessionStoreId] = $this->requireHistoryAccess();

            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = 20;

            $keyword = trim((string)($_GET['keyword'] ?? ''));
            $date = trim((string)($_GET['date'] ?? ''));

            [$whereSql, $params] = $this->buildHistoryWhere(
                $role,
                $sessionStoreId,
                $keyword,
                $date
            );

            $countSql = "
                SELECT COUNT(*) AS cnt
                FROM BILL b
                LEFT JOIN stores s
                  ON s.store_id = b.store_id
                {$whereSql}
            ";

            $countStmt = $pdo->prepare($countSql);
            $this->bindStringParams($countStmt, $params);
            $countStmt->execute();

            $totalCount = (int)($countStmt->fetchColumn() ?: 0);
            $totalPages = max(1, (int)ceil($totalCount / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            /*
             * BILL_PAYMENTをそのままJOINしてpay_methodをGROUP BYすると、
             * 分割会計時に同じBILLが複数行になるため、支払い情報は相関サブクエリで集約する。
             */
            $sql = "
                SELECT
                    b.bill_id,
                    b.order_bill_id,
                    b.store_id,
                    COALESCE(s.store_name, b.store_id) AS store_name,
                    s.store_address,
                    s.store_phone,
                    b.bill_time,
                    b.subtotal_amount,
                    b.discount_amount,
                    b.tax_amount,
                    b.total_amount,
                    COUNT(DISTINCT bd.bill_detail_id) AS items_count,
                    (
                        SELECT COUNT(*)
                        FROM BILL_PAYMENT bp_count
                        WHERE bp_count.bill_id = b.bill_id
                    ) AS payment_count,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT bp_method.pay_method
                            ORDER BY bp_method.pay_method
                            SEPARATOR ','
                        )
                        FROM BILL_PAYMENT bp_method
                        WHERE bp_method.bill_id = b.bill_id
                    ) AS pay_methods
                FROM BILL b
                LEFT JOIN stores s
                  ON s.store_id = b.store_id
                LEFT JOIN BILL_DETAIL bd
                  ON bd.bill_id = b.bill_id
                {$whereSql}
                GROUP BY
                    b.bill_id,
                    b.order_bill_id,
                    b.store_id,
                    s.store_name,
                    s.store_address,
                    s.store_phone,
                    b.bill_time,
                    b.subtotal_amount,
                    b.discount_amount,
                    b.tax_amount,
                    b.total_amount
                ORDER BY b.bill_time DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $pdo->prepare($sql);
            $this->bindStringParams($stmt, $params);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $bills = [];

            foreach ($rows as $row) {
                $billId = (string)($row['bill_id'] ?? '');
                $orderBillId = (string)($row['order_bill_id'] ?? '');
                $customerIds = $this->findCustomerIdsByOrderBillId($pdo, $orderBillId);
                $payMethods = $this->splitPayMethods((string)($row['pay_methods'] ?? ''));
                $discountAmount = (int)($row['discount_amount'] ?? 0);

                $bills[] = [
                    'billId'            => $billId,
                    'storeId'           => (string)($row['store_id'] ?? ''),
                    'storeName'         => (string)($row['store_name'] ?? ''),
                    'storeAddress'      => (string)($row['store_address'] ?? ''),
                    'storePhone'        => (string)($row['store_phone'] ?? ''),
                    'customerIds'       => $customerIds,
                    'customerIdText'    => $customerIds !== [] ? implode(', ', $customerIds) : '手入力',
                    'customerIdPreview' => $this->makeCustomerPreview($customerIds),
                    'payMethods'        => $payMethods,
                    'payMethod'         => count($payMethods) === 1 ? $payMethods[0] : '',
                    'payLabel'          => $this->makePaymentSummaryLabel($payMethods),
                    'paymentCount'      => (int)($row['payment_count'] ?? 0),
                    'datetime'          => $this->formatDateTime($row['bill_time'] ?? null),
                    'itemsCount'        => (int)($row['items_count'] ?? 0),
                    'subtotal'          => (int)($row['subtotal_amount'] ?? 0),
                    'tax'               => (int)($row['tax_amount'] ?? 0),
                    'discount'          => $discountAmount,
                    'discountLabel'     => $discountAmount > 0 ? '割引' : null,
                    'discountAmount'    => $discountAmount,
                    'total'             => (int)($row['total_amount'] ?? 0),
                ];
            }

            $title = '会計履歴';
            $pagination = [
                'page'       => $page,
                'perPage'    => $perPage,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
                'hasPrev'    => $page > 1,
                'hasNext'    => $page < $totalPages,
                'prevPage'   => $page > 1 ? $page - 1 : 1,
                'nextPage'   => $page < $totalPages ? $page + 1 : $totalPages,
            ];

            require dirname(__DIR__) . '/Views/history/index.php';
        } catch (Throwable $e) {
            $this->renderError('会計履歴の取得中にエラーが発生しました。', $e);
        }
    }

    /**
     * 会計履歴詳細モーダルを表示する。
     *
     * BILL全体の商品明細に加え、BILL_PAYMENT一覧を渡す。
     */
    public function detail(): void
    {
        $this->startSession();

        try {
            $pdo = $this->getConnection();
            [$role, $sessionStoreId] = $this->requireHistoryAccess();

            $billId = trim((string)($_GET['bill_id'] ?? ''));
            if ($billId === '') {
                $this->abort(400, 'bill_id が指定されていません。');
            }

            $detail = $this->findBill($pdo, $billId);
            if ($detail === null) {
                $this->abort(404, '会計詳細が見つかりません。');
            }

            $this->assertStoreAccess($role, $sessionStoreId, (string)$detail['storeId']);

            $payments = $this->findPaymentsByBillId($pdo, $billId);

            $data = [
                'detail'  => $detail,
                'payments' => $payments,
            ];

            require dirname(__DIR__) . '/Views/history/_detail_modal_body.php';
        } catch (Throwable $e) {
            $this->renderError('会計詳細の取得中にエラーが発生しました。', $e);
        }
    }

    /**
     * レシートを表示する。
     *
     * 優先順位:
     * 1. bill_payment_idが指定された場合: その支払い単位で再発行
     * 2. bill_idが指定された場合: 従来どおり会計全体を表示
     */
    public function receipt(): void
    {
        $this->renderPrintDocument('receipt');
    }

    /**
     * 領収書・請求書を表示する。
     *
     * bill_payment_id指定時は支払い単位、bill_id指定時は会計全体。
     */
    public function invoice(): void
    {
        $this->renderPrintDocument('invoice');
    }

    /**
     * レシートまたは領収書の共通表示処理。
     */
    private function renderPrintDocument(string $documentType): void
    {
        $this->startSession();

        try {
            $pdo = $this->getConnection();
            [$role, $sessionStoreId] = $this->requireHistoryAccess();

            $billPaymentId = trim((string)($_GET['bill_payment_id'] ?? $_POST['bill_payment_id'] ?? ''));
            $billId = trim((string)($_GET['bill_id'] ?? $_POST['bill_id'] ?? ''));

            $selectedPayment = null;

            if ($billPaymentId !== '') {
                $selectedPayment = $this->findPaymentById($pdo, $billPaymentId);

                if ($selectedPayment === null) {
                    $this->abort(404, '指定された支払い情報が見つかりません。');
                }

                $billId = (string)($selectedPayment['bill_id'] ?? '');
            }

            if ($billId === '') {
                $this->abort(400, 'bill_id または bill_payment_id が指定されていません。');
            }

            $bill = $this->findRawBill($pdo, $billId);
            if ($bill === null) {
                $this->abort(404, '対象の会計が見つかりません。');
            }

            $this->assertStoreAccess(
                $role,
                $sessionStoreId,
                (string)($bill['store_id'] ?? '')
            );

            $details = $this->findRawBillItems($pdo, $billId);
            $allPayments = $this->findRawPaymentsByBillId($pdo, $billId);
            $customerIds = $this->findCustomerIdsByOrderBillId(
                $pdo,
                (string)($bill['order_bill_id'] ?? '')
            );

            // bill_payment_id指定時は、帳票上の支払い情報を対象の1件に限定する。
            $paymentsForPrint = $selectedPayment !== null
                ? [$selectedPayment]
                : $allPayments;

            $paymentAmount = $selectedPayment !== null
                ? $this->paymentAmount($selectedPayment)
                : (int)($bill['total_amount'] ?? 0);

            $data = [
                'bill' => $bill,
                'store' => [
                    'store_id'      => $bill['store_id'] ?? '',
                    'store_name'    => $bill['store_name'] ?? '',
                    'store_address' => $bill['store_address'] ?? '',
                    'store_phone'   => $bill['store_phone'] ?? '',
                ],
                'details' => $details,
                'payments' => $paymentsForPrint,
                'payment' => $selectedPayment,
                'summary' => [
                    'subtotal_amount'    => (int)($bill['subtotal_amount'] ?? 0),
                    'discount_amount'    => (int)($bill['discount_amount'] ?? 0),
                    'tax_amount'         => (int)($bill['tax_amount'] ?? 0),
                    'total_amount'       => (int)($bill['total_amount'] ?? 0),
                    'bill_total_amount'  => (int)($bill['total_amount'] ?? 0),
                    'payment_amount'     => $paymentAmount,
                ],
                'customer_ids' => $customerIds,
                'is_reissue' => $billPaymentId !== '',
                'bill_payment_id' => $billPaymentId,
                'return_url' => self::HISTORY_URL,
            ];

            if ($documentType === 'invoice') {
                require dirname(__DIR__) . '/Views/print/invoice.php';
                return;
            }

            require dirname(__DIR__) . '/Views/print/receipt.php';
        } catch (Throwable $e) {
            $label = $documentType === 'invoice' ? '領収書' : 'レシート';
            $this->renderError($label . 'の取得中にエラーが発生しました。', $e);
        }
    }

    /**
     * 履歴検索条件を組み立てる。
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function buildHistoryWhere(
        string $role,
        string $sessionStoreId,
        string $keyword,
        string $date
    ): array {
        $where = [];
        $params = [];

        if ($keyword !== '') {
            $where[] = '(
                b.bill_id LIKE :keyword
                OR b.store_id LIKE :keyword
                OR s.store_name LIKE :keyword
                OR EXISTS (
                    SELECT 1
                    FROM ORDER_HEADER oh2
                    WHERE oh2.order_bill_id = b.order_bill_id
                      AND oh2.customer_id LIKE :keyword
                )
                OR EXISTS (
                    SELECT 1
                    FROM BILL_DETAIL bd2
                    WHERE bd2.bill_id = b.bill_id
                      AND bd2.menu_name LIKE :keyword
                )
            )';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        if ($date !== '') {
            $where[] = 'DATE(b.bill_time) = :date';
            $params[':date'] = $date;
        }

        if ($role === 'STAFF') {
            $where[] = 'b.store_id = :session_store_id';
            $params[':session_store_id'] = $sessionStoreId;
        }

        return [
            $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
            $params,
        ];
    }

    /**
     * 表示用の会計全体情報を取得する。
     */
    private function findBill(PDO $pdo, string $billId): ?array
    {
        $bill = $this->findRawBill($pdo, $billId);
        if ($bill === null) {
            return null;
        }

        $orderBillId = (string)($bill['order_bill_id'] ?? '');
        $customerIds = $this->findCustomerIdsByOrderBillId($pdo, $orderBillId);
        $items = $this->findBillItems($pdo, $billId);
        $payments = $this->findPaymentsByBillId($pdo, $billId);
        $payMethods = array_values(array_unique(array_filter(array_map(
            static fn(array $payment): string => (string)($payment['payMethod'] ?? ''),
            $payments
        ))));

        return [
            'billId'            => (string)($bill['bill_id'] ?? ''),
            'storeId'           => (string)($bill['store_id'] ?? ''),
            'storeName'         => (string)($bill['store_name'] ?? ''),
            'storeAddress'      => (string)($bill['store_address'] ?? ''),
            'storePhone'        => (string)($bill['store_phone'] ?? ''),
            'customerIds'       => $customerIds,
            'customerIdText'    => $customerIds !== [] ? implode(', ', $customerIds) : '手入力',
            'customerIdPreview' => $this->makeCustomerPreview($customerIds),
            'paidAt'            => $this->formatDateTime($bill['bill_time'] ?? null),
            'datetime'          => $this->formatDateTime($bill['bill_time'] ?? null),
            'payMethods'        => $payMethods,
            'payMethod'         => count($payMethods) === 1 ? $payMethods[0] : '',
            'payLabel'          => $this->makePaymentSummaryLabel($payMethods),
            'paymentCount'      => count($payments),
            'subtotal'          => (int)($bill['subtotal_amount'] ?? 0),
            'tax'               => (int)($bill['tax_amount'] ?? 0),
            'discount'          => (int)($bill['discount_amount'] ?? 0),
            'total'             => (int)($bill['total_amount'] ?? 0),
            'items'             => $items,
            'payments'          => $payments,
        ];
    }

    /**
     * BILLを店舗情報付きで1件取得する。
     */
    private function findRawBill(PDO $pdo, string $billId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                b.*,
                COALESCE(s.store_name, b.store_id) AS store_name,
                s.store_address,
                s.store_phone
            FROM BILL b
            LEFT JOIN stores s
              ON s.store_id = b.store_id
            WHERE b.bill_id = :bill_id
            LIMIT 1
        ");
        $stmt->execute([':bill_id' => $billId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * BILL_DETAILの商品明細を帳票向けの生配列として取得する。
     */
    private function findRawBillItems(PDO $pdo, string $billId): array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM BILL_DETAIL
            WHERE bill_id = :bill_id
            ORDER BY bill_detail_id ASC
        ");
        $stmt->execute([':bill_id' => $billId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * BILL_DETAILの商品明細を画面表示用に整形する。
     */
    private function findBillItems(PDO $pdo, string $billId): array
    {
        $rows = $this->findRawBillItems($pdo, $billId);
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'billDetailId' => (string)($row['bill_detail_id'] ?? ''),
                'name'         => (string)($row['menu_name'] ?? ''),
                'menu_name'    => (string)($row['menu_name'] ?? ''),
                'qty'          => (int)($row['qty'] ?? 0),
                'unit_price'   => (int)($row['unit_price'] ?? 0),
                'amount'       => (int)($row['amount'] ?? 0),
                'tax_rate'     => (int)($row['tax_rate'] ?? 10),
            ];
        }

        return $items;
    }

    /**
     * BILL_PAYMENTをbill_idで取得する。
     *
     * カラム名が環境ごとに異なる可能性があるためSELECT *を使い、
     * PHP側で候補列名を吸収する。
     */
    private function findRawPaymentsByBillId(PDO $pdo, string $billId): array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM BILL_PAYMENT
            WHERE bill_id = :bill_id
            ORDER BY pay_time ASC, bill_payment_id ASC
        ");
        $stmt->execute([':bill_id' => $billId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * BILL_PAYMENTを画面表示用に整形する。
     */
    private function findPaymentsByBillId(PDO $pdo, string $billId): array
    {
        $rows = $this->findRawPaymentsByBillId($pdo, $billId);

        return array_map(
            fn(array $row): array => $this->normalizePayment($row),
            $rows
        );
    }

    /**
     * bill_payment_idから支払いを1件取得する。
     */
    private function findPaymentById(PDO $pdo, string $billPaymentId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM BILL_PAYMENT
            WHERE bill_payment_id = :bill_payment_id
            LIMIT 1
        ");
        $stmt->execute([':bill_payment_id' => $billPaymentId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * BILL_PAYMENTの1行を画面用に整形する。
     *
     * 支払額などの実カラム名が異なる場合にも対応できるよう、
     * よく使われる候補名を順番に確認する。
     */
    private function normalizePayment(array $row): array
    {
        $payMethod = (string)($row['pay_method'] ?? '');
        $payTime = $this->firstValue($row, ['pay_time', 'paid_at', 'payment_time']);

        return [
            'billPaymentId' => (string)($row['bill_payment_id'] ?? ''),
            'billId'        => (string)($row['bill_id'] ?? ''),
            'payMethod'     => $payMethod,
            'payLabel'      => $this->payLabel($payMethod),
            'payAmount'     => $this->paymentAmount($row),
            'receivedAmount'=> $this->firstInt($row, [
                'received_amount',
                'received_money',
                'deposit_amount',
                'tendered_amount',
            ]),
            'changeAmount'  => $this->firstInt($row, [
                'change_amount',
                'change_money',
            ]),
            'payTimeRaw'    => (string)$payTime,
            'payTime'       => $this->formatDateTime($payTime),
            'raw'           => $row,
        ];
    }

    /**
     * 支払い金額を候補列から取得する。
     */
    private function paymentAmount(array $payment): int
    {
        return $this->firstInt($payment, [
            'pay_amount',
            'payment_amount',
            'paid_amount',
            'amount',
        ]);
    }

    private function findCustomerIdsByOrderBillId(PDO $pdo, string $orderBillId): array
    {
        if ($orderBillId === '') {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT customer_id
            FROM ORDER_HEADER
            WHERE order_bill_id = :order_bill_id
            ORDER BY customer_id ASC
        ");
        $stmt->execute([':order_bill_id' => $orderBillId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $customerIds = [];

        foreach ($rows as $row) {
            $customerId = trim((string)($row['customer_id'] ?? ''));
            if ($customerId !== '') {
                $customerIds[] = $customerId;
            }
        }

        return $customerIds;
    }

    /**
     * セッションと権限を確認する。
     *
     * @return array{0:string,1:string}
     */
    private function requireHistoryAccess(): array
    {
        $role = strtoupper(trim((string)($_SESSION['role'] ?? '')));
        $sessionStoreId = trim((string)($_SESSION['store_id'] ?? ''));

        if (!in_array($role, ['MASTER', 'STAFF'], true)) {
            $this->abort(403, 'このページにはアクセスできません。');
        }

        if ($role === 'STAFF' && $sessionStoreId === '') {
            $this->abort(403, '所属店舗情報が設定されていません。');
        }

        return [$role, $sessionStoreId];
    }

    private function assertStoreAccess(
        string $role,
        string $sessionStoreId,
        string $targetStoreId
    ): void {
        if ($role === 'STAFF' && $targetStoreId !== $sessionStoreId) {
            $this->abort(403, '他店舗の会計情報は参照できません。');
        }
    }

    private function getConnection(): PDO
    {
        /** @var PDO $pdo */
        $pdo = require dirname(__DIR__) . '/Database/db.php';
        return $pdo;
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @param array<string,string> $params
     */
    private function bindStringParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $keys
     */
    private function firstInt(array $row, array $keys): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (int)$row[$key];
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $keys
     */
    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function splitPayMethods(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(string $method): string => trim($method),
            explode(',', $value)
        ))));
    }

    /**
     * @param list<string> $payMethods
     */
    private function makePaymentSummaryLabel(array $payMethods): string
    {
        if ($payMethods === []) {
            return '未登録';
        }

        $labels = array_values(array_unique(array_map(
            fn(string $method): string => $this->payLabel($method),
            $payMethods
        )));

        return implode('・', $labels);
    }

    private function payLabel(string $payMethod): string
    {
        return match (strtoupper(trim($payMethod))) {
            'CASH'             => '現金',
            'CARD',
            'CREDIT_CARD'      => 'カード',
            'ELECTRONIC_MONEY' => '電子マネー',
            'QR',
            'QR_PAYMENT'       => 'QR決済',
            ''                 => '未登録',
            default            => $payMethod,
        };
    }

    private function makeCustomerPreview(array $customerIds): string
    {
        $count = count($customerIds);

        if ($count === 0) {
            return '手入力';
        }

        if ($count <= 2) {
            return implode(', ', $customerIds);
        }

        return implode(', ', array_slice($customerIds, 0, 2))
            . ' ほか'
            . ($count - 2)
            . '名';
    }

    private function formatDateTime(mixed $value): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $timestamp = strtotime($text);
        return $timestamp === false ? $text : date('Y/m/d H:i', $timestamp);
    }

    private function abort(int $statusCode, string $message): never
    {
        http_response_code($statusCode);
        exit($message);
    }

    private function renderError(string $message, Throwable $e): void
    {
        http_response_code(500);

        // 開発環境では原因を確認できるようにする。
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<br>';
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<br><small>';
        echo htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        echo '：' . (int)$e->getLine() . '行目';
        echo '</small>';
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Lib\MosApiClient;
use App\Lib\MosOrdersApi;
use PDO;
use Throwable;

final class ClosingController
{
    private const BASE_URL = '/regi/public';

    public function show(): void
    {
        $this->requireStaffAccess();

        $title = 'レジ締め';

        $storeId = (string)($_SESSION['store_id'] ?? '');
        $operatorName = (string)($_SESSION['login_user_name'] ?? '');

        $salesTotal = 0;
        $paidCount = 0;
        $paymentRows = [
            ['label' => '現金', 'amount' => 0, 'count' => 0],
            ['label' => 'クレジットカード', 'amount' => 0, 'count' => 0],
            ['label' => '電子マネー', 'amount' => 0, 'count' => 0],
        ];
        $arTotal = 0;
        $arCount = 0;
        $arRows = [];
        $registerStartAmount = 0;
        $expectedCashSales = 0;
        $expectedRegisterAmount = 0;
        $dateLabel = '';

        try {
            $pdo = $this->getPdo();
            $mosApi = $this->createMosOrdersApi();

            $now = date('Y-m-d H:i:s');
            $previousClose = $this->findPreviousClose($pdo, $storeId);

            if ($previousClose !== null) {
                $targetFrom = (string)$previousClose['target_to'];
                $registerStartAmount = (int)$previousClose['actual_cash'];
                $fromLabel = date('Y/m/d H:i', strtotime((string)$previousClose['created_at']));
            } else {
                $targetFrom = date('Y-m-d 00:00:00');
                $registerStartAmount = 0;
                $fromLabel = date('Y/m/d H:i', strtotime($targetFrom));
            }

            $targetTo = $now;
            $toLabel = date('Y/m/d H:i', strtotime($targetTo));
            $dateLabel = $fromLabel . ' ～ ' . $toLabel;

            /*
             * レジ締め対象売上を取得する。
             * billとbill_paymentを別々に集計し、
             * 分割決済時にbill側の金額が重複することを防ぐ。
             */
            $sales = $this->aggregatePaidSales(
                $pdo,
                $storeId,
                $targetFrom,
                $targetTo
            );

            $salesTotal = (int)$sales['total_amount_sum'];
            $paidCount = (int)$sales['bill_count'];

            $paymentRows = [
                [
                    'label' => '現金',
                    'amount' => (int)$sales['cash_sum'],
                    'count'  => (int)$sales['cash_count'],
                ],
                [
                    'label' => 'クレジットカード',
                    'amount' => (int)$sales['card_sum'],
                    'count'  => (int)$sales['card_count'],
                ],
                [
                    'label' => '電子マネー',
                    'amount' => (int)$sales['electronic_money_sum'],
                    'count'  => (int)$sales['electronic_money_count'],
                ],
            ];

            // 受付中注文を取得する。現時点では表示には使用しない。
            $this->fetchOrdersByStatus(
                $mosApi,
                $storeId,
                MosOrdersApi::BILL_STATUS_OPEN,
                $targetFrom,
                $targetTo
            );

            // 未収金注文を取得する。
            $receivableOrders = $this->fetchOrdersByStatus(
                $mosApi,
                $storeId,
                MosOrdersApi::BILL_STATUS_RECEIVABLE,
                null,
                null
            );

            $arRows = $this->buildArRows($receivableOrders);
            $arCount = count($arRows);
            $arTotal = array_sum(array_column($arRows, 'total_amount'));

            $expectedCashSales = (int)$sales['cash_sum'];
            $expectedRegisterAmount = $registerStartAmount + $expectedCashSales;
        } catch (Throwable $e) {
            $weekMap = ['日', '月', '火', '水', '木', '金', '土'];
            $dateLabel = date('Y/m/d') . '(' . $weekMap[(int)date('w')] . ')';

            $_SESSION['flash_error'] =
                'レジ締データの取得に失敗しました: ' . $e->getMessage();
        }

        require dirname(__DIR__) . '/Views/close/index.php';
    }

    public function store(): void
    {
        $this->requireStaffAccess();

        $payloadJson = (string)($_POST['payload'] ?? '');
        if ($payloadJson === '') {
            $_SESSION['flash_error'] =
                '保存データがありません。入力内容を確認してください。';
            header('Location: ' . self::BASE_URL . '/close');
            exit;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            $_SESSION['flash_error'] = '保存データの形式が不正です。';
            header('Location: ' . self::BASE_URL . '/close');
            exit;
        }

        $operatorName = trim((string)($payload['operatorName'] ?? ''));
        $registerStartAmount = max(
            0,
            (int)($payload['registerStartAmount'] ?? 0)
        );
        $counts = $payload['counts'] ?? [];
        $storeId = trim((string)($_SESSION['store_id'] ?? ''));

        if ($operatorName === '') {
            $_SESSION['flash_error'] = 'レジ締担当を入力してください。';
            header('Location: ' . self::BASE_URL . '/close');
            exit;
        }

        if (!is_array($counts)) {
            $_SESSION['flash_error'] = '金種データの形式が不正です。';
            header('Location: ' . self::BASE_URL . '/close');
            exit;
        }

        $denoms = [
            '10000' => 10000,
            '5000'  => 5000,
            '1000'  => 1000,
            '500'   => 500,
            '100'   => 100,
            '50'    => 50,
            '10'    => 10,
            '5'     => 5,
            '1'     => 1,
        ];

        $actualCash = 0;
        foreach ($denoms as $key => $yen) {
            $count = max(0, (int)($counts[$key] ?? 0));
            $actualCash += $count * $yen;
        }

        try {
            $pdo = $this->getPdo();
            $mosApi = $this->createMosOrdersApi();

            $pdo->beginTransaction();

            $targetTo = date('Y-m-d H:i:s');
            $previousClose = $this->findPreviousClose($pdo, $storeId);

            $targetFrom = $previousClose !== null
                ? (string)$previousClose['target_to']
                : date('Y-m-d 00:00:00');

            // 保存直前にDB売上を再取得する。
            $sales = $this->aggregatePaidSales(
                $pdo,
                $storeId,
                $targetFrom,
                $targetTo
            );

            // 保存直前にMOSの受付中注文を再取得する。
            $openOrdersBeforeUpdate = $this->fetchOrdersByStatus(
                $mosApi,
                $storeId,
                MosOrdersApi::BILL_STATUS_OPEN,
                $targetFrom,
                $targetTo
            );

            $openOrderCount = count($openOrdersBeforeUpdate);
            $openOrderAmount =
                $this->sumOrderTotalAmounts($openOrdersBeforeUpdate);

            $expectedCashSales = (int)$sales['cash_sum'];
            $expectedCash = $registerStartAmount + $expectedCashSales;
            $cashDiff = $actualCash - $expectedCash;

            // 受付中注文を未収金へ変更する。
            foreach ($openOrdersBeforeUpdate as $order) {
                $customerId = (string)($order['customerId'] ?? '');
                $hash = isset($order['hash'])
                    ? (string)$order['hash']
                    : null;

                if ($customerId === '') {
                    throw new \RuntimeException(
                        'MOS注文データにcustomerIdがありません。'
                    );
                }

                $mosApi->updateStatus(
                    $customerId,
                    $hash !== '' ? $hash : null,
                    MosOrdersApi::BILL_STATUS_RECEIVABLE
                );
            }

            // 更新後に受付中注文が残っていないことを確認する。
            $openOrdersAfterUpdate = $this->fetchOrdersByStatus(
                $mosApi,
                $storeId,
                MosOrdersApi::BILL_STATUS_OPEN,
                $targetFrom,
                $targetTo
            );

            if (count($openOrdersAfterUpdate) > 0) {
                throw new \RuntimeException(
                    '受付中注文の未収金更新が完了していません。'
                );
            }

            // レジ締め結果を保存する。
            $this->insertCloseHeader(
                $pdo,
                [
                    'store_id'              => $storeId,
                    'target_from'           => $targetFrom,
                    'target_to'             => $targetTo,
                    'executed_at'           => $targetTo,
                    'executed_by_name'      => $operatorName,
                    'bill_count'            => (int)$sales['bill_count'],
                    'subtotal_sum'          => (int)$sales['subtotal_sum'],
                    'discount_sum'          => (int)$sales['discount_sum'],
                    'tax_amount_sum'        => (int)$sales['tax_amount_sum'],
                    'total_amount_sum'      => (int)$sales['total_amount_sum'],
                    'cash_sum'              => (int)$sales['cash_sum'],
                    'card_sum'              => (int)$sales['card_sum'],
                    'electronic_money_sum'  =>
                        (int)$sales['electronic_money_sum'],
                    'register_start_amount' => $registerStartAmount,
                    'expected_cash'         => $expectedCash,
                    'actual_cash'           => $actualCash,
                    'cash_diff'             => $cashDiff,
                    'open_order_count'      => $openOrderCount,
                    'open_order_amount'     => $openOrderAmount,
                ]
            );

            /*
             * レジ締め日時が属する月を元データから再集計する。
             * 既存値への加算ではなくUPSERTによる上書きなので、
             * 再締めを行っても二重計上されない。
             */
            $this->updateMonthlySalesSummary(
                $pdo,
                $storeId,
                $targetTo
            );

            /*
             * 対象年の年次サマリを、
             * 月次サマリ最大12件から再集計する。
             */
            $this->updateYearlySalesSummary(
                $pdo,
                $storeId,
                $targetTo
            );

            $pdo->commit();

            $_SESSION['flash_success'] =
                'レジ締めを保存し、月次・年次売上サマリを更新しました。';

            header('Location: ' . self::BASE_URL . '/home');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['flash_error'] =
                'レジ締め保存に失敗しました: ' . $e->getMessage();
        }

        header('Location: ' . self::BASE_URL . '/close');
        exit;
    }

    private function insertCloseHeader(PDO $pdo, array $data): void
    {
        $sql = "
            INSERT INTO close_header (
                store_id,
                target_from,
                target_to,
                executed_at,
                executed_by_name,
                bill_count,
                subtotal_sum,
                discount_sum,
                tax_amount_sum,
                total_amount_sum,
                cash_sum,
                card_sum,
                electronic_money_sum,
                register_start_amount,
                expected_cash,
                actual_cash,
                cash_diff,
                open_order_count,
                open_order_amount
            ) VALUES (
                :store_id,
                :target_from,
                :target_to,
                :executed_at,
                :executed_by_name,
                :bill_count,
                :subtotal_sum,
                :discount_sum,
                :tax_amount_sum,
                :total_amount_sum,
                :cash_sum,
                :card_sum,
                :electronic_money_sum,
                :register_start_amount,
                :expected_cash,
                :actual_cash,
                :cash_diff,
                :open_order_count,
                :open_order_amount
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':store_id'              => $data['store_id'],
            ':target_from'           => $data['target_from'],
            ':target_to'             => $data['target_to'],
            ':executed_at'           => $data['executed_at'],
            ':executed_by_name'      => $data['executed_by_name'],
            ':bill_count'            => $data['bill_count'],
            ':subtotal_sum'          => $data['subtotal_sum'],
            ':discount_sum'          => $data['discount_sum'],
            ':tax_amount_sum'        => $data['tax_amount_sum'],
            ':total_amount_sum'      => $data['total_amount_sum'],
            ':cash_sum'              => $data['cash_sum'],
            ':card_sum'              => $data['card_sum'],
            ':electronic_money_sum'  => $data['electronic_money_sum'],
            ':register_start_amount' => $data['register_start_amount'],
            ':expected_cash'         => $data['expected_cash'],
            ':actual_cash'           => $data['actual_cash'],
            ':cash_diff'             => $data['cash_diff'],
            ':open_order_count'      => $data['open_order_count'],
            ':open_order_amount'     => $data['open_order_amount'],
        ]);
    }

    /**
     * レジ締め対象期間の売上を集計する。
     *
     * 前回締め時刻は含めず、今回締め時刻は含める。
     */
    private function aggregatePaidSales(
        PDO $pdo,
        string $storeId,
        string $from,
        string $to
    ): array {
        $billSql = "
            SELECT
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.subtotal_amount), 0) AS subtotal_sum,
                COALESCE(SUM(b.discount_amount), 0) AS discount_sum,
                COALESCE(SUM(b.tax_amount), 0) AS tax_amount_sum,
                COALESCE(SUM(b.total_amount), 0) AS total_amount_sum
            FROM bill b
            WHERE b.store_id = :store_id
              AND b.bill_time > :from_time
              AND b.bill_time <= :to_time
        ";

        $billStmt = $pdo->prepare($billSql);
        $billStmt->execute([
            ':store_id' => $storeId,
            ':from_time' => $from,
            ':to_time' => $to,
        ]);

        $billSummary = $billStmt->fetch() ?: [];

        $paymentSql = "
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CASH'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS cash_sum,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CARD'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS card_sum,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'ELECTRONIC_MONEY'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS electronic_money_sum,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CASH'
                        THEN 1
                        ELSE 0
                    END
                ), 0) AS cash_count,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CARD'
                        THEN 1
                        ELSE 0
                    END
                ), 0) AS card_count,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'ELECTRONIC_MONEY'
                        THEN 1
                        ELSE 0
                    END
                ), 0) AS electronic_money_count
            FROM bill_payment bp
            INNER JOIN bill b
                ON b.bill_id = bp.bill_id
            WHERE b.store_id = :store_id
              AND b.bill_time > :from_time
              AND b.bill_time <= :to_time
        ";

        $paymentStmt = $pdo->prepare($paymentSql);
        $paymentStmt->execute([
            ':store_id' => $storeId,
            ':from_time' => $from,
            ':to_time' => $to,
        ]);

        $paymentSummary = $paymentStmt->fetch() ?: [];

        return [
            'bill_count' => (int)($billSummary['bill_count'] ?? 0),
            'subtotal_sum' => (int)($billSummary['subtotal_sum'] ?? 0),
            'discount_sum' => (int)($billSummary['discount_sum'] ?? 0),
            'tax_amount_sum' =>
                (int)($billSummary['tax_amount_sum'] ?? 0),
            'total_amount_sum' =>
                (int)($billSummary['total_amount_sum'] ?? 0),
            'cash_sum' => (int)($paymentSummary['cash_sum'] ?? 0),
            'card_sum' => (int)($paymentSummary['card_sum'] ?? 0),
            'electronic_money_sum' =>
                (int)($paymentSummary['electronic_money_sum'] ?? 0),
            'cash_count' => (int)($paymentSummary['cash_count'] ?? 0),
            'card_count' => (int)($paymentSummary['card_count'] ?? 0),
            'electronic_money_count' =>
                (int)($paymentSummary['electronic_money_count'] ?? 0),
        ];
    }

    /**
     * 月次サマリ作成用に、対象月全体の売上を集計する。
     *
     * 月初を含み、翌月月初を含まない範囲で取得するため、
     * bill_timeのインデックスを利用しやすい。
     */
    private function aggregateMonthlySales(
        PDO $pdo,
        string $storeId,
        string $monthStart,
        string $nextMonthStart
    ): array {
        $billSql = "
            SELECT
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.subtotal_amount), 0) AS subtotal_sum,
                COALESCE(SUM(b.discount_amount), 0) AS discount_sum,
                COALESCE(SUM(b.tax_amount), 0) AS tax_amount_sum,
                COALESCE(SUM(b.total_amount), 0) AS total_amount_sum
            FROM bill b
            WHERE b.store_id = :store_id
              AND b.bill_time >= :month_start
              AND b.bill_time < :next_month_start
        ";

        $billStmt = $pdo->prepare($billSql);
        $billStmt->execute([
            ':store_id' => $storeId,
            ':month_start' => $monthStart,
            ':next_month_start' => $nextMonthStart,
        ]);

        $billSummary = $billStmt->fetch() ?: [];

        $paymentSql = "
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CASH'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS cash_sum,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'CARD'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS card_sum,

                COALESCE(SUM(
                    CASE
                        WHEN bp.pay_method = 'ELECTRONIC_MONEY'
                        THEN bp.pay_amount
                        ELSE 0
                    END
                ), 0) AS electronic_money_sum
            FROM bill_payment bp
            INNER JOIN bill b
                ON b.bill_id = bp.bill_id
            WHERE b.store_id = :store_id
              AND b.bill_time >= :month_start
              AND b.bill_time < :next_month_start
        ";

        $paymentStmt = $pdo->prepare($paymentSql);
        $paymentStmt->execute([
            ':store_id' => $storeId,
            ':month_start' => $monthStart,
            ':next_month_start' => $nextMonthStart,
        ]);

        $paymentSummary = $paymentStmt->fetch() ?: [];

        return [
            'bill_count' => (int)($billSummary['bill_count'] ?? 0),
            'subtotal_sum' => (int)($billSummary['subtotal_sum'] ?? 0),
            'discount_sum' => (int)($billSummary['discount_sum'] ?? 0),
            'tax_amount_sum' =>
                (int)($billSummary['tax_amount_sum'] ?? 0),
            'total_amount_sum' =>
                (int)($billSummary['total_amount_sum'] ?? 0),
            'cash_sum' => (int)($paymentSummary['cash_sum'] ?? 0),
            'card_sum' => (int)($paymentSummary['card_sum'] ?? 0),
            'electronic_money_sum' =>
                (int)($paymentSummary['electronic_money_sum'] ?? 0),
        ];
    }

    private function updateMonthlySalesSummary(
        PDO $pdo,
        string $storeId,
        string $targetDateTime
    ): void {
        $timestamp = strtotime($targetDateTime);
        if ($timestamp === false) {
            throw new \RuntimeException(
                '月次サマリの対象日時が不正です。'
            );
        }

        $summaryYear = (int)date('Y', $timestamp);
        $summaryMonth = (int)date('n', $timestamp);

        $monthStart = date('Y-m-01 00:00:00', $timestamp);
        $nextMonthStart = date(
            'Y-m-01 00:00:00',
            strtotime('+1 month', strtotime($monthStart))
        );

        $summary = $this->aggregateMonthlySales(
            $pdo,
            $storeId,
            $monthStart,
            $nextMonthStart
        );

        $sql = "
            INSERT INTO monthly_sales_summary (
                store_id,
                summary_year,
                summary_month,
                bill_count,
                subtotal_sum,
                discount_sum,
                tax_amount_sum,
                total_amount_sum,
                cash_sum,
                card_sum,
                electronic_money_sum,
                summarized_at
            ) VALUES (
                :store_id,
                :summary_year,
                :summary_month,
                :bill_count,
                :subtotal_sum,
                :discount_sum,
                :tax_amount_sum,
                :total_amount_sum,
                :cash_sum,
                :card_sum,
                :electronic_money_sum,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                bill_count = VALUES(bill_count),
                subtotal_sum = VALUES(subtotal_sum),
                discount_sum = VALUES(discount_sum),
                tax_amount_sum = VALUES(tax_amount_sum),
                total_amount_sum = VALUES(total_amount_sum),
                cash_sum = VALUES(cash_sum),
                card_sum = VALUES(card_sum),
                electronic_money_sum =
                    VALUES(electronic_money_sum),
                summarized_at = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':store_id' => $storeId,
            ':summary_year' => $summaryYear,
            ':summary_month' => $summaryMonth,
            ':bill_count' => $summary['bill_count'],
            ':subtotal_sum' => $summary['subtotal_sum'],
            ':discount_sum' => $summary['discount_sum'],
            ':tax_amount_sum' => $summary['tax_amount_sum'],
            ':total_amount_sum' => $summary['total_amount_sum'],
            ':cash_sum' => $summary['cash_sum'],
            ':card_sum' => $summary['card_sum'],
            ':electronic_money_sum' =>
                $summary['electronic_money_sum'],
        ]);
    }

    private function updateYearlySalesSummary(
        PDO $pdo,
        string $storeId,
        string $targetDateTime
    ): void {
        $timestamp = strtotime($targetDateTime);
        if ($timestamp === false) {
            throw new \RuntimeException(
                '年次サマリの対象日時が不正です。'
            );
        }

        $summaryYear = (int)date('Y', $timestamp);

        $sql = "
            INSERT INTO yearly_sales_summary (
                store_id,
                summary_year,
                bill_count,
                subtotal_sum,
                discount_sum,
                tax_amount_sum,
                total_amount_sum,
                cash_sum,
                card_sum,
                electronic_money_sum,
                summarized_at
            )
            SELECT
                store_id,
                summary_year,
                COALESCE(SUM(bill_count), 0),
                COALESCE(SUM(subtotal_sum), 0),
                COALESCE(SUM(discount_sum), 0),
                COALESCE(SUM(tax_amount_sum), 0),
                COALESCE(SUM(total_amount_sum), 0),
                COALESCE(SUM(cash_sum), 0),
                COALESCE(SUM(card_sum), 0),
                COALESCE(SUM(electronic_money_sum), 0),
                NOW()
            FROM monthly_sales_summary
            WHERE store_id = :store_id
              AND summary_year = :summary_year
            GROUP BY store_id, summary_year
            ON DUPLICATE KEY UPDATE
                bill_count = VALUES(bill_count),
                subtotal_sum = VALUES(subtotal_sum),
                discount_sum = VALUES(discount_sum),
                tax_amount_sum = VALUES(tax_amount_sum),
                total_amount_sum = VALUES(total_amount_sum),
                cash_sum = VALUES(cash_sum),
                card_sum = VALUES(card_sum),
                electronic_money_sum =
                    VALUES(electronic_money_sum),
                summarized_at = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':store_id' => $storeId,
            ':summary_year' => $summaryYear,
        ]);
    }

    private function createMosOrdersApi(): MosOrdersApi
    {
        /** @var array{
         *     base_url?: string,
         *     timeout_seconds?: int
         * } $config
         */
        $config = require dirname(__DIR__) . '/Config/mos.php';

        $baseUrl = trim((string)($config['base_url'] ?? ''));
        $timeoutSeconds = (int)($config['timeout_seconds'] ?? 10);

        if ($baseUrl === '') {
            throw new \RuntimeException(
                'MOS APIの接続先が設定されていません。'
            );
        }

        if ($timeoutSeconds <= 0) {
            throw new \RuntimeException(
                'MOS APIのタイムアウト設定が不正です。'
            );
        }

        $client = new MosApiClient(
            $baseUrl,
            $timeoutSeconds
        );

        return new MosOrdersApi($client);
    }

    private function getPdo(): PDO
    {
        $host = '127.0.0.1';
        $db = 'regi_system';
        $user = 'root';
        $pass = '';

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function findPreviousClose(
        PDO $pdo,
        string $storeId
    ): ?array {
        $sql = "
            SELECT
                close_id,
                target_to,
                actual_cash,
                created_at
            FROM close_header
            WHERE store_id = :store_id
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':store_id' => $storeId]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchOrdersByStatus(
        MosOrdersApi $mosApi,
        string $storeId,
        int $billStatus,
        ?string $from,
        ?string $to
    ): array {
        $fromIso = $from !== null
            ? date('Y-m-d\TH:i:s', strtotime($from))
            : null;

        $toIso = $to !== null
            ? date('Y-m-d\TH:i:s', strtotime($to))
            : null;

        $orders = $mosApi->getOrders(
            null,
            $billStatus,
            $fromIso,
            $toIso
        );

        $filtered = [];

        foreach ($orders as $order) {
            $orderStoreId = (string)($order['storeId'] ?? '');

            if ($orderStoreId === $storeId) {
                $filtered[] = $order;
            }
        }

        return $filtered;
    }

    private function sumOrderTotalAmounts(array $orders): int
    {
        $sum = 0;

        foreach ($orders as $order) {
            $items = $order['items'] ?? [];

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (isset($item['amount'])) {
                    $sum += (int)$item['amount'];
                    continue;
                }

                $qty = (int)(
                    $item['qty']
                    ?? $item['orderQty']
                    ?? 0
                );

                $unitPrice = (int)($item['unitPrice'] ?? 0);
                $sum += $qty * $unitPrice;
            }
        }

        return $sum;
    }

    private function buildArRows(array $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            $customerId = (string)($order['customerId'] ?? '');
            $entryTime = (string)($order['entryTime'] ?? '');
            $totalAmount = 0;

            $items = $order['items'] ?? [];

            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    if (isset($item['amount'])) {
                        $totalAmount += (int)$item['amount'];
                    } else {
                        $qty = (int)(
                            $item['qty']
                            ?? $item['orderQty']
                            ?? 0
                        );

                        $unitPrice =
                            (int)($item['unitPrice'] ?? 0);

                        $totalAmount += $qty * $unitPrice;
                    }
                }
            }

            $rows[] = [
                'customer_id' => $customerId,
                'entry_time' => $entryTime !== ''
                    ? date('Y/m/d H:i', strtotime($entryTime))
                    : '',
                'total_amount' => $totalAmount,
            ];
        }

        return $rows;
    }

    private function requireStaffAccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $role = (string)($_SESSION['role'] ?? '');
        $storeId = trim((string)($_SESSION['store_id'] ?? ''));

        if ($role !== 'STAFF') {
            http_response_code(403);
            exit('このページにはアクセスできません。');
        }

        if ($storeId === '') {
            http_response_code(403);
            exit('所属店舗情報が設定されていません。');
        }
    }
}

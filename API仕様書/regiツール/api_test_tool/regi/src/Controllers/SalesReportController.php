<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class SalesReportController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = require dirname(__DIR__, 2) . '/src/Database/db.php';
    }

    public function show(): void
    {
        $this->requireReportAccess();

        $title = '売上レポート';

        $stores = $this->fetchStoresForSelect();

        array_unshift($stores, [
            'id'   => 'all',
            'name' => '全店舗',
        ]);

        $storeId = (string)($_GET['store'] ?? 'all');
        $mode    = (string)($_GET['mode'] ?? 'daily');
        $date    = (string)($_GET['date'] ?? date('Y-m-d'));

        require dirname(__DIR__) . '/Views/sales/report.php';
    }

    public function data(): void
    {
        $this->requireReportAccess();

        header('Content-Type: application/json; charset=UTF-8');

        try {
            $storeId    = (string)($_GET['store'] ?? 'all');
            $mode       = (string)($_GET['mode'] ?? 'daily');
            $date       = (string)($_GET['date'] ?? date('Y-m-d'));
            $weekOffset = max(0, min(6, (int)($_GET['week_offset'] ?? 0)));

            $payload = $this->buildReport($storeId, $mode, $date, $weekOffset);

            echo json_encode([
                'ok' => true,
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => '売上レポートの取得に失敗しました。',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function export(): void
    {
        $this->requireReportAccess();

        try {
            $storeId    = (string)($_GET['store'] ?? 'all');
            $mode       = (string)($_GET['mode'] ?? 'daily');
            $date       = (string)($_GET['date'] ?? date('Y-m-d'));
            $weekOffset = max(0, min(6, (int)($_GET['week_offset'] ?? 0)));

            $payload = $this->buildReport($storeId, $mode, $date, $weekOffset);

            $filename = 'sales_report_' . $mode . '_' . $date . '_' . $storeId . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');
            fputcsv($out, ['区分', '売上金額']);

            foreach ($payload['rows'] as $r) {
                fputcsv($out, [$r['label'], (string)$r['sales']]);
            }

            fclose($out);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'CSV出力に失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    private function buildReport(string $storeId, string $mode, string $date, int $weekOffset): array
    {
        $mode = $this->normalizeMode($mode);

        return match ($mode) {
            'daily'   => $this->reportDaily($storeId, $date),
            'weekly'  => $this->reportWeekly($storeId, $date, $weekOffset),
            'monthly' => $this->reportMonthly($storeId, $date),
            'yearly'  => $this->reportYearly($storeId, $date),
            default   => $this->reportDaily($storeId, $date),
        };
    }

    private function normalizeMode(string $mode): string
    {
        $allowed = ['daily', 'weekly', 'monthly', 'yearly'];
        return in_array($mode, $allowed, true) ? $mode : 'daily';
    }

    /**
     * 日次：営業日 5:00 ～ 翌 5:00（表示は 05:00 ～ 29:00）
     */
    private function reportDaily(string $storeId, string $date): array
    {
        $base = new DateTimeImmutable($date);
        $start = $base->setTime(5, 0, 0);
        $end   = $start->modify('+24 hours');

        $labels = [];
        $salesMap = [];
        $sum = 0;

        // 05:00 ～ 28:00 を24本
        for ($i = 0; $i < 24; $i++) {
            $businessHour = 5 + $i;
            $label = sprintf('%02d:00', $businessHour);
            $labels[] = $label;
            $salesMap[$label] = 0;
        }

        $sql = "
            SELECT
                bill_time,
                total_amount
            FROM bill
            WHERE bill_time >= :start_dt
              AND bill_time < :end_dt
        ";

        $params = [
            ':start_dt' => $start->format('Y-m-d H:i:s'),
            ':end_dt'   => $end->format('Y-m-d H:i:s'),
        ];

        if ($storeId !== 'all') {
            $sql .= " AND store_id = :store_id ";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dt = new DateTimeImmutable((string)$row['bill_time']);
            $hour = (int)$dt->format('G');
            $dayOffset = (int)$start->diff($dt)->format('%a');

            if ($dayOffset === 0) {
                $businessHour = $hour;
            } else {
                $businessHour = $hour + 24;
            }

            $label = sprintf('%02d:00', $businessHour);
            if (array_key_exists($label, $salesMap)) {
                $salesMap[$label] += (int)$row['total_amount'];
            }
        }

        $values = [];
        $rows = [];

        foreach ($labels as $label) {
            $sales = $salesMap[$label];
            $values[] = $sales;
            $rows[] = [
                'label' => $label,
                'sales' => $sales,
            ];
            $sum += $sales;
        }

        return [
            'mode' => 'daily',
            'modeLabel' => '日次（5:00～29:00）',
            'storeId' => $storeId,
            'date' => $date,
            'totalSales' => $sum,
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 週次：曜日並びをずらせる
     */
    private function reportWeekly(string $storeId, string $date, int $weekOffset): array
    {
        $base = new DateTimeImmutable($date);
        $jpLabels = ['日', '月', '火', '水', '木', '金', '土'];

        // 0〜6 の範囲に制限
        $weekOffset = max(0, min(6, $weekOffset));

        // デフォルトは「基準日〜6日後」
        // offset が増えるほど「開始日を過去へずらす」
        $windowStart = $base->modify("-{$weekOffset} day");
        $windowEnd   = $windowStart->modify('+6 day');

        $dateMap = [];
        for ($i = 0; $i < 7; $i++) {
            $dateKey = $windowStart->modify("+{$i} day")->format('Y-m-d');
            $dateMap[$dateKey] = 0;
        }

        $sql = "
            SELECT
                DATE(bill_time) AS sales_date,
                SUM(total_amount) AS sales
            FROM bill
            WHERE DATE(bill_time) BETWEEN :start_date AND :end_date
        ";

        $params = [
            ':start_date' => $windowStart->format('Y-m-d'),
            ':end_date'   => $windowEnd->format('Y-m-d'),
        ];

        if ($storeId !== 'all') {
            $sql .= " AND store_id = :store_id ";
            $params[':store_id'] = $storeId;
        }

        $sql .= "
            GROUP BY DATE(bill_time)
            ORDER BY DATE(bill_time)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['sales_date'];
            if (array_key_exists($key, $dateMap)) {
                $dateMap[$key] = (int)$row['sales'];
            }
        }

        $labels = [];
        $values = [];
        $rows = [];
        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $dateObj = $windowStart->modify("+{$i} day");
            $dateKey = $dateObj->format('Y-m-d');
            $weekLabel = $jpLabels[(int)$dateObj->format('w')];
            $mmdd = $dateObj->format('m-d');
            $sales = $dateMap[$dateKey] ?? 0;

            $labels[] = $weekLabel;
            $values[] = $sales;
            $rows[] = [
                'label' => $weekLabel . ' (' . $mmdd . ')',
                'sales' => $sales,
            ];
            $sum += $sales;
        }

        return [
            'mode' => 'weekly',
            'modeLabel' => '週次（日別）',
            'storeId' => $storeId,
            'date' => $date,
            'weekOffset' => $weekOffset,
            'canShiftPrev' => $weekOffset < 6,
            'canShiftNext' => $weekOffset > 0,
            'totalSales' => $sum,
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 月次：曜日付き表示
     */
    private function reportMonthly(string $storeId, string $date): array
    {
        $base = new DateTimeImmutable($date);
        $start = $base->modify('first day of this month');
        $end   = $base->modify('last day of this month');
        $jpWeek = ['日', '月', '火', '水', '木', '金', '土'];

        $dayCount = (int)$end->format('j');
        $dateMap = [];

        for ($d = 1; $d <= $dayCount; $d++) {
            $dateKey = $start->setDate(
                (int)$start->format('Y'),
                (int)$start->format('m'),
                $d
            )->format('Y-m-d');
            $dateMap[$dateKey] = 0;
        }

        $sql = "
            SELECT
                DATE(bill_time) AS sales_date,
                SUM(total_amount) AS sales
            FROM bill
            WHERE DATE(bill_time) BETWEEN :start_date AND :end_date
        ";

        $params = [
            ':start_date' => $start->format('Y-m-d'),
            ':end_date'   => $end->format('Y-m-d'),
        ];

        if ($storeId !== 'all') {
            $sql .= " AND store_id = :store_id ";
            $params[':store_id'] = $storeId;
        }

        $sql .= "
            GROUP BY DATE(bill_time)
            ORDER BY DATE(bill_time)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dateMap[(string)$row['sales_date']] = (int)$row['sales'];
        }

        $labels = [];
        $values = [];
        $rows = [];
        $sum = 0;

        for ($d = 1; $d <= $dayCount; $d++) {
            $dateObj = $start->setDate(
                (int)$start->format('Y'),
                (int)$start->format('m'),
                $d
            );
            $dateKey = $dateObj->format('Y-m-d');
            $weekLabel = $jpWeek[(int)$dateObj->format('w')];

            $sales = $dateMap[$dateKey];
            $label = $d . '日(' . $weekLabel . ')';

            $labels[] = $label;
            $values[] = $sales;
            $rows[] = [
                'label' => $label,
                'sales' => $sales,
            ];
            $sum += $sales;
        }

        return [
            'mode' => 'monthly',
            'modeLabel' => '月次（日別）',
            'storeId' => $storeId,
            'date' => $date,
            'totalSales' => $sum,
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'rows' => $rows,
        ];
    }

    private function reportYearly(string $storeId, string $date): array
    {
        $year = (new DateTimeImmutable($date))->format('Y');

        $monthMap = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthMap[sprintf('%04d-%02d', (int)$year, $m)] = 0;
        }

        $sql = "
            SELECT
                DATE_FORMAT(bill_time, '%Y-%m') AS ym,
                SUM(total_amount) AS sales
            FROM bill
            WHERE YEAR(bill_time) = :target_year
        ";

        $params = [
            ':target_year' => $year,
        ];

        if ($storeId !== 'all') {
            $sql .= " AND store_id = :store_id ";
            $params[':store_id'] = $storeId;
        }

        $sql .= "
            GROUP BY DATE_FORMAT(bill_time, '%Y-%m')
            ORDER BY DATE_FORMAT(bill_time, '%Y-%m')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthMap[(string)$row['ym']] = (int)$row['sales'];
        }

        $labels = [];
        $values = [];
        $rows = [];
        $sum = 0;

        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', (int)$year, $m);
            $label = $m . '月';
            $sales = $monthMap[$key];

            $labels[] = $label;
            $values[] = $sales;
            $rows[] = [
                'label' => $label,
                'sales' => $sales,
            ];
            $sum += $sales;
        }

        return [
            'mode' => 'yearly',
            'modeLabel' => '年次（月別）',
            'storeId' => $storeId,
            'date' => $date,
            'totalSales' => $sum,
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'rows' => $rows,
        ];
    }

    private function fetchStoresForSelect(): array
    {
        $sql = "
            SELECT
                store_id,
                store_name
            FROM stores
            WHERE is_active = 1
            ORDER BY store_id
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stores = [];
        foreach ($rows as $row) {
            $stores[] = [
                'id'   => (string)$row['store_id'],
                'name' => (string)$row['store_name'],
            ];
        }

        return $stores;
    }

    private function requireReportAccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $role = (string)($_SESSION['role'] ?? '');

        if (!in_array($role, ['MASTER', 'STAFF'], true)) {
            http_response_code(403);
            exit('このページにはアクセスできません。');
        }
    }
}
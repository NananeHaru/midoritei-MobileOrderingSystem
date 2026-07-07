public function findMonthlySummary(
    int $year,
    int $month,
    string $storeId
): ?array {
    // monthly_sales_summaryを検索
}

public function findYearlySummary(
    int $year,
    string $storeId
): ?array {
    // yearly_sales_summaryを検索
}

public function findMonthlyTrend(
    int $year,
    string $storeId
): array {
    // 月次サマリから1年分の月別推移を取得
}
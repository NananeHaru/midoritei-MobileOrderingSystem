class SalesSummaryRepository
{
    public function getMonthlyBillSummary(
        string $storeId,
        string $monthStart,
        string $nextMonthStart
    ): array {
        // 会計テーブルから月次集計
    }

    public function getMonthlyPaymentSummary(
        string $storeId,
        string $monthStart,
        string $nextMonthStart
    ): array {
        // 支払テーブルから決済方法別集計
    }

    public function saveMonthlySummary(array $summary): void
    {
        // monthly_sales_summaryへUPSERT
    }

    public function saveYearlySummary(
        int $year,
        string $storeId
    ): void {
        // monthly_sales_summaryからyearly_sales_summaryを作成
    }
}
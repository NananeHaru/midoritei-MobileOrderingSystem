class SalesSummaryService
{
    public function summarize(
        string $businessDate,
        string $storeId
    ): void {
        $date = new \DateTimeImmutable($businessDate);

        $year = (int)$date->format('Y');
        $month = (int)$date->format('m');

        $monthStart = $date->modify('first day of this month')
            ->format('Y-m-d 00:00:00');

        $nextMonthStart = $date->modify('first day of next month')
            ->format('Y-m-d 00:00:00');

        // 月次サマリを更新
        $this->createMonthlySummary(
            $year,
            $month,
            $storeId,
            $monthStart,
            $nextMonthStart
        );

        // 年次サマリを更新
        $this->repository->saveYearlySummary(
            $year,
            $storeId
        );
    }
}
<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OfferInspectionService
{
    private const REPORTED_STATUSES = ['open', 'confirmed', 'rejected', 'paid'];

    /**
     * @param  list<int>|null  $allowedOfferIds
     * @param  list<int>|null  $allowedCampaignIds
     * @return array<string, array<string, mixed>>
     */
    public function build(
        int $offerId,
        string $selectedDateFrom,
        string $selectedDateTo,
        ?array $allowedOfferIds,
        ?array $allowedCampaignIds,
    ): array {
        $today = CarbonImmutable::today(config('app.timezone'));
        $dateTo = $today->toDateString();
        $reporting = new ReportingService(new ReportingRepository($allowedOfferIds, $allowedCampaignIds));
        $periods = [
            'lifetime' => ['label' => 'Lifetime', 'from' => '1000-01-01', 'to' => $dateTo],
            'this_year' => ['label' => 'This year', 'from' => $today->startOfYear()->toDateString(), 'to' => $dateTo],
            'last_90_days' => ['label' => 'Last 90 days', 'from' => $today->subDays(89)->toDateString(), 'to' => $dateTo],
            'selected' => ['label' => 'Selected period', 'from' => $selectedDateFrom, 'to' => $selectedDateTo],
        ];

        foreach ($periods as &$period) {
            $report = $reporting->buildReport([
                'date_from' => $period['from'],
                'date_to' => $period['to'],
                'group_by' => ['offer', 'campaign'],
                'level' => 1,
                'parent_filters' => ['offer_id' => $offerId],
                'sort_by' => 'profit',
                'sort_dir' => 'desc',
                'include_totals' => true,
            ]);

            $period['totals'] = $report['totals'];
            $period['campaigns'] = $report['rows'];
            $period['insights'] = $this->periodInsights($offerId, $period['from'], $period['to'], $allowedCampaignIds);
        }
        unset($period);

        return $periods;
    }

    /**
     * @param  list<int>|null  $allowedCampaignIds
     * @return array<string, mixed>
     */
    private function periodInsights(int $offerId, string $dateFrom, string $dateTo, ?array $allowedCampaignIds): array
    {
        return [
            'days' => $this->dailyPerformance($offerId, $dateFrom, $dateTo, $allowedCampaignIds),
            'transitionAverages' => $this->transitionAverages($offerId, $dateFrom, $dateTo, $allowedCampaignIds),
            'lowestCommission' => $this->commissionExtreme($offerId, $dateFrom, $dateTo, $allowedCampaignIds, 'asc'),
            'highestCommission' => $this->commissionExtreme($offerId, $dateFrom, $dateTo, $allowedCampaignIds, 'desc'),
        ];
    }

    /**
     * @param  list<int>|null  $allowedCampaignIds
     * @return list<array{date: string, clicks: int, conversions: int, revenue: float, cr: float, profit: float}>
     */
    private function dailyPerformance(int $offerId, string $dateFrom, string $dateTo, ?array $allowedCampaignIds): array
    {
        $conversionsByClick = DB::table('conversions_events as ce')
            ->join('conversions as cv', 'cv.conversion_event_id', '=', 'ce.id')
            ->whereIn(DB::raw('LOWER(cv.status)'), self::REPORTED_STATUSES)
            ->select('ce.click_id')
            ->selectRaw('COUNT(cv.id) AS conversions')
            ->selectRaw("SUM(CASE WHEN LOWER(cv.status) IN ('open', 'confirmed', 'paid') THEN cv.commission ELSE 0 END) AS revenue")
            ->groupBy('ce.click_id');

        $query = DB::table('clicks as c')
            ->leftJoinSub($conversionsByClick, 'conversion_totals', 'conversion_totals.click_id', '=', 'c.id')
            ->where('c.offer_id', $offerId)
            ->whereBetween('c.created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
        $this->applyCampaignScope($query, $allowedCampaignIds);

        return $query
            ->selectRaw('DATE(c.created_at) AS activity_date')
            ->selectRaw('COUNT(c.id) AS clicks')
            ->selectRaw('COALESCE(SUM(conversion_totals.conversions), 0) AS conversions')
            ->selectRaw('COALESCE(SUM(conversion_totals.revenue), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(conversion_totals.revenue), 0) - COALESCE(SUM(c.cost), 0) AS profit')
            ->groupByRaw('DATE(c.created_at)')
            ->orderBy('activity_date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->activity_date,
                'clicks' => (int) $row->clicks,
                'conversions' => (int) $row->conversions,
                'revenue' => (float) $row->revenue,
                'cr' => (int) $row->clicks > 0 ? ((int) $row->conversions / (int) $row->clicks) * 100 : 0.0,
                'profit' => (float) $row->profit,
            ])
            ->all();
    }

    /**
     * @param  list<int>|null  $allowedCampaignIds
     * @return array{
     *     openToConfirmedMinutes: float|null,
     *     openToRejectedMinutes: float|null,
     *     confirmedToPaidMinutes: float|null,
     *     samples: array{openToConfirmed: int, openToRejected: int, confirmedToPaid: int}
     * }
     */
    private function transitionAverages(int $offerId, string $dateFrom, string $dateTo, ?array $allowedCampaignIds): array
    {
        $query = DB::table('conversions_events as ce')
            ->join('clicks as c', 'c.id', '=', 'ce.click_id')
            ->where('c.offer_id', $offerId)
            ->whereNotNull('ce.commission_id')
            ->where('ce.commission_id', '<>', '')
            ->whereBetween('c.created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
        $this->applyCampaignScope($query, $allowedCampaignIds);

        $rows = $query
            ->select(['ce.click_id', 'ce.affiliate_catalog_id', 'ce.commission_id'])
            ->selectRaw("MIN(CASE WHEN LOWER(ce.status) = 'open' THEN ce.created_at END) AS first_open_at")
            ->selectRaw("MIN(CASE WHEN LOWER(ce.status) = 'confirmed' THEN ce.created_at END) AS first_confirmed_at")
            ->selectRaw("MIN(CASE WHEN LOWER(ce.status) = 'rejected' THEN ce.created_at END) AS first_rejected_at")
            ->selectRaw("MIN(CASE WHEN LOWER(ce.status) = 'paid' THEN ce.created_at END) AS first_paid_at")
            ->groupBy('ce.click_id', 'ce.affiliate_catalog_id', 'ce.commission_id')
            ->get();

        $durations = [
            'openToConfirmedMinutes' => [],
            'openToRejectedMinutes' => [],
            'confirmedToPaidMinutes' => [],
        ];

        foreach ($rows as $row) {
            $durations['openToConfirmedMinutes'][] = $this->durationInMinutes($row->first_open_at, $row->first_confirmed_at);
            $durations['openToRejectedMinutes'][] = $this->durationInMinutes($row->first_open_at, $row->first_rejected_at);
            $durations['confirmedToPaidMinutes'][] = $this->durationInMinutes($row->first_confirmed_at, $row->first_paid_at);
        }

        $averages = array_map(function (array $values): ?float {
            $values = array_values(array_filter($values, fn (?float $value): bool => $value !== null));

            return $values === [] ? null : array_sum($values) / count($values);
        }, $durations);

        return [
            ...$averages,
            'samples' => [
                'openToConfirmed' => count(array_filter($durations['openToConfirmedMinutes'], fn (?float $value): bool => $value !== null)),
                'openToRejected' => count(array_filter($durations['openToRejectedMinutes'], fn (?float $value): bool => $value !== null)),
                'confirmedToPaid' => count(array_filter($durations['confirmedToPaidMinutes'], fn (?float $value): bool => $value !== null)),
            ],
        ];
    }

    private function durationInMinutes(?string $from, ?string $to): ?float
    {
        if ($from === null || $to === null) {
            return null;
        }

        $seconds = CarbonImmutable::parse($to)->getTimestamp() - CarbonImmutable::parse($from)->getTimestamp();

        return $seconds >= 0 ? $seconds / 60 : null;
    }

    /**
     * @param  list<int>|null  $allowedCampaignIds
     * @return array{amount: float, currency: string|null, status: string, affiliate: string|null}|null
     */
    private function commissionExtreme(
        int $offerId,
        string $dateFrom,
        string $dateTo,
        ?array $allowedCampaignIds,
        string $direction,
    ): ?array {
        $query = DB::table('conversions as cv')
            ->join('conversions_events as ce', 'ce.id', '=', 'cv.conversion_event_id')
            ->join('clicks as c', 'c.id', '=', 'ce.click_id')
            ->leftJoin('affiliate_catalog as ac', 'ac.id', '=', 'ce.affiliate_catalog_id')
            ->where('c.offer_id', $offerId)
            ->whereNotNull('cv.commission')
            ->where('cv.commission', '>', 0)
            ->whereBetween('c.created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
        $this->applyCampaignScope($query, $allowedCampaignIds);

        $row = $query
            ->select(['cv.commission', 'cv.currency', 'cv.status', 'ac.name as affiliate'])
            ->orderBy('cv.commission', $direction)
            ->orderBy('cv.id')
            ->first();

        return $row === null ? null : [
            'amount' => (float) $row->commission,
            'currency' => $row->currency,
            'status' => (string) $row->status,
            'affiliate' => $row->affiliate,
        ];
    }

    /**
     * @param  list<int>|null  $allowedCampaignIds
     */
    private function applyCampaignScope(Builder $query, ?array $allowedCampaignIds): void
    {
        if ($allowedCampaignIds === null) {
            return;
        }

        $query->where(function (Builder $campaignQuery) use ($allowedCampaignIds): void {
            $campaignQuery
                ->whereIn('c.campaign_id', $allowedCampaignIds === [] ? [-1] : $allowedCampaignIds)
                ->orWhereNull('c.campaign_id');
        });
    }
}

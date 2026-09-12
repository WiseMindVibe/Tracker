<?php

namespace App\Services\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReportingRepository
{
    /** @var array<string, string> column-normalized dimensions (bare "(none)" fallback) */
    private const NORMALIZED_COLUMN = [
        'os' => 'os',
        'browser' => 'browser',
        'country' => 'country',
        'region' => 'region',
        'language' => 'language',
        'device' => 'device',
        'os_version' => 'os_version',
        'browser_version' => 'browser_version',
        'connection_type' => 'connection_type',
        'carrier' => 'carrier',
        'isp' => 'isp',
        'zoneid' => 'zoneid',
        'subzone_id' => 'subzone_id',
    ];

    /**
     * Conversion statuses tracked as separate columns in every report row.
     * Add/remove entries here to change which statuses are broken out.
     *
     * @var list<string>
     */
    public const CONVERSION_STATUSES = ['open', 'confirmed', 'rejected', 'paid', 'delayed'];

    /**
     * @param  list<int>|null  $allowedOfferIds  Offers the current user may see. Null = no offer restriction. Empty = sees nothing.
     * @param  list<int>|null  $allowedCampaignIds  Campaigns the current user may see, or null for "no extra restriction".
     */
    public function __construct(
        private readonly ?array $allowedOfferIds,
        private readonly ?array $allowedCampaignIds,
    ) {}

    /**
     * @param  array<string, int|string>  $parentFilters
     * @return list<array<string, mixed>>
     */
    public function fetchGroupedClicks(string $groupBy, string $dateFrom, string $dateTo, array $parentFilters): array
    {
        $dim = $this->dimensionConfig($groupBy);
        $query = $this->baseQuery($dateFrom, $dateTo, $groupBy, $parentFilters);

        $rows = $query
            ->select([
                DB::raw($dim['key_sql'].' AS group_key'),
                DB::raw($dim['name_sql'].' AS group_name'),
                DB::raw('COUNT(c.id) AS clicks'),
                DB::raw('COALESCE(SUM(c.cost), 0) AS spent'),
            ])
            ->groupBy(DB::raw($dim['group_by_sql']))
            ->orderByDesc('clicks')
            ->get();

        return $this->collectionToArray($rows);
    }

    /**
     * @param  array<string, int|string>  $parentFilters
     * @return list<array<string, mixed>>
     */
    public function fetchGroupedConversions(string $groupBy, string $dateFrom, string $dateTo, array $parentFilters): array
    {
        $dim = $this->dimensionConfig($groupBy);
        $query = $this->baseQuery($dateFrom, $dateTo, $groupBy, $parentFilters);
        $this->joinConversions($query);

        $select = [
            DB::raw($dim['key_sql'].' AS group_key'),
            DB::raw('COUNT(cv.id) AS total_conversions'),
        ];
        foreach (self::CONVERSION_STATUSES as $status) {
            $select[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER(cv.status) = '{$status}' THEN 1 ELSE 0 END), 0) AS {$status}_conversions");
            $select[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER(cv.status) = '{$status}' THEN cv.commission ELSE 0 END), 0) AS {$status}_conversions_sum");
        }

        $rows = $query
            ->select($select)
            ->groupBy(DB::raw($dim['group_by_sql']))
            ->orderByDesc('total_conversions')
            ->get();

        return $this->collectionToArray($rows);
    }

    /**
     * @param  array<string, int|string>  $parentFilters
     * @return array<string, mixed>
     */
    public function fetchTotalsClicks(string $dateFrom, string $dateTo, array $parentFilters): array
    {
        $query = $this->baseQuery($dateFrom, $dateTo, null, $parentFilters);

        $row = $query
            ->select([
                DB::raw('COUNT(c.id) AS clicks'),
                DB::raw('COALESCE(SUM(c.cost), 0) AS spent'),
            ])
            ->first();

        return $row ? (array) $row : [];
    }

    /**
     * @param  array<string, int|string>  $parentFilters
     * @return array<string, mixed>
     */
    public function fetchTotalsConversions(string $dateFrom, string $dateTo, array $parentFilters): array
    {
        $query = $this->baseQuery($dateFrom, $dateTo, null, $parentFilters);
        $this->joinConversions($query);

        $select = [DB::raw('COUNT(cv.id) AS total_conversions')];
        foreach (self::CONVERSION_STATUSES as $status) {
            $select[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER(cv.status) = '{$status}' THEN 1 ELSE 0 END), 0) AS {$status}_conversions");
            $select[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER(cv.status) = '{$status}' THEN cv.commission ELSE 0 END), 0) AS {$status}_conversions_sum");
        }

        $row = $query->select($select)->first();

        return $row ? (array) $row : [];
    }

    /**
     * @return array{key_sql: string, name_sql: string, group_by_sql: string}
     */
    private function dimensionConfig(string $groupBy): array
    {
        if ($groupBy === 'offer') {
            return [
                'key_sql' => 'COALESCE(c.offer_id, 0)',
                'name_sql' => "COALESCE(MAX(o.name), '(no offer)')",
                'group_by_sql' => 'COALESCE(c.offer_id, 0)',
            ];
        }

        if ($groupBy === 'campaign') {
            return [
                'key_sql' => 'COALESCE(c.campaign_id, 0)',
                'name_sql' => "COALESCE(MAX(cam.name), '(no campaign)')",
                'group_by_sql' => 'COALESCE(c.campaign_id, 0)',
            ];
        }

        if ($groupBy === 'external_campaign_id') {
            $sql = "COALESCE(NULLIF(cti_agg.ext_min, ''), '(none)')";

            return ['key_sql' => $sql, 'name_sql' => $sql, 'group_by_sql' => $sql];
        }

        if (isset(self::NORMALIZED_COLUMN[$groupBy])) {
            $column = self::NORMALIZED_COLUMN[$groupBy];
            $sql = "COALESCE(NULLIF(c.{$column}, ''), '(none)')";

            return ['key_sql' => $sql, 'name_sql' => $sql, 'group_by_sql' => $sql];
        }

        throw new InvalidArgumentException('Unsupported group: '.$groupBy);
    }

    /**
     * Builds the shared clicks base query: date range, tenancy scope, joins
     * needed for the requested dimension, and any parent (drill-down) filters.
     *
     * @param  array<string, int|string>  $parentFilters
     */
    private function baseQuery(string $dateFrom, string $dateTo, ?string $groupBy, array $parentFilters): Builder
    {
        $query = DB::table('clicks as c')
            ->whereBetween('c.created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);

        // Tenancy scope — see ReportingAccessScope for how these are derived.
        if ($this->allowedOfferIds !== null) {
            $query->whereIn('c.offer_id', $this->allowedOfferIds === [] ? [-1] : $this->allowedOfferIds);
        }
        if ($this->allowedCampaignIds !== null) {
            $query->where(function ($q) {
                $q->whereIn('c.campaign_id', $this->allowedCampaignIds === [] ? [-1] : $this->allowedCampaignIds)
                    ->orWhereNull('c.campaign_id');
            });
        }

        if ($groupBy === 'offer') {
            $query->leftJoin('offers as o', 'o.id', '=', 'c.offer_id');
        }
        if ($groupBy === 'campaign') {
            $query->leftJoin('campaigns as cam', 'cam.id', '=', 'c.campaign_id');
        }
        if ($groupBy === 'external_campaign_id' || array_key_exists('external_campaign_id', $parentFilters)) {
            $query->leftJoinSub(
                DB::table('campaigns_traffic_ids')
                    ->select('campaign_id', DB::raw('MIN(traffic_campaign_id) as ext_min'))
                    ->groupBy('campaign_id'),
                'cti_agg',
                'cti_agg.campaign_id',
                '=',
                'c.campaign_id'
            );
        }

        foreach ($parentFilters as $key => $value) {
            $this->applyParentFilter($query, $key, $value);
        }

        return $query;
    }

    private function joinConversions(Builder $query): void
    {
        $query->join('conversions_events as cv', function ($join) {
            $join->on('cv.click_id', '=', 'c.id')
                ->whereIn(DB::raw('LOWER(cv.status)'), self::CONVERSION_STATUSES);
        });
    }

    private function applyParentFilter(Builder $query, string $key, int|string $value): void
    {
        match ($key) {
            'offer_id' => $this->applyIntFilter($query, 'c.offer_id', (int) $value),
            'campaign_id' => $this->applyIntFilter($query, 'c.campaign_id', (int) $value),
            'external_campaign_id' => $this->applyStringFilter($query, 'cti_agg.ext_min', (string) $value),
            default => isset(self::NORMALIZED_COLUMN[$key])
                ? $this->applyStringFilter($query, 'c.'.self::NORMALIZED_COLUMN[$key], (string) $value)
                : throw new InvalidArgumentException('Unsupported parent filter: '.$key),
        };
    }

    private function applyIntFilter(Builder $query, string $column, int $value): void
    {
        if ($value > 0) {
            $query->where($column, $value);
        } else {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, 0);
            });
        }
    }

    private function applyStringFilter(Builder $query, string $column, string $value): void
    {
        if ($value === '(none)') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->where($column, $value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectionToArray(Collection $rows): array
    {
        return $rows->map(fn ($row) => (array) $row)->all();
    }
}

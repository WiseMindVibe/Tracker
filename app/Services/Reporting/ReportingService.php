<?php

namespace App\Services\Reporting;

use App\Services\Commissions\CommissionSnapshotRefresher;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ReportingService
{
    /** @var list<string> */
    private const ALLOWED_GROUPS = [
        'offer', 'campaign', 'external_campaign_id', 'os', 'browser', 'country',
        'region', 'language', 'device', 'os_version', 'browser_version',
        'connection_type', 'carrier', 'isp', 'zoneid', 'subzone_id',
    ];

    /** @var list<string> */
    private const ALLOWED_DATE_PRESETS = [
        'today', 'yesterday', 'last7', 'last_week', 'this_week', 'last30',
        'this_month', 'last_month', 'this_year', 'last_year', 'all_time',
    ];

    /**
     * Which conversion statuses count toward "revenue" / "profit" / "avg payout".
     *
     * @var list<string>
     */
    private const REVENUE_STATUSES = ['open', 'confirmed', 'paid'];

    /** @var list<string> */
    private const ALLOWED_SORT_FIELDS = [
        'group_name', 'clicks', 'total_conversions',
        'open_conversions', 'open_conversions_sum',
        'confirmed_conversions', 'confirmed_conversions_sum',
        'rejected_conversions', 'rejected_conversions_sum',
        'paid_conversions', 'paid_conversions_sum',
        'loss', 'revenue', 'spent', 'profit', 'cr', 'roi', 'avg_payout',
    ];

    public function __construct(
        private readonly ReportingRepository $repository,
        private readonly ?CommissionSnapshotRefresher $snapshotRefresher = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function buildReport(array $payload): array
    {
        [$dateFrom, $dateTo, $datePreset] = $this->resolveDateRange($payload);

        $this->snapshotRefresher?->refresh();

        $groupBy = $this->normalizeGroupBy($payload['group_by'] ?? null);
        $level = $this->normalizeLevel($payload['level'] ?? 0, count($groupBy));
        $parentFilters = $this->normalizeParentFilters($payload['parent_filters'] ?? [], $groupBy, $level);
        [$sortBy, $sortDir] = $this->resolveSort($payload);
        $includeTotals = $this->normalizeIncludeTotals($payload['include_totals'] ?? true);

        $currentGroup = $groupBy[$level];
        $nextLevel = $level < (count($groupBy) - 1);

        $clicks = $this->repository->fetchGroupedClicks($currentGroup, $dateFrom, $dateTo, $parentFilters);
        $conversions = $this->repository->fetchGroupedConversions($currentGroup, $dateFrom, $dateTo, $parentFilters);
        $rows = $this->buildRows($clicks, $conversions, $currentGroup, $parentFilters, $nextLevel);
        $this->sortRows($rows, $sortBy, $sortDir);

        $totals = null;
        if ($includeTotals) {
            $totalsClicks = $this->repository->fetchTotalsClicks($dateFrom, $dateTo, $parentFilters);
            $totalsConversions = $this->repository->fetchTotalsConversions($dateFrom, $dateTo, $parentFilters);
            $totals = $this->buildTotals($rows, $totalsClicks, $totalsConversions);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_preset' => $datePreset,
            'group_by' => $groupBy,
            'level' => $level,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'rows' => $rows,
            'totals' => $totals,
            'next_level' => $nextLevel,
        ];
    }

    private function normalizeIncludeTotals(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return ((int) $raw) !== 0;
        }
        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return ! ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'no');
        }

        return (bool) $raw;
    }

    /**
     * @return list<string>
     */
    private function normalizeGroupBy(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return ['offer', 'campaign'];
        }

        $out = [];
        foreach ($raw as $candidate) {
            $group = strtolower(trim((string) $candidate));
            if ($group !== '' && in_array($group, self::ALLOWED_GROUPS, true) && ! in_array($group, $out, true)) {
                $out[] = $group;
            }
        }

        return $out === [] ? ['offer', 'campaign'] : $out;
    }

    private function normalizeLevel(mixed $rawLevel, int $groupCount): int
    {
        if (! is_numeric($rawLevel)) {
            throw new InvalidArgumentException('level must be numeric');
        }
        $level = (int) $rawLevel;
        if ($level < 0 || $level >= $groupCount) {
            throw new InvalidArgumentException('level is out of range for selected group_by');
        }

        return $level;
    }

    /**
     * @param  list<string>  $groupBy
     * @return array<string, int|string>
     */
    private function normalizeParentFilters(mixed $rawFilters, array $groupBy, int $level): array
    {
        if (! is_array($rawFilters)) {
            throw new InvalidArgumentException('parent_filters must be an object');
        }

        $expectedKeys = [];
        for ($i = 0; $i < $level; $i++) {
            $expectedKeys[] = $this->filterKeyForGroup($groupBy[$i]);
        }

        foreach ($rawFilters as $key => $_value) {
            if (! in_array((string) $key, $expectedKeys, true)) {
                throw new InvalidArgumentException('Unexpected parent filter: '.(string) $key);
            }
        }

        $out = [];
        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $rawFilters)) {
                throw new InvalidArgumentException('Missing parent filter: '.$key);
            }
            $out[$key] = $this->normalizeFilterValue($key, $rawFilters[$key]);
        }

        return $out;
    }

    private function normalizeFilterValue(string $key, mixed $value): int|string
    {
        return match ($key) {
            'offer_id', 'campaign_id' => $this->normalizeIntFilter($key, $value),
            default => $this->normalizeStringFilter($value),
        };
    }

    private function normalizeIntFilter(string $key, mixed $value): int
    {
        if (! is_scalar($value) || ! is_numeric((string) $value)) {
            throw new InvalidArgumentException($key.' must be numeric');
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException($key.' must be >= 0');
        }

        return $int;
    }

    private function normalizeStringFilter(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Filter value must be a string');
        }
        $normalized = trim((string) $value);

        return $normalized === '' ? '(none)' : $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveDateRange(array $payload): array
    {
        $fromRaw = trim((string) ($payload['date_from'] ?? ''));
        $toRaw = trim((string) ($payload['date_to'] ?? ''));

        if ($fromRaw !== '' || $toRaw !== '') {
            if ($fromRaw === '' || $toRaw === '') {
                throw new InvalidArgumentException('date_from and date_to must be provided together');
            }
            $from = $this->parseDateYmd($fromRaw, 'date_from');
            $to = $this->parseDateYmd($toRaw, 'date_to');
            if ($from > $to) {
                throw new InvalidArgumentException('date_from cannot be greater than date_to');
            }

            return [$from->format('Y-m-d'), $to->format('Y-m-d'), 'custom'];
        }

        $preset = strtolower(trim((string) ($payload['date_preset'] ?? 'today')));
        if (! in_array($preset, self::ALLOWED_DATE_PRESETS, true)) {
            throw new InvalidArgumentException('date_preset is not supported');
        }

        $tz = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $tz);

        return match ($preset) {
            'today' => [$today->format('Y-m-d'), $today->format('Y-m-d'), 'today'],
            'yesterday' => [$today->modify('-1 day')->format('Y-m-d'), $today->modify('-1 day')->format('Y-m-d'), 'yesterday'],
            'last7' => [$today->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d'), 'last7'],
            'this_week' => [$today->modify('-'.(((int) $today->format('N')) - 1).' days')->format('Y-m-d'), $today->format('Y-m-d'), 'this_week'],
            'last_week' => [$today->modify('-'.((((int) $today->format('N')) - 1) + 7).' days')->format('Y-m-d'), $today->modify('-'.(((int) $today->format('N')) - 1).' days')->format('Y-m-d'), 'last_week'],
            'this_month' => [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d'), 'this_month'],
            'last30' => [$today->modify('-30 days')->format('Y-m-d'), $today->format('Y-m-d'), 'last30'],
            'last_month' => [$today->modify('first day of previous month')->format('Y-m-d'), $today->modify('last day of previous month')->format('Y-m-d'), 'last_month'],
            'this_year' => [$today->modify('first day of January this year')->format('Y-m-d'), $today->modify('last day of December this year')->format('Y-m-d'), 'this_year'],
            'last_year' => [$today->modify('first day of January last year')->format('Y-m-d'), $today->modify('last day of December last year')->format('Y-m-d'), 'last_year'],
            'all_time' => ['2000-01-01', $today->format('Y-m-d'), 'all_time'],
            default => [$today->format('Y-m-d'), $today->format('Y-m-d'), 'today'],
        };
    }

    private function parseDateYmd(string $raw, string $fieldName): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if (! $dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException($fieldName.' must be in YYYY-MM-DD format');
        }

        return $dt;
    }

    private function filterKeyForGroup(string $group): string
    {
        return match ($group) {
            'offer' => 'offer_id',
            'campaign' => 'campaign_id',
            default => $group,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $clickRows
     * @param  list<array<string, mixed>>  $conversionRows
     * @param  array<string, int|string>  $parentFilters
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $clickRows, array $conversionRows, string $group, array $parentFilters, bool $canExpand): array
    {
        $statuses = ReportingRepository::CONVERSION_STATUSES;

        $conversionByKey = [];
        foreach ($conversionRows as $row) {
            $key = $this->rowKeyToken($this->normalizeGroupKey($group, $row['group_key'] ?? null));
            $entry = ['total_conversions' => (int) ($row['total_conversions'] ?? 0)];
            foreach ($statuses as $status) {
                $entry[$status.'_conversions'] = (int) ($row[$status.'_conversions'] ?? 0);
                $entry[$status.'_conversions_sum'] = (float) ($row[$status.'_conversions_sum'] ?? 0);
            }
            $entry['loss'] = (float) ($row['loss'] ?? 0);
            $conversionByKey[$key] = $entry;
        }

        $emptyConv = ['total_conversions' => 0];
        foreach ($statuses as $status) {
            $emptyConv[$status.'_conversions'] = 0;
            $emptyConv[$status.'_conversions_sum'] = 0.0;
        }
        $emptyConv['loss'] = 0.0;

        $groupField = $this->filterKeyForGroup($group);
        $out = [];

        foreach ($clickRows as $row) {
            $groupKey = $this->normalizeGroupKey($group, $row['group_key'] ?? null);
            $conv = $conversionByKey[$this->rowKeyToken($groupKey)] ?? $emptyConv;

            $clicks = (int) ($row['clicks'] ?? 0);
            $spent = (float) ($row['spent'] ?? 0);

            $revenue = 0.0;
            $qualifiedConversions = 0;
            foreach (self::REVENUE_STATUSES as $status) {
                $revenue += (float) $conv[$status.'_conversions_sum'];
                $qualifiedConversions += (int) $conv[$status.'_conversions'];
            }
            $profit = $revenue - $spent;

            $cr = $clicks > 0 ? (($conv['total_conversions'] / $clicks) * 100.0) : 0.0;
            $roi = $spent > 0.0 ? (($profit / $spent) * 100.0) : null;
            $avgPayout = $qualifiedConversions > 0 ? ($revenue / $qualifiedConversions) : null;

            $nextParentFilters = $parentFilters;
            $nextParentFilters[$groupField] = $groupKey;

            $out[] = array_merge([
                'group_name' => (string) ($row['group_name'] ?? '(none)'),
                'group_key' => $groupKey,
                'group_field' => $groupField,
                'parent_filters' => $nextParentFilters,
                'can_expand' => $canExpand,
                'clicks' => $clicks,
                'total_conversions' => $conv['total_conversions'],
                'loss' => $conv['loss'],
            ], $this->statusColumns($conv, $statuses), [
                'revenue' => $revenue,
                'spent' => $spent,
                'profit' => $profit,
                'cr' => $cr,
                'roi' => $roi,
                'avg_payout' => $avgPayout,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $conv
     * @param  list<string>  $statuses
     * @return array<string, int|float>
     */
    private function statusColumns(array $conv, array $statuses): array
    {
        $out = [];
        foreach ($statuses as $status) {
            $out[$status.'_conversions'] = (int) $conv[$status.'_conversions'];
            $out[$status.'_conversions_sum'] = (float) $conv[$status.'_conversions_sum'];
        }

        return $out;
    }

    private function normalizeGroupKey(string $group, mixed $value): int|string
    {
        if ($group === 'offer' || $group === 'campaign') {
            return $this->normalizeIntFilter($this->filterKeyForGroup($group), $value);
        }

        return $this->normalizeStringFilter($value);
    }

    private function rowKeyToken(int|string $key): string
    {
        return is_int($key) ? 'i:'.$key : 's:'.$key;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totalClicks
     * @param  array<string, mixed>  $totalConversions
     * @return array<string, int|float|null>
     */
    private function buildTotals(array $rows, array $totalClicks, array $totalConversions): array
    {
        $statuses = ReportingRepository::CONVERSION_STATUSES;
        $clicks = (int) ($totalClicks['clicks'] ?? 0);
        $spent = (float) ($totalClicks['spent'] ?? 0);
        $totalConversionsCount = (int) ($totalConversions['total_conversions'] ?? 0);

        $statusColumns = [];
        $revenue = 0.0;
        $qualifiedConversions = 0;
        foreach ($statuses as $status) {
            $count = (int) ($totalConversions[$status.'_conversions'] ?? 0);
            $sum = (float) ($totalConversions[$status.'_conversions_sum'] ?? 0);
            $statusColumns[$status.'_conversions'] = $count;
            $statusColumns[$status.'_conversions_sum'] = $sum;
            if (in_array($status, self::REVENUE_STATUSES, true)) {
                $revenue += $sum;
                $qualifiedConversions += $count;
            }
        }
        $loss = (float) ($totalConversions['loss'] ?? 0);

        $profit = $revenue - $spent;
        $derivedCr = $clicks > 0 ? (($totalConversionsCount / $clicks) * 100.0) : 0.0;
        $derivedRoi = $spent > 0.0 ? (($profit / $spent) * 100.0) : null;
        $derivedAvgPayout = $qualifiedConversions > 0 ? ($revenue / $qualifiedConversions) : null;

        return array_merge([
            'clicks' => $clicks,
            'total_conversions' => $totalConversionsCount,
            'loss' => $loss,
        ], $statusColumns, [
            'revenue' => $revenue,
            'spent' => $spent,
            'profit' => $profit,
            'cr' => $this->averageMetric($rows, 'cr') ?? $derivedCr,
            'roi' => $this->averageMetric($rows, 'roi') ?? $derivedRoi,
            'avg_payout' => $this->averageMetric($rows, 'avg_payout') ?? $derivedAvgPayout,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function averageMetric(array $rows, string $metric): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            if (($row[$metric] ?? null) === null) {
                continue;
            }
            $sum += (float) $row[$metric];
            $count++;
        }

        return $count === 0 ? null : $sum / $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}
     */
    private function resolveSort(array $payload): array
    {
        $sortBy = strtolower(trim((string) ($payload['sort_by'] ?? 'clicks')));
        if (! in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            throw new InvalidArgumentException('sort_by is not supported');
        }

        $sortDir = strtolower(trim((string) ($payload['sort_dir'] ?? 'desc')));
        if ($sortDir !== 'asc' && $sortDir !== 'desc') {
            throw new InvalidArgumentException('sort_dir must be asc or desc');
        }

        return [$sortBy, $sortDir];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortRows(array &$rows, string $sortBy, string $sortDir): void
    {
        $compareGroupName = static fn (array $l, array $r): int => strcasecmp((string) ($l['group_name'] ?? ''), (string) ($r['group_name'] ?? ''));
        $compareNullable = static function (mixed $a, mixed $b): int {
            if ($a === null && $b === null) {
                return 0;
            }
            if ($a === null) {
                return 1;
            }
            if ($b === null) {
                return -1;
            }

            return ((float) $a) <=> ((float) $b);
        };

        usort($rows, static function (array $left, array $right) use ($sortBy, $sortDir, $compareGroupName, $compareNullable): int {
            if ($sortBy === 'group_name') {
                $cmp = $compareGroupName($left, $right);
            } else {
                $cmp = $compareNullable($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($cmp === 0) {
                    $cmp = $compareGroupName($left, $right);
                }
            }

            return $sortDir === 'desc' ? $cmp * -1 : $cmp;
        });
    }
}

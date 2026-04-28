<?php

require_once __DIR__ . '/../repositories/ReportingRepository.php';

final class ReportingService
{
    /** @var list<string> */
    private const ALLOWED_GROUPS = [
        'offer',
        'campaign',
        'external_campaign_id',
        'os',
        'browser',
        'country',
        'region',
        'language',
        'device',
        'os_version',
        'browser_version',
        'connection_type',
        'carrier',
        'isp',
        'zoneid',
        'subzone_id',
    ];

    /** @var list<string> */
    private const ALLOWED_DATE_PRESETS = ['today', 'yesterday', 'last7', 'last_week', 'this_week', 'last30', 'this_month', 'last_month', 'this_year', 'last_year', 'all_time'];

    /** @var list<string> */
    private const ALLOWED_SORT_FIELDS = [
        'group_name',
        'clicks',
        'total_conversions',
        'open_conversions',
        'open_conversions_sum',
        'confirmed_conversions',
        'confirmed_conversions_sum',
        'rejected_conversions',
        'rejected_conversions_sum',
        'paid_conversions',
        'paid_conversions_sum',
        'revenue',
        'spent',
        'profit',
        'cr',
        'roi',
        'avg_payout',
    ];

    private ReportingRepository $repository;

    public function __construct(?ReportingRepository $repository = null)
    {
        $this->repository = $repository ?? new ReportingRepository();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function buildReport(array $payload): array
    {
        [$dateFrom, $dateTo, $datePreset] = $this->resolveDateRange($payload);
        $groupBy = $this->normalizeGroupBy($payload['group_by'] ?? null);
        $level = $this->normalizeLevel($payload['level'] ?? 0, count($groupBy));
        $parentFilters = $this->normalizeParentFilters($payload['parent_filters'] ?? [], $groupBy, $level);
        [$sortBy, $sortDir] = $this->resolveSort($payload);
        $includeTotals = $this->normalizeIncludeTotals($payload['include_totals'] ?? true);

        $currentGroup = $groupBy[$level];
        $nextLevel = $level < (count($groupBy) - 1);

        $groupedClicks = $this->repository->fetchGroupedClicks($currentGroup, $dateFrom, $dateTo, $parentFilters);
        $groupedConversions = $this->repository->fetchGroupedConversions($currentGroup, $dateFrom, $dateTo, $parentFilters);
        $rows = $this->buildRows($groupedClicks, $groupedConversions, $currentGroup, $parentFilters, $nextLevel);
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
            if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'no') {
                return false;
            }

            return true;
        }

        return (bool) $raw;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeGroupBy(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (!is_array($raw)) {
            return ['offer', 'campaign'];
        }

        $out = [];
        foreach ($raw as $candidate) {
            $group = strtolower(trim((string) $candidate));
            if ($group === '' || !in_array($group, self::ALLOWED_GROUPS, true)) {
                continue;
            }
            if (!in_array($group, $out, true)) {
                $out[] = $group;
            }
        }

        if ($out === []) {
            $out = ['offer', 'campaign'];
        }

        return $out;
    }

    private function normalizeLevel(mixed $rawLevel, int $groupCount): int
    {
        if (!is_numeric($rawLevel)) {
            throw new InvalidArgumentException('level must be numeric');
        }

        $level = (int) $rawLevel;
        if ($level < 0 || $level >= $groupCount) {
            throw new InvalidArgumentException('level is out of range for selected group_by');
        }

        return $level;
    }

    /**
     * @param mixed $rawFilters
     * @param list<string> $groupBy
     * @return array<string, int|string>
     */
    private function normalizeParentFilters(mixed $rawFilters, array $groupBy, int $level): array
    {
        if (!is_array($rawFilters)) {
            throw new InvalidArgumentException('parent_filters must be an object');
        }

        $expectedKeys = [];
        for ($i = 0; $i < $level; $i++) {
            $expectedKeys[] = $this->parentFilterKeyForGroup($groupBy[$i]);
        }

        foreach ($rawFilters as $key => $_value) {
            if (!in_array((string) $key, $expectedKeys, true)) {
                throw new InvalidArgumentException('Unexpected parent filter: ' . (string) $key);
            }
        }

        $out = [];
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $rawFilters)) {
                throw new InvalidArgumentException('Missing parent filter: ' . $key);
            }
            $out[$key] = $this->normalizeParentFilterValue($key, $rawFilters[$key]);
        }

        return $out;
    }

    private function normalizeParentFilterValue(string $key, mixed $value): int|string
    {
        return match ($key) {
            'offer_id', 'campaign_id' => $this->normalizeIntegerFilter($key, $value),
            'external_campaign_id', 'os', 'browser', 'country', 'region', 'language', 'device', 'os_version', 'browser_version', 'connection_type', 'carrier', 'isp', 'zoneid', 'subzone_id' => $this->normalizeStringFilter($value),
            default => throw new InvalidArgumentException('Unsupported parent filter key: ' . $key),
        };
    }

    private function normalizeIntegerFilter(string $key, mixed $value): int
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            throw new InvalidArgumentException($key . ' must be numeric');
        }

        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException($key . ' must be >= 0');
        }

        return $int;
    }

    private function normalizeStringFilter(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Filter value must be a string');
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '(none)';
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
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
        if (!in_array($preset, self::ALLOWED_DATE_PRESETS, true)) {
            throw new InvalidArgumentException('date_preset must be one of: today, yesterday, last7, this_week');
        }

        $tz = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $tz);
        

        return match ($preset) {
            'today' => [$today->format('Y-m-d'), $today->format('Y-m-d'), 'today'],
            'yesterday' => [
                $today->modify('-1 day')->format('Y-m-d'),
                $today->modify('-1 day')->format('Y-m-d'),
                'yesterday',
            ],
            'last7' => [
                $today->modify('-7 days')->format('Y-m-d'),
                $today->modify('-1 day')->format('Y-m-d'),
                'last7',
            ],
            'this_week' => [
                $today->modify('-' . (((int) $today->format('N')) - 1) . ' days')->format('Y-m-d'),
                $today->format('Y-m-d'),
                'this_week',
            ],
            'last_week' => [
                $today->modify('-' . ((((int) $today->format('N')) - 1) + 7) . ' days')->format('Y-m-d'),
                $today->modify('-' . (((int) $today->format('N')) - 1) . ' days')->format('Y-m-d'),
                'last_week',
            ],
            'this_month' => [
                $today->modify('first day of this month')->format('Y-m-d'),
                $today->format('Y-m-d'),
                'this_month',
            ],
            'last30' => [
                $today->modify('-30 days')->format('Y-m-d'),
                $today->format('Y-m-d'),
                'last30',
            ],
            'last_month' => [
                $today->modify('first day of previous month')->format('Y-m-d'),
                $today->modify('last day of previous month')->format('Y-m-d'),
                'last_month',
            ],
            'this_year' => [
                $today->modify('first day of January this year')->format('Y-m-d'),
                $today->modify('last day of December this year')->format('Y-m-d'),
                'this_year',
            ],
            'last_year' => [
                $today->modify('first day of January last year')->format('Y-m-d'),
                $today->modify('last day of December last year')->format('Y-m-d'),
                'last_year',
            ],
            'all_time' => [
                '2000-01-01',
                $today->format('Y-m-d'),
                'all_time',
            ],
            default => [$today->format('Y-m-d'), $today->format('Y-m-d'), 'today'],
        };
    }

    private function parseDateYmd(string $raw, string $fieldName): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException($fieldName . ' must be in YYYY-MM-DD format');
        }

        return $dt;
    }

    private function parentFilterKeyForGroup(string $group): string
    {
        return match ($group) {
            'offer' => 'offer_id',
            'campaign' => 'campaign_id',
            'external_campaign_id' => 'external_campaign_id',
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
            default => throw new InvalidArgumentException('Unsupported group: ' . $group),
        };
    }

    /**
     * @param list<array<string, mixed>> $clickRows
     * @param list<array<string, mixed>> $conversionRows
     * @param array<string, int|string> $parentFilters
     * @return list<array<string, mixed>>
     */
    private function buildRows(
        array $clickRows,
        array $conversionRows,
        string $group,
        array $parentFilters,
        bool $canExpand
    ): array {
        $conversionByKey = [];
        foreach ($conversionRows as $row) {
            $groupKey = $this->normalizeGroupKey($group, $row['group_key'] ?? null);
            $conversionByKey[$this->rowKeyToken($groupKey)] = [
                'total_conversions' => $this->toInt($row['total_conversions'] ?? 0),
                'open_conversions' => $this->toInt($row['open_conversions'] ?? 0),
                'open_conversions_sum' => $this->toFloat($row['open_conversions_sum'] ?? 0),
                'confirmed_conversions' => $this->toInt($row['confirmed_conversions'] ?? 0),
                'confirmed_conversions_sum' => $this->toFloat($row['confirmed_conversions_sum'] ?? 0),
                'rejected_conversions' => $this->toInt($row['rejected_conversions'] ?? 0),
                'rejected_conversions_sum' => $this->toFloat($row['rejected_conversions_sum'] ?? 0),
                'paid_conversions' => $this->toInt($row['paid_conversions'] ?? 0),
                'paid_conversions_sum' => $this->toFloat($row['paid_conversions_sum'] ?? 0),
            ];
        }

        $out = [];
        $groupFilterField = $this->parentFilterKeyForGroup($group);
        foreach ($clickRows as $row) {
            $groupKey = $this->normalizeGroupKey($group, $row['group_key'] ?? null);
            $token = $this->rowKeyToken($groupKey);
            $conv = $conversionByKey[$token] ?? [
                'total_conversions' => 0,
                'open_conversions' => 0,
                'open_conversions_sum' => 0.0,
                'confirmed_conversions' => 0,
                'confirmed_conversions_sum' => 0.0,
                'rejected_conversions' => 0,
                'rejected_conversions_sum' => 0.0,
                'paid_conversions' => 0,
                'paid_conversions_sum' => 0.0,
            ];

            $clicks = $this->toInt($row['clicks'] ?? 0);
            $spent = $this->toFloat($row['spent'] ?? 0);

            $openSum = (float) $conv['open_conversions_sum'];
            $confirmedSum = (float) $conv['confirmed_conversions_sum'];
            $paidSum = (float) $conv['paid_conversions_sum'];
            $rejectedSum = (float) $conv['rejected_conversions_sum'];
            $revenue = $openSum + $confirmedSum + $paidSum;
            $profit = $revenue - $spent;
            $qualifiedConversions = (int) $conv['open_conversions']
                + (int) $conv['confirmed_conversions']
                + (int) $conv['paid_conversions'];

            $cr = $clicks > 0 ? (((int) $conv['total_conversions'] / $clicks) * 100.0) : 0.0;
            $roi = $spent > 0.0 ? (($profit / $spent) * 100.0) : null;
            $avgPayout = $qualifiedConversions > 0 ? ($revenue / $qualifiedConversions) : null;

            $nextParentFilters = $parentFilters;
            $nextParentFilters[$groupFilterField] = $groupKey;

            $out[] = [
                'group_name' => (string) ($row['group_name'] ?? '(none)'),
                'group_key' => $groupKey,
                'group_field' => $groupFilterField,
                'parent_filters' => $nextParentFilters,
                'can_expand' => $canExpand,
                'clicks' => $clicks,
                'total_conversions' => (int) $conv['total_conversions'],
                'open_conversions' => (int) $conv['open_conversions'],
                'open_conversions_sum' => $openSum,
                'confirmed_conversions' => (int) $conv['confirmed_conversions'],
                'confirmed_conversions_sum' => $confirmedSum,
                'rejected_conversions' => (int) $conv['rejected_conversions'],
                'rejected_conversions_sum' => $rejectedSum,
                'paid_conversions' => (int) $conv['paid_conversions'],
                'paid_conversions_sum' => $paidSum,
                'revenue' => $revenue,
                'spent' => $spent,
                'profit' => $profit,
                'cr' => $cr,
                'roi' => $roi,
                'avg_payout' => $avgPayout,
            ];
        }

        return $out;
    }

    private function normalizeGroupKey(string $group, mixed $value): int|string
    {
        return match ($group) {
            'offer', 'campaign' => $this->normalizeIntegerFilter($this->parentFilterKeyForGroup($group), $value),
            'external_campaign_id', 'os', 'browser', 'country', 'region', 'language', 'device', 'os_version', 'browser_version', 'connection_type', 'carrier', 'isp', 'zoneid', 'subzone_id' => $this->normalizeStringFilter($value),
            default => throw new InvalidArgumentException('Unsupported group key: ' . $group),
        };
    }

    private function rowKeyToken(int|string $key): string
    {
        return is_int($key) ? 'i:' . $key : 's:' . $key;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $totalClicks
     * @param array<string, mixed> $totalConversions
     * @return array<string, int|float|null>
     */
    private function buildTotals(array $rows, array $totalClicks, array $totalConversions): array
    {
        $clicks = $this->toInt($totalClicks['clicks'] ?? 0);
        $spent = $this->toFloat($totalClicks['spent'] ?? 0);

        $openConversions = $this->toInt($totalConversions['open_conversions'] ?? 0);
        $openSum = $this->toFloat($totalConversions['open_conversions_sum'] ?? 0);
        $confirmedConversions = $this->toInt($totalConversions['confirmed_conversions'] ?? 0);
        $confirmedSum = $this->toFloat($totalConversions['confirmed_conversions_sum'] ?? 0);
        $rejectedConversions = $this->toInt($totalConversions['rejected_conversions'] ?? 0);
        $rejectedSum = $this->toFloat($totalConversions['rejected_conversions_sum'] ?? 0);
        $paidConversions = $this->toInt($totalConversions['paid_conversions'] ?? 0);
        $paidSum = $this->toFloat($totalConversions['paid_conversions_sum'] ?? 0);
        $totalConversionsCount = $this->toInt($totalConversions['total_conversions'] ?? 0);

        $revenue = $openSum + $confirmedSum + $paidSum;
        $profit = $revenue - $spent;

        $derivedCr = $clicks > 0 ? (($totalConversionsCount / $clicks) * 100.0) : 0.0;
        $derivedRoi = $spent > 0.0 ? (($profit / $spent) * 100.0) : null;
        $qualifiedConversions = $openConversions + $confirmedConversions + $paidConversions;
        $derivedAvgPayout = $qualifiedConversions > 0 ? ($revenue / $qualifiedConversions) : null;

        $avgCr = $this->averageMetric($rows, 'cr');
        $avgRoi = $this->averageMetric($rows, 'roi');
        $avgPayout = $this->averageMetric($rows, 'avg_payout');

        return [
            'clicks' => $clicks,
            'total_conversions' => $totalConversionsCount,
            'open_conversions' => $openConversions,
            'open_conversions_sum' => $openSum,
            'confirmed_conversions' => $confirmedConversions,
            'confirmed_conversions_sum' => $confirmedSum,
            'rejected_conversions' => $rejectedConversions,
            'rejected_conversions_sum' => $rejectedSum,
            'paid_conversions' => $paidConversions,
            'paid_conversions_sum' => $paidSum,
            'revenue' => $revenue,
            'spent' => $spent,
            'profit' => $profit,
            'cr' => $avgCr ?? $derivedCr,
            'roi' => $avgRoi ?? $derivedRoi,
            'avg_payout' => $avgPayout ?? $derivedAvgPayout,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function averageMetric(array $rows, string $metric): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $val = $row[$metric] ?? null;
            if ($val === null) {
                continue;
            }
            $sum += (float) $val;
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        return $sum / $count;
    }

    private function toInt(mixed $value): int
    {
        return (int) $value;
    }

    private function toFloat(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string}
     */
    private function resolveSort(array $payload): array
    {
        $sortBy = strtolower(trim((string) ($payload['sort_by'] ?? 'clicks')));
        if (!in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            throw new InvalidArgumentException('sort_by is not supported');
        }

        $sortDir = strtolower(trim((string) ($payload['sort_dir'] ?? 'desc')));
        if ($sortDir !== 'asc' && $sortDir !== 'desc') {
            throw new InvalidArgumentException('sort_dir must be asc or desc');
        }

        return [$sortBy, $sortDir];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function sortRows(array &$rows, string $sortBy, string $sortDir): void
    {
        $compareGroupName = static function (array $left, array $right): int {
            return strcasecmp((string) ($left['group_name'] ?? ''), (string) ($right['group_name'] ?? ''));
        };
        $compareNullableNumeric = static function (mixed $a, mixed $b): int {
            $result = 0;

            if ($a === null && $b === null) {
                $result = 0;
            } elseif ($a === null) {
                $result = 1;
            } elseif ($b === null) {
                $result = -1;
            } else {
                $result = ((float) $a) <=> ((float) $b);
            }

            return $result;
        };

        usort($rows, static function (array $left, array $right) use ($sortBy, $sortDir, $compareGroupName, $compareNullableNumeric): int {
            if ($sortBy === 'group_name') {
                $cmp = $compareGroupName($left, $right);
            } else {
                $a = $left[$sortBy] ?? null;
                $b = $right[$sortBy] ?? null;
                $cmp = $compareNullableNumeric($a, $b);
                if ($cmp === 0) {
                    $cmp = $compareGroupName($left, $right);
                }
            }

            if ($sortDir === 'desc') {
                $cmp *= -1;
            }

            return $cmp;
        });
    }
}

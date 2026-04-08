<?php

require_once __DIR__ . '/ModelDashboard.php';

/**
 * Test harness: keeps Test-only date presets (e.g. last6); stats match production dashboard logic.
 */
class ModelTest
{
    /**
     * @return array{start: string, end: string, preset: string}
     */
    public static function resolveDateRange(string $preset, ?string $customStart = null, ?string $customEnd = null): array
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $today = new DateTime('today', $tz);
        $preset = strtolower(trim($preset));

        if ($preset === 'custom' && $customStart !== null && $customStart !== '' && $customEnd !== null && $customEnd !== '') {
            $s = DateTime::createFromFormat('Y-m-d', $customStart, $tz);
            $e = DateTime::createFromFormat('Y-m-d', $customEnd, $tz);
            if ($s instanceof DateTime && $e instanceof DateTime && $s <= $e) {
                return [
                    'start' => $s->format('Y-m-d'),
                    'end' => $e->format('Y-m-d'),
                    'preset' => 'custom',
                ];
            }
        }

        switch ($preset) {
            case 'last6':
                $start = (clone $today)->modify('-4 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'last6',
                ];
            default:
                return ModelDashboard::resolveDateRange($preset, $customStart, $customEnd);
        }
    }

    /**
     * @return array<string, float|int|null>
     */
    public static function fetchAggregateStats(string $start, string $end): array
    {
        return ModelDashboard::fetchAggregateStats($start, $end);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchAffiliateBreakdown(string $start, string $end): array
    {
        return ModelDashboard::fetchAffiliateBreakdown($start, $end);
    }

    /**
     * @return array{by_roi: list<array>, by_cr: list<array>, by_profit: list<array>}
     */
    public static function fetchTopOffersLast7Days(int $limit = 5): array
    {
        return ModelDashboard::fetchTopOffersLast7Days($limit);
    }
}


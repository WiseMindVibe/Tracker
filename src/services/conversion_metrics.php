<?php

/**
 * Shared KPI math for dashboard and inspect (keep definitions in one place).
 *
 * Status codes: @see migration 004.1 (0 unknown, 1 open, 2 confirmed, 3 rejected, 4 paid).
 */
final class ConversionMetrics
{
    /**
     * CR = conversion rows (known statuses) / clicks, as percentage.
     */
    public static function conversionRate(int $clicks, int $conversionRows): ?float
    {
        if ($clicks <= 0) {
            return null;
        }

        return ($conversionRows / $clicks) * 100.0;
    }

    /**
     * Rejection rate from O/C/P/R counts only (ignores unknown status rows).
     * If no confirmed and no paid: R / (O + R). Else: R / (O + C + P + R).
     */
    public static function rejectionRate(int $openCount, int $confirmedCount, int $paidCount, int $rejectedCount): ?float
    {
        $o = $openCount;
        $c = $confirmedCount;
        $p = $paidCount;
        $r = $rejectedCount;

        if ($c === 0 && $p === 0) {
            $den = $o + $r;
        } else {
            $den = $o + $c + $p + $r;
        }

        if ($den <= 0) {
            return null;
        }

        return ($r / $den) * 100.0;
    }

    /**
     * ROI % from cost and revenue that excludes rejected commissions only.
     */
    public static function roiPercent(float $cost, float $revenueExcludingRejected): ?float
    {
        if ($cost <= 0.0) {
            return null;
        }

        return (($revenueExcludingRejected - $cost) / $cost) * 100.0;
    }
}

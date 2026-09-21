<?php

namespace App\Services\Commissions;

use App\Models\AffiliateCatalog;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

final class CommissionCalculator
{
    /**
     * @return array{status: ?string, value: float, accumulated: float, loss: float, events_count: int, mode: string, rule: string}
     */
    public function calculate(AffiliateCatalog $catalog, Collection $events): array
    {
        $policy = $this->policy($catalog);
        $accumulated = 0.0;
        $value = 0.0;
        $loss = 0.0;
        $baseline = null;        // first non-zero amount reported by a non-rejected event
        $liveValue = 0.0;        // last amount produced by a non-rejected event
        $wasRejected = false;
        $latestStatus = null;
        $mode = $policy['mode'];
        $rule = $mode;

        foreach ($events->sortBy(fn($event) => $event->created_at ?? $event->id) as $event) {
            $latestStatus = strtolower((string) $event->status);
            $ruleConfig = $policy['statuses'][$latestStatus] ?? $policy;
            $eventMode = $ruleConfig['mode'] ?? $mode;
            $amount = (float) $event->commission;

            if ($eventMode === 'delta') {
                $accumulated += $amount;
                $value = $accumulated;
            } else {
                $absolute = ($ruleConfig['normalize_absolute_sign'] ?? $policy['normalize_absolute_sign'])
                    ? abs($amount)
                    : $amount;
                $value = $absolute == 0.0 && ($ruleConfig['fallback'] ?? null) === 'accumulated'
                    ? $accumulated
                    : $absolute;
            }

            if (($ruleConfig['mode'] ?? null) === 'absolute' && $latestStatus !== 'rejected') {
                $accumulated = max($accumulated, $value);
            }

            if (($ruleConfig['clamp_to_zero'] ?? $policy['clamp_to_zero']) === true) {
                $value = max(0.0, $value);
            }

            if ($latestStatus === 'rejected') {
                // $value currently holds what the rejection event carried (0, 50, ...).
                // Partial rejection (100 -> 50): the drop is a real loss.
                // Full rejection (100 -> 0): no loss, the whole amount is "rejected".
                if (! $wasRejected && $value > 0.0 && $value < $liveValue) {
                    $loss += $liveValue - $value;
                }

                // Report the amount that was rejected, not the rejection's own amount.
                if ($liveValue > 0.0) {
                    $value = $liveValue;
                }

                $wasRejected = true;
            } else {
                if ($baseline === null || $baseline <= 0.0) {
                    $baseline = $value;
                }

                $liveValue = $value;
                $wasRejected = false;

                // Absolute: expected (first amount) minus current. Negative = gain.
                // Delta: unchanged behaviour.
                $loss = $policy['mode'] === 'absolute'
                    ? $baseline - $value
                    : max(0.0, $accumulated - $value);
            }

            $mode = $eventMode;
            $rule = $latestStatus . ':' . $eventMode;
        }

        return [
            'status' => $latestStatus,
            'value' => $value,
            'accumulated' => $accumulated,
            'loss' => $loss,
            'events_count' => $events->count(),
            'mode' => $mode,
            'rule' => $rule,
        ];
    }

    /**
     * @return array{mode: string, statuses: array<string, array<string, mixed>>, normalize_absolute_sign: bool, clamp_to_zero: bool}
     */
    private function policy(AffiliateCatalog $catalog): array
    {
        $configuration = Container::getInstance()->bound('config')
            ? config('affiliate_commissions')
            : require dirname(__DIR__, 3) . '/config/affiliate_commissions.php';
        $fallback = $configuration['default'];
        $catalogMode = in_array($catalog->commission_mode, ['delta', 'absolute'], true)
            ? $catalog->commission_mode
            : $fallback['mode'];
        $configured = $configuration[$catalog->slug] ?? [];

        return array_replace_recursive(
            $fallback,
            ['mode' => $catalogMode],
            is_array($configured) ? $configured : [],
        );
    }
}

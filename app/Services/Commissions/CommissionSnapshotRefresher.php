<?php

namespace App\Services\Commissions;

use App\Models\Conversion;
use Illuminate\Support\Collection;

final class CommissionSnapshotRefresher
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
    ) {}

    public function refresh(): void
    {
        Conversion::query()
            ->with([
                'conversionEvent.affiliateCatalog',
                'conversionEvent.click.conversionEvents',
            ])
            ->chunkById(250, function (Collection $conversions): void {
                foreach ($conversions as $conversion) {
                    $event = $conversion->conversionEvent;
                    $click = $event?->click;
                    $catalog = $event?->affiliateCatalog;

                    if (! $event || ! $click || ! $catalog || ! $conversion->commission_id) {
                        continue;
                    }

                    $events = $click->conversionEvents
                        ->where('affiliate_catalog_id', $catalog->id)
                        ->where('commission_id', $conversion->commission_id)
                        ->values();
                    $snapshot = $this->calculator->calculate($catalog, $events);
                    $latestEvent = $events->sortByDesc('created_at')->first();

                    $conversion->update([
                        'conversion_event_id' => $latestEvent?->id ?? $conversion->conversion_event_id,
                        'commission' => $snapshot['value'],
                        'status' => $latestEvent?->status ?? $conversion->status,
                        'accumulated_commission' => $snapshot['accumulated'],
                        'loss' => $snapshot['loss'],
                        'calculation_mode' => $snapshot['mode'],
                        'calculation_rule' => $snapshot['rule'],
                        'events_count' => $snapshot['events_count'],
                    ]);
                }
            });
    }
}

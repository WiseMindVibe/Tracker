<?php

namespace App\Services\Affiliates\YieldKit;

use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class YieldKitCommissionImporter
{
    public function __construct(protected YieldKitClient $client)
    {
    }

    public function importByDateRange(CarbonInterface $start, CarbonInterface $end, string $dateType = 'sales'): int
    {
        return $this->import($this->client->fetchCommissionsByDateRange($start, $end, $dateType));
    }

    public function importByDelta(int $delta, string $dateType = 'sales'): int
    {
        return $this->import($this->client->fetchCommissionsByDelta($delta, $dateType));
    }

    protected function import(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if ($this->upsert($row)) {
                $count++;
            }
        }

        return $count;
    }

    protected function upsert(array $row): bool
    {
        $clickId = $this->resolveClickId($row);
        $affiliateId = '26';

        if ($clickId === null) {
            Log::warning('YieldKit commission skipped: no matching click_id', ['row' => $row]);

            return false;
        }

        $event = ConversionEvent::updateOrCreate(
            ['commission_id' => (string) $row['id']],
            [
                'click_id' => $clickId,
                'commission' => $row['commission'] ?? 0,
                'status' => $row['state'] ?? 'UNKNOWN',
                'created_at' => isset($row['date']) ? \Carbon\Carbon::parse($row['date']) : now(),
                'updated_at' => isset($row['modified_date']) ? \Carbon\Carbon::parse($row['modified_date']) : now(),
            ]
        );

        Conversion::updateOrCreate(
            [
                'conversion_id' => $affiliateId . '_' . $row['id'],
            ],
            [
                'conversion_event_id' => $event->id,
            ]
        );

        return true;
    }

    /**
     * YieldKit doesn't return your internal click_id directly. The common
     * pattern is: you pass your own click reference out on the affiliate
     * link as a tracking tag, and the network echoes it back — here, in
     * "ykTag". This assumes ykTag == your clicks.id (numeric).
     *
     * If your tracker ties conversions to clicks differently (e.g. via
     * orderId, a sub-id param, or a separate clicks table lookup by
     * siteId + timestamp), replace this method accordingly — everything
     * else in the importer stays the same.
     */
    protected function resolveClickId(array $row): ?int
{
    $ykTag = $row['ykTag'] ?? null;

    if (empty($ykTag)) {
        return null;
    }

    $click = \App\Models\Click::where('click_id', $ykTag)->first();

    return $click?->id;
}
}

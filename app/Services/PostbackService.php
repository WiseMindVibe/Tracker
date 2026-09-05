<?php

namespace App\Services;

use App\Models\AffiliateCatalog;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Models\Notification;
use App\Services\Postbacks\OponiaPostbackAdapter;
use App\Services\Postbacks\PostbackException;
use App\Services\Postbacks\PostbackResult;
use App\Services\Postbacks\YieldKitPostbackAdapter;
use Illuminate\Support\Facades\DB;

class PostbackService
{
    public function process(string $affiliateSlug, array $payload): PostbackResult
    {
        $catalog = AffiliateCatalog::query()->where('slug', $affiliateSlug)->first();

        if (! $catalog) {
            throw new PostbackException('Unknown affiliate.', 404);
        }

        $event = $this->adapter($affiliateSlug)->normalize($payload);

        $click = Click::query()
            ->with('offer.affiliateAccount.affiliateCatalog')
            ->where('click_id', $event['click_reference'])
            ->first();


        if (! $click || $click->offer?->affiliateAccount?->affiliateCatalog?->is($catalog) !== true) {
            throw new PostbackException('Unknown click.', 404);
        }

        return DB::transaction(function () use ($catalog, $click, $event): PostbackResult {
            $duplicate = ConversionEvent::query()
                ->where('affiliate_catalog_id', $catalog->id)
                ->where('external_event_id', $event['external_event_id'])
                ->lockForUpdate()
                ->first();

            if ($duplicate) {
                return new PostbackResult(false, $duplicate, null);
            }

            $conversionEvent = ConversionEvent::create([
                'click_id' => $click->id,
                'commission_id' => $event['commission_id'],
                'commission' => $event['commission'],
                'status' => $event['status'],
                'affiliate_catalog_id' => $catalog->id,
                'external_event_id' => $event['external_event_id'],
                'event_type' => $event['event_type'],
                'currency' => $event['currency'],
                'event_occurred_at' => $event['event_occurred_at'],
                'created_at' => $event['event_occurred_at'],
                'updated_at' => $event['event_occurred_at'],
            ]);

            $conversionId = $catalog->slug . ':' . $click->id . ':' . $event['commission_id'];
            $conversion = Conversion::query()->updateOrCreate(
                ['conversion_id' => $conversionId],
                [
                    'conversion_event_id' => $conversionEvent->id,
                    'commission_id' => $event['commission_id'],
                    'commission' => $this->finalCommission($catalog, $conversionId, $event['commission']),
                    'status' => $event['status'],
                    'currency' => $event['currency'],
                ],
            );

            $notification = Notification::create([
                'conversion_event_id' => $conversionEvent->id,
                'is_read' => false,
            ]);

            return new PostbackResult(true, $conversionEvent, $notification);
        });
    }

    private function adapter(string $affiliateSlug): YieldKitPostbackAdapter|OponiaPostbackAdapter
    {
        return match (strtolower($affiliateSlug)) {
            'yieldkit' => new YieldKitPostbackAdapter,
            'oponia' => new OponiaPostbackAdapter,
            default => throw new PostbackException('Postbacks are not configured for this affiliate.', 422),
        };
    }

    private function finalCommission(AffiliateCatalog $catalog, string $conversionId, string $amount): string
    {
        if ($catalog->commission_mode !== 'delta') {
            return $amount;
        }

        $previous = ConversionEvent::query()
            ->whereHas('click.offer.affiliateAccount', fn($query) => $query->where('affiliate_catalog_id', $catalog->id))
            ->whereHas('conversions', fn($query) => $query->where('conversion_id', $conversionId))
            ->sum('commission');

        return (string) ((float) $previous + (float) $amount);
    }
}

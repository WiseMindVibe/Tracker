<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCatalog;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Models\Notification;
use App\Services\Postbacks\PostbackAdapterResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostbackController extends Controller
{
    public function __construct(
        private PostbackAdapterResolver $adapterResolver
    ) {}

    public function __invoke(Request $request, string $affiliateCatalog)
    {
        $catalog = AffiliateCatalog::where('slug', $affiliateCatalog)
            ->firstOrFail();

        $adapter = $this->adapterResolver->resolve($catalog);

        $postback = $adapter->parse($request);

        if (
            blank($postback->clickId) ||
            blank($postback->commissionId) ||
            blank($postback->commission) ||
            blank($postback->status)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required postback data.',
            ], 422);
        }

        $click = Click::where('click_id', $postback->clickId)->first();

        if (!$click) {
            return response()->json([
                'success' => false,
                'message' => 'Click not found.',
            ], 404);
        }

        // Serialize processing per (catalog, commission) so two near-simultaneous
        // postbacks for the same commission can't both pass the duplicate check
        // before either has committed its insert.
        $lockKey = "postback:{$catalog->id}:{$postback->commissionId}";
        $lock = Cache::lock($lockKey, 10);

        try {
            $result = $lock->block(5, function () use ($catalog, $click, $postback) {
                return $this->processPostback($catalog, $click, $postback);
            });

            return response()->json([
                'success' => true,
                'message' => $result['duplicate']
                    ? 'Duplicate postback ignored.'
                    : 'Postback processed successfully.',
                //'affiliate' => $catalog->slug,
                //'transaction_id' => $result['conversion']->transaction_id,
                //'event_id' => $result['event']->id,
                //'duplicate' => $result['duplicate'],
                //'notify' => $result['notify'],
            ], 200, [], JSON_PRETTY_PRINT);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not acquire processing lock, please retry.',
            ], 409);
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500, [], JSON_PRETTY_PRINT);
        } finally {
            $lock->release();
        }
    }

    private function processPostback(
        AffiliateCatalog $catalog,
        Click $click,
        $postback
    ): array {
        return DB::transaction(function () use ($catalog, $click, $postback) {

            $existingEvent = ConversionEvent::where('affiliate_catalog_id', $catalog->id)
                ->where('click_id', $click->id)
                ->where('commission_id', $postback->commissionId)
                ->latest('id')
                ->first();

            if ($existingEvent) {

                $sameStatus = $existingEvent->status === $postback->status;

                $sameCommission = round((float) $existingEvent->commission, 2) ===
                    round((float) $postback->commission, 2);

                if ($sameStatus && $sameCommission) {
                    return [
                        'event' => $existingEvent,
                        'conversion' => Conversion::where(
                            'transaction_id',
                            $catalog->id . '_' . $postback->commissionId
                        )->first(),
                        'duplicate' => true,
                        'notify' => false,
                    ];
                }
            }

            $notify = true;

            if ($existingEvent) {
                $statusChanged = $existingEvent->status !== $postback->status;

                $commissionDifference = abs(
                    (float) $postback->commission - (float) $existingEvent->commission
                );

                $commissionChangedMeaningfully = $commissionDifference >= 0.1;

                $notify = $statusChanged || $commissionChangedMeaningfully;
            }

            try {
                $event = ConversionEvent::create([
                    'click_id' => $click->id,
                    'affiliate_catalog_id' => $catalog->id,
                    'commission_id' => $postback->commissionId,
                    'event_id' => $postback->eventId,
                    'event_type' => $postback->eventType,
                    'status' => $postback->status,
                    'commission' => $postback->commission,
                    'currency' => $postback->currency,
                    'created_at' => now(),
                ]);
            } catch (QueryException $e) {
                // 23000 = integrity constraint violation (unique index hit).
                // Belt-and-suspenders: the lock should already prevent this,
                // but if it ever races or expires, fall back to duplicate handling.
                if ($e->getCode() === '23000') {
                    $event = ConversionEvent::where('affiliate_catalog_id', $catalog->id)
                        ->where('click_id', $click->id)
                        ->where('commission_id', $postback->commissionId)
                        ->where('status', $postback->status)
                        ->where('commission', $postback->commission)
                        ->latest('id')
                        ->firstOrFail();

                    return [
                        'event' => $event,
                        'conversion' => Conversion::where(
                            'transaction_id',
                            $catalog->id . '_' . $postback->commissionId
                        )->first(),
                        'duplicate' => true,
                        'notify' => false,
                    ];
                }

                throw $e;
            }

            $transactionId = $catalog->id . '_' . $postback->commissionId;
            $conversion = Conversion::where('transaction_id', $transactionId)->first();

            if (!$conversion) {
                $conversion = Conversion::create([
                    'conversion_event_id' => $event->id,
                    'transaction_id' => $transactionId,
                    'currency' => $postback->currency,
                ]);
            } else {
                $conversion->update([
                    'conversion_event_id' => $event->id,
                    'transaction_id' => $transactionId,
                    'currency' => $postback->currency,
                ]);
            }

            if ($notify) {
                Notification::create([
                    'conversion_event_id' => $event->id,
                    'is_read' => false,
                    'read_at' => null,
                ]);
            }

            return [
                'event' => $event,
                'conversion' => $conversion,
                'duplicate' => false,
                'notify' => $notify,
            ];
        });
    }
}

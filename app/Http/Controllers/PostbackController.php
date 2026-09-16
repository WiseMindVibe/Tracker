<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCatalog;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Models\Notification;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Postbacks\PostbackAdapterResolver;
use App\Services\Telegram\TelegramService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostbackController extends Controller
{
    public function __construct(
        private PostbackAdapterResolver $adapterResolver,
        private CommissionCalculator $commissionCalculator,
        private TelegramService $telegramService,

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
            is_null($postback->commission) ||
            blank($postback->status)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required postback data.',
            ], 422);
        }

        $click = Click::where('click_id', $postback->clickId)->first();

        if (! $click) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown click.',
            ], 404);
        }

        // Serialize processing per (catalog, commission) so two near-simultaneous
        // postbacks for the same commission can't both pass the duplicate check
        // before either has committed its insert.
        $lockKey = "postback:{$catalog->id}:{$click->id}:{$postback->commissionId}:{$postback->eventId}";
        $lock = Cache::lock($lockKey, 10);

        try {
            $result = $lock->block(5, function () use ($catalog, $click, $postback) {
                return $this->processPostback($catalog, $click, $postback);
            });

            // Send Telegram notification for a new/meaningful postback.
            // Duplicate postbacks do not create another Telegram message.
            if (! $result['duplicate'] && $result['notify']) {
                $conversion = $result['conversion'];
                $event = $result['event'];

                $offerName = $click->offer?->name ?? 'Unknown Offer';
                $campaignName = $click->campaign?->name ?? 'Unknown Campaign';

                $eventType = $postback->eventType ?? ' ? ';
                $status = strtolower($event->status ?? '');

                $statusDisplay = match ($status) {
                    'open' => '🟡 OPEN 🟡',
                    'confirmed' => '🔵 CONFIRMED 🔵',
                    'paid' => '🟢 PAID 🟢',
                    'rejected' => '🔴 REJECTED 🔴',
                    default => '⚪ '.strtoupper($event->status ?? 'UNKNOWN'),
                };

                $message =
                    '🔔 <b>!'.e($eventType).'! - '.$statusDisplay.' - '.e($offerName)."</b>\n\n".

                    '💰 <b>Commission</b>    : '.
                    number_format((float) $postback->commission, 2).
                    ' ('.
                    number_format((float) $conversion->commission, 2).
                    ') '.
                    e($conversion->currency ?? '')."\n".

                    '🗣️ <b>Campaign</b>      : '.e($campaignName)."\n\n".

                    '🪪 <b>Click ID</b>      : <code>'.e($click->click_id)."</code>\n".

                    '🆔 <b>Commission ID</b> : <code>'.e($postback->commissionId)."</code>\n".
                    '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -';

                $this->telegramService->send($message);
            }

            return response()->json([
                'success' => true,
                'message' => $result['duplicate']
                    ? 'Duplicate postback ignored.'
                    : 'Postback processed successfully.',
                'duplicate' => $result['duplicate'],
                // 'affiliate' => $catalog->slug,
                // 'transaction_id' => $result['conversion']->transaction_id,
                // 'event_id' => $result['event']->id,
                // 'duplicate' => $result['duplicate'],
                // 'notify' => $result['notify'],
            ], 200, [], JSON_PRETTY_PRINT);
        } catch (LockTimeoutException $e) {

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

            // Same commission ID + same event ID = duplicate.
            $existingEvent = ConversionEvent::where('affiliate_catalog_id', $catalog->id)
                ->where('click_id', $click->id)
                ->where('commission_id', $postback->commissionId)
                ->where('event_id', $postback->eventId)
                ->latest('id')
                ->first();

            if ($existingEvent) {
                return [
                    'event' => $existingEvent,
                    'conversion' => Conversion::where(
                        'transaction_id',
                        $catalog->id.'_'.$click->id.'_'.$postback->commissionId
                    )->first(),
                    'duplicate' => true,
                    'notify' => false,
                ];
            }

            // Different event ID = new event.
            $notify = true;

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

            $transactionId = $catalog->id.'_'.$click->id.'_'.$postback->commissionId;

            $conversion = Conversion::where(
                'transaction_id',
                $transactionId
            )->first();

            $events = ConversionEvent::where('affiliate_catalog_id', $catalog->id)
                ->where('click_id', $click->id)
                ->where('commission_id', $postback->commissionId)
                ->get();

            $snapshot = $this->commissionCalculator->calculate(
                $catalog,
                $events
            );

            if (! $conversion) {
                $conversion = Conversion::create([
                    'conversion_event_id' => $event->id,
                    'transaction_id' => $transactionId,
                    'commission_id' => $postback->commissionId,
                    'commission' => $snapshot['value'],
                    'status' => $event->status,
                    'accumulated_commission' => $snapshot['accumulated'],
                    'loss' => $snapshot['loss'],
                    'calculation_mode' => $snapshot['mode'],
                    'calculation_rule' => $snapshot['rule'],
                    'events_count' => $snapshot['events_count'],
                    'currency' => $postback->currency,
                ]);
            } else {
                $conversion->update([
                    'conversion_event_id' => $event->id,
                    'commission_id' => $postback->commissionId,
                    'commission' => $snapshot['value'],
                    'status' => $event->status,
                    'accumulated_commission' => $snapshot['accumulated'],
                    'loss' => $snapshot['loss'],
                    'calculation_mode' => $snapshot['mode'],
                    'calculation_rule' => $snapshot['rule'],
                    'events_count' => $snapshot['events_count'],
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

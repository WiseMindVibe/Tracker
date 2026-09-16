<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commissionCalculator,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');

        $notifications = Notification::query()
            ->with(['conversionEvent.click', 'conversionEvent.affiliateCatalog'])
            ->when($search, function ($query, $search) {
                $query->whereHas('conversionEvent', function ($q) use ($search) {
                    $q->where('commission_id', 'like', "%{$search}%")
                        ->orWhereHas('click', fn ($c) => $c->where('click_id', 'like', "%{$search}%"))
                        ->orWhereHas('affiliateCatalog', fn ($a) => $a->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Notification $notification) {
                $event = $notification->conversionEvent;
                $click = $event?->click;
                $catalog = $event?->affiliateCatalog;
                $commissionEvents = $click?->conversionEvents
                    ?->where('commission_id', $event?->commission_id)
                    ?? collect();
                $commissionSnapshot = $catalog
                    ? $this->commissionCalculator->calculate($catalog, $commissionEvents)
                    : null;
                $clickSnapshots = $click?->conversionEvents
                    ?->filter(fn ($clickEvent) => $clickEvent->commission_id !== null)
                    ->groupBy('commission_id')
                    ->map(fn ($events) => $catalog
                        ? $this->commissionCalculator->calculate($catalog, $events)
                        : null)
                    ->filter()
                    ?? collect();

                return [
                    'id' => $notification->id,
                    'click_id' => $event?->click?->click_id,
                    'click_pk' => $event?->click_id,
                    'offer' => $event->click->offer->name,
                    'campaign' => $event->click->campaign->name,
                    'traffic_campaign_id' => $event->click->trafficCampaign->traffic_campaign_id,
                    'affiliate' => $event?->affiliateCatalog?->slug,
                    'commission_id' => $event?->commission_id,
                    'status' => $event?->status,
                    'commission' => $event?->commission,
                    'final_commission' => $commissionSnapshot['value'] ?? null,
                    'accumulated_commission' => $commissionSnapshot['accumulated'] ?? null,
                    'loss' => $commissionSnapshot['loss'] ?? null,
                    'click_commission' => $clickSnapshots->sum('value'),
                    'click_loss' => $clickSnapshots->sum('loss'),
                    'currency' => $event?->currency,
                    'event_type' => $event?->event_type,
                    'is_read' => $notification->is_read,
                    'read_at' => $notification->read_at?->toDateTimeString(),
                    'created_at' => $event?->created_at
                        ? Carbon::parse($event->created_at)->toDateTimeString()
                        : null,
                ];
            });

        return Inertia::render('Notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('is_read', false)->count(),
            'search' => $search,
        ]);
    }

    public function toggleRead(Notification $notification)
    {
        $notification->update([
            'is_read' => ! $notification->is_read,
            'read_at' => $notification->is_read ? null : now(),
        ]);

        return back();
    }

    public function markAllRead()
    {
        Notification::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back();
    }
}

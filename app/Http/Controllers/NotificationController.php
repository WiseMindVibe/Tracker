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

    private const PER_PAGE_OPTIONS = [15, 50, 100, 250];

    private const DEFAULT_PER_PAGE = 100;

    public function index(Request $request)
    {
        $search = $request->input('search');

        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $notifications = Inertia::defer(function () use ($search, $perPage) {
            return Notification::query()
                ->with([
                    'conversionEvent.click.offer',
                    'conversionEvent.click.campaign',
                    'conversionEvent.click.conversionEvents',
                    'conversionEvent.affiliateCatalog',
                ])
                ->when($search, function ($query, $search) {
                    $query->whereHas('conversionEvent', function ($q) use ($search) {
                        $q->where('commission_id', 'like', "%{$search}%")
                            ->orWhereHas('click', function ($clickQuery) use ($search) {
                                $clickQuery->where('click_id', 'like', "%{$search}%")
                                    ->orWhereHas('offer', fn ($offerQuery) => $offerQuery->where('name', 'like', "%{$search}%"))
                                    ->orWhereHas('campaign', fn ($campaignQuery) => $campaignQuery->where('name', 'like', "%{$search}%"));
                            })
                            ->orWhereHas('affiliateCatalog', fn ($a) => $a->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
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

                    // Rejected commissions keep their amount for display, but must not count toward the total.
                    $clickCommission = $clickSnapshots
                        ->reject(fn ($snapshot) => $snapshot['status'] === 'rejected')
                        ->sum('value');

                    return [
                        'id' => $notification->id,
                        'click_id' => $click?->click_id,
                        'click_pk' => $event?->click_id,
                        'offer' => $click?->offer?->name,
                        'campaign' => $click?->campaign?->name,
                        'traffic_campaign_id' => $click?->traffic_campaign_id,
                        'affiliate' => $catalog?->slug,
                        'commission_id' => $event?->commission_id,
                        'status' => $event?->status,
                        'commission' => $event?->commission,
                        'final_commission' => $commissionSnapshot['value'] ?? null,
                        'accumulated_commission' => $commissionSnapshot['accumulated'] ?? null,
                        'loss' => $commissionSnapshot['loss'] ?? null,
                        'click_commission' => $clickCommission,
                        'click_loss' => $clickSnapshots->sum('loss'),
                        'currency' => $event?->currency,
                        'event_type' => $event?->event_type,
                        'is_read' => $notification->is_read,
                        'read_at' => $notification->read_at?->toDateTimeString(),
                        'notification_created_at' => $notification->created_at?->toDateTimeString(),
                        'created_at' => $event?->created_at
                            ? Carbon::parse($event->created_at)->toDateTimeString()
                            : null,
                        'click_created_at' => $click?->created_at?->toDateTimeString(),
                    ];
                });
        });

        return Inertia::render('Notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => Inertia::defer(
                fn () => Notification::where('is_read', false)->count()
            ),
            'search' => $search,
            'perPage' => $perPage,

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

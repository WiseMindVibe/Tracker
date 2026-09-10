<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()
            ->with(['conversionEvent.click', 'conversionEvent.affiliateCatalog'])
            ->latest('id')
            ->paginate(25)
            ->through(function (Notification $notification) {
                $event = $notification->conversionEvent;

                return [
                    'id' => $notification->id,
                    'click_id' => $event?->click?->click_id,
                    'click_pk' => $event?->click_id,
                    'affiliate' => $event?->affiliateCatalog?->slug,
                    'commission_id' => $event?->commission_id,
                    'status' => $event?->status,
                    'commission' => $event?->commission,
                    'currency' => $event?->currency,
                    'event_type' => $event?->event_type,
                    'is_read' => $notification->is_read,
                    'read_at' => $notification->read_at?->toDateTimeString(),
                    'created_at' => $event?->created_at
                        ? \Illuminate\Support\Carbon::parse($event->created_at)->toDateTimeString()
                        : null,
                ];
            });

        return Inertia::render('Notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('is_read', false)->count(),
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

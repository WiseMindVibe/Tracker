<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use Inertia\Inertia;

class ConversionController extends Controller
{
    public function index()
    {
        $conversions = Conversion::query()
            ->with([
                'conversionEvent.affiliateCatalog',
                'conversionEvent.click' => function ($query) {
                    $query->withCount('conversionEvents');
                },
            ])
            ->latest('id')
            ->paginate(25)
            ->through(function (Conversion $conversion) {
                $event = $conversion->conversionEvent;
                $catalog = $event?->affiliateCatalog;

                return [
                    'id' => $conversion->id,
                    'click_id' => $event?->click?->click_id,
                    'click_pk' => $event?->click_id,
                    'commission' => $catalog?->commission_mode === 'absolute'
                        ? $event?->commission
                        : null,
                    'commission_mode' => $catalog?->commission_mode,
                    'status' => $event?->status,
                    'transaction_id' => $conversion->transaction_id,
                    'events_count' => $event?->click?->conversion_events_count,
                    'created_at' => $conversion->created_at?->toDateTimeString(),
                    'updated_at' => $conversion->updated_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('Conversions/index', [
            'conversions' => $conversions,
        ]);
    }

    public function history(Click $click)
    {
        $events = ConversionEvent::where('click_id', $click->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $events]);
    }
}

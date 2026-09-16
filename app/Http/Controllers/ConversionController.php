<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ConversionController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commissionCalculator,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');

        $conversions = Conversion::query()
            ->with([
                'conversionEvent.affiliateCatalog',
                'conversionEvent.click.offer',
                'conversionEvent.click.conversionEvents',
            ])
            ->when($search, function ($query, $search) {
                $query->whereHas('conversionEvent', function ($q) use ($search) {
                    $q->where('commission_id', 'like', "%{$search}%")
                        ->orWhereHas('click', function ($c) use ($search) {
                            $c->where('click_id', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'offer',
                                    fn ($o) => $o->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                );
                        });
                });
            })
            ->get();

        /*
     * One row per Click ID.
     *
     * conversions_events.click_id = clicks.id
     * clicks.click_id            = actual external Click ID
     */
        $grouped = $conversions
            ->filter(
                fn (Conversion $conversion) => $conversion->conversionEvent?->click
            )
            ->groupBy(
                fn (Conversion $conversion) => $conversion->conversionEvent->click->click_id
            )
            ->map(function ($conversionGroup) {

                /** @var Conversion $firstConversion */
                $firstConversion = $conversionGroup->first();

                $firstEvent = $firstConversion->conversionEvent;
                $click = $firstEvent?->click;
                $catalog = $firstEvent?->affiliateCatalog;

                if (! $click) {
                    return null;
                }

                /*
 * ---------------------------------------------------------
 * COMMISSIONS
 * ---------------------------------------------------------
 *
 * Calculate the CURRENT value of every commission_id.
 *
 * YieldKit = delta
 * Oponia   = absolute
 */
                $commissions = $click->conversionEvents
                    ->filter(fn ($event) => ! empty($event->commission_id))
                    ->groupBy('commission_id')
                    ->map(function ($events, $commissionId) use ($catalog) {
                        $snapshot = $this->commissionCalculator->calculate($catalog, $events);

                        return [
                            'commission_id' => (string) $commissionId,
                            'status' => $snapshot['status'],
                            'commission' => $snapshot['value'],
                            'accumulated' => $snapshot['accumulated'],
                            'loss' => $snapshot['loss'],
                            'events_count' => $snapshot['events_count'],
                            'commission_mode' => $catalog?->commission_mode,
                            'calculation_mode' => $snapshot['mode'],
                            'calculation_rule' => $snapshot['rule'],
                        ];
                    })
                    ->values()
                    ->all();

                /*
 * ---------------------------------------------------------
 * FINAL COMMISSION FOR THE CLICK
 * ---------------------------------------------------------
 *
 * The final click commission is simply the sum of the
 * CURRENT values of all commission IDs belonging to it.
 *
 * This works for BOTH:
 *
 * YieldKit:
 *     commission_id A = 0
 *     commission_id B = 5
 *     total = 5
 *
 * Oponia:
 *     commission_id A = 6.40
 *     commission_id B = 0.51
 *     total = 6.91
 */
                $commission = collect($commissions)->sum(function ($item) {
                    return (float) ($item['commission'] ?? 0);
                });

                $rejectedCommission = collect($commissions)
                    ->filter(fn ($item) => strtolower($item['status'] ?? '') === 'rejected')
                    ->sum(fn ($item) => (float) ($item['commission'] ?? 0));

                $commission -= $rejectedCommission;

                /*
             * Get the latest event for the overall click.
             */
                $latestEvent = $click->conversionEvents
                    ->sortByDesc('created_at')
                    ->first();

                return [
                    'id' => $firstConversion->id,

                    'click_id' => $click->click_id,
                    'click_pk' => $click->id,

                    'offer' => $click->offer?->name,

                    // FINAL commission for the entire click
                    'commission' => $commission,

                    'commission_mode' => $catalog?->commission_mode,

                    // Individual commission IDs
                    'commissions' => $commissions,

                    'loss' => collect($commissions)->sum(
                        fn ($item) => (float) ($item['loss'] ?? 0)
                    ),

                    // ALL events across all commission IDs
                    'events_count' => $click->conversionEvents->count(),

                    'created_at' => $click->conversionEvents->min('created_at'),
                    'updated_at' => $latestEvent?->created_at,
                ];
            })
            ->filter()
            ->sortByDesc('updated_at')
            ->values();

        /*
     * Manual pagination because grouping happens after retrieving
     * the conversion records.
     */
        $perPage = 25;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $items = $grouped
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        $conversions = new LengthAwarePaginator(
            $items,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Conversions/index', [
            'conversions' => $conversions,
            'search' => $search,
        ]);
    }

    public function history(Click $click)
    {
        $events = ConversionEvent::with('affiliateCatalog')
            ->where('click_id', $click->id)
            ->orderBy('created_at')
            ->get();

        $groups = $events
            ->groupBy(fn ($event) => $event->commission_id ?? '—')
            ->map(function ($commissionEvents, $commissionId) {
                $catalog = $commissionEvents->first()->affiliateCatalog;
                $snapshot = $catalog
                    ? $this->commissionCalculator->calculate($catalog, $commissionEvents)
                    : null;

                return [
                    'commission_id' => $commissionId,
                    'latest_status' => $commissionEvents->last()->status,
                    'snapshot' => $snapshot,
                    'events' => $commissionEvents->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $groups]);
    }
}

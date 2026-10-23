<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\ConversionEvent;
use App\Models\Offer;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use stdClass;

class ConversionController extends Controller
{
    public function __construct(
        private readonly CommissionCalculator $commissionCalculator,
    ) {}

    private const PER_PAGE_OPTIONS = [15, 50, 100];

    private const DEFAULT_PER_PAGE = 100;

    public function index(Request $request): InertiaResponse
    {
        $search = $request->input('search');
        $perPage = $this->perPage($request);
        $isOfferSearch = filled($search) && Offer::query()
            ->where('name', 'like', "%{$search}%")
            ->whereHas('clicks.conversionEvents')
            ->exists();

        $conversions = $isOfferSearch ? null : Inertia::defer(function () use ($search, $perPage) {
            return $this->clicksQuery()
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('click_id', 'like', "%{$search}%")
                            ->orWhereHas('conversionEvents', fn ($event) => $event->where('commission_id', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('conversion_events_max_created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Click $click) => $this->formatClick($click));
        });

        $offerGroups = $isOfferSearch ? Inertia::defer(function () use ($search, $perPage) {
            return DB::table('offers')
                ->join('clicks', 'clicks.offer_id', '=', 'offers.id')
                ->join('conversions_events', 'conversions_events.click_id', '=', 'clicks.id')
                ->leftJoin('affiliate_accounts', 'affiliate_accounts.id', '=', 'offers.affiliate_account_id')
                ->leftJoin('affiliate_catalog', 'affiliate_catalog.id', '=', 'affiliate_accounts.affiliate_catalog_id')
                ->where('offers.name', 'like', "%{$search}%")
                ->groupBy('offers.id', 'offers.name', 'affiliate_catalog.name')
                ->select('offers.id', 'offers.name')
                ->selectRaw('affiliate_catalog.name as affiliate')
                ->selectRaw('COUNT(DISTINCT clicks.id) as conversions_count')
                ->selectRaw('MAX(conversions_events.created_at) as latest_conversion_at')
                ->orderByDesc('latest_conversion_at')
                ->orderBy('offers.name')
                ->orderBy('offers.id')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (stdClass $offer) => [
                    'id' => (int) $offer->id,
                    'name' => $offer->name,
                    'affiliate' => $offer->affiliate,
                    'conversions_count' => (int) $offer->conversions_count,
                    'latest_conversion_at' => $offer->latest_conversion_at,
                ]);
        }) : null;

        return Inertia::render('Conversions/index', [
            'conversions' => $conversions,
            'offerGroups' => $offerGroups,
            'searchMode' => $isOfferSearch ? 'offers' : 'conversions',
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function offerConversions(Request $request, Offer $offer): JsonResponse
    {
        $conversions = $this->clicksQuery()
            ->where('offer_id', $offer->id)
            ->orderByDesc('conversion_events_max_created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->through(fn (Click $click) => $this->formatClick($click));

        return response()->json($conversions);
    }

    /** @return Builder<Click> */
    private function clicksQuery(): Builder
    {
        return Click::query()
            ->whereHas('conversionEvents')
            ->withMax('conversionEvents', 'created_at')
            ->with([
                'offer',
                'conversionEvents.affiliateCatalog',
            ]);
    }

    /** @return array<string, mixed> */
    private function formatClick(Click $click): array
    {
        $commissions = $click->conversionEvents
            ->filter(fn ($event) => ! empty($event->commission_id))
            ->groupBy(fn ($event) => $event->affiliate_catalog_id.':'.$event->commission_id)
            ->map(function ($events) {
                $catalog = $events->first()->affiliateCatalog;
                $snapshot = $this->commissionCalculator->calculate($catalog, $events);

                return [
                    'affiliate_catalog_id' => $catalog->id,
                    'affiliate' => $catalog->name,
                    'commission_id' => (string) $events->first()->commission_id,
                    'status' => $snapshot['status'],
                    'commission' => $snapshot['value'],
                    'accumulated' => $snapshot['accumulated'],
                    'loss' => $snapshot['loss'],
                    'events_count' => $snapshot['events_count'],
                    'commission_mode' => $catalog->commission_mode,
                    'calculation_mode' => $snapshot['mode'],
                    'calculation_rule' => $snapshot['rule'],
                ];
            })
            ->values();

        $commission = $commissions->sum(fn ($item) => (float) $item['commission']);
        $rejectedCommission = $commissions
            ->filter(fn ($item) => strtolower($item['status'] ?? '') === 'rejected')
            ->sum(fn ($item) => (float) $item['commission']);
        $latestEvent = $click->conversionEvents->sortByDesc('created_at')->first();

        return [
            'id' => $click->id,
            'click_id' => $click->click_id,
            'click_pk' => $click->id,
            'offer' => $click->offer?->name,
            'affiliates' => $click->conversionEvents
                ->map(fn ($event) => $event->affiliateCatalog?->name)
                ->filter()
                ->unique()
                ->values(),
            'commission' => $commission - $rejectedCommission,
            'commission_mode' => $commissions->first()['commission_mode'] ?? null,
            'commissions' => $commissions,
            'loss' => $commissions->sum(fn ($item) => (float) $item['loss']),
            'events_count' => $click->conversionEvents->count(),
            'created_at' => $click->conversionEvents->min('created_at'),
            'updated_at' => $latestEvent?->created_at,
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    public function history(Click $click): JsonResponse
    {
        $events = ConversionEvent::with('affiliateCatalog')
            ->where('click_id', $click->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
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
                    'latest_status' => $commissionEvents->first()->status, // was ->last()
                    'snapshot' => $snapshot,
                    'events' => $commissionEvents->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $groups]);
    }
}

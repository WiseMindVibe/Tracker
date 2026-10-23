<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Conversion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /** Conversion statuses that do not count (compared lower-cased). */
    private const EXCLUDED_CONVERSION_STATUSES = ['rejected'];

    public function index(Request $request)
    {
        $currentTime = now()->toDateTimeString();

        // Use $range['start'] / $range['end'] (Y-m-d, null = all time) to filter your stats.
        $range = $this->resolveRange($request);

        return Inertia::render('dashboard', [
            'timezone' => config('app.timezone'),
            'currentTime' => $currentTime,
            'range' => $range,
            // Deferred: the page renders immediately, this loads right after (needs Inertia v2).
            // Separate groups = separate requests, so one slow table doesn't hold up the other.
            'totals' => Inertia::defer(fn () => $this->totalsSummary($range), 'totals'),
            'affiliates' => Inertia::defer(fn () => $this->affiliateSummary($range), 'affiliates'),
            'trafficSources' => Inertia::defer(fn () => $this->trafficSummary($range), 'traffic'),
        ]);
    }

    /**
     * Custom dates / a chosen preset from the query string win;
     * otherwise (first visit, or invalid input) default to "This month".
     */
    private function resolveRange(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'preset' => ['nullable', 'regex:/^(today|yesterday|all-time|custom|(this|previous)-(week|month|year)|last-\d+)$/'],
            'include_today' => ['nullable', 'boolean'],
        ]);

        if (! $validator->fails()) {
            $data = $validator->validated();
            $includeToday = (bool) ($data['include_today'] ?? false);
            $preset = $data['preset'] ?? null;

            if ($preset === 'all-time') {
                return ['start' => null, 'end' => null, 'preset' => 'all-time', 'includeToday' => $includeToday];
            }

            if (! empty($data['start']) && ! empty($data['end'])) {
                return [
                    'start' => $data['start'],
                    'end' => $data['end'],
                    'preset' => $preset ?? 'custom',
                    'includeToday' => $includeToday,
                ];
            }
        }

        $now = now(); // app timezone

        return [
            'start' => $now->copy()->startOfMonth()->toDateString(),
            'end' => $now->copy()->endOfMonth()->toDateString(),
            'preset' => 'this-month',
            'includeToday' => false,
        ];
    }

    // ASSUMPTION - adjust to your conversions table:
    private const CONVERSION_OFFER_COLUMN = 'offer_id';  // conversions.offer_id -> offers.id

    private const CONVERSION_REVENUE_COLUMN = 'payout';  // revenue amount per conversion

    /** Selected range as [from, to] Carbon bounds in the app timezone (null = unbounded). */
    private function bounds(array $range): array
    {
        $tz = config('app.timezone');

        return [
            $range['start'] ? Carbon::parse($range['start'], $tz)->startOfDay() : null,
            $range['end'] ? Carbon::parse($range['end'], $tz)->endOfDay() : null,
        ];
    }

    /**
     * Clicks + cost per group (affiliate account, traffic account...), by click date.
     * $table/$fk/$groupCol are internal constants, never user input.
     * Each click has one row in $table, so the join cannot multiply clicks.
     */
    private function clickStats(string $table, string $fk, string $groupCol, ?Carbon $from, ?Carbon $to)
    {
        return DB::table('clicks as cl')
            ->join("{$table} as g", 'g.id', '=', "cl.{$fk}")
            ->when($from, fn ($q) => $q->where('cl.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('cl.created_at', '<=', $to))
            ->selectRaw("g.{$groupCol} as group_id, COUNT(*) as total, COALESCE(SUM(cl.cost), 0) as cost")
            ->groupBy("g.{$groupCol}")
            ->get()
            ->keyBy('group_id');
    }

    /**
     * Conversions + revenue per group, attributed by the CLICK date so that
     * revenue and cost always describe the same clicks.
     * Each conversion has one event, each event one click.
     */
    private function conversionStats(string $table, string $fk, string $groupCol, ?Carbon $from, ?Carbon $to)
    {
        return DB::table('conversions as c')
            ->join('conversions_events as ce', 'ce.id', '=', 'c.conversion_event_id')
            ->join('clicks as cl', 'cl.id', '=', 'ce.click_id')
            ->join("{$table} as g", 'g.id', '=', "cl.{$fk}")
            ->when($from, fn ($q) => $q->where('cl.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('cl.created_at', '<=', $to))
            ->where(fn ($q) => $this->countableConversions($q))
            ->selectRaw("g.{$groupCol} as group_id, COUNT(*) as total, COALESCE(SUM(c.accumulated_commission), 0) as revenue")
            ->groupBy("g.{$groupCol}")
            ->get()
            ->keyBy('group_id');
    }

    /** Conversions that count (everything except EXCLUDED_CONVERSION_STATUSES). Expects alias `c`. */
    private function countableConversions($query)
    {
        return $query
            ->whereNull('c.status')
            ->orWhereNotIn(DB::raw('LOWER(c.status)'), self::EXCLUDED_CONVERSION_STATUSES);
    }

    /** Grand totals for the summary cards (same rules as the tables, so the numbers match). */
    private function totalsSummary(array $range): array
    {
        [$from, $to] = $this->bounds($range);

        $clicks = DB::table('clicks as cl')
            ->when($from, fn ($q) => $q->where('cl.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('cl.created_at', '<=', $to))
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(cl.cost), 0) as cost')
            ->first();

        $conversions = DB::table('conversions as c')
            ->join('conversions_events as ce', 'ce.id', '=', 'c.conversion_event_id')
            ->join('clicks as cl', 'cl.id', '=', 'ce.click_id')
            ->when($from, fn ($q) => $q->where('cl.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('cl.created_at', '<=', $to))
            ->where(fn ($q) => $this->countableConversions($q))
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(c.accumulated_commission), 0) as revenue')
            ->first();

        return [
            'clicks' => (int) $clicks->total,
            'conversions' => (int) $conversions->total,
            'revenue' => round((float) $conversions->revenue, 5),
            'cost' => round((float) $clicks->cost, 5),
        ];
    }

    /** One row per affiliate account (including ones with no activity). */
    private function affiliateSummary(array $range): array
    {
        [$from, $to] = $this->bounds($range);

        // clicks -> offers.affiliate_account_id
        $clicks = $this->clickStats('offers', 'offer_id', 'affiliate_account_id', $from, $to);
        $conversions = $this->conversionStats('offers', 'offer_id', 'affiliate_account_id', $from, $to);

        return DB::table('affiliate_accounts as aa')
            ->join('affiliate_catalog as ac', 'ac.id', '=', 'aa.affiliate_catalog_id')
            ->orderBy('ac.name')
            ->get(['aa.id', 'ac.name', 'aa.status'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'status' => $a->status,
                'clicks' => (int) ($clicks[$a->id]->total ?? 0),
                'conversions' => (int) ($conversions[$a->id]->total ?? 0),
                'revenue' => round((float) ($conversions[$a->id]->revenue ?? 0), 5),
                'cost' => round((float) ($clicks[$a->id]->cost ?? 0), 5),
            ])
            ->all();
    }

    /** One row per traffic account (including ones with no activity). */
    private function trafficSummary(array $range): array
    {
        [$from, $to] = $this->bounds($range);

        // clicks -> campaigns.traffic_account_id
        $clicks = $this->clickStats('campaigns', 'campaign_id', 'traffic_account_id', $from, $to);
        $conversions = $this->conversionStats('campaigns', 'campaign_id', 'traffic_account_id', $from, $to);

        // campaigns.impressions is a lifetime counter (not stored per day), so it only
        // matches the cost for "All time". For any other range it is sent as null
        // and the table shows CPM as "-" instead of a misleading number.
        $impressions = ($from || $to)
            ? null
            : DB::table('campaigns')
                ->selectRaw('traffic_account_id, COALESCE(SUM(impressions), 0) as total')
                ->groupBy('traffic_account_id')
                ->pluck('total', 'traffic_account_id');

        return DB::table('traffic_accounts as ta')
            ->join('traffic_catalog as tc', 'tc.id', '=', 'ta.traffic_catalog_id')
            ->orderBy('tc.name')
            ->get(['ta.id', 'tc.name', 'ta.status'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'clicks' => (int) ($clicks[$t->id]->total ?? 0),
                'conversions' => (int) ($conversions[$t->id]->total ?? 0),
                'revenue' => round((float) ($conversions[$t->id]->revenue ?? 0), 5),
                'cost' => round((float) ($clicks[$t->id]->cost ?? 0), 5),
                'impressions' => $impressions === null ? null : (int) ($impressions[$t->id] ?? 0),
            ])
            ->all();
    }
}

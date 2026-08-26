<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Conversion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->query('start')
            ? Carbon::parse($request->query('start'))->startOfDay()
            : now()->subDays(6)->startOfDay();

        $end = $request->query('end')
            ? Carbon::parse($request->query('end'))->endOfDay()
            : now()->endOfDay();

        $clicksQuery = Click::whereBetween('created_at', [$start, $end]);
        $totalClicks = (clone $clicksQuery)->count();
        $totalCost = (float) (clone $clicksQuery)->sum('cost');

        $conversionsQuery = Conversion::whereBetween('created_at', [$start, $end]);
        $totalConversions = (clone $conversionsQuery)->count();
        $totalRevenue = 0; //(float) (clone $conversionsQuery)->withSum('commission');

        $profit = $totalRevenue - $totalCost;
        $cr = $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2) : 0;
        $roi = $totalCost > 0 ? round(($profit / $totalCost) * 100, 2) : 0;
        $epc = $totalClicks > 0 ? round($totalRevenue / $totalClicks, 4) : 0;

        // Group clicks and revenue by day, for the trend chart
        $clicksByDay = (clone $clicksQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')->pluck('total', 'date');

        //$revenueByDay = (clone $conversionsQuery)
        //    ->selectRaw('DATE(created_at) as date, SUM(commission) as total')
        //    ->groupBy('date')->pluck('total', 'date');

        $trend = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $d = $date->format('Y-m-d');
            $trend[] = [
                'date' => $d,
                'clicks' => (int) ($clicksByDay[$d] ?? 0),
                'revenue' => (float) ($revenueByDay[$d] ?? 0),
            ];
        }

        return Inertia::render('dashboard', [
            'summary' => [
                'clicks' => $totalClicks,
                'conversions' => $totalConversions,
                'cr' => $cr,
                'cost' => $totalCost,
                'revenue' => $totalRevenue,
                'profit' => $profit,
                'roi' => $roi,
                'epc' => $epc,
            ],
            'trend' => $trend,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Commissions\CommissionSnapshotRefresher;
use App\Services\Reporting\ReportingAccessScope;
use App\Services\Reporting\ReportingRepository;
use App\Services\Reporting\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Throwable;

final class ReportingController extends Controller
{
    /** @var array<string, string> */
    private const AVAILABLE_GROUPS = [
        'offer' => 'Offer',
        'campaign' => 'Campaign',
        'external_campaign_id' => 'External Campaign ID',
        'device' => 'Device',
        'os' => 'OS',
        'os_version' => 'OS Version',
        'browser' => 'Browser',
        'browser_version' => 'Browser Version',
        'country' => 'Country',
        'region' => 'Region',
        'language' => 'Language',
        'connection_type' => 'Connection Type',
        'carrier' => 'Carrier',
        'isp' => 'ISP',
        'zoneid' => 'Zone ID',
        'subzone_id' => 'Subzone ID',
    ];

    public function index(Request $request): InertiaResponse
    {
        $today = now()->toDateString();

        return Inertia::render('Reporting/Index', [
            'defaultDatePreset' => 'last7',
            'defaultDateFrom' => $today,
            'defaultDateTo' => $today,
            'defaultGroupBy' => ['offer', 'campaign', 'os', 'browser'],
            'availableGroups' => self::AVAILABLE_GROUPS,
            'apiEndpoint' => route('reporting.report'),
        ]);
    }

    public function report(Request $request, ReportingAccessScope $scope): JsonResponse
    {
        $user = $request->user();

        $service = new ReportingService(new ReportingRepository(
            $scope->allowedOfferIds($user),
            $scope->allowedCampaignIds($user),
        ), app(CommissionSnapshotRefresher::class));

        try {
            return response()->json($service->buildReport($request->all()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Unable to build report'], 500);
        }
    }
}

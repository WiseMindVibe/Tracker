<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\User;
use App\Services\Reporting\OfferInspectionService;
use App\Services\Reporting\ReportingAccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InspectController extends Controller
{
    public function index(Request $request, ReportingAccessScope $scope): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $allowedOfferIds = $scope->allowedOfferIds($user);
        $offers = Offer::query()
            ->with([
                'blog:id,domain',
                'affiliateAccount:id,affiliate_catalog_id',
                'affiliateAccount.affiliateCatalog:id,name',
            ])
            ->when($allowedOfferIds !== null, fn ($query) => $query->whereIn('id', $allowedOfferIds))
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'blog_id', 'affiliate_account_id'])
            ->map(fn (Offer $offer): array => [
                'id' => $offer->id,
                'name' => $offer->name,
                'country' => $offer->country,
                'blog' => $offer->blog?->domain,
                'affiliateNetwork' => $offer->affiliateAccount?->affiliateCatalog?->name,
            ])
            ->values();

        return Inertia::render('Inspect/Index', [
            'offers' => $offers,
            'selectedOfferId' => $offers->first()['id'] ?? null,
            'analyticsEndpoint' => route('inspect.analytics'),
            'today' => now()->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function analytics(
        Request $request,
        ReportingAccessScope $scope,
        OfferInspectionService $inspection,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'offer_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $allowedOfferIds = $scope->allowedOfferIds($user);
        abort_unless($allowedOfferIds === null || in_array((int) $validated['offer_id'], $allowedOfferIds, true), 404);

        return response()->json($inspection->build(
            (int) $validated['offer_id'],
            $validated['date_from'],
            $validated['date_to'],
            $allowedOfferIds,
            $scope->allowedCampaignIds($user),
        ));
    }
}

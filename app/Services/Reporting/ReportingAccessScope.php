<?php

namespace App\Services\Reporting;

use App\Models\Campaign;
use App\Models\Offer;
use App\Models\User;

/**
 * Resolves which offer_id / campaign_id values a given user is allowed to see
 * in the reporting screen.
 *
 * Tenancy is company-based via the user_companies pivot. Users with no
 * company memberships are treated as unrestricted so reporting matches
 * dashboard / conversions (those screens are not company-scoped yet).
 *
 * Returning null means "do not restrict". Returning [] means "see nothing".
 */
final class ReportingAccessScope
{
    /**
     * @return list<int>|null
     */
    public function allowedOfferIds(User $user): ?array
    {
        $companyIds = $this->companyIdsFor($user);
        if ($companyIds === null) {
            return null;
        }

        return Offer::query()
            ->whereHas('affiliateAccount', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<int>|null null means "do not additionally restrict by campaign ownership"
     */
    public function allowedCampaignIds(User $user): ?array
    {
        $companyIds = $this->companyIdsFor($user);
        if ($companyIds === null) {
            return null;
        }

        $ids = Campaign::query()
            ->whereHas('trafficAccount', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->pluck('id')
            ->all();

        return $ids === [] ? null : $ids;
    }

    /**
     * @return list<int>|null
     */
    private function companyIdsFor(User $user): ?array
    {
        $ids = $user->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->values()->all();

        return $ids === [] ? null : $ids;
    }
}

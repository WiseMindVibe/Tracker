<?php

namespace App\Services\Affiliates;

use App\Models\AffiliateAccount;
use RuntimeException;

/**
 * Looks up API credentials for a given affiliate network by its
 * catalog slug (e.g. "yieldkit"), walking:
 *   affiliate_accounts_credentials -> affiliate_accounts -> affiliate_catalogs
 */
class AffiliateCredentialResolver
{
    /**
     * @return array<string,string> e.g. ['api_key' => '...', 'api_secret' => '...']
     */
    public function get(string $slug): array
    {
        $account = AffiliateAccount::whereHas('AffiliateCatalog', function ($query) use ($slug) {
            $query->where('slug', $slug)->orWhere('name', $slug);
        })->first();

        if (! $account) {
            throw new RuntimeException("No affiliate_accounts row found for network '{$slug}' (checked affiliate_catalogs.slug / .name).");
        }

        $credentials = $account->AffiliateCredentials()->pluck('value', 'key')->toArray();

        if (empty($credentials)) {
            throw new RuntimeException("No credentials found in affiliate_accounts_credentials for affiliate_account_id={$account->id}.");
        }

        return $credentials;
    }
}

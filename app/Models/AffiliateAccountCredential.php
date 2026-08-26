<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ASSUMPTION: this table is a generic key/value credential store, i.e.
 *   id | affiliate_account_id | key | value
 * one row per secret (e.g. one row with key="api_key", one with key="api_secret").
 * This is the natural read of the plural table name "...credentials" and
 * scales cleanly to networks that need more than 2 secrets.
 *
 * If instead your table has literal columns like `api_key` / `api_secret`
 * directly on affiliate_accounts (or on this table, one row per account),
 * skip this model entirely and simplify AffiliateCredentialResolver::get()
 * to just return ['api_key' => $account->api_key, 'api_secret' => $account->api_secret].
 */
class AffiliateAccountCredential extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliateAccountCredentialFactory> */
    use HasFactory;

    protected $table = 'affiliate_accounts_credentials';

    protected $fillable = [
        'affiliate_account_id',
        'label',
        'key',
        'value',
    ];
}

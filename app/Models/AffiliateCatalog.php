<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The catalog of known affiliate networks (YieldKit, Awin, etc).
 * Adjust $table if your actual table name differs.
 */
class AffiliateCatalog extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliateCatalogFactory> */
    use HasFactory;

    protected $table = 'affiliate_catalog';

    protected $fillable = [
        'name',
        'slug',
        'affilaite_token',
        'offer_mode',
        'commission_mode',
        'merchant_id_label',
        'blog_redirect_rate',
    ];

    public $timestamps = false;


    public function affiliateAccounts(): HasMany
    {
        return $this->hasMany(AffiliateAccount::class);
    }

    public function fieldDefinitions(): HasMany
    {
        return $this->hasMany(AffiliateFieldDefinition::class);
    }
}

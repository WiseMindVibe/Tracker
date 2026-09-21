<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Your account with a given affiliate network.
 * Adjust $table / the FK column name if your actual schema differs.
 */
class AffiliateAccount extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliateAccountFactory> */
    use HasFactory;

    protected $table = 'affiliate_accounts';

    protected $fillable = [
        'company_id',
        'affiliate_catalog_id',
        'status',
    ];

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function affiliateCatalog(): BelongsTo
    {
        return $this->belongsTo(AffiliateCatalog::class);
    }

    public function affiliateCredentials()
    {
        return $this->hasMany(AffiliateAccountCredential::class);
    }
}

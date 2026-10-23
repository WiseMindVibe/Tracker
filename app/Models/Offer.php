<?php

namespace App\Models;

use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    public $table = 'offers';

    public $fillable = ['blog_id', 'affiliate_account_id', 'name', 'type', 'merchant_id', 'country', 'affiliate_link', 'is_tester', 'status'];

    /** @return BelongsTo<AffiliateAccount, $this> */
    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    /** @return HasMany<OfferArticle, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(OfferArticle::class);
    }

    /** @return HasMany<Click, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }

    /** @return BelongsTo<Blog, $this> */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}

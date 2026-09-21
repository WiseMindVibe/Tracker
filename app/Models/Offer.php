<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    /** @use HasFactory<\Database\Factories\OfferFactory> */
    use HasFactory;

    public $table = 'offers';

    public $fillable = ['blog_id', 'affiliate_account_id', 'name', 'type', 'merchant_id', 'country', 'affiliate_link', 'is_tester', 'status'];

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(OfferArticle::class);
    }

    public function blog(): BelongsTo { return $this->belongsTo(Blog::class); }

}

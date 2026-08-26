<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferArticle extends Model
{
    /** @use HasFactory<\Database\Factories\OfferArticleFactory> */
    use HasFactory;

    public $table = 'offers_articles';

    public $fillable = ['offer_id', 'article_url'];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}

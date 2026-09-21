<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignOffer extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignOfferFactory> */
    use HasFactory;

    public $table = 'campaigns_offers';

    public $fillable = ['campaign_id', 'offer_id', 'current_views', 'cap_views'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function offer(): BelongsTo { return $this->belongsTo(Offer::class); }

}

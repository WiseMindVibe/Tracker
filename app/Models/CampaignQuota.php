<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = how many clicks a traffic source may send to an offer inside a campaign,
 * and how many it already sent.
 */
class CampaignQuota extends Model
{
    public $table = 'campaigns_quotas';

    public $fillable = ['campaign_id', 'offer_id', 'traffic_account_id', 'quota', 'clicks'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function trafficAccount(): BelongsTo
    {
        return $this->belongsTo(TrafficAccount::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTrafficId extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignTrafficIdFactory> */
    use HasFactory;

    public $table = 'campaigns_traffic_ids';

    public $fillable = ['campaign_id', 'traffic_campaign_id', 'status'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

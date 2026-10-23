<?php

namespace App\Models;

use Database\Factories\ClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Click extends Model
{
    /** @use HasFactory<ClickFactory> */
    use HasFactory;

    public $table = 'clicks';

    public $fillable = [
        'sub_id',
        'click_id',
        'offer_id',
        'campaign_id',
        'traffic_campaign_id',
        'country',
        'region',
        'language',
        'device',
        'os',
        'os_version',
        'browser',
        'browser_version',
        'connection_type',
        'isp',
        'carrier',
        'zoneid',
        'subzone_id',
        'user_agent',
        'user_activity',
        'ip_address',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
    ];

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /** @return HasMany<ConversionEvent, $this> */
    public function conversionEvents(): HasMany
    {
        return $this->hasMany(ConversionEvent::class);
    }
}

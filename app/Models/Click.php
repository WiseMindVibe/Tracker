<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    /** @use HasFactory<\Database\Factories\ClickFactory> */
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

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}

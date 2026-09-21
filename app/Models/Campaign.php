<?php

namespace App\Models;

use App\Modules\Support\TrackingLinkBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory;

    public $table = 'campaigns';

    public $fillable = ['traffic_account_id', 'name', 'country', 'is_tester', 'fallback_url', 'status'];

    protected $appends = ['tracking_link'];

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (empty($campaign->fallback_url)) {
                $campaign->fallback_url = 'https://google.com';
            }
        });
    }

    public function trafficAccount(): BelongsTo
    {
        return $this->belongsTo(TrafficAccount::class);
    }

    public function trafficIds(): HasMany
    {
        return $this->hasMany(CampaignTrafficId::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(CampaignOffer::class); // was lowercase 'campaignOffer::class' — fixed
    }

    /**
     * Only meaningful when total_current_views / total_cap_views were
     * selected via Config::extendQuery() (index listing). Falls back to
     * null (rendered as '-') anywhere else the model is loaded normally.
     */
    protected function trackingLink(): Attribute
    {
        return Attribute::get(
            fn () => TrackingLinkBuilder::build(
                $this->uuid,
                $this->trafficAccount?->trafficCatalog?->slug
            )
        );
    }
}

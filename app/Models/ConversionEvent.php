<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversionEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ConversionEventFactory> */
    use HasFactory;

    protected $table = 'conversions_events';

    protected $fillable = [
        'click_id',
        'affiliate_catalog_id',
        'commission_id',
        'commission',
        'currency',
        'status',
        'event_id',
        'event_type',
        'advertiser_id',
        'sale_date',
        'modified_date',
        'advertiser_sale_amount',
        'advertiser_name',
        'commission_type',
        'payout_id',
        'country_code',
        'site_id',
        'created_at',
    ];

    /**
     * We set created_at / updated_at ourselves from YieldKit's own
     * "date" / "modified_date" fields (so your record reflects when the
     * conversion actually happened / was last changed at YieldKit, not
     * when your import job happened to run). Turning off Eloquent's
     * automatic timestamp management stops it from overwriting those.
     */
    public $timestamps = false;

    protected $casts = [
        'commission' => 'decimal:5',
        'event_occurred_at' => 'datetime',
    ];

    public function click(): BelongsTo
    {
        return $this->belongsTo(Click::class);
    }

    public function affiliateCatalog(): BelongsTo
    {
        return $this->belongsTo(AffiliateCatalog::class);
    }
}

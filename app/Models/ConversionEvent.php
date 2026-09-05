<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversionEvent extends Model
{
    protected $table = 'conversions_events';

    protected $fillable = [
        'click_id',
        'affiliate_catalog_id',
        'commission_id',
        'commission',
        'status',
        'affiliate_catalog_id',
        'external_event_id',
        'event_type',
        'currency',
        'event_occurred_at',
        'created_at',
        'updated_at',
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

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }
}

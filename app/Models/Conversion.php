<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversion extends Model
{
    protected $fillable = [
        'conversion_event_id',
        'conversion_id',
        'commission_id',
        'commission',
        'status',
        'currency',
    ];

    public function conversionEvent(): BelongsTo
    {
        return $this->belongsTo(
            ConversionEvent::class,
            'conversion_event_id'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            ConversionEvent::class,
            'commission_id',
            'commission_id'
        );
    }
}

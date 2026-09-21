<?php

namespace App\Models;

use Database\Factories\ConversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversion extends Model
{
    /** @use HasFactory<ConversionFactory> */
    use HasFactory;

    protected $fillable = [
        'conversion_event_id',
        'transaction_id',
        'commission_id',
        'commission',
        'status',
        'accumulated_commission',
        'loss',
        'calculation_mode',
        'calculation_rule',
        'events_count',
        'currency',
    ];

    protected $casts = [
        'commission' => 'decimal:5',
        'accumulated_commission' => 'decimal:5',
        'loss' => 'decimal:5',
        'events_count' => 'integer',
    ];

    public function conversionEvent(): BelongsTo
    {
        return $this->belongsTo(ConversionEvent::class);
    }
}

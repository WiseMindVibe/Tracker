<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversion extends Model
{
    /** @use HasFactory<\Database\Factories\ConversionFactory> */
    use HasFactory;

    protected $fillable = [
        'conversion_event_id',
        'transaction_id',
        'currency',
    ];

    public function conversionEvent(): BelongsTo
    {
        return $this->belongsTo(ConversionEvent::class);
    }
}

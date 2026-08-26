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

    public $table = 'conversions';

    public $fillable = ['conversion_event_id', 'conversion_id'];

    public function conversionEvent(): BelongsTo
    {
        return $this->belongsTo(ConversionEvent::class);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationFactory> */
    use HasFactory;

    public $table = 'notifications';

    public $fillable = ['conversion_event_id', 'is_read', 'read_at'];

    public function conversionEvent(): BelongsTo
    {
        return $this->belongsTo(ConversionEvent::class);
    }
}

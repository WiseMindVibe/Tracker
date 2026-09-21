<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficAccount extends Model
{
    /** @use HasFactory<\Database\Factories\TrafficAccountFactory> */
    use HasFactory;

    public $table = 'traffic_accounts';

    public $fillable = ['company_id', 'traffic_catalog_id', 'status'];

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function TrafficCatalog(): BelongsTo
    {
        return $this->belongsTo(TrafficCatalog::class);
    }

    public function trafficCredentials(): HasMany
    {
        return $this->hasMany(TrafficAccountCredential::class);
    }
}

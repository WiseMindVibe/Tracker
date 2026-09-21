<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficCatalog extends Model
{
    /** @use HasFactory<\Database\Factories\TrafficCatalogFactory> */
    use HasFactory;

    public $table = 'traffic_catalog';

    public $fillable = ['name'];

    public $timestamps = false;

    public function fieldDefinitions(): HasMany
    {
        return $this->hasMany(TrafficFieldDefinition::class);
    }
}

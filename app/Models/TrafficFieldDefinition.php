<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrafficFieldDefinition extends Model
{
    /** @use HasFactory<\Database\Factories\TrafficFieldDefinitionFactory> */
    use HasFactory;

    public $table = 'traffic_field_definitions';

    public $fillable = ['traffic_catalog_id', 'label', 'field_key'];

    public $timestamps = false;
}

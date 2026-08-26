<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateFieldDefinition extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliateFieldDefinitionFactory> */
    use HasFactory;

    public $table = 'affiliate_field_definitions';

    public $fillable = ['affiliate_catalog_id', 'label', 'field_key'];

    public $timestamps = false;
}

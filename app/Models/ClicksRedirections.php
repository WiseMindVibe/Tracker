<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClicksRedirections extends Model
{
    /** @use HasFactory<\Database\Factories\ClickFactory> */
    use HasFactory;

    public $table = 'clicks_redirections';

    public $fillable = [
        'click_id',
        'status',
    ];

    public $timestamps = false;
}

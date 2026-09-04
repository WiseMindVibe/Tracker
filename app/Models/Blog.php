<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blog extends Model
{
    /** @use HasFactory<\Database\Factories\BlogFactory> */
    use HasFactory;

    public $table = 'blogs';

    public $fillable = ['company_id', 'domain', 'main_geo', 'status'];

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function buffers(): HasMany
    {
        return $this->hasMany(BlogBuffer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Blog;
use App\Models\AffiliateAccount;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    public $table = 'companies';

    public $fillable = ['name', 'slug', 'status'];

    public function Blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function AffiliatesAccounts(): HasMany
    {
        return $this->hasMany(AffiliateAccount::class);
    }
}

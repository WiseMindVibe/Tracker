<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrafficAccountCredential extends Model
{
    /** @use HasFactory<\Database\Factories\TrafficAccountCredentialFactory> */
    use HasFactory;

    public $table = 'traffic_accounts_credentials';

    public $fillable = ['traffic_account_id', 'label', 'key', 'value'];

}

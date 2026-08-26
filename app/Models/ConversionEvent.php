<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionEvent extends Model
{
    protected $table = 'conversions_events';

    protected $fillable = [
        'click_id',
        'commission_id',
        'commission',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * We set created_at / updated_at ourselves from YieldKit's own
     * "date" / "modified_date" fields (so your record reflects when the
     * conversion actually happened / was last changed at YieldKit, not
     * when your import job happened to run). Turning off Eloquent's
     * automatic timestamp management stops it from overwriting those.
     */
    public $timestamps = false;
}

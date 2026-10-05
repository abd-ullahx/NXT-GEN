<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'business_name',
        'trading_region',
        'contact_email',
        'phone',
    ];
}

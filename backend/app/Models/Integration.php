<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'desc_text',
        'category',
        'connected',
        'color',
    ];

    protected $casts = [
        'connected' => 'boolean',
    ];
}

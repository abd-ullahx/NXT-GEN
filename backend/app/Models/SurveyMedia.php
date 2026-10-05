<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyMedia extends Model
{
    protected $fillable = [
        'lead_id',
        'type',          // 'image' | 'video' | 'note'
        'file_url',
        'file_name',
        'file_size',
        'mime_type',
        'notes',
        'caption',
        'surveyor_name',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];
}

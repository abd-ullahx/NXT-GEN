<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyVideo extends Model
{
    protected $table = 'survey_videos';

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'user_id',
        'title',
        'file_name',
        'video_url',
        'file_size',
        'mime_type',
        'duration_seconds',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

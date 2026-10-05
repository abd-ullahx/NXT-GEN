<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCallSession extends Model
{
    protected $fillable = [
        'caller_id',
        'recipient_id',
        'room_id',
        'status',
        'is_video',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_video' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSession extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'caller_id',
        'caller_role',
        'target_role',
        'target_id',
        'accepted_by',
        'room_id',
        'is_video',
        'call_type',
        'status',
        'end_reason',
        'duration_seconds',
        'created_at',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_video' => 'boolean',
        'duration_seconds' => 'integer',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyReminder extends Model
{
    protected $table = 'survey_reminders';

    protected $fillable = [
        'lead_id',
        'type',
        'label',
        'scheduled_at',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    /** The lead this reminder belongs to. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /** Convenience: is this reminder still actionable? */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

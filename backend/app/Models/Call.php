<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Call extends Model
{
    use HasFactory;

    protected $table = 'calls';

    protected $fillable = [
        'contact_id',
        'user_id',
        'provider_call_sid',
        'direction',
        'from_number',
        'to_number',
        'duration_seconds',
        'started_at',
        'ended_at',
        'recording_url',
        'recording_path',
        'transcript',
        'transcript_language',
        'transcript_status',
        'transcript_error',
        'join_token',
        'join_token_expires_at',
    ];

    protected $casts = [
        'duration_seconds'      => 'integer',
        'started_at'            => 'datetime',
        'ended_at'              => 'datetime',
        'join_token_expires_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope to search calls by transcript content.
     */
    public function scopeSearchTranscript(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where('transcript', 'like', "%{$term}%");
    }

    /**
     * Check if recording file exists on configured private disk.
     */
    public function hasRecordingFile(): bool
    {
        if (empty($this->recording_path)) {
            return false;
        }

        $disk = config('services.whisper.disk', 'local');
        return Storage::disk($disk)->exists($this->recording_path);
    }
}

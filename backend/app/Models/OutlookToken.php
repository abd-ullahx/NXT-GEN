<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutlookToken extends Model
{
    protected $table = 'outlook_tokens';

    protected $fillable = [
        'user_email',
        'display_name',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Check if the current token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

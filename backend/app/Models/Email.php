<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $table = 'emails';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'conversation_id',
        'direction',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'body_preview',
        'body_html',
        'is_read',
        'opened_at',
        'received_at',
        'lead_id',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'opened_at'   => 'datetime',
        'received_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    /**
     * Get the lead linked to this email (if any).
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'id');
    }
}

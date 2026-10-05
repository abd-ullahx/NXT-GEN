<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'channel_id',
        'sender_id',
        'receiver_id',
        'message',
        'attachment_url',
        'attachment_name',
        'attachment_mime',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function channel()
    {
        return $this->belongsTo(ChatChannel::class, 'channel_id');
    }
}

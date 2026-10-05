<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatChannel extends Model
{
    protected $table = 'chat_channels';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_private',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'channel_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'chat_channel_user')->withTimestamps();
    }
}

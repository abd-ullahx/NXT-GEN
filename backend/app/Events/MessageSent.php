<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // If the message belongs to a channel (group chat)
        if ($this->message->channel_id) {
            $channels[] = new PrivateChannel('chat.channel.' . $this->message->channel_id);
        }

        // If it's a direct message
        if ($this->message->receiver_id) {
            // Both the sender and receiver should get the event on their personal channel
            $channels[] = new PrivateChannel('chat.' . $this->message->receiver_id);
            $channels[] = new PrivateChannel('chat.' . $this->message->sender_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'            => $this->message->id,
            'channelId'     => $this->message->channel_id,
            'senderId'      => $this->message->sender_id,
            'receiverId'    => $this->message->receiver_id,
            'message'       => $this->message->message,
            'attachmentUrl' => $this->message->attachment_url,
            'attachmentName' => $this->message->attachment_name,
            'attachmentMime' => $this->message->attachment_mime,
            'isRead'        => $this->message->is_read,
            'createdAt'     => $this->message->created_at ? \Carbon\Carbon::parse($this->message->created_at)->toIso8601String() : null,
            'senderName'    => $this->message->sender?->name ?: 'Staff',
            'senderRole'    => $this->message->sender?->role ?: 'staff',
            'senderEmail'   => $this->message->sender?->email ?: '',
        ];
    }
}

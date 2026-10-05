<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ChatMessage;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChatNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;
    protected $receiverId;

    /**
     * Create a new job instance.
     */
    public function __construct($messageId, $receiverId)
    {
        $this->messageId = $messageId;
        $this->receiverId = $receiverId;
    }

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        $receiver = User::find($this->receiverId);
        if (!$receiver || empty($receiver->fcm_token)) {
            return; // Target has no push token
        }

        $chatMessage = ChatMessage::with('sender')->find($this->messageId);
        if (!$chatMessage) {
            return;
        }

        $senderName = $chatMessage->sender ? $chatMessage->sender->name : 'Someone';
        
        // Strip tags in case message contains HTML
        $preview = strip_tags($chatMessage->message);
        if (mb_strlen($preview) > 50) {
            $preview = mb_substr($preview, 0, 47) . '...';
        }
        
        if ($chatMessage->attachment_url && empty($preview)) {
            $preview = 'Sent an attachment 📎';
        }

        $title = "New Message from {$senderName}";
        $body = $preview;
        
        $data = [
            'type' => 'chat',
            'channel_id' => $chatMessage->channel_id,
            'channel_title' => $chatMessage->channel_id && $chatMessage->channel ? $chatMessage->channel->display_name : null,
            'message_id' => $chatMessage->id,
            'screen' => 'chat_detail'
        ];

        $fcmService->sendToDevice($receiver->fcm_token, $title, $body, $data);
    }
}

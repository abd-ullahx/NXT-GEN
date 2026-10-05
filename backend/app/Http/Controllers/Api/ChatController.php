<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    /**
     * Get all available chat channels.
     */
    public function channels(): JsonResponse
    {
        $channels = Cache::remember('chat_channels', 30, function () {
            return ChatChannel::orderBy('name', 'asc')->get()->toArray();
        });
        return response()->json($channels);
    }

    /**
     * Create a new group channel.
     */
    public function createChannel(Request $request): JsonResponse
    {
        if (Auth::user()?->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create group chats.'], 403);
        }

        $request->validate([
            'displayName' => 'required|string|max:100',
            'members'     => 'nullable|array',
        ]);

        $displayName = trim($request->input('displayName'));
        $slug = \Illuminate\Support\Str::slug($displayName);

        $count = ChatChannel::where('name', 'like', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $channel = ChatChannel::create([
            'name'         => $slug,
            'display_name' => $displayName,
            'description'  => $request->input('description', 'Custom group chat created by ' . (Auth::user()?->name ?: 'Staff')),
            'is_private'   => (bool) $request->input('isPrivate', false),
            'created_by'   => Auth::id(),
            'created_at'   => now(),
        ]);

        if ($request->has('members') && is_array($request->input('members'))) {
            $memberIds = $request->input('members');
            // Always include creator
            if (!in_array(Auth::id(), $memberIds)) {
                $memberIds[] = Auth::id();
            }
            $channel->members()->sync($memberIds);
        }

        $channel->load('members:id,name,role');

        return response()->json($channel, 201);
    }

    /**
     * Delete a channel.
     */
    public function deleteChannel(int $id): JsonResponse
    {
        if (Auth::user()?->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete group chats.'], 403);
        }

        $channel = ChatChannel::find($id);
        if (!$channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        // Explicitly delete messages if cascade is not set up
        ChatMessage::where('channel_id', $channel->id)->delete();
        $channel->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get internal staff team members for Direct Messages (excluding self).
     */
    public function members(Request $request): JsonResponse
    {
        $email = $request->query('email') ?: ($request->query('user_email') ?: $request->query('sender_email'));
        $currentUserId = Auth::id() ?: ($email ? User::where('email', strtolower(trim($email)))->value('id') : null);

        // Removed synchronous last_seen_at update to optimize read performance
        $users = User::query()
            ->when($currentUserId, function ($q) use ($currentUserId) {
                $q->where('id', '!=', $currentUserId);
            })
            ->select(['id', 'name', 'email', 'role', 'last_seen_at'])
            ->orderBy('name', 'asc')
            ->get();

        // Batch fetch all unread counts in a SINGLE query instead of N+1
        $unreadCounts = [];
        if ($currentUserId) {
            $unreadCounts = ChatMessage::where('receiver_id', $currentUserId)
                ->where('is_read', false)
                ->selectRaw('sender_id, COUNT(*) as cnt')
                ->groupBy('sender_id')
                ->pluck('cnt', 'sender_id')
                ->toArray();
        }

        $result = $users->map(function ($u) use ($currentUserId, $unreadCounts) {
                $unread = $unreadCounts[$u->id] ?? 0;
                $isOnline = $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(3));

                return [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'email'       => $u->email,
                    'role'        => $u->role ?: 'staff',
                    'unreadCount' => $unread,
                    'isOnline'    => (bool) $isOnline,
                    'lastSeenText' => $isOnline ? 'Online now' : ($u->last_seen_at ? 'Last active ' . $u->last_seen_at->diffForHumans() : 'Offline'),
                ];
            });

        return response()->json($result);
    }

    /**
     * Fetch message history for a channel or a 1-on-1 DM thread.
     */
    public function messages(Request $request): JsonResponse
    {
        $channelId     = $request->query('channel_id') ?: $request->query('channelId');
        $receiverId    = $request->query('receiver_id') ?: $request->query('receiverId');
        $receiverEmail = $request->query('receiver_email') ?: $request->query('receiverEmail');
        $senderEmail   = $request->query('sender_email') ?: ($request->query('user_email') ?: $request->query('email'));
        $afterId  = $request->query('after_id') ?: $request->query('afterId'); // Delta fetching for live sync
        $beforeId = $request->query('before_id') ?: $request->query('beforeId'); // Historical scroll loading
        $limit    = (int) ($request->query('limit') ?: 50);
        if ($limit < 1 || $limit > 100) $limit = 50;

        $currentUserId = Auth::id() ?: ($senderEmail ? User::where('email', strtolower(trim($senderEmail)))->value('id') : null);
        if (!$receiverId && $receiverEmail) {
            $receiverId = User::where('email', strtolower(trim($receiverEmail)))->value('id');
        }

        // Removed synchronous last_seen_at update to optimize read performance
        if ($channelId) {
            $query = ChatMessage::with('sender:id,name,email,role')
                ->where('channel_id', $channelId);
                
            if ($afterId) {
                $query->where('id', '>', $afterId);
            }
            if ($beforeId) {
                $query->where('id', '<', $beforeId);
            }
                
            $messages = $query->orderBy('id', 'desc')
                ->take($limit)
                ->get()
                ->reverse()
                ->values();
        } elseif ($receiverId && $currentUserId) {
            $query = ChatMessage::with(['sender:id,name,email,role', 'receiver:id,name,email,role'])
                ->where(function ($q) use ($currentUserId, $receiverId) {
                    $q->where('sender_id', $currentUserId)->where('receiver_id', $receiverId);
                })
                ->orWhere(function ($q) use ($currentUserId, $receiverId) {
                    $q->where('sender_id', $receiverId)->where('receiver_id', $currentUserId);
                });
                
            if ($afterId) {
                $query->where('id', '>', $afterId);
            }
            if ($beforeId) {
                $query->where('id', '<', $beforeId);
            }
                
            $messages = $query->orderBy('id', 'desc')
                ->take($limit)
                ->get()
                ->reverse()
                ->values();

            // Mark unread DM messages as read ONLY IF we fetched them
            if ($messages->isNotEmpty()) {
                ChatMessage::where('sender_id', $receiverId)
                    ->where('receiver_id', $currentUserId)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }
        } else {
            return response()->json([], 200);
        }

        $formatted = $messages->map(function ($m) {
            return [
                'id'            => $m->id,
                'channelId'     => $m->channel_id,
                'senderId'      => $m->sender_id,
                'receiverId'    => $m->receiver_id,
                'message'       => $m->message,
                'attachmentUrl' => $m->attachment_url,
                'attachmentName' => $m->attachment_name,
                'attachmentMime' => $m->attachment_mime,
                'isRead'        => $m->is_read,
                'createdAt'     => $m->created_at ? \Carbon\Carbon::parse($m->created_at)->toIso8601String() : null,
                'senderName'    => $m->sender?->name ?: 'Staff',
                'senderRole'    => $m->sender?->role ?: 'staff',
                'senderEmail'   => $m->sender?->email ?: '',
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Send a message to a channel or direct message.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message'         => 'nullable|string',
            // Mobile cameras and document providers may send HEIC, 3GP,
            // M4A, or application/octet-stream; enforce size here and keep
            // the original filename/type for the client renderer.
            'attachment'      => 'nullable|file|max:51200', // 50MB max
            'attachment_file' => 'nullable|file|max:51200',
        ]);

        $channelId     = $request->input('channelId') ?: $request->input('channel_id');
        $receiverId    = $request->input('receiverId') ?: $request->input('receiver_id');
        $receiverEmail = $request->input('receiverEmail') ?: $request->input('receiver_email');
        $senderEmail   = $request->input('senderEmail') ?: ($request->input('user_email') ?: $request->input('email'));
        $senderId      = Auth::id() ?: ($request->input('senderId') ?: ($senderEmail ? User::where('email', strtolower(trim($senderEmail)))->value('id') : null));
        $text          = $request->input('message') ?? '';

        if (!$receiverId && $receiverEmail) {
            $receiverId = User::where('email', strtolower(trim($receiverEmail)))->value('id');
        }

        if (!$channelId && !$receiverId) {
            return response()->json(['error' => 'Specify a channel or recipient user.'], 422);
        }

        if (!$senderId) {
            return response()->json(['error' => 'Sender user not identified. Pass senderEmail or user_email.'], 422);
        }

        $fileKey = $request->hasFile('attachment') ? 'attachment' : ($request->hasFile('attachment_file') ? 'attachment_file' : null);

        if (!$text && !$fileKey) {
            return response()->json(['error' => 'Message or attachment is required.'], 422);
        }

        $attachmentUrl = null;
        $attachmentName = null;
        $attachmentMime = null;
        if ($fileKey) {
            $file = $request->file($fileKey);
            $path = $file->store('chat_attachments', 'public');
            $attachmentUrl = url('storage/' . $path);
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getMimeType() ?: $file->getClientMimeType();
        }

        $msg = ChatMessage::create([
            'channel_id'     => $channelId ?: null,
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId ?: null,
            'message'        => $text,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'is_read'        => false,
            'created_at'     => now(),
        ]);

        $msg->load('sender:id,name,email,role');

        // Clear chat member cache so unread counts update immediately
        Cache::forget("chat_members_{$receiverId}");
        Cache::forget("chat_members_{$senderId}");

        if ($receiverId) {
            \App\Jobs\SendChatNotificationJob::dispatch($msg->id, $receiverId);
        }

        // Broadcast the real-time event to WebSockets
        broadcast(new \App\Events\MessageSent($msg));

        return response()->json([
            'id'            => $msg->id,
            'channelId'     => $msg->channel_id,
            'senderId'      => $msg->sender_id,
            'receiverId'    => $msg->receiver_id,
            'message'       => $msg->message,
            'attachmentUrl' => $msg->attachment_url,
            'attachmentName' => $msg->attachment_name,
            'attachmentMime' => $msg->attachment_mime,
            'isRead'        => $msg->is_read,
            'createdAt'     => $msg->created_at ? \Carbon\Carbon::parse($msg->created_at)->toIso8601String() : null,
            'senderName'    => $msg->sender?->name ?: 'Staff',
            'senderRole'    => $msg->sender?->role ?: 'staff',
            'senderEmail'   => $msg->sender?->email ?: '',
        ], 201);
    }

    /**
     * Edit (update) a message — only the original sender can edit.
     */
    public function updateMessage(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $msg = ChatMessage::find($id);
        if (!$msg) {
            return response()->json(['error' => 'Message not found'], 404);
        }
        if ($msg->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden — you can only edit your own messages'], 403);
        }

        $msg->update([
            'message'    => $request->input('message'),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id'        => $msg->id,
            'message'   => $msg->message,
            'updatedAt' => $msg->updated_at ? \Carbon\Carbon::parse($msg->updated_at)->toIso8601String() : null,
            'isEdited'  => true,
        ]);
    }

    /**
     * Delete a message — only the original sender can delete.
     */
    public function deleteMessage(int $id): JsonResponse
    {
        $msg = ChatMessage::find($id);
        if (!$msg) {
            return response()->json(['error' => 'Message not found'], 404);
        }
        if ($msg->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden — you can only delete your own messages'], 403);
        }

        $msg->delete();

        return response()->json(['success' => true, 'deletedId' => $id]);
    }
}

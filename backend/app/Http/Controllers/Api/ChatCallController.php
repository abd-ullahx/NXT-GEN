<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatCallController extends Controller
{
    private function resolveUserId(Request $request): string
    {
        if ($user = $request->user()) {
            return (string) $user->id;
        }

        $id = $request->input('caller_id')
            ?: ($request->input('callerId')
            ?: ($request->query('user_id')
            ?: ($request->query('userId')
            ?: ($request->input('user_id')
            ?: ($request->input('userId')
            ?: ($request->query('surveyor_id')
            ?: ($request->header('X-User-Id') ?: '1')))))));

        return (string) $id;
    }

    private function resolveUserRole(Request $request): string
    {
        if ($user = $request->user()) {
            return $user->role ?: 'staff';
        }
        return $request->input('caller_role') ?: ($request->input('callerRole') ?: 'surveyor');
    }

    public function start(Request $request): JsonResponse
    {
        set_time_limit(0);

        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'is_video'     => 'required|boolean',
        ]);

        $callerId = $this->resolveUserId($request);
        $callerRole = $this->resolveUserRole($request);

        if ((int) $data['recipient_id'] === (int) $callerId) {
            return response()->json(['success' => false, 'error' => 'You cannot call yourself.'], 422);
        }

        $recipient = User::findOrFail($data['recipient_id']);

        // Expire any existing hanging or stale call sessions between these two users
        CallSession::where(function ($query) use ($callerId, $data) {
            $query->where(function ($q) use ($callerId, $data) {
                $q->where('caller_id', $callerId)->where('target_id', (string) $data['recipient_id']);
            })->orWhere(function ($q) use ($callerId, $data) {
                $q->where('caller_id', (string) $data['recipient_id'])->where('target_id', $callerId);
            });
        })
            ->whereIn('status', ['ringing', 'in_progress'])
            ->update([
                'status'     => 'ended',
                'end_reason' => 'replaced',
                'ended_at'   => now(),
            ]);

        $session = CallSession::create([
            'caller_id'   => $callerId,
            'caller_role' => $callerRole,
            'target_role' => $recipient->role ?: 'staff',
            'target_id'   => (string) $recipient->id,
            'room_id'     => 'chat_' . Str::lower(Str::random(24)),
            'status'      => 'ringing',
            'is_video'    => $data['is_video'],
            'call_type'   => $data['is_video'] ? 'video' : 'audio',
        ]);

        $callerUser = User::find($callerId);

        // Dispatch High-Priority VoIP FCM Data Push to recipient if device FCM token is registered
        if (!empty($recipient->fcm_token)) {
            try {
                $fcm = app(\App\Services\FcmService::class);
                $fcm->sendIncomingCallPush($recipient->fcm_token, [
                    'call_id'       => (string) $session->id,
                    'room_id'       => (string) $session->room_id,
                    'caller_id'     => (string) $callerId,
                    'caller_name'   => (string) ($callerUser?->name ?: 'Admin Office'),
                    'caller_email'  => (string) ($callerUser?->email ?: 'admin@nextgenrelocation.co.uk'),
                    'caller_role'   => (string) $callerRole,
                    'is_video'      => $session->is_video ? 'true' : 'false',
                    'status'        => 'ringing',
                    'zego_app_id'   => (string) config('services.zego.app_id', '949748271'),
                    'zego_app_sign' => (string) config('services.zego.app_sign', ''),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("FCM Push trigger failed for call #{$session->id}: " . $e->getMessage());
            }
        }

        // Create a WhatsApp-style call log system message in chat
        $callTypeLabel = $data['is_video'] ? 'Video call' : 'Voice call';
        $chatMsg = \App\Models\ChatMessage::create([
            'sender_id'   => (int) $callerId,
            'receiver_id' => (int) $recipient->id,
            'message'     => json_encode([
                'type'              => 'call_log',
                'call_id'           => $session->id,
                'call_type'         => $session->call_type,
                'is_video'          => (bool) $session->is_video,
                'status'            => 'outgoing',
                'title'             => $callTypeLabel,
                'duration_seconds'  => 0,
            ]),
            'is_read'     => false,
            'created_at'  => now(),
        ]);

        return $this->present($session->load(['caller:id,name,role', 'target:id,name,role']));
    }

    public function incoming(Request $request): JsonResponse
    {
        set_time_limit(0);

        $targetId = $this->resolveUserId($request);

        // Find any active ringing call targeting this user OR any admin if targetId is an admin
        $session = CallSession::with(['caller:id,name,role', 'target:id,name,role'])
            ->where(function ($q) use ($targetId) {
                $q->where('target_id', $targetId)
                  ->orWhereNull('target_id');
            })
            ->where('status', 'ringing')
            ->latest('created_at')
            ->first();

        // Check timeout (>35s)
        if ($session && $session->created_at?->isBefore(now()->subSeconds(35))) {
            $session->update(['status' => 'missed', 'end_reason' => 'no_answer', 'ended_at' => now()]);
            $this->syncCallChatMessage($session->fresh());

            if ($session->target && !empty($session->target->fcm_token)) {
                try {
                    app(\App\Services\FcmService::class)->sendCallCancelledPush($session->target->fcm_token, $session->id, 'missed');
                } catch (\Throwable $e) {}
            }
            $session = null;
        }

        return $session ? $this->present($session) : response()->json(['success' => true, 'call' => null]);
    }

    /**
     * Poll current call status — used by caller side to detect when recipient accepts.
     * GET /api/chat/calls/{id}/status
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $session = CallSession::with(['caller:id,name,role', 'target:id,name,role'])->find($id);

        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Call session not found.'], 404);
        }

        // Auto-expire timed-out ringing sessions
        if ($session->status === 'ringing' && $session->created_at?->isBefore(now()->subSeconds(35))) {
            $session->update(['status' => 'missed', 'end_reason' => 'no_answer', 'ended_at' => now()]);
            $this->syncCallChatMessage($session->fresh());

            if ($session->target && !empty($session->target->fcm_token)) {
                try {
                    app(\App\Services\FcmService::class)->sendCallCancelledPush($session->target->fcm_token, $session->id, 'missed');
                } catch (\Throwable $e) {}
            }

            $session->refresh();
        }

        return $this->present($session);
    }

    public function action(Request $request, int $id): JsonResponse
    {
        set_time_limit(0);

        $data = $request->validate(['action' => 'required|in:accept,decline,end']);
        $session = CallSession::with(['caller:id,name,role', 'target:id,name,role'])->find($id);

        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Call session not found.'], 404);
        }

        $userId = $this->resolveUserId($request);

        $status = match ($data['action']) {
            'accept'  => 'in_progress',
            'decline' => 'declined',
            'end'     => 'completed',
        };

        $updates = [
            'status'     => $status,
            'end_reason' => $status === 'declined' ? 'declined' : ($status === 'completed' ? 'hangup' : null),
        ];
        if ($status === 'in_progress') {
            $updates['accepted_by'] = $userId;
            $updates['started_at']  = now();
        }
        if (in_array($status, ['declined', 'completed'], true)) {
            $updates['ended_at']        = now();
            $updates['duration_seconds'] = $session->started_at ? max(0, $session->started_at->diffInSeconds(now())) : 0;

            // Notify other party via FCM to dismiss ringing UI
            $otherUserId = ((string) $session->caller_id === (string) $userId) ? $session->target_id : $session->caller_id;
            if ($otherUserId && ($otherUser = User::find($otherUserId)) && !empty($otherUser->fcm_token)) {
                try {
                    app(\App\Services\FcmService::class)->sendCallCancelledPush($otherUser->fcm_token, $session->id, $status);
                } catch (\Throwable $e) {}
            }
        }
        $session->update($updates);
        $session = $session->fresh();

        $this->syncCallChatMessage($session);

        return $this->present($session);
    }

    private function syncCallChatMessage(CallSession $session): void
    {
        if (!$session->caller_id || !$session->target_id) return;

        $msgs = \App\Models\ChatMessage::where('sender_id', (int) $session->caller_id)
            ->where('receiver_id', (int) $session->target_id)
            ->where('message', 'like', '%"call_id":' . $session->id . '%')
            ->get();

        $title = $session->is_video ? 'Video call' : 'Voice call';

        foreach ($msgs as $msg) {
            $data = json_decode($msg->message, true);
            if (is_array($data) && isset($data['type']) && $data['type'] === 'call_log') {
                $data['status'] = $session->status;
                $data['end_reason'] = $session->end_reason;
                $data['duration_seconds'] = (int) $session->duration_seconds;
                $data['title'] = $title;
                $msg->update(['message' => json_encode($data)]);
            }
        }
    }

    private function present(CallSession $session): JsonResponse
    {
        return response()->json([
            'success' => true,
            'call'    => [
                'id'              => $session->id,
                'roomId'          => $session->room_id,
                'status'          => $session->status,
                'callType'        => $session->call_type,
                'isVideo'         => (bool) $session->is_video,
                'callerId'        => $session->caller_id,
                'callerName'      => $session->caller?->name ?: 'Team Member',
                'callerRole'      => $session->caller?->role ?: $session->caller_role,
                'recipientId'     => $session->target_id,
                'recipientName'   => $session->target?->name ?: 'Staff',
                'acceptedBy'      => $session->accepted_by,
                'endReason'       => $session->end_reason,
                'durationSeconds' => $session->duration_seconds,
                'zegoAppId'       => (int) config('services.zego.app_id'),
                'zegoAppSign'     => config('services.zego.app_sign'),
                'zegoServerSecret' => config('services.zego.server_secret'),
            ],
        ]);
    }
}
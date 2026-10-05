<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoCallController extends Controller
{
    /**
     * Create a new HD virtual survey / team video call room.
     */
    public function createRoom(Request $request): JsonResponse
    {
        $leadId   = $request->input('lead_id');
        $leadName = $request->input('lead_name', 'Virtual Survey');

        $roomCode = null;
        if ($leadId) {
            $lead = \App\Models\Lead::find($leadId);
            if ($lead && $lead->video_call_room_id) {
                $roomCode = $lead->video_call_room_id;
            }
        }

        if (!$roomCode) {
            $roomCode = $leadId ? ("ROOM-" . $leadId) : ("NextGen-" . strtoupper(Str::random(8)));
        }

        // Jitsi / Zego room URL
        $jitsiRoomUrl = "https://meet.jit.si/" . $roomCode;

        return response()->json([
            'roomCode'     => $roomCode,
            'roomUrl'      => $jitsiRoomUrl,
            'leadId'       => $leadId,
            'leadName'     => $leadName,
            'zegoAppId'    => (int) config('services.zego.app_id'),
            'zegoAppSign'  => config('services.zego.app_sign'),
            'zegoServerSecret' => config('services.zego.server_secret'),
            'created_at'   => now()->toIso8601String(),
            'inviteText'   => "Join Next Gen Relocation Live Video Call: {$jitsiRoomUrl}",
        ]);
    }
}

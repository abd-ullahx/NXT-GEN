<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Currently broadcasting to all admins or the specific user
        $user = $request->user();
        
        $notifications = AppNotification::where(function($q) use ($user) {
                $q->whereNull('user_id');
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->take(50)
            ->get();

        $unreadCount = $notifications->where('is_read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = AppNotification::findOrFail($id);
        $notification->update(['is_read' => true]);
        
        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        AppNotification::where(function($q) use ($user) {
                $q->whereNull('user_id');
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        return response()->json(['message' => 'All notifications marked as read']);
    }
}

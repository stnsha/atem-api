<?php

namespace App\Http\Controllers;

use App\Models\AtemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemNotificationController extends Controller
{
    /**
     * GET /api/notifications?staff_id=&limit=
     */
    public function index(Request $request): JsonResponse
    {
        $staffId = (int) $request->query('staff_id', 0);
        if ($staffId <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing staff_id'], 422);
        }

        $limit = min((int) $request->query('limit', 20), 50);

        $recent = AtemNotification::where('recipient_staff_id', $staffId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $unreadCount = AtemNotification::where('recipient_staff_id', $staffId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => $recent,
            'meta'    => ['unread_count' => $unreadCount],
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = AtemNotification::findOrFail($id);

        $staffId = (int) $request->input('recipient_staff_id', 0);
        if ($staffId === 0 || $staffId !== (int) $notification->recipient_staff_id) {
            return response()->json(['success' => false, 'message' => 'Not your notification.'], 403);
        }

        if (!$notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * PATCH /api/notifications/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $staffId = (int) $request->input('recipient_staff_id', 0);
        if ($staffId <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing recipient_staff_id'], 422);
        }

        AtemNotification::where('recipient_staff_id', $staffId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Notification feed for the authenticated user. Newest first.
     *
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread_only' => 'nullable|in:0,1,true,false',
            'type' => 'nullable|string|in:'.implode(',', UserNotification::TYPES),
        ]);

        $notifications = $request->user()->appNotifications()
            ->with('project')
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $notifications->map(fn ($n) => $this->resource($n)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ]);
    }

    /**
     * Unread count, for the bell badge.
     *
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count()],
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = $request->user()->appNotifications()->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'Notification marked as read.', 'data' => $this->resource($notification)]);
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => "{$count} notification(s) marked as read."]);
    }

    private function resource(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'project' => $notification->project ? ['id' => $notification->project->id, 'name' => $notification->project->name] : null,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toDateTimeString(),
            'time_ago' => $notification->created_at?->diffForHumans(),
        ];
    }
}

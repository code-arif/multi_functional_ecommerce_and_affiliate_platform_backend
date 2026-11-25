<?php

namespace Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Notifications\Transformers\NotificationResource;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/notifications
     * Retrieve paginated notifications and unread count for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = $user->notifications()->latest();

        // Optional filter: unread only
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        // Optional filter: type
        if ($request->filled('type') && $request->type !== 'all') {
            $type = strtolower($request->type);
            $query->where(function ($q) use ($type) {
                $q->where('type', 'like', "%{$type}%")
                  ->orWhere('data->type', $type);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data'    => NotificationResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'unread'  => $unreadCount,
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markAsRead(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Marked as read.',
            'unread'  => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/v1/notifications/read-all
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'unread'  => 0,
        ]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Dismiss / delete a single notification.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->notifications()->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
            'unread'  => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * DELETE /api/v1/notifications
     * Clear all read notifications for current user.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->notifications()->whereNotNull('read_at')->delete();

        return response()->json([
            'success' => true,
            'message' => 'Read notifications cleared.',
            'unread'  => $user->unreadNotifications()->count(),
        ]);
    }
}

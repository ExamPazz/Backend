<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'type' => 'nullable|string',
                'search' => 'nullable|string',
                'sort' => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $notifications = $this->notificationService->getUserNotifications(
                $request->user(),
                $filters
            );

            return ApiResponse::success(
                'Notifications retrieved successfully',
                NotificationResource::collection($notifications)
            );
        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $notification = $this->notificationService->getNotification(
                request()->user(),
                $id
            );

            return ApiResponse::success(
                'Notification retrieved successfully',
                new NotificationResource($notification)
            );
        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    public function markAsRead(string $id)
    {
        try {
            $this->notificationService->markAsRead(request()->user(), $id);
            return ApiResponse::success('Notification marked as read');
        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    public function markAllAsRead()
    {
        try {
            $this->notificationService->markAllAsRead(request()->user());
            return ApiResponse::success('All notifications marked as read');
        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->notificationService->deleteNotification(request()->user(), $id);
            return ApiResponse::success('Notification deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }
}

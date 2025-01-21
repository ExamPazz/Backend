<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getUserNotifications(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->notifications();

        if (isset($filters['type'])) {
            $query->where('type', 'LIKE', "%{$filters['type']}%");
        }

        if (isset($filters['search'])) {
            $query->where('data', 'LIKE', "%{$filters['search']}%");
        }

        $sort = $filters['sort'] ?? 'desc';
        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', $sort)
            ->paginate($perPage);
    }

    public function getNotification(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->findOrFail($id);

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return $notification;
    }

    public function markAsRead(User $user, string $id): void
    {
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function deleteNotification(User $user, string $id): void
    {
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();
    }
}

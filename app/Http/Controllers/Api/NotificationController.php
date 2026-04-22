<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $member = $request->attributes->get('effectiveMember') ?? $request->user()->member;

        if (!$member) {
            return $this->error('Member not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $notifications = $member->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'message'    => $n->data['message'] ?? '',
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at->toISOString(),
            ]);

        $unread_count = $member->unreadNotifications()->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count'  => $unread_count,
        ], 'Notifications loaded.');
    }

    public function markRead(Request $request, string $id)
    {
        $member = $request->attributes->get('effectiveMember') ?? $request->user()->member;

        $notification = $member->notifications()->find($id);

        if (!$notification) {
            return $this->error('Notification not found.', 'NOT_FOUND', [], 404);
        }

        $notification->markAsRead();

        return $this->success(null, 'Marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $member = $request->attributes->get('effectiveMember') ?? $request->user()->member;
        $member->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }
}
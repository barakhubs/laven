<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $user            = $request->user();
        $effectiveMember = $request->attributes->get('effectiveMember');
        $member          = $effectiveMember ?? $user->member;

        Log::info('[NotificationController@index]', [
            'user_id'               => $user?->id,
            'user_email'            => $user?->email,
            'user_is_staff'         => $user?->user_type === 'user',
            'x_client_id_header'    => $request->header('X-Client-Id'),
            'effectiveMember_id'    => $effectiveMember?->id,
            'effectiveMember_name'  => $effectiveMember ? trim($effectiveMember->first_name . ' ' . $effectiveMember->last_name) : null,
            'fallback_member_id'    => $user->member?->id,
            'resolved_member_id'    => $member?->id,
        ]);

        if (!$member) {
            Log::warning('[NotificationController@index] No member resolved — returning 404');
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

        Log::info('[NotificationController@index] Success', [
            'member_id'    => $member->id,
            'notif_count'  => $notifications->count(),
            'unread_count' => $unread_count,
        ]);

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
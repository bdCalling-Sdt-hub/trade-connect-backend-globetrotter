<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getAllNotifications()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->with('notifiable:id,full_name,image,user_name')->get()->map(function ($notification) {
        $likedUser = User::find($notification->data['user_id']);

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
                'read_at' => $notification->read_at,
                'full_name' => $likedUser->full_name ?? 'N/A',
                'image' => url('Profile/',$likedUser->image) ?? null,
                'user_name' => $likedUser->user_name ?? 'N/A',

            ];
        });

        return $this->sendResponse($notifications, 'All notifications retrieved successfully.');
    }

    public function getUnreadNotifications()
    {
        $user = Auth::user();
        $unreadNotifications = $user->unreadNotifications()->with('notifiable:id,full_name,image,user_name')->get()->map(function ($notification) {
            $likedUser = User::find($notification->data['user_id']);

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
                'read_at' => $notification->read_at,
                'full_name' => $likedUser->full_name ?? 'N/A',
                'image' => $likedUser->image ?? null,
                'user_name' => $likedUser->user_name ?? 'N/A',
                
            ];
        });

        return $this->sendResponse($unreadNotifications, 'Unread notifications retrieved successfully.');
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->sendError('Notification not found.', [], 404);
        }

        $notification->markAsRead();

        return $this->sendResponse(null, 'Notification marked as read.');
    }
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return $this->sendResponse(null, 'All unread notifications marked as read.');
    }




}

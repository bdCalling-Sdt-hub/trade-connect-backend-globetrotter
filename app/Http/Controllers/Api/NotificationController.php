<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getAllNotifications()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->get();

        return $this->sendResponse($notifications, 'All notifications retrieved successfully.');
    }
    public function getUnreadNotifications()
    {
        $user = Auth::user();
        $unreadNotifications = $user->unreadNotifications()->get();

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

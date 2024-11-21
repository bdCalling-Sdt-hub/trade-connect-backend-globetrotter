<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JoinRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    private $joinRequest;
    public function __construct($joinRequest)
    {
        $this->joinRequest = $joinRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }


    public function toArray(object $notifiable): array
    {
        return [
            'group_id' => $this->joinRequest['group_id'],
            'user_id' => $this->joinRequest['user_id'],
            'image' => $this->joinRequest->user->image
                    ? url('profile/',$this->joinRequest->user->image)
                    : url('avatar/profile.png'),
            'message' => $this->joinRequest->user->full_name . ' has requested to join ' . $this->joinRequest->group->name,
        ];
    }
}

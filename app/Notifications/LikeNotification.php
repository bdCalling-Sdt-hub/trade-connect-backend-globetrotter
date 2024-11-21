<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LikeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $like;
    public function __construct($like)
    {
        $this->like = $like;
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
            'message' => 'Your newsfeed has been liked.',
            'newsfeed_id' => $this->like->newsfeed_id,
            'user_id' => $this->like->user_id,
            'image' => $this->like->user->image
                    ? url('profile/',$this->like->user->image)
                    : url('avatar/profile.png'),
        ];
    }
}

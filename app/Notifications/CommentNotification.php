<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $comment;
    public function __construct($comment)
    {
        $this->comment = $comment;
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
            'comment_id' => $this->comment->id,
            'newsfeed_id' => $this->comment->newsfeed_id,
            'user_id' => $this->comment->user_id,
            'image' => $this->comment->user->image
                    ? url('profile/',$this->comment->user->image)
                    : url('avatar/profile.png'),
            'message' => 'A new comment has been made on your news feed.',
            'content' => $this->comment->comments,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsFeedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $newsFeedData;
    public function __construct($newsFeedData)
    {
        $this->newsFeedData = $newsFeedData;
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
            'news_feed_id' => $this->newsFeedData->id,
            'user_id' => $this->newsFeedData->user_id,
            'message' => 'A new news feed has been created.',
            'content' => $this->newsFeedData->share_your_thoughts,
            'created_at' => $this->newsFeedData->created_at,
        ];
    }
}

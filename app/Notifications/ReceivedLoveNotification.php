<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceivedLoveNotification extends Notification
{
    use Queueable;

    public $wallet;

    public function __construct($wallet)
    {
        $this->wallet = $wallet;
    }

    public function via($notifiable)
    {
        return ['database']; 
    }
    public function toArray($notifiable)
    {
        return [
            'message' => 'You have received love.',
            'total_love' => $this->wallet->total_love,
            'wallet_id' => $this->wallet->id,
        ];
    }
}

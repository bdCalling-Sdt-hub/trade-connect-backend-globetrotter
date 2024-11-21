<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoveRequestNotification extends Notification
{
    use Queueable;

    public $amount;
    public $senderId;

    public function __construct($amount, $senderId)
    {
        $this->amount = $amount;
        $this->senderId = $senderId;
    }

    public function via($notifiable)
    {
        return ['database']; // Use the database channel
    }

    public function toDatabase($notifiable)
    {
        $user = User::find($this->senderId);
        return [
            'message' => 'You have received a love request.',
            'amount' => $this->amount,
            'sender_id' => $this->senderId,
            'image' => $user->image
                ? url('profile/',$user->image)
                : url('avatar/profile.png'),
        ];
    }
}

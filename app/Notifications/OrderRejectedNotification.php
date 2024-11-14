<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRejectedNotification extends Notification
{
    use Queueable;

    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database'];
    }
    public function toArray($notifiable)
    {
        $user = $this->order->user;
        return [
            'order_id' => $this->order->id,
            'status' => 'rejectDelivery',
            'message' => "{$user->full_name} has rejected the delivery.",
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'image' => $user->image ? url('profile/', $user->image) : url('avatar/profile.png'),
            ]
        ];
    }
}

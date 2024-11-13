<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderAcceptedNotification extends Notification
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
        return [
            'order_id' => $this->order->id,
            'status' => 'accepted',
            'message' => "Your order has been accepted.",
        ];
    }
}

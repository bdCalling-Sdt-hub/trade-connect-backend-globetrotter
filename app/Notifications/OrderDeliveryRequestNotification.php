<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveryRequestNotification extends Notification
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
            'user_id' =>$this->order->user_id,
            'image' => $this->order->user->image
                    ? url('profile/',$this->order->user->image)
                    : url('avatar/profile.png'),
            'status' => 'deliveryRequest',
            'message' => "{$this->order->product->product_name} is ready for delivery.",
        ];
    }
}

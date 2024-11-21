<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductPendingNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $product;
    public function __construct($product)
    {
        $this->product = $product;
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
            'product_id' => $this->product->id,
            'user_id' =>$this->product->user_id,
            'image' => $this->product->user->image
                    ? url('profile/',$this->product->user->image)
                    : url('avatar/profile.png'),
            'product_name' => $this->product->product_name,
            'message' => 'Your product "' . $this->product->product_name . '" is pending approval.',
        ];
    }
}

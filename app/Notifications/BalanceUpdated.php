<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BalanceUpdated extends Notification
{
    use Queueable;

    protected $amount;
    protected $type; // 'increase' or 'decrease'

    public function __construct($amount, $type)
    {
        $this->amount = $amount;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['database']; // or 'mail' if you want to send email notifications as well
    }

    public function toArray($notifiable)
    {
        return [
            'message' => $this->type === 'increase'
                ? "Your balance was increased by {$this->amount} from Admin."
                : "Your balance was decreased by {$this->amount} from Admin.",
            'amount' => $this->amount,
            'type' => $this->type,
        ];
    }
}

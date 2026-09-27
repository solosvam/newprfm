<?php

namespace App\Mail;

use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $mailLocale = 'az')
    {
        $this->afterCommit();
        $this->onQueue('emails');
    }

    public function build(): static
    {
        $locale = in_array($this->mailLocale, ['az', 'ru', 'en'], true) ? $this->mailLocale : 'az';
        $subjects = [
            'az' => 'Sifarişiniz qəbul edildi: ',
            'ru' => 'Ваш заказ принят: ',
            'en' => 'Your order is confirmed: ',
        ];
        $this->order->loadMissing(['items.product', 'items.variant.size', 'address', 'paymentMethod']);

        return $this->subject($subjects[$locale].$this->order->order_no)
            ->view('emails.order-created', ['locale' => $locale]);
    }
}

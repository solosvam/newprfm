<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $customerName, public string $mailLocale = 'az')
    {
        $this->afterCommit();
        $this->onQueue('emails');
    }

    public function build(): static
    {
        $locale = in_array($this->mailLocale, ['az', 'ru', 'en'], true) ? $this->mailLocale : 'az';
        $subjects = [
            'az' => 'ParfumShop.az-a xoş gəlmisiniz!',
            'ru' => 'Добро пожаловать в ParfumShop.az!',
            'en' => 'Welcome to ParfumShop.az!',
        ];

        return $this->subject($subjects[$locale])
            ->view('emails.welcome', ['locale' => $locale]);
    }
}

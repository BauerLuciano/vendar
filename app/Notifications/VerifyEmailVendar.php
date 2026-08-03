<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailVendar extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verificá tu cuenta en VendAR')
            ->markdown('emails.verificar-cuenta', [
                'url' => $verificationUrl,
                'name' => $notifiable->name,
                'appName' => config('app.name'),
            ]);
    }
}

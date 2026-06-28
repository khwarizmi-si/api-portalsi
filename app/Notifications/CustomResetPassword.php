<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends BaseResetPassword
{
    use Queueable;

    public function __construct($token)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable)
    {
        $resetUrl = url('/reset-password') . '?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset Password - Portal SI')
            ->view('emails.reset-password', [
                'url' => $resetUrl,
                'user' => $notifiable,
                'preheader' => 'Gunakan tautan ini untuk mengatur ulang password Portal SI Anda.',
            ]);
    }
}

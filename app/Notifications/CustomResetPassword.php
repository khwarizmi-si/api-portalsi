<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends BaseResetPassword
{
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Password - Portal SI')
            ->view('emails.reset-password', [
                'url' => url('/api/reset-password?token=' . $this->token . '&email=' . $notifiable->getEmailForPasswordReset()),
                'user' => $notifiable,
            ]);
    }
}

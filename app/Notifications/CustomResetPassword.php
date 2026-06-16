<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    public function __construct($token)
    {
        parent::__construct($token);

        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function viaConnections(): array
    {
        return [
            'mail' => config('mail.queue.connection', 'database'),
        ];
    }

    public function viaQueues(): array
    {
        return [
            'mail' => config('mail.queue.name', 'mail'),
        ];
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

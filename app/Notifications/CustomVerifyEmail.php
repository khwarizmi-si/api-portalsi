<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    public function __construct()
    {
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
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Email Anda - Portal SI')
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'user' => $notifiable,
                'preheader' => 'Satu langkah lagi untuk mengaktifkan akun Portal SI Anda.',
            ]);
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }
}

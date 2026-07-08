<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmEmailChange extends Notification
{
    use Queueable;

    public function __construct(
        public string $url,
        public string $name,
        public string $newEmail,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Konfirmasi Perubahan Email - Portal SI')
            ->view('emails.confirm-email-change', [
                'url' => $this->url,
                'name' => $this->name,
                'newEmail' => $this->newEmail,
                'preheader' => 'Konfirmasi perubahan email akun Portal SI Anda.',
            ]);
    }
}

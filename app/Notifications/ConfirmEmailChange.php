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
            ->greeting('Halo '.$this->name)
            ->line('Kami menerima permintaan untuk mengganti email akun Portal SI Anda menjadi alamat ini ('.$this->newEmail.').')
            ->action('Konfirmasi Email Baru', $this->url)
            ->line('Tautan ini berlaku selama 60 menit.')
            ->line('Jika Anda tidak meminta perubahan ini, abaikan email ini — email akun Anda tidak akan berubah.');
    }
}

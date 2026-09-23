<?php

declare(strict_types=1);

namespace App\Domain\Access\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan akun terkunci (10-access §8, NFR-04).
 */
class AccountLockedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly int $minutes) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Anda terkunci sementara')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Akun Anda terkunci selama '.$this->minutes.' menit karena beberapa kali gagal masuk.')
            ->line('Bila ini bukan Anda, segera hubungi Admin Company.');
    }
}

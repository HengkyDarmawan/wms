<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email satu kejadian (Blueprint §10). Tautan dibangun dari host company (A-01). */
class EventMail extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = tenant();
        $pesan = (new MailMessage)
            ->subject($this->title.' — '.($tenant?->name ?? config('app.name')))
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->title);

        if ($this->body) {
            $pesan->line($this->body);
        }

        if ($this->url) {
            $akar = $tenant === null ? rtrim((string) config('app.url'), '/') : 'https://'.$tenant->host();
            $pesan->action('Buka', str_starts_with($this->url, 'http') ? $this->url : $akar.$this->url);
        }

        return $pesan->line('Atur notifikasi Anda di menu Notifikasi → Preferensi.');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email undangan user (10-access §8, template `access.invitation`).
 */
class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $plainToken,
        private readonly CarbonInterface $expiresAt,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = tenant();
        $company = $tenant?->name ?? config('app.name');

        // url() memakai APP_URL yang menunjuk domain pusat. Di luar konteks
        // permintaan (antrean, artisan) tautan itu menghasilkan 404 'Company
        // tidak dikenal', jadi host dibangun dari company-nya sendiri (A-01).
        $url = $tenant === null
            ? url('/invitation/'.$this->plainToken)
            : 'https://'.$tenant->host().'/invitation/'.$this->plainToken;

        return (new MailMessage)
            ->subject('Undangan bergabung ke '.$company)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Anda diundang memakai '.config('app.name').' untuk '.$company.'.')
            ->line('Klik tombol di bawah untuk mengatur password dan mulai memakai akun Anda.')
            ->action('Atur Password', $url)
            ->line('Tautan berlaku sampai '.$this->expiresAt->timezone(
                tenant()?->timezone ?? 'Asia/Jakarta'
            )->format('d M Y H:i').'.')
            ->line('Bila Anda tidak merasa diundang, abaikan email ini.');
    }
}

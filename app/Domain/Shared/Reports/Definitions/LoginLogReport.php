<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Access\Enums\LoginResult;
use App\Domain\Access\Models\LoginAttempt;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 10-access §9 — log percobaan masuk 30 hari terakhir. */
class LoginLogReport extends Report
{
    private const HARI = 30;

    public function key(): string
    {
        return 'log-login';
    }

    public function title(): string
    {
        return 'Log masuk 30 hari';
    }

    public function permission(): string
    {
        return 'user.view';
    }

    public function description(): string
    {
        return 'Percobaan masuk 30 hari terakhir beserta hasil dan kanalnya.';
    }

    public function columns(): array
    {
        return [
            'waktu' => 'Waktu',
            'email' => 'Email',
            'user' => 'Pengguna',
            'ip' => 'Alamat IP',
            'kanal' => 'Kanal',
            'hasil' => 'Hasil',
        ];
    }

    public function filters(): array
    {
        return [
            'hasil' => [
                'label' => 'Hasil',
                'options' => collect(LoginResult::cases())
                    ->mapWithKeys(fn (LoginResult $r) => [$r->value => $r->label()])
                    ->all(),
            ],
            'email' => ['label' => 'Email mengandung'],
        ];
    }

    public function rows(array $filters): Collection
    {
        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        return LoginAttempt::query()
            ->with('user:id,name')
            ->where('attempted_at', '>=', now()->subDays(self::HARI))
            ->when(($filters['hasil'] ?? '') !== '', fn ($q) => $q->where('result', $filters['hasil']))
            ->when(($filters['email'] ?? '') !== '', fn ($q) => $q->where('email', 'like', '%'.$filters['email'].'%'))
            ->orderByDesc('attempted_at')
            ->limit(5000)
            ->get()
            ->map(fn (LoginAttempt $baris) => [
                'waktu' => $baris->attempted_at?->timezone($zona)->format('d/m/Y H:i'),
                'email' => $baris->email,
                'user' => $baris->user?->name ?? '—',
                'ip' => $baris->ip_address ?? '—',
                'kanal' => $baris->channel,
                'hasil' => $baris->result->label(),
            ]);
    }
}

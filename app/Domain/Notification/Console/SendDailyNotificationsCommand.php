<?php

declare(strict_types=1);

namespace App\Domain\Notification\Console;

use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Platform\Support\OperatingCompanies;
use Illuminate\Console\Command;

/**
 * `notifications:daily` — pengingat harian (Blueprint §10, A-189): aset lewat
 * jatuh tempo (BR-AST-06) ke pemegang `asset.manage` proyeknya. Notifikasi
 * yang sama dan belum dibaca tidak digandakan.
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company.
 */
class SendDailyNotificationsCommand extends Command
{
    protected $signature = 'notifications:daily {--tenants=* : id company; kosong = semua}';

    protected $description = 'Kirim pengingat harian: aset lewat jatuh tempo (BR-AST-06)';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->laporkan((string) tenant()?->getTenantKey(), app(DomainNotifications::class)->assetsOverdue());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), app(DomainNotifications::class)->assetsOverdue());
        }, $this);

        return self::SUCCESS;
    }

    private function laporkan(string $company, int $jumlah): void
    {
        $this->info(sprintf('Company %s: %d notifikasi pengingat dikirim.', $company, $jumlah));
    }
}

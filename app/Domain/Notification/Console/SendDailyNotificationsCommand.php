<?php

declare(strict_types=1);

namespace App\Domain\Notification\Console;

use App\Domain\Notification\Support\DailyReminders;
use App\Domain\Platform\Support\OperatingCompanies;
use Illuminate\Console\Command;

/**
 * `notifications:daily` — pengingat harian (Blueprint §10, A-189, A-233): SLA
 * tinjau REQ (BR-REQ-14), reservasi menggantung (BR-STK-16), aset lewat jatuh
 * tempo (BR-AST-06), dan sisa umur aset (BR-AST-08). Notifikasi yang sama dan
 * belum dibaca tidak digandakan; company ditangguhkan dilewati (BR-SUB-02).
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company.
 */
class SendDailyNotificationsCommand extends Command
{
    protected $signature = 'notifications:daily {--tenants=* : id company; kosong = semua}';

    protected $description = 'Kirim pengingat harian: SLA tinjau, reservasi menggantung, aset jatuh tempo & sisa umur';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $company = tenant();
            $this->laporkan((string) $company?->getTenantKey(), $company !== null && OperatingCompanies::halted($company) ? 0 : app(DailyReminders::class)->run());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), app(DailyReminders::class)->run());
        }, $this);

        return self::SUCCESS;
    }

    private function laporkan(string $company, int $jumlah): void
    {
        $this->info(sprintf('Company %s: %d notifikasi pengingat dikirim.', $company, $jumlah));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Request\Console;

use App\Domain\Platform\Support\OperatingCompanies;
use App\Domain\Request\Actions\RespondDeliveryReceipt;
use Illuminate\Console\Command;

/**
 * `deliveries:auto-confirm` — job harian BR-REQ-10: bukti terima yang lewat
 * `receipt_confirm_days` tanpa tanggapan pemohon menjadi `auto_confirmed`.
 * Dijadwalkan harian di routes/console.php; aman dijalankan berulang.
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company.
 */
class AutoConfirmReceiptsCommand extends Command
{
    protected $signature = 'deliveries:auto-confirm {--tenants=* : id company; kosong = semua}';

    protected $description = 'Konfirmasi otomatis bukti terima yang lewat batas tanggapan pemohon (BR-REQ-10)';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->laporkan((string) tenant()?->getTenantKey(), app(RespondDeliveryReceipt::class)->autoConfirm());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), app(RespondDeliveryReceipt::class)->autoConfirm());
        }, $this);

        return self::SUCCESS;
    }

    private function laporkan(string $company, int $jumlah): void
    {
        $this->info(sprintf('Company %s: %d bukti terima dikonfirmasi otomatis.', $company, $jumlah));
    }
}

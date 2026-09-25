<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Console;

use App\Domain\Platform\Support\OperatingCompanies;
use App\Domain\PurchaseRequest\Support\ReorderPlanner;
use Illuminate\Console\Command;

/**
 * `purchase-requests:reorder` — job harian titik pesan ulang (BR-REQ-11):
 * draf PRQ `reorder_point` per gudang untuk item di bawah titik pesan ulang.
 * Dijadwalkan harian di routes/console.php; aman dijalankan berulang karena
 * tidak membuat ulang selama masih ada PRQ terbuka untuk item & gudang itu.
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company.
 */
class GenerateReorderRequestsCommand extends Command
{
    protected $signature = 'purchase-requests:reorder {--tenants=* : id company; kosong = semua}';

    protected $description = 'Buat draf Purchase Request untuk item di bawah titik pesan ulang (BR-REQ-11)';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->laporkan((string) tenant()?->getTenantKey(), count(app(ReorderPlanner::class)->run()));

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), count(app(ReorderPlanner::class)->run()));
        }, $this);

        return self::SUCCESS;
    }

    private function laporkan(string $company, int $jumlah): void
    {
        $this->info(sprintf('Company %s: %d draf PRQ titik pesan ulang dibuat.', $company, $jumlah));
    }
}

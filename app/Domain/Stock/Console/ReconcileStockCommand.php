<?php

declare(strict_types=1);

namespace App\Domain\Stock\Console;

use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Platform\Support\OperatingCompanies;
use App\Domain\Stock\Support\StockReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * `stock:reconcile` — rekonsiliasi saldo terjadwal (BR-STK-01, A-243): saldo
 * tersimpan dibandingkan dengan kartu stok. Selisih dicatat di log dan
 * diberitahukan ke Admin Company; saldo tidak pernah diubah otomatis (P-01).
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company yang beroperasi.
 */
class ReconcileStockCommand extends Command
{
    protected $signature = 'stock:reconcile {--tenants=* : id company; kosong = semua}';

    protected $description = 'Bandingkan saldo stok dengan kartu stok dan laporkan selisihnya (BR-STK-01)';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->periksa((string) tenant()?->getTenantKey());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, fn ($company) => $this->periksa((string) $company->getTenantKey()), $this);

        return self::SUCCESS;
    }

    private function periksa(string $company): void
    {
        $selisih = app(StockReconciler::class)->differences();

        if ($selisih === []) {
            $this->info(sprintf('Company %s: saldo cocok dengan kartu stok.', $company));

            return;
        }

        Log::warning('Rekonsiliasi saldo: '.count($selisih).' selisih', ['company' => $company, 'contoh' => array_slice($selisih, 0, 20)]);
        app(DomainNotifications::class)->balanceMismatch(count($selisih));

        $this->warn(sprintf('Company %s: %d saldo tidak cocok dengan kartu stok.', $company, count($selisih)));
        $this->table(['Kunci', 'Kartu stok', 'Saldo', 'Selisih'], array_map(
            fn (array $s) => [$s['key'], $s['ledger'], $s['balance'], $s['difference']],
            array_slice($selisih, 0, 50),
        ));
    }
}

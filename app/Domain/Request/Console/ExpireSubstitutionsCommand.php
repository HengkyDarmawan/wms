<?php

declare(strict_types=1);

namespace App\Domain\Request\Console;

use App\Domain\Platform\Support\OperatingCompanies;
use App\Domain\Request\Actions\RespondSubstitution;
use Illuminate\Console\Command;

/**
 * `requests:expire-substitutions` — penuaan tenggat penggantian item
 * (BR-REQ-13, A-239): baris yang diganti dan tidak ditanggapi klien sampai
 * `substitution_deadline_at` menjadi `expired` (dianggap setuju). Dijadwalkan
 * tiap jam di routes/console.php karena tenggatnya jam demi jam; aman
 * dijalankan berulang.
 *
 * Tanpa --tenants: bila tenancy sudah aktif, hanya company itu; bila belum,
 * semua company yang beroperasi.
 */
class ExpireSubstitutionsCommand extends Command
{
    protected $signature = 'requests:expire-substitutions {--tenants=* : id company; kosong = semua}';

    protected $description = 'Tandai penggantian item yang lewat tenggat keberatan klien sebagai kedaluwarsa (BR-REQ-13)';

    public function handle(): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->laporkan((string) tenant()?->getTenantKey(), app(RespondSubstitution::class)->expireOverdue());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), app(RespondSubstitution::class)->expireOverdue());
        }, $this);

        return self::SUCCESS;
    }

    private function laporkan(string $company, int $jumlah): void
    {
        $this->info(sprintf('Company %s: %d penggantian kedaluwarsa.', $company, $jumlah));
    }
}

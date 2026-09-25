<?php

declare(strict_types=1);

namespace App\Domain\Approval\Console;

use App\Domain\Approval\Actions\EscalateApprovalTask;
use App\Domain\Platform\Support\OperatingCompanies;
use Illuminate\Console\Command;

/**
 * `approval:escalate` — eskalasi tugas approval yang lewat batas waktu atau
 * approvernya nonaktif, dan pemindahan tugas ke delegat yang mulai berlaku
 * (BR-APR-05, BR-APR-06, BR-APR-08, A-90). Dijadwalkan tiap jam di
 * routes/console.php; aman dijalankan berulang.
 *
 * Tanpa --tenants: bila tenancy sudah aktif (mis. dari `tenants:run` atau
 * uji), hanya company itu; bila belum, semua company.
 */
class EscalateApprovalsCommand extends Command
{
    protected $signature = 'approval:escalate {--tenants=* : id company; kosong = semua}';

    protected $description = 'Eskalasi tugas approval yang lewat batas waktu atau approvernya nonaktif (BR-APR-06, BR-APR-08)';

    public function handle(EscalateApprovalTask $action): int
    {
        if (tenancy()->initialized && $this->option('tenants') === []) {
            $this->laporkan((string) tenant()?->getTenantKey(), $action->runScheduled());

            return self::SUCCESS;
        }

        $ids = $this->option('tenants') ?: null;

        OperatingCompanies::each($ids, function ($company) {
            $this->laporkan((string) $company->getTenantKey(), app(EscalateApprovalTask::class)->runScheduled());
        }, $this);

        return self::SUCCESS;
    }

    /** @param  array{escalated: int, skipped: int, delegated: int}  $hasil */
    private function laporkan(string $company, array $hasil): void
    {
        $this->info(sprintf(
            'Company %s: %d tugas dieskalasi, %d tanpa tujuan, %d dipindah ke delegat.',
            $company,
            $hasil['escalated'],
            $hasil['skipped'],
            $hasil['delegated'],
        ));
    }
}

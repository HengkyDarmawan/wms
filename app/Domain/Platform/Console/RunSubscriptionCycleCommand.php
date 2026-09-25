<?php

declare(strict_types=1);

namespace App\Domain\Platform\Console;

use App\Domain\Platform\Support\SubscriptionLifecycle;
use Illuminate\Console\Command;

/**
 * `subscriptions:cycle` — siklus langganan harian di database pusat
 * (BR-SUB-01, A-177): terbitkan tagihan, jatuh tempo, tangguhkan, akhiri.
 * Dijadwalkan harian di routes/console.php; aman dijalankan berulang.
 */
class RunSubscriptionCycleCommand extends Command
{
    protected $signature = 'subscriptions:cycle';

    protected $description = 'Jalankan siklus langganan harian: tagihan, jatuh tempo, penangguhan, pengakhiran (BR-SUB-01)';

    public function handle(SubscriptionLifecycle $siklus): int
    {
        $hasil = $siklus->run();

        $this->info(sprintf(
            'Tagihan terbit: %d · jatuh tempo: %d · ditangguhkan: %d · diakhiri: %d.',
            $hasil['invoiced'], $hasil['past_due'], $hasil['suspended'], $hasil['terminated'],
        ));

        return self::SUCCESS;
    }
}

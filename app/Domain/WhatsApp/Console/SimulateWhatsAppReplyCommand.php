<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Console;

use App\Domain\WhatsApp\Support\WhatsAppInbound;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * `whatsapp:simulate` — uji lokal tanpa akun Meta (driver `log`, A-274):
 * meniru approver menekan tombol template. Payload tombol dibaca dari
 * `storage/logs/laravel.log` (baris `[wms.whatsapp]`, bagian `payload`).
 *
 *   php artisan whatsapp:simulate 6281234567890 "APR|1|<token>|A"
 */
class SimulateWhatsAppReplyCommand extends Command
{
    protected $signature = 'whatsapp:simulate {from : nomor pengirim, mis. 6281234567890} {payload : payload tombol APR|company|token|A atau R}';

    protected $description = 'Tiru tombol WhatsApp ditekan (uji lokal tanpa Meta)';

    public function handle(WhatsAppInbound $inbound): int
    {
        if (app()->isProduction()) {
            $this->error('Tidak boleh dijalankan di produksi.');

            return self::FAILURE;
        }

        $hasil = $inbound->process(['entry' => [['changes' => [['value' => ['messages' => [[
            'id' => 'wamid.sim.'.Str::uuid()->toString(),
            'from' => (string) $this->argument('from'),
            'type' => 'button',
            'button' => ['payload' => (string) $this->argument('payload'), 'text' => 'Simulasi'],
        ]]]]]]]]);

        $this->info(sprintf('Diputus: %d, diabaikan: %d. Balasan ada di log (driver log).', $hasil['decisions'], $hasil['ignored']));

        return self::SUCCESS;
    }
}

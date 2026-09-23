<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire\Concerns;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan aturan pengiriman menjadi pesan di layar.
 *
 * Penolakan buku besar ikut ditangkap: memberangkatkan surat jalan bisa gagal
 * karena bin Loading Area tidak lagi berisi apa yang dikira, dan pemakai layar
 * ini perlu membacanya sebagai satu kegagalan yang sama.
 */
trait HandlesShipmentRules
{
    public string $ruleError = '';

    public string $ruleCode = '';

    protected function jalankan(callable $aksi, string $formProperty = 'form'): bool
    {
        $this->ruleError = '';
        $this->ruleCode = '';

        try {
            $aksi();

            return true;
        } catch (ShipmentRuleException $e) {
            $this->ruleCode = $e->rule;

            if ($e->fieldErrors !== []) {
                foreach ($e->fieldErrors as $field => $pesan) {
                    $this->addError($formProperty.'.'.$field, $pesan);
                }

                return false;
            }

            $this->ruleError = $e->getMessage();

            return false;
        } catch (LedgerException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            return false;
        }
    }

    /** @return array<string, string> */
    protected function pilihanAlasan(ReasonContext $context = ReasonContext::Cancel): array
    {
        return ReasonCode::options($context);
    }
}

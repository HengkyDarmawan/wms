<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire\Concerns;

use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Transfer\Exceptions\TransferRuleException;

/**
 * Menerjemahkan penolakan aturan transfer/retur — dan modul yang dipanggilnya
 * (approval, picking, penerimaan, buku besar) — menjadi pesan di layar beserta
 * kodenya. Dipakai layar TRF dan RET.
 */
trait HandlesTransferRules
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
        } catch (TransferRuleException|ReturnRuleException|ShipmentRuleException|ReceiptRuleException|RequestRuleException|ApprovalRuleException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            foreach ($e->fieldErrors as $field => $pesan) {
                $this->addError($formProperty.'.'.$field, $pesan);
            }

            return false;
        } catch (LedgerException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            return false;
        }
    }

    /** @return array<string, string> kode => label */
    protected function pilihanAlasan(ReasonContext $context): array
    {
        return ReasonCode::options($context);
    }

    protected function alasanId(string $code, ReasonContext $context): ?int
    {
        if ($code === '') {
            return null;
        }

        $id = ReasonCode::query()->where('context', $context->value)->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }
}

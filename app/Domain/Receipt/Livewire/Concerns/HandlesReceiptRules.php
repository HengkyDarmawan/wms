<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire\Concerns;

use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan aturan penerimaan (dan buku besar) menjadi pesan di
 * layar, dengan kode aturannya.
 */
trait HandlesReceiptRules
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
        } catch (ReceiptRuleException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            foreach ($e->fieldErrors as $field => $pesan) {
                $this->addError($formProperty.'.'.$field, $pesan);
            }

            return false;
        } catch (ApprovalRuleException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

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

    /** Kode alasan (unik per konteks) → id. */
    protected function alasanId(string $code, ReasonContext $context): ?int
    {
        if ($code === '') {
            return null;
        }

        $id = ReasonCode::query()->where('context', $context->value)->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }
}

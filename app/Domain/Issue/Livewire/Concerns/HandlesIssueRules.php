<?php

declare(strict_types=1);

namespace App\Domain\Issue\Livewire\Concerns;

use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan aturan pemakaian material (dan buku besar serta mesin
 * approval) menjadi pesan di layar, dengan kode aturannya.
 */
trait HandlesIssueRules
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
        } catch (IssueRuleException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            foreach ($e->fieldErrors as $field => $pesan) {
                $this->addError($formProperty.'.'.$field, $pesan);
            }

            return false;
        } catch (ApprovalRuleException|LedgerException $e) {
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

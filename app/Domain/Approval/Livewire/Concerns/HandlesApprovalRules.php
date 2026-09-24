<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire\Concerns;

use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan aturan approval — dan penolakan dokumen yang
 * dipicu keputusan akhir (mis. BR-REQ-05 saat reservasi gagal) — menjadi
 * pesan di layar beserta kodenya.
 */
trait HandlesApprovalRules
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
        } catch (ApprovalRuleException|RequestRuleException|ReceiptRuleException|CountRuleException|AdjustmentRuleException|TransferRuleException|ReturnRuleException|ShipmentRuleException|IssueRuleException $e) {
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
}

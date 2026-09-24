<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire\Concerns;

use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan aturan REQ menjadi pesan di layar.
 *
 * Penolakan dari buku besar stok ikut ditangkap: menyetujui REQ bisa gagal
 * karena stoknya keburu habis, dan pemakai layar ini perlu membacanya sebagai
 * satu kegagalan yang sama, bukan sebagai galat teknis.
 */
trait HandlesRequestRules
{
    public string $ruleError = '';

    public string $ruleCode = '';

    /** Menjalankan aksi; mengembalikan false bila aturan menolaknya. */
    protected function jalankan(callable $aksi, string $formProperty = 'form'): bool
    {
        $this->ruleError = '';
        $this->ruleCode = '';

        try {
            $aksi();

            return true;
        } catch (RequestRuleException $e) {
            $this->ruleCode = $e->rule;

            if ($e->fieldErrors !== []) {
                foreach ($e->fieldErrors as $field => $pesan) {
                    $this->addError($formProperty.'.'.$field, $pesan);
                }

                return false;
            }

            $this->ruleError = $e->getMessage();

            return false;
        } catch (ApprovalRuleException $e) {
            // Keputusan approval ditolak mesin (SoD, keputusan pertama menang, …).
            $this->ruleCode = $e->rule;
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

<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire\Concerns;

use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Enums\ReasonContext;

/**
 * Menerjemahkan pelanggaran aturan Master menjadi pesan di layar: kesalahan
 * per field bila aksinya menyebut field, kalau tidak sebagai pesan umum.
 *
 * Dipakai seluruh komponen Livewire modul Master supaya pesan aturan tampil
 * seragam dan tidak ada yang tertelan diam-diam.
 */
trait HandlesMasterRules
{
    /** Pesan aturan yang tidak menempel pada field tertentu. */
    public string $ruleError = '';

    /**
     * Menjalankan aksi; mengembalikan false bila aturan bisnis menolaknya.
     */
    protected function jalankan(callable $aksi, string $formProperty = 'form'): bool
    {
        $this->ruleError = '';

        try {
            $aksi();

            return true;
        } catch (MasterRuleException $e) {
            if ($e->fieldErrors !== []) {
                foreach ($e->fieldErrors as $field => $pesan) {
                    $this->addError($formProperty.'.'.$field, $pesan);
                }

                return false;
            }

            $this->ruleError = $e->getMessage();

            return false;
        }
    }

    /**
     * Pilihan alasan untuk dialog nonaktifkan/batal (BR-GEN-02).
     *
     * @return array<string, string>
     */
    protected function pilihanAlasan(ReasonContext $context): array
    {
        return ReasonCode::options($context);
    }
}

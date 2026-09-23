<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire\Concerns;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;

/**
 * Menerjemahkan pelanggaran aturan Warehouse menjadi pesan di layar: kesalahan
 * per field bila aksinya menyebut field, kalau tidak sebagai pesan umum.
 *
 * Sama polanya dengan modul Master supaya pesan aturan seragam di seluruh
 * aplikasi dan tidak ada yang tertelan diam-diam.
 */
trait HandlesWarehouseRules
{
    /** Pesan aturan yang tidak menempel pada field tertentu. */
    public string $ruleError = '';

    /** Menjalankan aksi; mengembalikan false bila aturan bisnis menolaknya. */
    protected function jalankan(callable $aksi, string $formProperty = 'form'): bool
    {
        $this->ruleError = '';

        try {
            $aksi();

            return true;
        } catch (WarehouseRuleException $e) {
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
     * Pilihan alasan untuk dialog nonaktifkan dan bekukan (BR-GEN-02).
     *
     * @return array<string, string>
     */
    protected function pilihanAlasan(ReasonContext $context = ReasonContext::Cancel): array
    {
        return ReasonCode::options($context);
    }
}

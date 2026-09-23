<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire\Concerns;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Menerjemahkan penolakan buku besar stok menjadi pesan di layar, dengan kode
 * aturannya ikut disebut.
 *
 * Kode aturan sengaja ditampilkan: bila stok ditolak, penjaga gudang perlu tahu
 * aturan mana yang menolak agar bisa menanyakannya, bukan sekadar "gagal".
 */
trait HandlesStockRules
{
    public string $ruleError = '';

    public string $ruleCode = '';

    /** Menjalankan aksi; mengembalikan false bila aturan stok menolaknya. */
    protected function jalankan(callable $aksi): bool
    {
        $this->ruleError = '';
        $this->ruleCode = '';

        try {
            $aksi();

            return true;
        } catch (LedgerException $e) {
            $this->ruleError = $e->getMessage();
            $this->ruleCode = $e->rule;

            return false;
        }
    }

    /** @return array<string, string> */
    protected function pilihanAlasan(ReasonContext $context = ReasonContext::Cancel): array
    {
        return ReasonCode::options($context);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Livewire\Concerns;

use App\Domain\Shared\Pilihan\Pilihan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Json;

/**
 * Pencarian ke server untuk `<x-pilih server>` (A-384) — method Livewire di
 * komponen yang sama, **tanpa route baru**. Komponen menyatakan daftar mana
 * yang boleh dicari lewat `pilihanServer($model)`, dan method itu juga yang
 * dipakai render (isian awal + label nilai terpilih) serta validasi simpan,
 * sehingga izin & cakupannya identik.
 *
 *   protected function pilihanServer(string $model): ?Pilihan
 *   {
 *       $this->authorize(...);               // izin layar diulang di sini
 *       return match ($model) {
 *           'managerId' => $this->opsiAtasan(),
 *           default => null,                 // model lain tidak bisa dicari
 *       };
 *   }
 *
 * Dibatasi 60 pencarian/menit per pengguna; hasil ±30 baris; minimal 2 huruf.
 */
trait CariPilihan
{
    public const BATAS_CARI_PER_MENIT = 60;

    /** Null = model itu tidak bisa dicari dari browser. */
    abstract protected function pilihanServer(string $model): ?Pilihan;

    /**
     * Renderless & async (`#[Json]`): hanya mengembalikan daftar opsi.
     *
     * @return list<array<string, mixed>>
     */
    #[Json]
    public function cariPilihan(string $model, string $kata): array
    {
        $kata = trim(mb_substr($kata, 0, 100));

        if (mb_strlen($kata) < Pilihan::MIN_HURUF) {
            return [];
        }

        $kunci = 'cari-pilihan:'.(tenant()?->getTenantKey() ?? '-').':'.(auth()->id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_CARI_PER_MENIT)) {
            return [];
        }

        RateLimiter::hit($kunci, 60);

        return $this->pilihanServer($model)?->cari($kata) ?? [];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Pilihan;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu daftar pilihan untuk `<x-pilih>` (A-383, A-384): **satu query** yang
 * dipakai render (daftar dimuat sekaligus, isian awal, label nilai terpilih),
 * pencarian ke server (`CariPilihan::cariPilihan`), dan validasi simpan.
 * Karena ketiganya memakai query yang sama, izin & cakupan (BR-GEN-09,
 * BR-ACC-05, A-354) tidak mungkin berbeda antara yang tampil, yang bisa
 * dicari, dan yang boleh disimpan.
 *
 * Tiap opsi: `['value', 'text', 'badge'?, 'sub'?, 'group'?]`.
 */
final class Pilihan
{
    /** Jumlah hasil per pencarian dan isian awal mode server. */
    public const BATAS = 30;

    /** Pencarian ke server baru jalan setelah sekian huruf. */
    public const MIN_HURUF = 2;

    /**
     * @param  array<int, string>  $kolomCari  kolom model, atau `relasi.kolom`
     * @param  Closure(Model): array<string, mixed>  $opsi
     */
    private function __construct(
        private readonly Builder $query,
        private readonly array $kolomCari,
        private readonly Closure $opsi,
    ) {}

    /**
     * @param  array<int, string>  $kolomCari
     * @param  Closure(Model): array<string, mixed>  $opsi
     */
    public static function dari(Builder $query, array $kolomCari, Closure $opsi): self
    {
        return new self($query, $kolomCari, $opsi);
    }

    /** Salinan query (untuk dipersempit pemanggil tanpa mengubah aslinya). */
    public function query(): Builder
    {
        return clone $this->query;
    }

    /** Pilihan baru yang dipersempit lagi — cakupan asal tetap berlaku. */
    public function saring(Closure $saring): self
    {
        $query = $this->query();
        $saring($query);

        return new self($query, $this->kolomCari, $this->opsi);
    }

    /** @return list<array<string, mixed>> seluruh daftar (mode dimuat sekaligus) */
    public function semua(): array
    {
        return $this->petakan($this->query()->get());
    }

    /** @return list<array<string, mixed>> */
    public function awal(int $batas = self::BATAS): array
    {
        return $this->petakan($this->query()->limit($batas)->get());
    }

    /**
     * Isian awal mode server: `$batas` baris pertama (urutan query) ditambah
     * nilai terpilih bila ada di luar potongan itu. Nilai di luar cakupan
     * tidak diberi label (A-384).
     *
     * @return list<array<string, mixed>>
     */
    public function awalDengan(mixed $nilai, int $batas = self::BATAS): array
    {
        $daftar = $this->awal($batas);

        if ($this->kosong($nilai) || collect($daftar)->contains(fn (array $o) => (string) $o['value'] === (string) $nilai)) {
            return $daftar;
        }

        $label = $this->label($nilai);

        return $label === null ? $daftar : [$label, ...$daftar];
    }

    /**
     * Setiap kata harus cocok dengan salah satu kolom cari (mis. "andi gudang"
     * = nama Andi di unit Gudang).
     *
     * @return list<array<string, mixed>>
     */
    public function cari(string $kata, int $batas = self::BATAS): array
    {
        $kata = trim(mb_substr($kata, 0, 100));

        if (mb_strlen($kata) < self::MIN_HURUF) {
            return [];
        }

        $query = $this->query();

        foreach (preg_split('/\s+/u', $kata) ?: [] as $potong) {
            $pola = '%'.addcslashes($potong, '%_\\').'%';

            $query->where(function (Builder $q) use ($pola): void {
                foreach ($this->kolomCari as $kolom) {
                    if (str_contains($kolom, '.')) {
                        [$relasi, $isi] = explode('.', $kolom, 2);
                        $q->orWhereHas($relasi, fn (Builder $r) => $r->where($r->qualifyColumn($isi), 'like', $pola));
                    } else {
                        $q->orWhere($q->qualifyColumn($kolom), 'like', $pola);
                    }
                }
            });
        }

        // `cari` = isi kolom yang dicocokkan (mis. email, kode) supaya saringan
        // Tom Select di browser tidak menyembunyikan hasil yang cocok di kolom itu.
        return array_map(
            fn (Model $m) => ($this->opsi)($m) + ['cari' => $this->teksCari($m)],
            $query->limit($batas)->get()->all(),
        );
    }

    private function teksCari(Model $model): string
    {
        return trim(implode(' ', array_map(fn (string $k) => (string) data_get($model, $k), $this->kolomCari)));
    }

    /** Opsi untuk satu nilai — null bila nilainya di luar daftar (cakupan). */
    public function label(mixed $nilai): ?array
    {
        if ($this->kosong($nilai)) {
            return null;
        }

        $model = $this->query()->whereKey($nilai)->first();

        return $model === null ? null : ($this->opsi)($model);
    }

    public function berisi(mixed $nilai): bool
    {
        return ! $this->kosong($nilai) && $this->query()->whereKey($nilai)->exists();
    }

    /**
     * Aturan validasi: nilai terisi harus ada di daftar ini (di dalam
     * cakupan pembaca). Mencegah id yang dikirim langsung dari browser.
     */
    public function aturan(?string $pesan = null): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal) use ($pesan): void {
            if ($nilai !== null && $nilai !== '' && ! $this->berisi($nilai)) {
                $gagal($pesan ?? __('Pilihan :attribute tidak tersedia. Pilih dari daftar.'));
            }
        };
    }

    /** Kosong, bukan skalar, atau bukan angka untuk kunci angka ("5abc" tidak dianggap 5). */
    private function kosong(mixed $nilai): bool
    {
        if ($nilai === null || $nilai === '' || ! is_scalar($nilai)) {
            return true;
        }

        return $this->query->getModel()->getKeyType() === 'int' && ! ctype_digit((string) $nilai);
    }

    /**
     * @param  iterable<int, Model>  $baris
     * @return list<array<string, mixed>>
     */
    private function petakan(iterable $baris): array
    {
        $hasil = [];

        foreach ($baris as $model) {
            $hasil[] = ($this->opsi)($model);
        }

        return $hasil;
    }
}

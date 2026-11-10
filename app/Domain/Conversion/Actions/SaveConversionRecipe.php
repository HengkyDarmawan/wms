<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\ConversionRecipe;
use App\Domain\Master\Models\Item;

/**
 * Permission: `conversion.recipe` — simpan resep konversi (BR-CNV-06, A-431).
 * Resep tidak pernah dihapus (dipakai di riwayat CNV lewat `recipe_id`, P-03);
 * yang tidak dipakai lagi dinonaktifkan.
 */
class SaveConversionRecipe
{
    /**
     * @param  array{code?: mixed, name?: mixed, conversion_type?: mixed, input_item_id?: mixed, potong?: array<int, array<string, mixed>>, hasil?: array<int, array<string, mixed>>, is_active?: mixed}  $data
     */
    public function handle(?ConversionRecipe $resep, array $data, User $actor): ConversionRecipe
    {
        if (! $actor->hasPermission('conversion.recipe')) {
            throw ConversionRuleException::rule('BR-GEN-09', 'Anda tidak berwenang mengelola resep konversi.');
        }

        $kode = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $nama = trim((string) ($data['name'] ?? ''));
        $jenis = ConversionType::tryFrom((string) ($data['conversion_type'] ?? ''));
        $galat = [];

        if ($kode === '' || mb_strlen($kode) > 30 || ! preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $kode)) {
            $galat['code'] = 'Kode wajib, maks. 30 huruf/angka (boleh . _ -).';
        } elseif (ConversionRecipe::query()->where('code', $kode)->when($resep, fn ($q) => $q->whereKeyNot($resep->id))->exists()) {
            $galat['code'] = 'Kode '.$kode.' sudah dipakai resep lain.';
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            $galat['name'] = 'Nama wajib, maks. 100 karakter.';
        }

        if ($jenis === null) {
            $galat['conversion_type'] = 'Pilih jenis konversi.';
        }

        $input = (int) ($data['input_item_id'] ?? 0);

        if ($input > 0 && ! Item::query()->whereKey($input)->exists()) {
            $galat['input_item_id'] = 'Item input tidak dikenal.';
        }

        $potong = [];
        $hasil = [];

        if ($jenis === ConversionType::Cut) {
            foreach ((array) ($data['potong'] ?? []) as $i => $p) {
                $panjang = $this->angka($p['length'] ?? null);
                $jumlah = (int) ($p['count'] ?? 0);
                $item = (int) ($p['item_id'] ?? 0);

                if ($panjang === null && $jumlah === 0 && $item === 0) {
                    continue; // baris kosong
                }

                if ($panjang === null || $panjang <= 0 || $jumlah < 1 || ($item > 0 && ! Item::query()->whereKey($item)->exists())) {
                    $galat['potong.'.$i] = 'Baris potong '.($i + 1).': ukuran > 0 dan jumlah ≥ 1.';

                    continue;
                }

                $potong[] = ['item_id' => $item > 0 ? $item : null, 'length' => $panjang, 'count' => $jumlah];
            }

            if ($potong === [] && ! isset($galat['conversion_type'])) {
                $galat['potong'] = 'Isi minimal satu ukuran potong.';
            }
        } elseif ($jenis !== null) {
            foreach ((array) ($data['hasil'] ?? []) as $i => $h) {
                $item = (int) ($h['item_id'] ?? 0);
                $qty = $this->angka($h['qty'] ?? null);
                $kind = ($h['kind'] ?? 'output') === 'waste' ? 'waste' : 'output';

                if ($item === 0 && $qty === null) {
                    continue;
                }

                if ($item === 0 || ! Item::query()->whereKey($item)->exists() || $qty === null || $qty <= 0) {
                    $galat['hasil.'.$i] = 'Baris hasil '.($i + 1).': pilih item dan jumlah > 0.';

                    continue;
                }

                $hasil[] = ['kind' => $kind, 'item_id' => $item, 'qty' => $qty];
            }

            if (! collect($hasil)->contains(fn ($h) => $h['kind'] === 'output') && ! array_filter(array_keys($galat), fn ($k) => str_starts_with($k, 'hasil.'))) {
                $galat['hasil'] = 'Isi minimal satu hasil (bukan waste).';
            }
        }

        if ($galat !== []) {
            throw ConversionRuleException::rule('BR-CNV-06', 'Resep belum lengkap.', $galat);
        }

        $nilai = [
            'code' => $kode,
            'name' => $nama,
            'definition' => ['conversion_type' => $jenis->value, 'input_item_id' => $input > 0 ? $input : null, 'potong' => $potong, 'hasil' => $hasil],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($resep?->is_active ?? true),
        ];

        if ($resep === null) {
            $resep = ConversionRecipe::create($nilai + ['created_by' => $actor->id]);
        } else {
            $resep->update($nilai);
        }

        activity('conversion')->performedOn($resep)->causedBy($actor)
            ->withProperties(['kode' => $kode, 'aktif' => $resep->is_active])
            ->log('Resep konversi '.$kode.' disimpan');

        return $resep->refresh();
    }

    private function angka(mixed $nilai): ?float
    {
        $isi = str_replace(',', '.', trim((string) $nilai));

        return is_numeric($isi) ? round((float) $isi, 4) : null;
    }
}

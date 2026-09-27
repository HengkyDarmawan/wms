<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\LabelMedia;
use App\Domain\Template\Models\LabelFormat;
use Illuminate\Validation\ValidationException;

/**
 * Tambah atau ubah ukuran label (A-261, 18 §6). Permission:
 * `document_layout.manage` (Admin Company); otorisasi di layar.
 *
 * Gulungan: halaman = satu label. Lembar: kolom × baris harus muat di
 * halaman bersama margin dan jarak antar label. Kode huruf besar dan
 * terkunci setelah dibuat (pola BR-MST-01).
 */
class SaveLabelFormat
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data, User $actor, ?LabelFormat $format = null): LabelFormat
    {
        $isi = $this->validate($data, $format);
        $baru = $format === null;
        $format ??= new LabelFormat(['is_active' => true]);
        $format->fill($isi)->save();

        activity('template')->performedOn($format)->causedBy($actor)
            ->withProperties(['ukuran' => $format->summary()])
            ->log(($baru ? 'Ukuran label ditambah: ' : 'Ukuran label diubah: ').$format->code);

        return $format->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data, ?LabelFormat $format): array
    {
        $galat = [];
        $kode = $format?->code ?? strtoupper(trim((string) ($data['code'] ?? '')));
        $nama = trim((string) ($data['name'] ?? ''));
        $media = LabelMedia::tryFrom((string) ($data['media'] ?? ''));

        if ($format === null) {
            if (! preg_match('/^[A-Z0-9][A-Z0-9\-]{0,19}$/', $kode)) {
                $galat['code'] = __('Kode 1–20 karakter: huruf besar, angka, atau tanda hubung.');
            } elseif (LabelFormat::query()->where('code', $kode)->exists()) {
                $galat['code'] = __('Kode sudah dipakai.');
            }
        }
        if ($nama === '' || mb_strlen($nama) > 80) {
            $galat['name'] = __('Nama wajib diisi, maksimal 80 karakter.');
        }
        if ($media === null) {
            $galat['media'] = __('Pilih bentuk kertas.');
        }

        $w = $this->mm($data, 'width_mm', LabelFormat::MIN_LABEL, LabelFormat::MAKS_LABEL, $galat, __('Lebar label'));
        $h = $this->mm($data, 'height_mm', LabelFormat::MIN_LABEL, LabelFormat::MAKS_LABEL, $galat, __('Tinggi label'));

        $isi = [
            'code' => $kode, 'name' => $nama, 'media' => $media,
            'width_mm' => $w, 'height_mm' => $h,
            'page_width_mm' => null, 'page_height_mm' => null,
            'columns' => 1, 'rows' => 1,
            'margin_top_mm' => 0, 'margin_left_mm' => 0, 'gap_x_mm' => 0, 'gap_y_mm' => 0,
        ];

        if ($media === LabelMedia::Sheet) {
            $pw = $this->mm($data, 'page_width_mm', 50, 500, $galat, __('Lebar halaman'));
            $ph = $this->mm($data, 'page_height_mm', 50, 500, $galat, __('Tinggi halaman'));
            $kolom = $this->bulat($data, 'columns', 1, 10, $galat, __('Kolom'));
            $baris = $this->bulat($data, 'rows', 1, 40, $galat, __('Baris'));
            $mt = $this->mm($data, 'margin_top_mm', 0, 100, $galat, __('Margin atas'));
            $ml = $this->mm($data, 'margin_left_mm', 0, 100, $galat, __('Margin kiri'));
            $gx = $this->mm($data, 'gap_x_mm', 0, 50, $galat, __('Jarak antar kolom'));
            $gy = $this->mm($data, 'gap_y_mm', 0, 50, $galat, __('Jarak antar baris'));

            if ($galat === []) {
                $pakaiLebar = $ml + $kolom * $w + ($kolom - 1) * $gx;
                $pakaiTinggi = $mt + $baris * $h + ($baris - 1) * $gy;

                if ($pakaiLebar > $pw + 0.01) {
                    $galat['columns'] = __('Label tidak muat ke samping: butuh :a mm, halaman :b mm.', ['a' => LabelFormat::mm($pakaiLebar), 'b' => LabelFormat::mm($pw)]);
                }
                if ($pakaiTinggi > $ph + 0.01) {
                    $galat['rows'] = __('Label tidak muat ke bawah: butuh :a mm, halaman :b mm.', ['a' => LabelFormat::mm($pakaiTinggi), 'b' => LabelFormat::mm($ph)]);
                }
            }

            $isi = array_merge($isi, [
                'page_width_mm' => $pw, 'page_height_mm' => $ph, 'columns' => $kolom, 'rows' => $baris,
                'margin_top_mm' => $mt, 'margin_left_mm' => $ml, 'gap_x_mm' => $gx, 'gap_y_mm' => $gy,
            ]);
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $isi;
    }

    /** @param  array<string, string>  $galat */
    private function mm(array $data, string $kunci, float $min, float $maks, array &$galat, string $nama): float
    {
        $nilai = str_replace(',', '.', trim((string) ($data[$kunci] ?? '')));

        if (! is_numeric($nilai) || (float) $nilai < $min || (float) $nilai > $maks) {
            $galat[$kunci] = __(':nama harus :min–:maks mm.', ['nama' => $nama, 'min' => LabelFormat::mm($min), 'maks' => LabelFormat::mm($maks)]);

            return 0.0;
        }

        return round((float) $nilai, 2);
    }

    /** @param  array<string, string>  $galat */
    private function bulat(array $data, string $kunci, int $min, int $maks, array &$galat, string $nama): int
    {
        $nilai = trim((string) ($data[$kunci] ?? ''));

        if (! ctype_digit($nilai) || (int) $nilai < $min || (int) $nilai > $maks) {
            $galat[$kunci] = __(':nama harus :min–:maks.', ['nama' => $nama, 'min' => $min, 'maks' => $maks]);

            return $min;
        }

        return (int) $nilai;
    }
}

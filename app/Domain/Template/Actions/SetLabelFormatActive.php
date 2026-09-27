<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\LabelFormat;
use Illuminate\Validation\ValidationException;

/**
 * Nonaktifkan atau aktifkan kembali ukuran label (A-261). Tidak dihapus
 * (P-03). Format yang menjadi bawaan salah satu jenis label, atau format
 * aktif terakhir, tidak bisa dinonaktifkan.
 */
class SetLabelFormatActive
{
    public function handle(LabelFormat $format, bool $active, User $actor): LabelFormat
    {
        if (! $active) {
            $bawaan = collect(DocumentTemplateType::labels())
                ->filter(fn (DocumentTemplateType $t) => (int) LabelFormat::defaultFor($t)?->id === (int) $format->id)
                ->map(fn (DocumentTemplateType $t) => $t->label());

            if ($bawaan->isNotEmpty()) {
                throw ValidationException::withMessages(['format' => __('Masih menjadi ukuran bawaan :jenis. Pilih ukuran lain di Desain label dulu.', ['jenis' => $bawaan->implode(', ')])]);
            }

            if (LabelFormat::query()->active()->whereKeyNot($format->id)->doesntExist()) {
                throw ValidationException::withMessages(['format' => __('Harus ada minimal satu ukuran label aktif.')]);
            }
        }

        $format->forceFill(['is_active' => $active])->save();

        activity('template')->performedOn($format)->causedBy($actor)
            ->log(($active ? 'Ukuran label diaktifkan: ' : 'Ukuran label dinonaktifkan: ').$format->code);

        return $format->refresh();
    }
}

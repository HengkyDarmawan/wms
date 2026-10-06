<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Approval\Support\ApprovalRuleSentence;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `approval_rule.manage` — tombol *Kembalikan ke aturan dasar* (A-406).
 *
 * Per jenis dokumen yang dipilih:
 *  - aturan aktif buatan sendiri **dinonaktifkan**, tidak dihapus (P-03);
 *  - aturan dasar ditulis ulang ke bentuk bawaan dan diaktifkan, atau dibuat
 *    bila belum ada ({@see InstallBasicApprovalRules::tulis()});
 *  - ADJ, OPN, ISU tidak punya aturan dasar: semua aturannya dinonaktifkan
 *    sehingga kembali ke lapis minimum sistem.
 *
 * Dokumen yang sedang menunggu tidak terpengaruh karena memegang salinan
 * aturannya (BR-APR-01).
 */
class ResetBasicApprovalRules
{
    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly InstallBasicApprovalRules $dasar,
        private readonly SaveApprovalRule $saveRule,
    ) {}

    /**
     * @return array<int, array{jenis: ApprovalDocumentType, dinonaktifkan: int, hasil: string, peringatan: ?string}>
     */
    public function preview(): array
    {
        $aktif = ApprovalRule::query()->where('is_active', true)->get()
            ->groupBy(fn (ApprovalRule $r) => $r->document_type->value);

        return array_map(function (ApprovalDocumentType $jenis) use ($aktif): array {
            $aturan = $aktif->get($jenis->value, collect());
            $minimum = $this->minimum($jenis);

            return [
                'jenis' => $jenis,
                'dinonaktifkan' => $aturan->filter(fn (ApprovalRule $r) => $minimum || ! $r->is_basic)->count(),
                'hasil' => $minimum
                    ? __('lapis minimum sistem:').' '.__(ApprovalRuleSentence::LAPIS_MINIMUM[$jenis->value] ?? '')
                    : $this->dasar->labelApprover($jenis),
                // Jenis yang kini tanpa aturan aktif berubah dari "disetujui otomatis".
                'peringatan' => ! $minimum && $aturan->isEmpty() ? (InstallBasicApprovalRules::PERINGATAN[$jenis->value] ?? null) : null,
            ];
        }, $this->registry->types());
    }

    /**
     * @param  array<int, string>  $jenis  nilai `ApprovalDocumentType`
     * @return array<int, ApprovalDocumentType> jenis yang dikembalikan
     */
    public function handle(array $jenis, ?User $actor = null): array
    {
        $dipilih = array_values(array_filter(
            $this->registry->types(),
            fn (ApprovalDocumentType $t) => in_array($t->value, $jenis, true),
        ));

        return DB::transaction(function () use ($dipilih, $actor): array {
            foreach ($dipilih as $t) {
                $aturan = ApprovalRule::query()->where('document_type', $t->value)->orderBy('id')->lockForUpdate()->get();
                $dasar = $this->minimum($t) ? null : $aturan->firstWhere('is_basic', true);

                foreach ($aturan as $r) {
                    if ($r->is_active && $r->id !== $dasar?->id) {
                        $this->saveRule->setActive($r, false, $actor);
                    }
                }

                if (! $this->minimum($t)) {
                    $this->dasar->tulis($dasar, $t, $actor);
                }
            }

            activity('approval')->causedBy($actor)
                ->withProperties(['jenis' => array_map(fn (ApprovalDocumentType $t) => $t->value, $dipilih)])
                ->log('Aturan dikembalikan ke aturan dasar');

            return $dipilih;
        });
    }

    private function minimum(ApprovalDocumentType $jenis): bool
    {
        return in_array($jenis, InstallBasicApprovalRules::DILEWATI, true);
    }
}

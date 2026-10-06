<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `approval_rule.manage` — tombol *Pasang aturan dasar* (A-347).
 *
 * Membuat **satu aturan umum biasa** per jenis dokumen yang tersambung ke
 * mesin approval dan belum punya aturan sama sekali (aktif maupun nonaktif):
 *  - REQ → Kepala gudang terkait (pemohon bisa klien tanpa jabatan);
 *  - jenis lain → Atasan langsung pemohon, 1 tingkat (peta jabatan, A-344).
 * Prioritas paling akhir (900), batas 24 jam, tanpa cadangan eksplisit —
 * lewat batas waktu mesin menaikkan ke atasan si approver (BR-APR-06).
 *
 * ADJ, OPN, dan ISU **dilewati**: tanpa aturan pun sudah dijaga lapis minimum
 * sistem, dan aturan "atasan langsung" akan menggantikannya (keputusan pemilik
 * produk 29 Sep 2026). Idempoten; tidak pernah menimpa aturan yang ada. Mesin
 * tidak berubah: jenis tanpa aturan tetap disetujui otomatis (A-08).
 *
 * Juga dijalankan otomatis saat provisioning company baru (A-405), dan
 * {@see ResetBasicApprovalRules} memakai {@see tulis()} sebagai satu sumber
 * bentuk aturan dasar (A-406).
 */
class InstallBasicApprovalRules
{
    public const PRIORITAS = 900;

    public const BATAS_JAM = 24;

    /** Jenis yang dijaga lapis minimum sistem. */
    public const DILEWATI = [
        ApprovalDocumentType::StockAdjustment,
        ApprovalDocumentType::StockCount,
        ApprovalDocumentType::MaterialIssue,
    ];

    /** Peringatan perubahan alur kerja bila aturan dipasang. */
    public const PERINGATAN = [
        'conversion' => 'Konversi material tidak lagi bisa langsung diselesaikan; harus diajukan ke approval dulu.',
        'waste_disposal' => 'Berita acara waste tidak lagi disetujui otomatis.',
    ];

    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly SaveApprovalRule $saveRule,
    ) {}

    /**
     * Rencana per jenis: `pasang` | `ada_aturan` | `lapis_minimum`.
     *
     * @return array<int, array{jenis: ApprovalDocumentType, status: string, approver: ?string, peringatan: ?string}>
     */
    public function preview(): array
    {
        $punyaAturan = ApprovalRule::query()->distinct()->pluck('document_type')
            ->map(fn ($v) => $v instanceof ApprovalDocumentType ? $v->value : (string) $v)->all();

        return array_map(function (ApprovalDocumentType $jenis) use ($punyaAturan): array {
            $status = match (true) {
                in_array($jenis, self::DILEWATI, true) => 'lapis_minimum',
                in_array($jenis->value, $punyaAturan, true) => 'ada_aturan',
                default => 'pasang',
            };

            return [
                'jenis' => $jenis,
                'status' => $status,
                'approver' => $status === 'pasang' ? $this->labelApprover($jenis) : null,
                'peringatan' => $status === 'pasang' ? (self::PERINGATAN[$jenis->value] ?? null) : null,
            ];
        }, $this->registry->types());
    }

    /** @return array<int, ApprovalRule> aturan yang baru dipasang */
    public function handle(?User $actor = null): array
    {
        return DB::transaction(function () use ($actor): array {
            $dipasang = [];

            foreach ($this->preview() as $rencana) {
                if ($rencana['status'] !== 'pasang') {
                    continue;
                }

                $jenis = $rencana['jenis'];

                // Diperiksa ulang di dalam transaksi: klik ganda tidak menggandakan.
                if (ApprovalRule::query()->where('document_type', $jenis->value)->lockForUpdate()->exists()) {
                    continue;
                }

                $dipasang[] = $this->tulis(null, $jenis, $actor);
            }

            activity('approval')->causedBy($actor)
                ->withProperties(['jenis' => array_map(fn (ApprovalRule $r) => $r->document_type->value, $dipasang)])
                ->log('Aturan dasar dipasang');

            return $dipasang;
        });
    }

    /**
     * Satu sumber "seperti apa aturan dasar": membuat aturan baru, atau menulis
     * ulang aturan dasar yang ada ke bawaan (A-406). Selalu aktif.
     */
    public function tulis(?ApprovalRule $rule, ApprovalDocumentType $jenis, ?User $actor = null): ApprovalRule
    {
        $rule = $this->saveRule->handle($rule, [
            'document_type' => $jenis->value,
            'name' => 'Aturan dasar — '.$jenis->label(),
            'priority' => self::PRIORITAS,
            'is_active' => true,
            'conditions' => [],
        ], [$this->lapis($jenis)], $actor);

        $rule->forceFill(['is_basic' => true])->save();

        return $rule;
    }

    /** @return array<string, mixed> */
    private function lapis(ApprovalDocumentType $jenis): array
    {
        $req = $jenis === ApprovalDocumentType::MaterialRequest;

        return [
            'approver_type' => $req ? ApproverType::WarehouseHead->value : ApproverType::DirectManager->value,
            'approver_ref_id' => null,
            'manager_levels' => $req ? null : 1,
            'decision_mode' => DecisionMode::Any->value,
            'timeout_hours' => self::BATAS_JAM,
            'channel' => 'web',
            'backup_approver_type' => '',
            'backup_ref_id' => '',
        ];
    }

    public function labelApprover(ApprovalDocumentType $jenis): string
    {
        return $jenis === ApprovalDocumentType::MaterialRequest ? 'Kepala gudang terkait' : 'Atasan langsung pemohon (1 tingkat)';
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;

/**
 * Mengubah lapis aturan menjadi lapis snapshot: approver nyata per lapis,
 * setelah pemisahan tugas dan eskalasi saat resolusi (BR-APR-01, BR-APR-03,
 * BR-APR-06). Dipakai saat dokumen diajukan dan oleh simulasi (BR-APR-11),
 * jadi keduanya selalu memberi jawaban yang sama.
 *
 * Urutan pengganti bila lapis tidak punya approver yang memenuhi syarat:
 * atasan pengaju (bila lapis menunjuk pengaju sendiri) → approver cadangan
 * lapis → atasan approver yang tidak memenuhi syarat → Admin Company dengan
 * peringatan. Bila semuanya kosong, lapis "buntu" dan dokumen tidak bisa
 * diajukan.
 */
class ApprovalPlanner
{
    public function __construct(private readonly ApproverResolver $resolver) {}

    /**
     * @param  array<int, array<string, mixed>>  $steps  lapis mentah (ApprovalStep::toPlanInput)
     * @return array<int, array<string, mixed>>
     */
    public function plan(array $steps, ApprovalContext $ctx, string $permission): array
    {
        usort($steps, fn (array $a, array $b) => (int) $a['step_no'] <=> (int) $b['step_no']);

        $hasil = [];

        foreach ($steps as $step) {
            $hasil[] = $this->planStep($step, $ctx, $permission);
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function planStep(array $step, ApprovalContext $ctx, string $permission): array
    {
        $jenis = ApproverType::from((string) $step['approver_type']);
        $ref = isset($step['approver_ref_id']) && $step['approver_ref_id'] !== '' ? (int) $step['approver_ref_id'] : null;
        $pemohon = $ctx->requesterIds;
        $catatan = [];
        $keAdmin = false;

        $kandidat = $this->resolver->resolve($jenis, $ref, $ctx);
        $kenaSod = array_values(array_intersect($kandidat, $pemohon));
        $sisa = array_values(array_diff($kandidat, $pemohon));

        if ($kenaSod !== []) {
            $catatan[] = 'Pengaju/pemohon dilewati (BR-APR-03).';
        }

        $layak = $this->resolver->eligible($sisa, $permission);
        $tidakLayak = array_values(array_diff($sisa, $layak));

        if ($tidakLayak !== []) {
            $catatan[] = 'Tidak memenuhi syarat (nonaktif atau tanpa izin '.$permission.'): '.$this->nama($tidakLayak).'.';
        }

        // BR-APR-03: lapis yang hanya menunjuk pengaju dilewati ke atasannya.
        if ($layak === [] && $kenaSod !== [] && $sisa === []) {
            $layak = $this->pengganti(array_map(fn (int $u) => $this->resolver->managerOf($u), $kenaSod), $pemohon, $permission);

            if ($layak !== []) {
                $catatan[] = 'Dialihkan ke atasan pengaju (BR-APR-03).';
            }
        }

        // BR-APR-06: approver cadangan lapis.
        if ($layak === [] && ! empty($step['backup_approver_type'])) {
            $cadangan = $this->resolver->resolve(
                ApproverType::from((string) $step['backup_approver_type']),
                isset($step['backup_ref_id']) && $step['backup_ref_id'] !== '' ? (int) $step['backup_ref_id'] : null,
                $ctx,
            );
            $layak = $this->pengganti($cadangan, $pemohon, $permission);

            if ($layak !== []) {
                $catatan[] = 'Dialihkan ke approver cadangan (BR-APR-06).';
            }
        }

        // BR-APR-06: atasan approver yang tidak memenuhi syarat.
        if ($layak === [] && $tidakLayak !== []) {
            $layak = $this->pengganti(array_map(fn (int $u) => $this->resolver->managerOf($u), $tidakLayak), $pemohon, $permission);

            if ($layak !== []) {
                $catatan[] = 'Dialihkan ke atasan approver (BR-APR-06).';
            }
        }

        // BR-APR-06: terakhir Admin Company, dengan peringatan.
        if ($layak === []) {
            $admin = $this->pengganti($this->resolver->companyAdmins(), $pemohon, $permission);

            if ($admin !== []) {
                $layak = [$admin[0]];
                $keAdmin = true;
                $catatan[] = 'Peringatan: tidak ada approver yang memenuhi syarat; dialihkan ke Admin Company (BR-APR-06).';
            }
        }

        return [
            'step_no' => (int) $step['step_no'],
            'approver_type' => $jenis->value,
            'approver_ref_id' => $ref,
            'approver_label' => $this->resolver->label($jenis, $ref),
            'decision_mode' => DecisionMode::tryFrom((string) ($step['decision_mode'] ?? ''))?->value ?? DecisionMode::Any->value,
            'backup_approver_type' => $step['backup_approver_type'] ?? null,
            'backup_ref_id' => $step['backup_ref_id'] ?? null,
            'timeout_hours' => max(1, (int) ($step['timeout_hours'] ?? 24)),
            'channel' => $step['channel'] ?? 'web',
            'approver_user_ids' => $layak,
            'approver_names' => $this->daftarNama($layak),
            'escalated_to_admin' => $keAdmin,
            'blocked' => $layak === [],
            'notes' => $catatan,
        ];
    }

    /**
     * @param  array<int, int|null>  $ids
     * @param  array<int, int>  $pemohon
     * @return array<int, int>
     */
    private function pengganti(array $ids, array $pemohon, string $permission): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($v) => $v === null ? null : (int) $v, $ids))));
        $ids = array_values(array_diff($ids, $pemohon));
        sort($ids);

        return $this->resolver->eligible($ids, $permission);
    }

    /** @param  array<int, int>  $ids */
    private function nama(array $ids): string
    {
        return implode(', ', $this->daftarNama($ids));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function daftarNama(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $nama = User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return array_map(fn (int $id) => (string) ($nama[$id] ?? '#'.$id), $ids);
    }
}

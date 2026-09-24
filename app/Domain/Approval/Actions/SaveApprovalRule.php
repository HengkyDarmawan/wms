<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalStep;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Approval\Support\ConditionMatcher;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `approval_rule.manage` — membuat, mengubah, menonaktifkan, dan
 * mengaktifkan kembali aturan approval (Blueprint §8.1, D-17).
 *
 * Kondisi tidak pernah memuat nilai uang (BR-APR-07, D-07): kunci yang tidak
 * dikenal dibuang. Lapis ditulis ulang utuh saat aturan disimpan; dokumen yang
 * sedang menunggu tidak terpengaruh karena memegang salinannya (BR-APR-01,
 * A-94). Aturan tidak dihapus, hanya dinonaktifkan (P-03).
 */
class SaveApprovalRule
{
    public const MAX_STEPS = 10;

    public function __construct(private readonly ApprovalRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $data  document_type, name, priority, is_active, conditions
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(?ApprovalRule $rule, array $data, array $steps, ?User $actor = null): ApprovalRule
    {
        $jenis = ApprovalDocumentType::tryFrom((string) ($data['document_type'] ?? ''));

        if ($jenis === null || ! $this->registry->has($jenis)) {
            throw ApprovalRuleException::field('BR-GEN-10', 'document_type', 'Jenis dokumen belum tersambung ke mesin approval.');
        }

        if ($rule !== null && $rule->document_type !== $jenis) {
            throw ApprovalRuleException::field('BR-APR-01', 'document_type', 'Jenis dokumen aturan tidak bisa diubah; buat aturan baru.');
        }

        $nama = trim((string) ($data['name'] ?? ''));

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw ApprovalRuleException::field('BR-GEN-11', 'name', 'Nama aturan wajib diisi (maks. 100 karakter).');
        }

        $prioritas = (int) ($data['priority'] ?? 100);

        if ($prioritas < 1 || $prioritas > 9999) {
            throw ApprovalRuleException::field('BR-APR-01', 'priority', 'Prioritas 1–9999 (kecil diperiksa lebih dulu).');
        }

        $kondisi = ConditionMatcher::normalize((array) ($data['conditions'] ?? []));
        $kondisi = array_intersect_key($kondisi, array_flip(array_merge(['match'], $jenis->conditions())));

        $lapis = $this->lapis($steps);

        return DB::transaction(function () use ($rule, $jenis, $nama, $prioritas, $kondisi, $lapis, $data, $actor) {
            $rule ??= new ApprovalRule(['document_type' => $jenis]);

            $rule->fill([
                'name' => $nama,
                'priority' => $prioritas,
                'conditions' => $kondisi,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            ApprovalStep::query()->where('approval_rule_id', $rule->id)->delete();

            foreach ($lapis as $l) {
                ApprovalStep::create($l + ['approval_rule_id' => $rule->id]);
            }

            activity('approval')->performedOn($rule)->causedBy($actor)
                ->withProperties(['lapis' => $lapis, 'kondisi' => $kondisi])
                ->log('Aturan approval disimpan');

            return $rule->refresh()->load('steps');
        });
    }

    public function setActive(ApprovalRule $rule, bool $active, ?User $actor = null): ApprovalRule
    {
        $rule->forceFill(['is_active' => $active])->save();

        activity('approval')->performedOn($rule)->causedBy($actor)
            ->log($active ? 'Aturan approval diaktifkan' : 'Aturan approval dinonaktifkan');

        return $rule->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function lapis(array $steps): array
    {
        $steps = array_values($steps);

        if ($steps === []) {
            throw ApprovalRuleException::field('D-17', 'steps', 'Aturan harus punya minimal satu lapis.');
        }

        if (count($steps) > self::MAX_STEPS) {
            throw ApprovalRuleException::field('D-17', 'steps', 'Maksimal '.self::MAX_STEPS.' lapis.');
        }

        $hasil = [];

        foreach ($steps as $i => $s) {
            $no = $i + 1;
            $jenis = ApproverType::tryFrom((string) ($s['approver_type'] ?? ''));

            if ($jenis === null) {
                throw ApprovalRuleException::field('BR-GEN-11', 'steps.'.$i.'.approver_type', 'Lapis '.$no.': jenis approver wajib dipilih.');
            }

            $ref = $this->rujukan($jenis, $s['approver_ref_id'] ?? null, $no, 'steps.'.$i.'.approver_ref_id');

            $mode = DecisionMode::tryFrom((string) ($s['decision_mode'] ?? DecisionMode::Any->value));

            if ($mode === null) {
                throw ApprovalRuleException::field('BR-GEN-11', 'steps.'.$i.'.decision_mode', 'Lapis '.$no.': cara putus tidak dikenal.');
            }

            $jam = (int) ($s['timeout_hours'] ?? 24);

            if ($jam < 1 || $jam > 720) {
                throw ApprovalRuleException::field('BR-APR-08', 'steps.'.$i.'.timeout_hours', 'Lapis '.$no.': batas waktu 1–720 jam.');
            }

            $cadangan = ApproverType::tryFrom((string) ($s['backup_approver_type'] ?? ''));
            $refCadangan = $cadangan !== null
                ? $this->rujukan($cadangan, $s['backup_ref_id'] ?? null, $no, 'steps.'.$i.'.backup_ref_id')
                : null;

            $hasil[] = [
                'step_no' => $no,
                'approver_type' => $jenis->value,
                'approver_ref_id' => $ref,
                'decision_mode' => $mode->value,
                'backup_approver_type' => $cadangan?->value,
                'backup_ref_id' => $refCadangan,
                'timeout_hours' => $jam,
                'channel' => 'web',          // WhatsApp: Fase 2a (BR-GEN-10)
                'require_pin' => false,
            ];
        }

        return $hasil;
    }

    private function rujukan(ApproverType $jenis, mixed $ref, int $no, string $field): ?int
    {
        if (! $jenis->needsReference()) {
            return null;
        }

        $id = (int) $ref;
        $ada = $id > 0 && match ($jenis) {
            ApproverType::User => User::query()->whereKey($id)->whereNull('client_id')->exists(),
            ApproverType::Position => Position::query()->whereKey($id)->exists(),
            ApproverType::Role => Role::query()->whereKey($id)->where('is_client_role', false)->exists(),
            default => false,
        };

        if (! $ada) {
            throw ApprovalRuleException::field('BR-GEN-11', $field, 'Lapis '.$no.': pilih '.mb_strtolower($jenis->label()).'.');
        }

        return $id;
    }
}

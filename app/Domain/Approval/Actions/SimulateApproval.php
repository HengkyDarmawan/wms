<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Approval\Support\ApprovalPlanner;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Approval\Support\ApprovalRuleSentence;
use App\Domain\Approval\Support\ConditionMatcher;

/**
 * Permission: `approval.simulate` — "siapa yang akan menyetujui dokumen ini?"
 * (BR-APR-11). Tidak menulis apa pun.
 *
 * Memakai perencana yang sama dengan pengajuan sungguhan, jadi jawabannya
 * sama dengan yang akan terjadi saat dokumen diajukan sekarang. Bisa untuk
 * aturan tersimpan (semua aturan aktif diperiksa berurutan) atau untuk draf
 * aturan yang belum disimpan.
 */
class SimulateApproval
{
    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly ApprovalEngine $engine,
        private readonly ApprovalPlanner $planner,
        private readonly ConditionMatcher $matcher,
    ) {}

    /** Konteks dari dokumen nyata yang dicari dengan nomornya. */
    public function contextForDocument(ApprovalDocumentType $type, string $number): ApprovalContext
    {
        $handler = $this->registry->handler($type);
        $dokumen = $handler->findByNumber(trim($number));

        if ($dokumen === null) {
            throw ApprovalRuleException::field('BR-APR-11', 'number', 'Dokumen '.$type->code().' bernomor itu tidak ditemukan.');
        }

        return $handler->context($dokumen);
    }

    /**
     * "Cek untuk orang" di Peta approval (A-349): dokumen rekaan dari seorang
     * pemohon, jenis dokumen, dan (opsional) gudang/proyek. Pemohon klien
     * dihitung sebagai permintaan dari klien.
     */
    public function contextForPerson(ApprovalDocumentType $type, int $userId, ?int $warehouseId = null, ?int $projectId = null): ApprovalContext
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw ApprovalRuleException::field('BR-APR-11', 'requester', 'Pemohon tidak ditemukan.');
        }

        return new ApprovalContext(
            documentType: $type,
            warehouseIds: $warehouseId !== null ? [$warehouseId] : [],
            projectId: $projectId,
            lineCount: 1,
            maxLineQty: 1.0,
            fromClient: $user->client_id !== null,
            requesterId: (int) $user->id,
            requesterIds: [(int) $user->id],
        );
    }

    /**
     * @param  array{conditions?: array<string, mixed>, steps?: array<int, array<string, mixed>>}|null  $draft
     * @return array<string, mixed>
     */
    public function run(ApprovalContext $ctx, ?array $draft = null): array
    {
        $handler = $this->registry->handler($ctx->documentType);
        $izin = $handler->approvePermission();

        if ($draft !== null) {
            $hasil = $this->matcher->evaluate($draft['conditions'] ?? [], $ctx);
            $lapis = $hasil['matched'] ? $this->planner->plan($this->nomori($draft['steps'] ?? []), $ctx, $izin) : [];

            return [
                'mode' => 'draft',
                'matched' => $hasil['matched'],
                'checks' => $hasil['checks'],
                'evaluations' => [],
                'rule' => null,
                'layers' => $lapis,
                'auto_approved' => false,
                'blocked' => collect($lapis)->contains(fn (array $l) => $l['blocked']),
                'permission' => $izin,
            ];
        }

        ['rule' => $rule, 'evaluations' => $evaluasi] = $this->engine->matchRule($ctx->documentType, $ctx);

        // Tanpa aturan: lapis minimum penangan, sama seperti pengajuan sungguhan
        // (ApprovalEngine::submit). Butuh dokumennya; untuk data rekaan cukup
        // disebutkan (A-349) supaya tidak keliru terbaca "disetujui otomatis".
        $dokumen = $rule === null && $ctx->documentNumber !== null ? $handler->findByNumber($ctx->documentNumber) : null;
        $mentah = $rule !== null ? $rule->steps->map->toPlanInput()->all() : ($dokumen !== null ? $handler->fallbackSteps($dokumen) : []);
        $lapis = $this->planner->plan($mentah, $ctx, $izin);
        $minimum = $rule === null && $lapis === [] && $dokumen === null
            ? (ApprovalRuleSentence::LAPIS_MINIMUM[$ctx->documentType->value] ?? null)
            : null;

        return [
            'mode' => 'stored',
            'matched' => $rule !== null,
            'checks' => [],
            'evaluations' => $evaluasi,
            'rule' => $rule === null ? null : ['id' => $rule->id, 'name' => $rule->name, 'priority' => $rule->priority],
            'layers' => $lapis,
            'auto_approved' => $lapis === [] && $minimum === null,
            'minimum' => $minimum,
            'blocked' => collect($lapis)->contains(fn (array $l) => $l['blocked']),
            'permission' => $izin,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function nomori(array $steps): array
    {
        $hasil = [];

        foreach (array_values($steps) as $i => $s) {
            if (($s['approver_type'] ?? '') === '') {
                continue;
            }

            $hasil[] = $s + ['step_no' => $i + 1] + ['decision_mode' => 'any', 'timeout_hours' => 24];
            $hasil[count($hasil) - 1]['step_no'] = $i + 1;
        }

        return $hasil;
    }
}

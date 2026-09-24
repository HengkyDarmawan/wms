<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalDecision;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Mesin approval (alur 9, BR-APR-01–09, D-17, D-28).
 *
 * Satu-satunya penulis tabel snapshot, tugas, dan keputusan. Dokumen tidak
 * pernah mengubah status approval-nya sendiri: modul dokumen memanggil
 * `submit()`/`withdraw()`, lalu mesin memanggil balik penangannya
 * (`onApproved`/`onRejected`) saat keputusan akhir jatuh.
 */
class ApprovalEngine
{
    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly ConditionMatcher $matcher,
        private readonly ApprovalPlanner $planner,
        private readonly ApproverResolver $resolver,
        private readonly ApprovalNotifier $notifier,
    ) {}

    // ------------------------------------------------------------ pengajuan

    /**
     * Aturan aktif pertama (urut prioritas) yang kondisinya cocok.
     *
     * @return array{rule: ApprovalRule|null, evaluations: array<int, array<string, mixed>>}
     */
    public function matchRule(ApprovalDocumentType $type, ApprovalContext $ctx): array
    {
        $cocok = null;
        $evaluasi = [];

        $aturan = ApprovalRule::query()->active()->where('document_type', $type->value)
            ->evaluationOrder()->with('steps')->get();

        foreach ($aturan as $rule) {
            $hasil = $this->matcher->evaluate($rule->conditions, $ctx);
            $evaluasi[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'priority' => $rule->priority,
                'matched' => $hasil['matched'],
                'checks' => $hasil['checks'],
                'chosen' => $cocok === null && $hasil['matched'],
            ];

            if ($cocok === null && $hasil['matched']) {
                $cocok = $rule;
            }
        }

        return ['rule' => $cocok, 'evaluations' => $evaluasi];
    }

    /**
     * Dokumen masuk `pending_approval`: snapshot aturan yang cocok dibuat dan
     * tugas lapis pertama dikirim. Tanpa aturan (dan tanpa lapis minimum
     * penangan) dokumen langsung disetujui (A-08, BR-APR-02).
     *
     * Dipanggil dari dalam transaksi aksi dokumen; kegagalan di sini
     * membatalkan pengajuan seluruhnya.
     */
    public function submit(ApprovalDocumentType|string $type, Model $document, ?User $submitter): ApprovalSnapshot
    {
        $handler = $this->registry->handler($type);

        return DB::transaction(function () use ($handler, $document, $submitter) {
            $jenis = $handler->documentType();

            // Pengajuan ulang membuang snapshot lama yang masih menunggu.
            $this->withdraw($jenis, (int) $document->getKey(), 'Dokumen diajukan ulang.', $submitter);

            $ctx = $handler->context($document);
            ['rule' => $rule] = $this->matchRule($jenis, $ctx);

            $lapisMentah = $rule !== null
                ? $rule->steps->map->toPlanInput()->all()
                : $handler->fallbackSteps($document);

            $lapis = $this->planner->plan($lapisMentah, $ctx, $handler->approvePermission());

            foreach ($lapis as $l) {
                if ($l['blocked']) {
                    throw ApprovalRuleException::rule(
                        'BR-APR-06',
                        'Lapis '.$l['step_no'].' ('.$l['approver_label'].') tidak punya approver yang memenuhi syarat, dan tidak ada Admin Company pengganti.',
                    );
                }
            }

            $snapshot = ApprovalSnapshot::create([
                'document_type' => $jenis->value,
                'document_id' => $document->getKey(),
                'document_number' => $handler->number($document),
                'rule_id' => $rule?->id,
                'rule_name' => $rule?->name,
                'context' => $ctx->toArray(),
                'steps' => $lapis,
                'status' => ApprovalSnapshotStatus::Pending,
                'current_step' => null,
                'submitted_by' => $submitter?->id,
                'submitted_at' => now(),
            ]);

            $handler->attachSnapshot($document, $snapshot);

            if ($lapis === []) {
                $snapshot->forceFill(['status' => ApprovalSnapshotStatus::Approved, 'decided_at' => now()])->save();

                $this->catat($handler, $document, $submitter, 'Disetujui otomatis: tidak ada aturan approval yang berlaku (A-08)', [
                    'snapshot' => $snapshot->id,
                ]);

                $handler->onApproved($document, null);
                $this->notifier->documentDecided($snapshot);

                return $snapshot->refresh();
            }

            $this->catat($handler, $document, $submitter, 'Diajukan ke approval: aturan '.($rule?->name ?? 'lapis minimum').' ('.count($lapis).' lapis)', [
                'snapshot' => $snapshot->id,
                'rule' => $rule?->id,
            ]);

            $this->aktifkan($snapshot, $lapis[0], $handler, $document, $submitter);
            $this->maju($snapshot, $handler, $document, $submitter);

            return $snapshot->refresh();
        });
    }

    /**
     * Snapshot yang masih menunggu dibuang: dokumen dibatalkan, atau klien
     * menambah baris sehingga yang akan disetujui bukan lagi dokumen yang
     * dulu diajukan (BR-REQ-12).
     */
    public function withdraw(ApprovalDocumentType|string $type, int $documentId, string $reason, ?User $actor): void
    {
        $snapshots = ApprovalSnapshot::query()->forDocument($type, $documentId)->pending()->lockForUpdate()->get();

        foreach ($snapshots as $s) {
            $s->forceFill(['status' => ApprovalSnapshotStatus::Cancelled, 'decided_at' => now()])->save();

            ApprovalTask::query()->where('approval_snapshot_id', $s->id)->open()
                ->update(['status' => ApprovalTaskStatus::Superseded->value, 'updated_at' => now()]);

            if ($this->registry->has($type) && ($doc = $this->registry->handler($type)->find($documentId)) !== null) {
                $this->catat($this->registry->handler($type), $doc, $actor, 'Approval dihentikan: '.$reason, ['snapshot' => $s->id]);
            }
        }
    }

    // ------------------------------------------------------------- keputusan

    public function pendingSnapshot(ApprovalDocumentType|string $type, int $documentId): ?ApprovalSnapshot
    {
        return ApprovalSnapshot::query()->forDocument($type, $documentId)->pending()->latest('id')->first();
    }

    public function openTaskFor(ApprovalDocumentType|string $type, int $documentId, User $user): ?ApprovalTask
    {
        $snapshot = $this->pendingSnapshot($type, $documentId);

        if ($snapshot === null) {
            return null;
        }

        return ApprovalTask::query()->open()
            ->where('approval_snapshot_id', $snapshot->id)
            ->where('approver_user_id', $user->id)
            ->first();
    }

    public function approve(ApprovalTask $task, User $actor, ?string $comment = null): ApprovalSnapshot
    {
        return DB::transaction(function () use ($task, $actor, $comment) {
            [$snapshot, $task, $handler, $document] = $this->kunciUntukKeputusan($task, $actor);

            $this->putuskan($task, ApprovalDecisionType::Approved, $actor->id, $comment);

            $this->catat($handler, $document, $actor, 'Lapis '.$task->step_no.' disetujui', [
                'snapshot' => $snapshot->id, 'tugas' => $task->id, 'komentar' => $comment,
            ]);

            $this->maju($snapshot, $handler, $document, $actor);

            return $snapshot->refresh();
        });
    }

    public function reject(ApprovalTask $task, User $actor, ?int $reasonCodeId, ?string $comment = null): ApprovalSnapshot
    {
        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw ApprovalRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        return DB::transaction(function () use ($task, $actor, $reasonCodeId, $comment) {
            [$snapshot, $task, $handler, $document] = $this->kunciUntukKeputusan($task, $actor);

            $this->putuskan($task, ApprovalDecisionType::Rejected, $actor->id, $comment, $reasonCodeId);

            $this->tutupTugasTerbuka($snapshot);
            $snapshot->forceFill(['status' => ApprovalSnapshotStatus::Rejected, 'decided_at' => now()])->save();

            $this->catat($handler, $document, $actor, 'Ditolak pada lapis '.$task->step_no, [
                'snapshot' => $snapshot->id, 'reason_code_id' => $reasonCodeId, 'komentar' => $comment,
            ]);

            $handler->onRejected($document, (int) $reasonCodeId, $comment, $actor);
            $this->notifier->documentDecided($snapshot);

            return $snapshot->refresh();
        });
    }

    // -------------------------------------------------- delegasi & eskalasi

    /**
     * BR-APR-06/08: tugas dialihkan ke approver cadangan, atasan approver, atau
     * Admin Company (dengan peringatan). Tugas lama `expired`.
     *
     * @param  string  $cause  overdue|inactive|manual
     */
    public function escalate(ApprovalTask $task, string $cause, ?User $actor): ApprovalTask
    {
        return DB::transaction(function () use ($task, $cause, $actor) {
            $snapshot = ApprovalSnapshot::query()->whereKey($task->approval_snapshot_id)->lockForUpdate()->firstOrFail();
            $task = ApprovalTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($snapshot->status !== ApprovalSnapshotStatus::Pending || $task->status !== ApprovalTaskStatus::Open) {
                throw ApprovalRuleException::rule('BR-APR-09', 'Tugas ini sudah diputus atau dialihkan.');
            }

            $handler = $this->registry->handler($snapshot->document_type);
            $tujuan = $this->tujuanEskalasi($snapshot, $task, $handler);

            if ($tujuan === null) {
                throw ApprovalRuleException::rule('BR-APR-06', 'Tidak ada tujuan eskalasi: cadangan, atasan, dan Admin Company tidak tersedia.');
            }

            [$userId, $lewat] = $tujuan;
            $sebab = match ($cause) {
                'overdue' => 'lewat batas waktu',
                'inactive' => 'approver nonaktif atau tidak lagi berizin',
                default => 'dialihkan manual',
            };
            $nama = User::query()->whereKey($userId)->value('name');

            $this->putuskan($task, ApprovalDecisionType::Escalated, $actor?->id,
                'Dieskalasi ke '.$nama.' ('.$lewat.'): '.$sebab.'.', null, ApprovalTaskStatus::Expired);

            $step = $snapshot->step($task->step_no) ?? ['timeout_hours' => 24];
            $baru = $this->buatTugas($snapshot, $step, $userId, $handler, $task->id);

            $document = $handler->find($snapshot->document_id);

            if ($document !== null) {
                $this->catat($handler, $document, $actor, 'Tugas approval lapis '.$task->step_no.' dieskalasi ke '.$nama.' ('.$sebab.')', [
                    'snapshot' => $snapshot->id, 'dari_tugas' => $task->id, 'ke_tugas' => $baru->id, 'lewat' => $lewat,
                ]);
            }

            return $baru;
        });
    }

    /**
     * Penjadwal: eskalasi tugas yang lewat batas waktu (BR-APR-08) dan tugas
     * milik approver yang nonaktif/tidak berizin (BR-APR-06), lalu pindahkan
     * tugas ke delegat yang delegasinya mulai berlaku (BR-APR-05).
     *
     * @return array{escalated: int, skipped: int, delegated: int}
     */
    public function runScheduled(): array
    {
        $naik = 0;
        $lewat = 0;

        $tugas = ApprovalTask::query()->open()
            ->whereHas('snapshot', fn ($q) => $q->where('status', ApprovalSnapshotStatus::Pending->value))
            ->with('snapshot')->orderBy('id')->get();

        foreach ($tugas as $t) {
            $izin = $this->registry->has($t->snapshot->document_type)
                ? $this->registry->handler($t->snapshot->document_type)->approvePermission()
                : null;

            $sebab = match (true) {
                $izin !== null && ! $this->resolver->isEligible((int) $t->approver_user_id, $izin) => 'inactive',
                $t->isOverdue() => 'overdue',
                default => null,
            };

            if ($sebab === null) {
                continue;
            }

            try {
                $this->escalate($t, $sebab, null);
                $naik++;
            } catch (ApprovalRuleException) {
                $lewat++;
            }
        }

        return ['escalated' => $naik, 'skipped' => $lewat, 'delegated' => $this->applyDelegations()];
    }

    /**
     * Tugas terbuka milik user yang delegasinya sedang berlaku dipindahkan ke
     * delegat. Tugas hasil delegasi atau eskalasi tidak didelegasikan lagi
     * (BR-APR-05: tidak berantai).
     */
    public function applyDelegations(?int $fromUserId = null): int
    {
        $pindah = 0;

        $tugas = ApprovalTask::query()->open()
            ->whereNull('delegated_from_user_id')
            ->when($fromUserId !== null, fn ($q) => $q->where('approver_user_id', $fromUserId))
            ->whereHas('snapshot', fn ($q) => $q->where('status', ApprovalSnapshotStatus::Pending->value))
            ->with('snapshot')->orderBy('id')->get();

        foreach ($tugas as $t) {
            if (! $this->registry->has($t->snapshot->document_type)) {
                continue;
            }

            $handler = $this->registry->handler($t->snapshot->document_type);
            $delegasi = $this->delegasiUntuk((int) $t->approver_user_id, $t->snapshot, $handler);

            if ($delegasi === null) {
                continue;
            }

            DB::transaction(function () use ($t, $handler) {
                $t->forceFill(['status' => ApprovalTaskStatus::Superseded])->save();
                $step = $t->snapshot->step($t->step_no) ?? ['timeout_hours' => 24];
                $this->buatTugas($t->snapshot, $step, (int) $t->approver_user_id, $handler, null, $t->due_at);
            });

            $pindah++;
        }

        return $pindah;
    }

    // ------------------------------------------------------------ internal

    /**
     * Mengunci snapshot & tugas, lalu memeriksa siapa yang boleh memutus
     * (BR-APR-03, BR-APR-09, A-86).
     *
     * @return array{0: ApprovalSnapshot, 1: ApprovalTask, 2: ApprovalHandler, 3: Model}
     */
    private function kunciUntukKeputusan(ApprovalTask $task, User $actor): array
    {
        $snapshot = ApprovalSnapshot::query()->whereKey($task->approval_snapshot_id)->lockForUpdate()->firstOrFail();
        $task = ApprovalTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

        if ($snapshot->status !== ApprovalSnapshotStatus::Pending) {
            throw ApprovalRuleException::rule('BR-APR-09', 'Approval dokumen ini sudah selesai ('.$snapshot->status->label().').');
        }

        if ($task->status !== ApprovalTaskStatus::Open) {
            throw ApprovalRuleException::rule('BR-APR-09', 'Tugas ini sudah diputus atau dialihkan; keputusan pertama yang berlaku.');
        }

        if (in_array((int) $actor->id, $snapshot->requesterIds(), true)) {
            throw ApprovalRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak dokumennya sendiri.');
        }

        if ((int) $task->approver_user_id !== (int) $actor->id) {
            throw ApprovalRuleException::rule('BR-APR-01', 'Tugas approval ini tidak ditugaskan kepada Anda.');
        }

        $handler = $this->registry->handler($snapshot->document_type);

        if (! $actor->is_active || ! $actor->hasPermission($handler->approvePermission())) {
            throw ApprovalRuleException::rule('BR-GEN-09', 'Anda tidak memegang izin '.$handler->approvePermission().'.');
        }

        $document = $handler->find($snapshot->document_id);

        if ($document === null) {
            throw ApprovalRuleException::rule('BR-GEN-01', 'Dokumen approval tidak ditemukan.');
        }

        return [$snapshot, $task, $handler, $document];
    }

    /**
     * Memajukan snapshot sejauh mungkin: persetujuan otomatis orang yang sama
     * di lapis berurutan (BR-APR-04), tugas berikutnya pada cara putus
     * berurutan, lapis berikutnya, atau keputusan akhir.
     */
    private function maju(ApprovalSnapshot $snapshot, ApprovalHandler $handler, Model $document, ?User $actor): void
    {
        for ($putaran = 0; $putaran < 100; $putaran++) {
            $snapshot->refresh();

            if ($snapshot->status !== ApprovalSnapshotStatus::Pending) {
                return;
            }

            $step = $snapshot->step($snapshot->current_step);

            if ($step === null) {
                return;
            }

            $this->bawaPersetujuanSebelumnya($snapshot, $step, $handler, $document);

            $tugas = $this->tugasLapis($snapshot, (int) $step['step_no']);
            $setuju = $tugas->filter(fn (ApprovalTask $t) => $t->wasApproved())->count();
            $mode = DecisionMode::from((string) $step['decision_mode']);
            $penyetuju = $step['approver_user_ids'];

            if ($setuju >= $mode->required(count($penyetuju))) {
                $this->tutupTugasTerbuka($snapshot, (int) $step['step_no']);
                $berikut = $snapshot->stepAfter((int) $step['step_no']);

                if ($berikut === null) {
                    $snapshot->forceFill(['status' => ApprovalSnapshotStatus::Approved, 'decided_at' => now()])->save();
                    $this->catat($handler, $document, $actor, 'Semua lapis approval setuju', ['snapshot' => $snapshot->id]);
                    $handler->onApproved($document, $actor);
                    $this->notifier->documentDecided($snapshot);

                    return;
                }

                $this->aktifkan($snapshot, $berikut, $handler, $document, $actor);

                continue;
            }

            // Berurutan: satu tugas terbuka pada satu waktu.
            if ($mode === DecisionMode::Sequential
                && $tugas->where('status', ApprovalTaskStatus::Open)->isEmpty()
                && isset($penyetuju[$setuju])) {
                $this->buatTugas($snapshot, $step, (int) $penyetuju[$setuju], $handler);

                continue;
            }

            return;
        }
    }

    /** @param  array<string, mixed>  $step */
    private function aktifkan(ApprovalSnapshot $snapshot, array $step, ApprovalHandler $handler, Model $document, ?User $actor): void
    {
        $snapshot->forceFill(['current_step' => (int) $step['step_no']])->save();

        $penyetuju = $step['approver_user_ids'];
        $sasaran = DecisionMode::from((string) $step['decision_mode']) === DecisionMode::Sequential
            ? array_slice($penyetuju, 0, 1)
            : $penyetuju;

        foreach ($sasaran as $userId) {
            $this->buatTugas($snapshot, $step, (int) $userId, $handler);
        }

        $this->catat($handler, $document, $actor, 'Menunggu approval lapis '.$step['step_no'].': '.implode(', ', $step['approver_names'] ?? []), [
            'snapshot' => $snapshot->id,
        ]);
    }

    /**
     * BR-APR-04: orang yang sudah menyetujui lapis sebelumnya cukup sekali.
     *
     * @param  array<string, mixed>  $step
     */
    private function bawaPersetujuanSebelumnya(ApprovalSnapshot $snapshot, array $step, ApprovalHandler $handler, Model $document): void
    {
        $sebelum = null;

        foreach ($snapshot->steps as $s) {
            if ((int) $s['step_no'] < (int) $step['step_no']) {
                $sebelum = (int) $s['step_no'];
            }
        }

        if ($sebelum === null) {
            return;
        }

        $sudahSetuju = $this->tugasLapis($snapshot, $sebelum)
            ->filter(fn (ApprovalTask $t) => $t->wasApproved())
            ->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all();

        foreach ($this->tugasLapis($snapshot, (int) $step['step_no'])->where('status', ApprovalTaskStatus::Open) as $t) {
            if (in_array((int) $t->approver_user_id, $sudahSetuju, true)) {
                $this->putuskan($t, ApprovalDecisionType::Approved, (int) $t->approver_user_id,
                    'Disetujui otomatis: sudah menyetujui lapis '.$sebelum.' (BR-APR-04).');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function buatTugas(
        ApprovalSnapshot $snapshot,
        array $step,
        int $userId,
        ApprovalHandler $handler,
        ?int $escalatedFrom = null,
        mixed $dueAt = null,
    ): ApprovalTask {
        $delegasi = $escalatedFrom === null ? $this->delegasiUntuk($userId, $snapshot, $handler) : null;

        $task = ApprovalTask::create([
            'approval_snapshot_id' => $snapshot->id,
            'step_no' => (int) $step['step_no'],
            'approver_user_id' => $delegasi?->to_user_id ?? $userId,
            'delegated_from_user_id' => $delegasi !== null ? $userId : null,
            'escalated_from_task_id' => $escalatedFrom,
            'due_at' => $dueAt ?? now()->addHours(max(1, (int) ($step['timeout_hours'] ?? 24))),
            'status' => ApprovalTaskStatus::Open,
        ]);

        if ($delegasi !== null) {
            ApprovalDecision::create([
                'approval_task_id' => $task->id,
                'decision' => ApprovalDecisionType::Delegated,
                'decided_by' => null,
                'decided_at' => now(),
                'channel' => 'web',
                'comment' => 'Didelegasikan dari '.User::query()->whereKey($userId)->value('name')
                    .' sampai '.$delegasi->ends_at->format('d/m/Y H:i').' (BR-APR-05).',
            ]);
        }

        $this->notifier->taskAssigned($task);

        return $task;
    }

    /** Delegasi yang berlaku untuk user ini dan jenis dokumen ini (satu lompatan saja). */
    private function delegasiUntuk(int $userId, ApprovalSnapshot $snapshot, ApprovalHandler $handler): ?ApprovalDelegation
    {
        $kandidat = ApprovalDelegation::query()->effectiveAt(now())
            ->where('from_user_id', $userId)->orderByDesc('id')->get();

        foreach ($kandidat as $d) {
            if (! $d->covers($snapshot->document_type)) {
                continue;
            }

            if (in_array((int) $d->to_user_id, $snapshot->requesterIds(), true)) {
                continue; // SoD tetap berlaku untuk delegat.
            }

            if ($this->resolver->isEligible((int) $d->to_user_id, $handler->approvePermission())) {
                return $d;
            }
        }

        return null;
    }

    /** @return array{0: int, 1: string}|null [user, jalur] */
    private function tujuanEskalasi(ApprovalSnapshot $snapshot, ApprovalTask $task, ApprovalHandler $handler): ?array
    {
        $izin = $handler->approvePermission();
        $ctx = ApprovalContext::fromArray($snapshot->context ?? ['document_type' => $snapshot->document_type->value]);
        $step = $snapshot->step($task->step_no) ?? [];

        $kecuali = array_merge(
            $snapshot->requesterIds(),
            [(int) $task->approver_user_id],
            $this->tugasLapis($snapshot, $task->step_no)->where('status', ApprovalTaskStatus::Open)
                ->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all(),
        );

        $pilih = function (array $ids) use ($kecuali, $izin): ?int {
            $ids = array_values(array_diff(array_filter(array_map(fn ($v) => $v === null ? null : (int) $v, $ids)), $kecuali));
            sort($ids);

            return $this->resolver->eligible($ids, $izin)[0] ?? null;
        };

        if (! empty($step['backup_approver_type'])) {
            $cadangan = $this->resolver->resolve(
                ApproverType::from((string) $step['backup_approver_type']),
                isset($step['backup_ref_id']) ? (int) $step['backup_ref_id'] : null,
                $ctx,
            );

            if (($u = $pilih($cadangan)) !== null) {
                return [$u, 'approver cadangan'];
            }
        }

        $asal = (int) ($task->delegated_from_user_id ?? $task->approver_user_id);

        if (($u = $pilih([$this->resolver->managerOf($asal), $this->resolver->managerOf((int) $task->approver_user_id)])) !== null) {
            return [$u, 'atasan approver'];
        }

        if (($u = $pilih($this->resolver->companyAdmins())) !== null) {
            return [$u, 'Admin Company — peringatan: tidak ada cadangan/atasan'];
        }

        return null;
    }

    /** @return \Illuminate\Support\Collection<int, ApprovalTask> */
    private function tugasLapis(ApprovalSnapshot $snapshot, int $stepNo): \Illuminate\Support\Collection
    {
        return ApprovalTask::query()->with('decisions')
            ->where('approval_snapshot_id', $snapshot->id)
            ->where('step_no', $stepNo)
            ->orderBy('id')->get();
    }

    private function putuskan(
        ApprovalTask $task,
        ApprovalDecisionType $decision,
        ?int $by,
        ?string $comment = null,
        ?int $reasonCodeId = null,
        ApprovalTaskStatus $status = ApprovalTaskStatus::Decided,
    ): void {
        ApprovalDecision::create([
            'approval_task_id' => $task->id,
            'decision' => $decision,
            'decided_by' => $by,
            'decided_at' => now(),
            'channel' => 'web',
            'reason_code_id' => $reasonCodeId,
            'comment' => $comment !== null ? mb_substr($comment, 0, 255) : null,
        ]);

        $task->forceFill(['status' => $status])->save();
    }

    private function tutupTugasTerbuka(ApprovalSnapshot $snapshot, ?int $stepNo = null): void
    {
        ApprovalTask::query()->open()
            ->where('approval_snapshot_id', $snapshot->id)
            ->when($stepNo !== null, fn ($q) => $q->where('step_no', $stepNo))
            ->update(['status' => ApprovalTaskStatus::Superseded->value, 'updated_at' => now()]);
    }

    /** Jejak approval di riwayat dokumen (BR-GEN-05). @param  array<string, mixed>  $props */
    private function catat(ApprovalHandler $handler, Model $document, ?User $actor, string $pesan, array $props = []): void
    {
        activity($handler->logName())->performedOn($document)->causedBy($actor)
            ->withProperties($props + ['approval' => true])
            ->log($pesan);
    }
}

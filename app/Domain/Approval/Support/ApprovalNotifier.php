<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Notification\Support\Notifier;

/**
 * Notifikasi approval (alur 9 langkah 5, Blueprint §10): in-app + email
 * sesuai preferensi lewat modul notifikasi (27-pendukung-f1, A-189).
 * WhatsApp bertombol + token sekali pakai tetap Fase 2a (BR-APR-10).
 */
class ApprovalNotifier
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly ApprovalRegistry $registry,
    ) {}

    public function taskAssigned(ApprovalTask $task): void
    {
        $snapshot = $task->snapshot;
        $approver = $task->approver_user_id !== null ? User::query()->find($task->approver_user_id) : null;

        if ($snapshot === null || $approver === null) {
            return;
        }

        // Eskalasi (BR-APR-06/08) dan delegasi (BR-APR-05) juga lewat sini; sebutkan asalnya.
        $asal = match (true) {
            $task->delegated_from_user_id !== null => ' Didelegasikan dari '.User::query()->whereKey($task->delegated_from_user_id)->value('name').'.',
            $task->escalated_from_task_id !== null => ' Dieskalasi kepada Anda.',
            default => '',
        };

        $this->notifier->send($approver, 'approval.task_assigned',
            'Tugas approval: '.$this->jenis($snapshot).' '.$snapshot->document_number,
            'Lapis '.$task->step_no.' menunggu keputusan Anda.'.$asal,
            route('approval.inbox', absolute: false),
            $snapshot->document_type->value, (int) $snapshot->document_id);
    }

    public function documentDecided(ApprovalSnapshot $snapshot): void
    {
        $pengaju = $snapshot->submitted_by !== null ? User::query()->find($snapshot->submitted_by) : null;

        if ($pengaju === null) {
            return;
        }

        $this->notifier->send($pengaju, 'approval.decided',
            $this->jenis($snapshot).' '.$snapshot->document_number.' '.mb_strtolower($snapshot->status->label()),
            null,
            $this->tautan($snapshot),
            $snapshot->document_type->value, (int) $snapshot->document_id);
    }

    private function jenis(ApprovalSnapshot $snapshot): string
    {
        return $snapshot->document_type->code();
    }

    private function tautan(ApprovalSnapshot $snapshot): ?string
    {
        if (! $this->registry->has($snapshot->document_type)) {
            return null;
        }

        $handler = $this->registry->handler($snapshot->document_type);
        $dokumen = $handler->find((int) $snapshot->document_id);
        $url = $dokumen === null ? null : $handler->url($dokumen);

        return $url === null ? null : (parse_url($url, PHP_URL_PATH) ?: null);
    }
}

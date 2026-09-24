<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use Illuminate\Support\Collection;

/**
 * Data panel "Riwayat approval" di halaman detail dokumen (REQ, RTV, dan
 * dokumen lain yang tersambung): setiap pengajuan beserta lapis, tugas, dan
 * keputusannya, terbaru di atas.
 */
class ApprovalHistory
{
    /** @return Collection<int, ApprovalSnapshot> */
    public function for(ApprovalDocumentType|string $type, int $documentId): Collection
    {
        return ApprovalSnapshot::query()
            ->forDocument($type, $documentId)
            ->with([
                'submitter:id,name',
                'tasks.approver:id,name',
                'tasks.delegatedFrom:id,name',
                'tasks.decisions.decider:id,name',
                'tasks.decisions.reason:id,label',
            ])
            ->orderByDesc('id')
            ->get();
    }
}

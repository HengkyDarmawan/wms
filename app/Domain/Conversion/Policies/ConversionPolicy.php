<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionApprovalRoute;

/**
 * Izin konversi material (24-konversi-waste §2, A-158). Cakupan gudang/proyek
 * lewat global scope model (di luar cakupan = 404).
 */
class ConversionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('conversion.view');
    }

    public function view(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('conversion.create');
    }

    /** Draf CNV biasa diubah pembuatnya atau staf yang akan menyelesaikannya. */
    public function update(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.create')
            && $cnv->status === ConversionStatus::Draft
            && ! $cnv->isReversal()
            && ((int) $cnv->prepared_by === (int) $actor->id || $actor->hasPermission('conversion.complete'));
    }

    /** Katalog §2.10 `draft → pending_approval`: hanya bila ada aturan approval (A-153). */
    public function submit(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.submit')
            && $cnv->status === ConversionStatus::Draft
            && app(ConversionApprovalRoute::class)->needsApproval($cnv);
    }

    /** Katalog §2.10 `draft → completed`: hanya tanpa aturan approval (A-153). */
    public function complete(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.complete')
            && $cnv->status === ConversionStatus::Draft
            && ! app(ConversionApprovalRoute::class)->needsApproval($cnv);
    }

    /** Katalog §2.10 aktor "Pembuat"; pengaju dan pemegang `conversion.approve` juga boleh (A-158). */
    public function cancel(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.cancel')
            && $cnv->status->isCancellable()
            && (in_array((int) $actor->id, [(int) $cnv->prepared_by, (int) $cnv->submitted_by], true)
                || $actor->hasPermission('conversion.approve'));
    }

    public function approve(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.approve')
            && $cnv->isAwaitingApproval()
            && ! in_array((int) $actor->id, [(int) $cnv->prepared_by, (int) $cnv->submitted_by], true)
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::Conversion, (int) $cnv->id, $actor) !== null;
    }

    /** CNV pembalik: izin buat, CNV asal selesai, belum punya pembalik aktif (A-157). */
    public function reverse(User $actor, Conversion $cnv): bool
    {
        return $actor->hasPermission('conversion.create')
            && $cnv->status === ConversionStatus::Completed
            && ! $cnv->isReversal()
            && $cnv->activeReversal() === null;
    }
}

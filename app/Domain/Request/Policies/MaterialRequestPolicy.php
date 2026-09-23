<?php

declare(strict_types=1);

namespace App\Domain\Request\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;

/**
 * Izin REQ (14-request §2).
 *
 * Dua lapis di setiap metode: permission, lalu jangkauan. Klien hanya boleh
 * menyentuh REQ milik proyek kliennya sendiri, dan itu diperiksa di sini —
 * bukan hanya disembunyikan dari daftar.
 */
class MaterialRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('request.view');
    }

    public function view(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.view') && $this->dalamJangkauan($actor, $request);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('request.create');
    }

    /** Hanya pembuatnya yang mengubah draf; staf mengubah lewat layar tinjau. */
    public function update(User $actor, MaterialRequest $request): bool
    {
        if (! $request->status->isEditable() || ! $this->dalamJangkauan($actor, $request)) {
            return false;
        }

        $miliknya = (int) $request->requester_id === (int) $actor->id;

        return $miliknya
            ? $actor->hasPermission('request.create')
            : $actor->hasPermission('request.review') && $request->status === MaterialRequestStatus::UnderReview;
    }

    public function submit(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.submit')
            && (int) $request->requester_id === (int) $actor->id
            && $request->status === MaterialRequestStatus::Draft;
    }

    public function review(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.review')
            && $request->status === MaterialRequestStatus::UnderReview
            && $this->dalamJangkauan($actor, $request);
    }

    public function splitLine(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.split_line')
            && $request->status->isEditable()
            && $this->dalamJangkauan($actor, $request);
    }

    /**
     * BR-REQ-07: pemohon tidak menyetujui dokumennya sendiri. Dicegah di sini
     * agar tombolnya tidak muncul, dan diulang di kelas aksi agar tetap ditolak
     * bila dipanggil langsung.
     */
    public function approve(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.approve')
            && $request->status === MaterialRequestStatus::PendingApproval
            && (int) $request->requester_id !== (int) $actor->id
            && $this->dalamJangkauan($actor, $request);
    }

    public function addLines(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.add_lines')
            && ! $request->status->isFinal()
            && $this->dalamJangkauan($actor, $request);
    }

    public function respondSubstitution(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.respond_substitution')
            && $this->dalamJangkauan($actor, $request);
    }

    public function requestCancel(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.request_cancel')
            && $request->status->hasReservations()
            && $this->dalamJangkauan($actor, $request);
    }

    public function confirmCancel(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.confirm_cancel')
            && $this->dalamJangkauan($actor, $request);
    }

    public function closeShort(User $actor, MaterialRequest $request): bool
    {
        return $actor->hasPermission('request.close_short')
            && $this->dalamJangkauan($actor, $request);
    }

    public function cancel(User $actor, MaterialRequest $request): bool
    {
        if (! $actor->hasPermission('request.cancel') || ! $this->dalamJangkauan($actor, $request)) {
            return false;
        }

        // Sebelum disetujui, pembatalan hak pembuatnya. Sesudahnya hak Kepala
        // Gudang, karena yang dilepas bukan lagi sekadar niat melainkan stok.
        return $request->status->hasReservations()
            ? $actor->hasPermission('request.close_short')
            : true;
    }

    /**
     * Klien hanya menjangkau REQ proyek kliennya. Cakupan gudang/proyek
     * pengguna internal sudah ditegakkan global scope; ini lapis keduanya.
     */
    private function dalamJangkauan(User $actor, MaterialRequest $request): bool
    {
        if ($actor->client_id === null) {
            return true;
        }

        $request->loadMissing('project');

        return (int) $request->project?->client_id === (int) $actor->client_id;
    }
}

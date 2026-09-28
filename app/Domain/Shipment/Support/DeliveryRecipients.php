<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * A-312, A-316 — siapa mengisi bukti terima sebuah SJ (driver tidak punya
 * akun sejak A-311).
 *
 * - **Penerima utama** (`shipment.confirm_delivery`):
 *   - SJ ke proyek klien → user Klien dari klien proyek itu (portal, cakupan A-21);
 *   - SJ ke gudang (TRF, Gudang Site, SJ jemput) → user internal yang
 *     cakupannya menyebut gudang tujuan (atau cakupan semua);
 *   - SJ ke proyek → user internal bercakupan proyek itu / gudang site-nya,
 *     atau pemohon REQ yang dimuat.
 * - **Cadangan** (`shipment.confirm_delivery_signed`): Kepala Gudang asal
 *   mengisi dari SJ bertanda tangan & cap (foto wajib). Tautan + OTP (A-41)
 *   tetap jalur cadangan lain untuk penerima tanpa akun.
 *
 * Cakupan "tidak dibatasi" karena user tidak punya penugasan di dimensi itu
 * (lihat `User::accessibleScopeIds`) **tidak** dihitung sebagai penerima:
 * Kepala Gudang CKG bukan penerima SJ ke proyek hanya karena proyeknya tidak
 * dibatasi.
 */
class DeliveryRecipients
{
    /** Kanal yang tercatat bila `$user` mengisi bukti terima SJ ini; null = tidak boleh. */
    public function channelFor(User $user, Shipment $sj): ?ProofChannel
    {
        if ($user->hasPermission('shipment.confirm_delivery') && $this->isRecipient($user, $sj)) {
            return $user->isClient() ? ProofChannel::ClientPortal : ProofChannel::RecipientAccount;
        }

        if (! $user->isClient()
            && $user->hasPermission('shipment.confirm_delivery_signed')
            && $user->canAccessWarehouse((int) $sj->warehouse_id)) {
            return ProofChannel::SignedDocument;
        }

        return null;
    }

    /** Penerima utama (tanpa melihat izin). */
    public function isRecipient(User $user, Shipment $sj): bool
    {
        $sj->loadMissing('destinationProject:id,client_id');

        if ($user->isClient()) {
            $proyek = $sj->destinationProject;

            return $sj->destination_type === DestinationType::ProjectClient
                && $proyek !== null
                && (int) $proyek->client_id === (int) $user->client_id
                && $user->canAccessProject((int) $proyek->id);
        }

        $penugasan = $user->validAssignments();

        if ($sj->destination_type === DestinationType::ProjectClient) {
            $proyekId = (int) $sj->destination_project_id;

            return $this->punya($penugasan, ScopeType::Project, [$proyekId])
                || $this->punya($penugasan, ScopeType::Warehouse, $this->gudangSite($proyekId))
                || in_array((int) $user->id, $this->pemohon($sj), true);
        }

        if ($sj->destination_warehouse_id !== null) {
            return $penugasan->contains(fn (RoleAssignment $a) => $a->scope_type === ScopeType::All)
                || $this->punya($penugasan, ScopeType::Warehouse, [(int) $sj->destination_warehouse_id]);
        }

        // Vendor: tidak ada akun penerima — tautan bertoken atau cadangan gudang.
        return false;
    }

    /**
     * A-319: calon penerima utama yang diberi tahu saat SJ berangkat.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(Shipment $sj): Collection
    {
        $pemohon = $this->pemohon($sj);

        // Pemegang cakupan semua (Admin Company) tidak ikut diberi tahu kecuali ia pemohonnya.
        return User::query()->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission('shipment.confirm_delivery')
                && (in_array((int) $u->id, $pemohon, true) || ! $this->cakupanSemua($u))
                && $this->isRecipient($u, $sj))
            ->values();
    }

    /**
     * @param  Collection<int, RoleAssignment>  $penugasan
     * @param  array<int, int>  $ids
     */
    private function punya(Collection $penugasan, ScopeType $jenis, array $ids): bool
    {
        return $ids !== [] && $penugasan->contains(fn (RoleAssignment $a) => $a->scope_type === $jenis
            && in_array((int) $a->scope_id, $ids, true));
    }

    private function cakupanSemua(User $user): bool
    {
        return $user->validAssignments()->contains(fn (RoleAssignment $a) => $a->scope_type === ScopeType::All);
    }

    /** @return array<int, int> gudang site milik proyek */
    private function gudangSite(int $proyekId): array
    {
        return Warehouse::query()->withoutGlobalScopes()->where('project_id', $proyekId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, int> pemohon REQ yang dimuat SJ */
    private function pemohon(Shipment $sj): array
    {
        return MaterialRequest::query()->withoutGlobalScopes()->whereIn('id', $sj->materialRequestIds())
            ->pluck('requester_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}

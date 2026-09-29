<?php

declare(strict_types=1);

namespace App\Domain\Shared\Pilihan;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vendor;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sumber pilihan baku untuk lima jenis data yang dicari ke server (A-384):
 * pengguna, proyek, item, vendor, bin. Cakupan pembaca (BR-GEN-09,
 * BR-ACC-05) sudah terpasang di sini, jadi layar yang memakainya tidak
 * bisa lupa. Layar boleh mempersempit lagi dengan `Pilihan::saring()`,
 * tidak pernah memperluas.
 *
 * Akun Klien (A-21, A-354): proyek hanya milik kliennya; pengguna internal,
 * vendor, dan bin tidak pernah ditawarkan. Item tetap master bersama (portal
 * juga memilih item aktif).
 */
final class SumberPilihan
{
    /** Pengguna internal aktif — nama + badge jabatan + unit (A-358). */
    public static function pengguna(?User $pembaca = null): Pilihan
    {
        $query = User::query()->active()->internal()
            ->with(['position:id,name', 'orgUnit:id,name'])
            ->orderBy('name')->orderBy('id');

        return Pilihan::dari(self::tutupUntukKlien($query, $pembaca), ['name', 'email', 'position.name', 'orgUnit.name'], fn (User $u) => [
            'value' => (int) $u->id,
            'text' => $u->name,
            'badge' => $u->position?->name,
            'sub' => $u->orgUnit?->name,
        ]);
    }

    /**
     * Pemohon untuk Peta/Simulasi approval: semua pengguna aktif, termasuk
     * akun Klien (bertanda) — hanya untuk layar ber-izin `approval_rule.view`
     * / `approval.simulate`. Tertutup bagi pembaca Klien.
     */
    public static function pemohon(?User $pembaca = null): Pilihan
    {
        $query = User::query()->active()->with(['position:id,name', 'orgUnit:id,name'])->orderBy('name')->orderBy('id');

        return Pilihan::dari(self::tutupUntukKlien($query, $pembaca), ['name', 'email'], fn (User $u) => [
            'value' => (int) $u->id,
            'text' => $u->name,
            'badge' => $u->client_id !== null ? __('klien') : $u->position?->name,
            'sub' => $u->orgUnit?->name,
        ]);
    }

    /** Proyek dalam cakupan pembaca, semua status (filter & simulasi; A-354). */
    public static function proyekSemuaStatus(?User $pembaca = null): Pilihan
    {
        $query = Project::query()->dalamCakupan($pembaca ?? self::pembaca())->orderBy('code');

        return Pilihan::dari($query, ['code', 'name'], fn (Project $p) => [
            'value' => (int) $p->id,
            'text' => $p->code.' — '.$p->name,
        ]);
    }

    /** Proyek aktif dalam cakupan pembaca (`Project::dalamCakupan`, A-354). */
    public static function proyek(?User $pembaca = null): Pilihan
    {
        $query = Project::query()->active()->dalamCakupan($pembaca ?? self::pembaca())->orderBy('code');

        return Pilihan::dari($query, ['code', 'name'], fn (Project $p) => [
            'value' => (int) $p->id,
            'text' => $p->code.' — '.$p->name,
        ]);
    }

    /**
     * Item berstatus tertentu (bawaan: aktif).
     *
     * @param  array<int, ItemStatus>  $status
     */
    public static function item(array $status = [ItemStatus::Active]): Pilihan
    {
        $query = Item::query()
            ->whereIn('status', array_map(fn (ItemStatus $s) => $s->value, $status))
            ->orderBy('code');

        return Pilihan::dari($query, ['code', 'name', 'barcode'], fn (Item $i) => [
            'value' => (int) $i->id,
            'text' => $i->code.' — '.$i->name,
        ]);
    }

    /** Vendor aktif (A-310). */
    public static function vendor(?User $pembaca = null): Pilihan
    {
        $query = Vendor::query()->active()->orderBy('name');

        return Pilihan::dari(self::tutupUntukKlien($query, $pembaca), ['code', 'name'], fn (Vendor $v) => [
            'value' => (int) $v->id,
            'text' => $v->name,
            'sub' => $v->code,
        ]);
    }

    /**
     * Bin aktif satu gudang. Global scope `ScopedToUser` membatasi ke gudang
     * dalam cakupan pembaca; akun Klien (cakupan proyek, gudangnya tidak
     * dibatasi) ditutup di sini.
     */
    public static function bin(int $gudangId, ?User $pembaca = null): Pilihan
    {
        $query = Bin::query()->active()->where('warehouse_id', $gudangId)->where('is_virtual', false)->orderBy('code');

        return Pilihan::dari(self::tutupUntukKlien($query, $pembaca), ['code'], fn (Bin $b) => [
            'value' => (int) $b->id,
            'text' => $b->code,
        ]);
    }

    private static function tutupUntukKlien(Builder $query, ?User $pembaca): Builder
    {
        $pembaca ??= self::pembaca();

        return $pembaca?->isClient() ? $query->whereRaw('1 = 0') : $query;
    }

    private static function pembaca(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}

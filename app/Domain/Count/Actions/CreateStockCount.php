<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `count.create` — sesi opname `planned` (Katalog §2.13).
 *
 * Guard Katalog: cakupan, tim, jenis, pembekuan ya/tidak. Pemeriksaan
 * mendadak (`spot_check`) selalu tanpa pembekuan dan cakupannya kecil —
 * daftar bin atau item (BR-OPN-10). Sesi yang dibuat Auditor ditandai sesi
 * audit sehingga disetujui Auditor Internal atau Manajemen (BR-OPN-08,
 * BR-OPN-09). Nomor memakai kode gudang atau `ALL` bila lintas gudang
 * (BR-GEN-06).
 */
class CreateStockCount
{
    public function __construct(private readonly DocumentNumber $nomor) {}

    /**
     * @param  array{count_type?: string, warehouse_ids?: array<int, int|string>, zone_ids?: array<int, int|string>, bin_ids?: array<int, int|string>, item_ids?: array<int, int|string>, freeze_bins?: bool, planned_start?: ?string, team_user_ids?: array<int, int|string>, notes?: ?string}  $data
     */
    public function handle(array $data, ?User $actor = null): StockCount
    {
        $jenis = CountType::tryFrom((string) ($data['count_type'] ?? ''));

        if ($jenis === null || ! $jenis->isAvailable()) {
            throw CountRuleException::field('BR-GEN-10', 'count_type', 'Jenis opname wajib dipilih (cycle count ABC baru Fase 2).');
        }

        $gudangIds = $this->ids($data['warehouse_ids'] ?? []);
        // Global scope Warehouse membatasi ke cakupan pembuat (BR-GEN-09).
        $gudang = Warehouse::query()->whereIn('id', $gudangIds)->where('is_active', true)->get();

        if ($gudangIds === [] || $gudang->count() !== count($gudangIds)) {
            throw CountRuleException::field('BR-GEN-09', 'warehouse_ids', 'Pilih minimal satu gudang aktif dalam cakupan Anda.');
        }

        $zonaIds = $this->ids($data['zone_ids'] ?? []);
        $binIds = $this->ids($data['bin_ids'] ?? []);
        $itemIds = $this->ids($data['item_ids'] ?? []);

        if ($zonaIds !== [] && Zone::query()->whereIn('id', $zonaIds)->whereIn('warehouse_id', $gudangIds)->count() !== count($zonaIds)) {
            throw CountRuleException::field('BR-OPN-01', 'zone_ids', 'Zona harus milik gudang cakupan.');
        }

        if ($binIds !== [] && Bin::withoutGlobalScopes()->whereIn('id', $binIds)->whereIn('warehouse_id', $gudangIds)
            ->where('is_virtual', false)->count() !== count($binIds)) {
            throw CountRuleException::field('BR-OPN-01', 'bin_ids', 'Bin harus bin fisik milik gudang cakupan.');
        }

        if ($itemIds !== [] && Item::query()->whereIn('id', $itemIds)->count() !== count($itemIds)) {
            throw CountRuleException::field('BR-OPN-01', 'item_ids', 'Ada item yang tidak dikenal.');
        }

        if ($jenis->isSpotCheck() && $binIds === [] && $itemIds === []) {
            throw CountRuleException::field('BR-OPN-10', 'bin_ids', 'Pemeriksaan mendadak mencakup beberapa bin atau item; pilih minimal satu.');
        }

        $tim = $this->ids($data['team_user_ids'] ?? []);
        $this->periksaTim($tim, $gudangIds);

        $beku = $jenis->isSpotCheck() ? false : (bool) ($data['freeze_bins'] ?? true);
        $mulai = $this->tanggal($data['planned_start'] ?? null);

        return DB::transaction(function () use ($jenis, $gudang, $gudangIds, $zonaIds, $binIds, $itemIds, $tim, $beku, $mulai, $data, $actor) {
            $segmen = count($gudangIds) === 1 ? (string) $gudang->first()->code : 'ALL';

            $count = StockCount::create([
                'number' => $this->nomor->next('OPN', $segmen),
                'count_type' => $jenis,
                'status' => StockCountStatus::Planned,
                'freeze_bins' => $beku,
                'scope' => [
                    'warehouse_ids' => $gudangIds,
                    'zone_ids' => $zonaIds,
                    'bin_ids' => $binIds,
                    'item_ids' => $itemIds,
                ],
                'team_user_ids' => $tim,
                'is_audit' => $actor !== null && ($actor->hasRoleCode('internal_auditor') || $actor->hasRoleCode('external_auditor')),
                'planned_start' => $mulai,
                'created_by' => $actor?->id,
                'notes' => $this->teks($data['notes'] ?? null),
            ]);

            $count->warehouses()->attach($gudangIds);

            activity('count')->performedOn($count)->causedBy($actor)
                ->withProperties(['jenis' => $jenis->value, 'gudang' => $gudang->pluck('code')->all(), 'tim' => count($tim)])
                ->log('Sesi opname direncanakan');

            return $count->refresh();
        });
    }

    /**
     * Tim penghitung: user internal aktif pemegang `count.record` yang boleh
     * mengakses minimal satu gudang cakupan.
     *
     * @param  array<int, int>  $tim
     * @param  array<int, int>  $gudangIds
     */
    public function periksaTim(array $tim, array $gudangIds): void
    {
        if ($tim === []) {
            throw CountRuleException::field('BR-OPN-05', 'team_user_ids', 'Tim penghitung wajib diisi minimal satu orang.');
        }

        foreach (User::query()->whereIn('id', $tim)->get() as $u) {
            $bolehGudang = collect($gudangIds)->contains(fn (int $g) => $u->canAccessWarehouse($g));

            if (! $u->is_active || $u->client_id !== null || ! $u->hasPermission('count.record') || ! $bolehGudang) {
                throw CountRuleException::field('BR-GEN-09', 'team_user_ids', $u->name.' tidak bisa menjadi penghitung di gudang ini.');
            }
        }

        if (User::query()->whereIn('id', $tim)->count() !== count($tim)) {
            throw CountRuleException::field('BR-GEN-09', 'team_user_ids', 'Ada anggota tim yang tidak dikenal.');
        }
    }

    /** @return array<int, int> */
    private function ids(mixed $nilai): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $nilai, fn ($v) => $v !== '' && $v !== null && (int) $v > 0))));
    }

    private function tanggal(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        if ($isi === '') {
            return null;
        }

        try {
            return Carbon::parse($isi)->toDateString();
        } catch (\Throwable) {
            throw CountRuleException::field('BR-GEN-11', 'planned_start', 'Tanggal rencana tidak dikenali.');
        }
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}

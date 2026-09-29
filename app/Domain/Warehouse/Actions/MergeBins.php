<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\StockGuard;
use App\Domain\Warehouse\Enums\BinMergeDirection;
use App\Domain\Warehouse\Enums\BinMergeType;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\RackLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission: `bin.manage` — **Gabung Bin** dan **Pisah** (K-B, A-359).
 *
 * Barang besar atau rak yang memang lebar/tinggi memakai beberapa petak.
 * Stok selalu dicatat di **satu bin utama**; bin tergabung wajib kosong
 * (tanpa saldo, reservasi, atau tugas picking/put-away terbuka) dan menolak
 * pergerakan stok sendiri ({@see Bin::acceptsMovement()}). Kode bin tidak
 * berubah (BR-WH-01).
 *
 * - Arah **samping**: tingkat yang sama di rak yang sama, petak berurutan.
 * - Arah **atas**: petak bernomor sama di tingkat-tingkat di atas bin utama.
 * - Sifat **sementara**: pisah kapan saja, dan otomatis saat bin utama
 *   kosong ({@see releaseWhenEmpty()}); **permanen**: pisah manual dengan alasan.
 *
 * Menggantikan "bin ikut terpakai" A-255; kolom penunjuknya tetap
 * `occupied_by_bin_id` (data lama = gabung sementara ke samping, A-360).
 */
class MergeBins
{
    public function __construct(private readonly StockGuard $stock) {}

    /**
     * @param  array<int, int|string>  $binIds  bin yang digabung ke bin utama
     * @return int jumlah bin yang digabung
     */
    public function merge(Bin $utama, array $binIds, mixed $arah, mixed $sifat, ?string $alasan, ?User $actor = null): int
    {
        $alasan = trim((string) $alasan);
        $arah = BinMergeDirection::tryFrom((string) $arah);
        $sifat = BinMergeType::tryFrom((string) $sifat);

        if ($arah === null || $sifat === null) {
            throw WarehouseRuleException::fields(['arah' => 'Pilih arah (samping/atas) dan sifat (sementara/permanen).'], 'BR-GEN-11');
        }

        if ($alasan === '') {
            throw WarehouseRuleException::fields(['alasan' => 'Alasan wajib diisi, mis. "genset besar memakan 2 petak".'], 'BR-GEN-11');
        }

        $this->bolehJadiUtama($utama);
        $ada = $utama->mergedBins()->get();

        if ($ada->isNotEmpty() && ($ada->first()->merge_direction !== $arah || $ada->first()->merge_type !== $sifat)) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$utama->code.' sudah digabung '.mb_strtolower($ada->first()->merge_direction?->label().', '.$ada->first()->merge_type?->label()).'. Pisah dulu bila ingin mengubah arah atau sifatnya.');
        }

        $ids = array_values(array_unique(array_map('intval', $binIds)));
        $bins = Bin::query()->withoutGlobalScopes()->with('rackLevel')->whereIn('id', $ids)->get();

        if ($bins->isEmpty() || $bins->count() !== count($ids)) {
            throw WarehouseRuleException::fields(['bins' => 'Pilih minimal satu bin lain untuk digabung.'], 'BR-GEN-11');
        }

        foreach ($bins as $b) {
            $this->bolehDigabung($utama, $b);
        }

        $this->bersebelahan($utama, $ada->merge($bins), $arah);

        DB::transaction(function () use ($bins, $utama, $arah, $sifat, $alasan): void {
            foreach ($bins as $b) {
                $b->disableLogging()->forceFill([
                    'occupied_by_bin_id' => $utama->id,
                    'merge_direction' => $arah,
                    'merge_type' => $sifat,
                    'occupied_reason' => mb_substr($alasan, 0, 255),
                    'occupied_at' => now(),
                ])->save();
            }
        });

        activity('warehouse')->performedOn($utama)->causedBy($actor)
            ->withProperties(['bin_tergabung' => $bins->pluck('code')->all(), 'arah' => $arah->value, 'sifat' => $sifat->value, 'alasan' => $alasan])
            ->log('Bin digabung ('.mb_strtolower($arah->label().', '.$sifat->label()).'): '.$utama->code.' + '.$bins->pluck('code')->implode(', '));

        return $bins->count();
    }

    /**
     * Pisah seluruh gabungan. `$bin` boleh bin utama atau salah satu bin
     * tergabungnya. Gabungan permanen wajib beralasan.
     *
     * @return int jumlah bin yang dipisah
     */
    public function split(Bin $bin, ?string $alasan = null, ?User $actor = null, bool $otomatis = false): int
    {
        $utama = $bin->isMerged() ? Bin::query()->withoutGlobalScopes()->findOrFail($bin->occupied_by_bin_id) : $bin;
        $tergabung = $utama->mergedBins()->get();

        if ($tergabung->isEmpty()) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$bin->code.' tidak sedang digabung.');
        }

        $alasan = trim((string) $alasan);

        if (! $otomatis && $alasan === '' && $tergabung->contains(fn (Bin $b) => $b->merge_type === BinMergeType::Permanent)) {
            throw WarehouseRuleException::fields(['alasan' => 'Gabungan permanen hanya bisa dipisah dengan alasan.'], 'BR-GEN-11');
        }

        foreach ($tergabung as $b) {
            if (($tolak = $this->stock->refuseBin((int) $b->id)) !== null) {
                throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$b->code.': '.$tolak);
            }
        }

        DB::transaction(function () use ($tergabung): void {
            foreach ($tergabung as $b) {
                $b->disableLogging()->forceFill([
                    'occupied_by_bin_id' => null, 'merge_direction' => null, 'merge_type' => null,
                    'occupied_reason' => null, 'occupied_at' => null,
                ])->save();
            }
        });

        activity('warehouse')->performedOn($utama)->causedBy($actor)
            ->withProperties(['bin' => $tergabung->pluck('code')->all(), 'alasan' => $alasan !== '' ? $alasan : null, 'otomatis' => $otomatis])
            ->log(($otomatis ? 'Gabungan dipisah otomatis (bin utama kosong): ' : 'Gabungan dipisah: ').$utama->code.' + '.$tergabung->pluck('code')->implode(', '));

        return $tergabung->count();
    }

    /**
     * Observer kartu stok (A-360): barang keluar dari bin utama gabungan
     * **sementara** dan bin itu kosong → gabungan dipisah otomatis.
     * Gabungan permanen tidak pernah dipisah otomatis.
     */
    public function releaseWhenEmpty(StockMovement $m): void
    {
        if ($m->from_bin_id === null) {
            return;
        }

        $adaSementara = Bin::query()->withoutGlobalScopes()->where('occupied_by_bin_id', $m->from_bin_id)
            ->where('merge_type', BinMergeType::Temporary->value)->exists();

        if (! $adaSementara || StockBalance::query()->withoutGlobalScopes()->where('bin_id', $m->from_bin_id)->where('qty_base', '>', 0.00005)->exists()) {
            return;
        }

        $utama = Bin::query()->withoutGlobalScopes()->find($m->from_bin_id);

        if ($utama !== null) {
            $this->split($utama, null, null, true);
        }
    }

    private function bolehJadiUtama(Bin $utama): void
    {
        if ($utama->bin_type !== BinType::Storage || $utama->rack_level_id === null) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin utama harus bin penyimpanan di rak.');
        }

        if ($utama->isMerged()) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$utama->code.' sudah tergabung ke bin lain; pilih bin utamanya.');
        }

        if ($utama->bin_status !== BinStatus::Active) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$utama->code.' berstatus '.$utama->bin_status->label().'.');
        }

        if ($utama->rackLevel?->rack?->is_area) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Area lantai tidak digabung; ubah ukurannya saja.');
        }
    }

    private function bolehDigabung(Bin $utama, Bin $b): void
    {
        $nama = 'Bin '.$b->code;

        if ((int) $b->id === (int) $utama->id) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin utama tidak bisa digabung ke dirinya sendiri.');
        }

        if ((int) $b->warehouse_id !== (int) $utama->warehouse_id || $b->rackLevel === null
            || (int) $b->rackLevel->rack_id !== (int) $utama->rackLevel?->rack_id) {
            throw WarehouseRuleException::rule('BR-WH-08', $nama.' ada di rak lain; gabung hanya untuk petak di rak yang sama.');
        }

        if ($b->bin_type !== BinType::Storage || $b->bin_status !== BinStatus::Active) {
            throw WarehouseRuleException::rule('BR-WH-08', $nama.' bukan bin penyimpanan aktif.');
        }

        if ($b->isMerged() || $b->mergedBins()->exists()) {
            throw WarehouseRuleException::rule('BR-WH-08', $nama.' sudah termasuk gabungan lain. Pisah dulu.');
        }

        if (($tolak = $this->stock->refuseBin((int) $b->id)) !== null) {
            throw WarehouseRuleException::rule('BR-WH-08', $nama.' harus kosong untuk digabung. '.str_replace('Bin ini', 'Bin itu', $tolak));
        }

        if ($this->adaTugasTerbuka((int) $b->id)) {
            throw WarehouseRuleException::rule('BR-WH-08', $nama.' masih dipakai tugas picking atau put-away yang belum selesai.');
        }
    }

    private function adaTugasTerbuka(int $binId): bool
    {
        $putaway = DB::table('putaway_task_lines')->join('putaway_tasks', 'putaway_tasks.id', '=', 'putaway_task_lines.putaway_task_id')
            ->where('putaway_tasks.status', 'pending')
            ->where(fn ($q) => $q->where('putaway_task_lines.suggested_bin_id', $binId)->orWhere('putaway_task_lines.bin_id', $binId))
            ->exists();

        return $putaway || DB::table('pick_task_lines')->join('pick_tasks', 'pick_tasks.id', '=', 'pick_task_lines.pick_task_id')
            ->whereIn('pick_tasks.status', ['pending', 'in_progress'])
            ->where(fn ($q) => $q->where('pick_task_lines.bin_id', $binId)->orWhere('pick_task_lines.suggested_bin_id', $binId))
            ->exists();
    }

    /**
     * Petak gabungan harus bersebelahan secara wajar:
     * samping = satu tingkat dan urutan petak tanpa celah;
     * atas = nomor petak sama, tingkat berurutan mulai dari tingkat bin utama ke atas.
     *
     * @param  Collection<int, Bin>  $tergabung
     */
    private function bersebelahan(Bin $utama, Collection $tergabung, BinMergeDirection $arah): void
    {
        $grup = $tergabung->push($utama);

        if ($arah === BinMergeDirection::Side) {
            if ($grup->contains(fn (Bin $b) => (int) $b->rack_level_id !== (int) $utama->rack_level_id)) {
                throw WarehouseRuleException::rule('BR-WH-08', 'Gabung ke samping hanya untuk petak di tingkat yang sama.');
            }

            $urut = Bin::query()->withoutGlobalScopes()->where('rack_level_id', $utama->rack_level_id)->orderBy('code')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $posisi = $grup->map(fn (Bin $b) => array_search((int) $b->id, $urut, true))->sort()->values();

            if ($posisi->last() - $posisi->first() !== $posisi->count() - 1) {
                throw WarehouseRuleException::rule('BR-WH-08', 'Petak yang digabung ke samping harus bersebelahan tanpa celah.');
            }

            return;
        }

        $petak = Str::afterLast((string) $utama->code, '-');

        if ($grup->contains(fn (Bin $b) => Str::afterLast((string) $b->code, '-') !== $petak)) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Gabung ke atas hanya untuk petak bernomor sama ('.$petak.') di tingkat atasnya.');
        }

        $tingkat = RackLevel::query()->where('rack_id', $utama->rackLevel?->rack_id)->pluck('code', 'id')
            ->sort(fn ($a, $b) => strnatcmp((string) $a, (string) $b))->keys()->map(fn ($id) => (int) $id)->values()->all();
        $posisi = $grup->map(fn (Bin $b) => array_search((int) $b->rack_level_id, $tingkat, true))->sort()->values();

        if ($posisi->unique()->count() !== $posisi->count() || $posisi->first() !== array_search((int) $utama->rack_level_id, $tingkat, true)
            || $posisi->last() - $posisi->first() !== $posisi->count() - 1) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Gabung ke atas: bin utama di tingkat paling bawah, bin lain tepat di tingkat-tingkat atasnya.');
        }
    }
}

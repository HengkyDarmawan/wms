<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use App\Domain\Warehouse\Support\BinUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission: `bin.manage` — ubah nama & kode zona, rak, atau tingkat (A-410).
 *
 * Nama selalu boleh diubah. **Kode** membentuk kode bin (BR-WH-01), jadi hanya
 * boleh diubah selama semua bin di bawahnya **belum pernah dipakai**
 * ({@see BinUsage}, sama dengan aturan hapus bin A-362); kode bin di bawahnya
 * ikut dibentuk ulang dan layar mengingatkan mencetak ulang labelnya.
 * Mengembalikan jumlah bin yang kodenya berubah.
 */
class EditLocation
{
    public function __construct(private readonly BinUsage $pemakaian) {}

    /** @param  array<string, mixed>  $data  code, name */
    public function zone(Zone $zone, array $data, ?User $actor = null): int
    {
        $nama = trim((string) ($data['name'] ?? ''));

        if ($nama === '') {
            throw WarehouseRuleException::fields(['name' => 'Nama zona wajib diisi.'], 'BR-GEN-11');
        }

        return DB::transaction(function () use ($zone, $data, $nama, $actor): int {
            $berubah = $this->gantiKode($zone, (string) ($data['code'] ?? ''), 'zona',
                Zone::query()->where('warehouse_id', $zone->warehouse_id), $this->binZona($zone));
            $zone->forceFill(['name' => mb_substr($nama, 0, 60)])->save();
            $this->catat($zone, 'Zona', $berubah, $actor);

            return $berubah;
        });
    }

    /** @param  array<string, mixed>  $data  code, name, orientation (h|v) */
    public function rack(Rack $rack, array $data, ?User $actor = null): int
    {
        return DB::transaction(function () use ($rack, $data, $actor): int {
            $berubah = $this->gantiKode($rack, (string) ($data['code'] ?? ''), 'rak',
                Rack::query()->where('zone_id', $rack->zone_id), $this->binRak($rack));
            $nama = trim((string) ($data['name'] ?? ''));
            $rack->forceFill(['name' => $nama === '' ? null : mb_substr($nama, 0, 60)]);

            if (in_array($data['orientation'] ?? null, ['h', 'v'], true)) {
                $rack->orientation = $data['orientation'];
            }

            $rack->save();
            $this->catat($rack, 'Rak', $berubah, $actor);

            return $berubah;
        });
    }

    /** @param  array<string, mixed>  $data  code */
    public function level(RackLevel $level, array $data, ?User $actor = null): int
    {
        return DB::transaction(function () use ($level, $data, $actor): int {
            $berubah = $this->gantiKode($level, (string) ($data['code'] ?? ''), 'tingkat',
                RackLevel::query()->where('rack_id', $level->rack_id), Bin::query()->withoutGlobalScopes()->where('rack_level_id', $level->id)->get());
            $this->catat($level, 'Tingkat', $berubah, $actor);

            return $berubah;
        });
    }

    /**
     * @param  Collection<int, Bin>  $bins  semua bin di bawah lokasi ini
     * @return int jumlah bin yang kodenya dibentuk ulang
     */
    private function gantiKode(Zone|Rack|RackLevel $model, string $kodeBaru, string $label, mixed $saudara, Collection $bins): int
    {
        $kode = BinCodeBuilder::segment($kodeBaru);

        if ($kode === '' || $kode === (string) $model->code) {
            return 0;
        }

        if (mb_strlen($kode) > 10) {
            throw WarehouseRuleException::fields(['code' => 'Kode '.$label.' maksimal 10 huruf/angka.'], 'BR-WH-01');
        }

        if ($saudara->where('code', $kode)->whereKeyNot($model->getKey())->exists()) {
            throw WarehouseRuleException::fields(['code' => 'Kode '.$label.' "'.$kode.'" sudah dipakai.'], 'BR-WH-01');
        }

        $dipakai = $this->pemakaian->dipakai($bins->pluck('id')->map(fn ($id) => (int) $id)->all());

        if ($dipakai !== []) {
            $contoh = $bins->first(fn (Bin $b) => isset($dipakai[(int) $b->id]));

            throw WarehouseRuleException::fields(['code' => 'Kode '.$label.' tidak bisa diubah: bin '.$contoh?->code.' sudah pernah dipakai (kode bin tercatat di dokumen & label). Nama tetap bisa diubah.'], 'BR-WH-01');
        }

        $model->forceFill(['code' => $kode])->save();

        foreach ($bins as $b) {
            $level = RackLevel::query()->with('rack.zone.warehouse')->find($b->rack_level_id);
            $baru = $level ? BinCodeBuilder::forRackLevel($level, Str::afterLast((string) $b->code, '-')) : (string) $b->code;

            if ($baru === (string) $b->code) {
                continue;
            }

            if (Bin::query()->withoutGlobalScopes()->where('code', $baru)->whereKeyNot($b->id)->exists()) {
                throw WarehouseRuleException::fields(['code' => 'Kode bin '.$baru.' sudah ada.'], 'BR-WH-01');
            }

            $b->disableLogging()->forceFill(['code' => $baru])->save();
        }

        return $bins->count();
    }

    /** @return Collection<int, Bin> */
    private function binRak(Rack $rack): Collection
    {
        return Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $rack->levels()->pluck('id'))->get();
    }

    /** @return Collection<int, Bin> */
    private function binZona(Zone $zone): Collection
    {
        return Bin::query()->withoutGlobalScopes()
            ->whereIn('rack_level_id', RackLevel::query()->whereIn('rack_id', $zone->racks()->pluck('id'))->pluck('id'))->get();
    }

    private function catat(object $model, string $label, int $binBerubah, ?User $actor): void
    {
        activity('warehouse')->performedOn($model)->causedBy($actor)
            ->withProperties(['bin_berubah_kode' => $binBerubah])
            ->log($label.' '.$model->code.' diubah'.($binBerubah > 0 ? ' ('.$binBerubah.' kode bin ikut berubah)' : ''));
    }
}

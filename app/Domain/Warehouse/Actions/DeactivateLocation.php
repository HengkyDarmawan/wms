<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — menonaktifkan rak atau zona dari denah (A-324).
 *
 * Tidak ada hapus fisik (P-03). Boleh hanya bila semua bin di bawahnya kosong,
 * tanpa reservasi, dan tidak dibekukan opname (BR-GEN-04, BR-OPN-02); bin,
 * level, dan rak ikut nonaktif dengan alasan yang sama (lewat
 * {@see ChangeBinStatus::deactivate}). Zona hanya bila rak aktifnya sudah habis.
 */
class DeactivateLocation
{
    public function __construct(private readonly ChangeBinStatus $bin) {}

    public function rack(Rack $rack, string $reasonCode, ?User $actor = null): Rack
    {
        if (trim($reasonCode) === '') {
            throw WarehouseRuleException::fields(['reason' => 'Alasan wajib dipilih.'], 'BR-GEN-11');
        }

        $bins = $this->binRak($rack);

        if ($beku = $bins->first(fn (Bin $b) => $b->bin_status === BinStatus::Frozen)) {
            throw WarehouseRuleException::rule('BR-OPN-02', 'Bin '.$beku->code.' sedang dibekukan opname.');
        }

        return DB::transaction(function () use ($rack, $bins, $reasonCode, $actor) {
            foreach ($bins->where('bin_status', '!=', BinStatus::Inactive) as $b) {
                // BR-GEN-04: bin berisi / bereservasi ditolak di sini.
                $this->bin->deactivate($b, $reasonCode, 'Rak '.$rack->code.' dinonaktifkan dari denah', $actor);
            }

            $rack->levels()->update(['is_active' => false]);
            $rack->forceFill(['is_active' => false])->save();

            activity('warehouse')->performedOn($rack)->causedBy($actor)
                ->withProperties(['reason_code' => $reasonCode, 'bin' => $bins->count()])->log('Rak dinonaktifkan');

            return $rack->refresh();
        });
    }

    public function zone(Zone $zone, string $reasonCode, ?User $actor = null): Zone
    {
        if (trim($reasonCode) === '') {
            throw WarehouseRuleException::fields(['reason' => 'Alasan wajib dipilih.'], 'BR-GEN-11');
        }

        $aktif = $zone->racks()->where('is_active', true)->pluck('code');

        if ($aktif->isNotEmpty()) {
            throw WarehouseRuleException::rule('BR-WH-07', 'Nonaktifkan dulu rak '.$aktif->implode(', ').' di zona '.$zone->code.'.');
        }

        $zone->forceFill(['is_active' => false])->save();

        activity('warehouse')->performedOn($zone)->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode])->log('Zona dinonaktifkan');

        return $zone->refresh();
    }

    /** @return Collection<int, Bin> */
    private function binRak(Rack $rack): Collection
    {
        return Bin::query()->withoutGlobalScopes()
            ->whereIn('rack_level_id', $rack->levels()->pluck('id'))
            ->get();
    }
}

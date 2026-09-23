<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pick.create` — membuat tugas picking dari REQ yang disetujui
 * (Katalog Status §2.2).
 *
 * Di sinilah janji berubah menjadi penunjukan: reservasi lunak REQ yang hanya
 * menyebut item dan gudang dipecah menjadi alokasi **keras** per bin, lot,
 * serial, atau potongan.
 *
 * Satu PCK untuk satu gudang sumber. Baris REQ yang gudangnya berbeda
 * melahirkan PCK sendiri-sendiri, karena yang mengambil adalah orang yang
 * berdiri di gudang itu.
 */
class CreatePickTask
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ManageReservation $reservasi,
    ) {}

    /**
     * @return array<int, PickTask>  satu PCK per gudang sumber
     */
    public function handle(MaterialRequest $request, ?User $actor = null): array
    {
        if (! $request->status->hasReservations()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Hanya REQ yang sudah disetujui yang bisa dipetik; REQ ini berstatus '.$request->status->label().'.',
            );
        }

        $baris = $request->lines()
            ->open()
            ->where('fulfillment_source', 'stock')
            ->whereNotNull('source_warehouse_id')
            ->with('item')
            ->get();

        if ($baris->isEmpty()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Tidak ada baris bersumber stok yang siap dipetik di REQ ini.',
            );
        }

        // Baris yang sudah punya PCK tidak diambil dua kali.
        $sudah = PickTaskLine::query()
            ->whereHas('pickTask', fn (Builder $q) => $q
                ->withoutGlobalScopes()
                ->forSource('material_request', (int) $request->id)
                ->whereNot('status', PickTaskStatus::Cancelled->value))
            ->pluck('source_line_id')
            ->all();

        $baris = $baris->reject(fn (MaterialRequestLine $l) => in_array($l->id, $sudah, true));

        if ($baris->isEmpty()) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'Seluruh baris REQ ini sudah punya tugas picking.');
        }

        return DB::transaction(function () use ($request, $baris, $actor) {
            $hasil = [];

            foreach ($baris->groupBy('source_warehouse_id') as $warehouseId => $kelompok) {
                $hasil[] = $this->buatSatuTugas($request, (int) $warehouseId, $kelompok->all(), $actor);
            }

            if ($request->status === MaterialRequestStatus::Approved) {
                // Katalog Status §2.1: PCK pertama menggeser REQ ke `in_progress`.
                $request->forceFill(['status' => MaterialRequestStatus::InProgress])->save();
            }

            return $hasil;
        });
    }

    /** @param  array<int, MaterialRequestLine>  $lines */
    private function buatSatuTugas(MaterialRequest $request, int $warehouseId, array $lines, ?User $actor): PickTask
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($warehouseId);

        $tugas = PickTask::create([
            'number' => $this->nomor->next('PCK', (string) $gudang->code),
            'warehouse_id' => $gudang->id,
            'source_type' => 'material_request',
            'source_id' => $request->id,
            'status' => PickTaskStatus::Pending,
        ]);

        foreach ($lines as $baris) {
            $this->alokasikan($tugas, $gudang, $baris, $actor);
        }

        activity('shipment')
            ->performedOn($tugas)
            ->causedBy($actor)
            ->withProperties(['req' => $request->number, 'baris' => count($lines)])
            ->log('Tugas picking dibuat');

        return $tugas->refresh();
    }

    /** Melepas reservasi lunak satu baris REQ sebelum alokasi keras dibuat. */
    private function lepasReservasiLunak(MaterialRequestLine $baris, ?User $actor): void
    {
        $reservasi = StockReservation::query()
            ->active()
            ->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', (int) $baris->material_request_id)
            ->where('document_line_id', $baris->id)
            ->get();

        foreach ($reservasi as $r) {
            $this->reservasi->release($r, 'PICK_ALLOCATED', $actor);
        }
    }

    /**
     * Memecah satu baris REQ menjadi alokasi per bin.
     *
     * Bin diambil menurut kode — pendekatan yang cukup untuk Fase 1. Strategi
     * pengambilan sesungguhnya (FEFO, FIFO, potongan terdekat) menyusul di
     * modul yang memilikinya; yang penting sekarang alokasinya benar-benar
     * menunjuk barang yang ada.
     */
    private function alokasikan(PickTask $tugas, Warehouse $gudang, MaterialRequestLine $baris, ?User $actor): void
    {
        $sisa = (float) $baris->qty_base;

        // BR-STK-04: alokasi keras MENGGANTIKAN janji lunak, tidak menumpuk di
        // atasnya. Tanpa pelepasan ini, barang yang sama terhitung dua kali dan
        // stok tersedia jadi kurang dari kenyataan.
        $this->lepasReservasiLunak($baris, $actor);

        $saldo = StockBalance::query()->withoutGlobalScopes()
            ->with('bin')
            ->where('item_id', $baris->item_id)
            ->where('stock_status', StockStatus::Available->value)
            ->nonZero()
            ->whereHas('bin', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('warehouse_id', $gudang->id)
                ->where('bin_status', BinStatus::Active->value))
            ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
            ->orderBy('b.code')
            ->select('stock_balances.*')
            ->get();

        foreach ($saldo as $s) {
            if ($sisa <= 0) {
                break;
            }

            $ambil = min($sisa, (float) $s->qty_base);

            PickTaskLine::create([
                'pick_task_id' => $tugas->id,
                'source_line_id' => $baris->id,
                'item_id' => $baris->item_id,
                'bin_id' => $s->bin_id,
                'suggested_bin_id' => $s->bin_id,
                'lot_id' => $s->lot_id,
                'serial_id' => $s->serial_id,
                'piece_id' => $s->piece_id,
                'qty_allocated' => $ambil,
            ]);

            // BR-STK-04: alokasi keras menggantikan janji lunak REQ.
            $this->reservasi->reserveHard(
                $baris->item,
                $gudang,
                $ambil,
                array_filter([
                    'bin_id' => $s->bin_id,
                    'lot_id' => $s->lot_id,
                    'serial_id' => $s->serial_id,
                    'piece_id' => $s->piece_id,
                ]),
                'pick_task',
                (int) $tugas->id,
                null,
                $actor,
            );

            $sisa -= $ambil;
        }

        if ($sisa > 0) {
            // Stok tidak cukup meski REQ menjanjikannya: reservasi lunak dibuat
            // saat approval dan barangnya berpindah sejak itu. Ini bukan
            // kesalahan staf, jadi disebut apa adanya.
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Stok '.$baris->displayName().' di gudang '.$gudang->code.' tinggal '
                .((float) $baris->qty_base - $sisa).' dari '.(float) $baris->qty_base.' yang dijanjikan REQ.',
            );
        }
    }
}

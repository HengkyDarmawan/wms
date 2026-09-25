<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Shipment\Support\RequestLineOutstanding;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\RemovalOrder;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pick.create` — membuat tugas picking dari dokumen niat yang
 * disetujui (Katalog Status §2.2: "REQ/TRF `approved`").
 *
 * Di sinilah janji berubah menjadi penunjukan: reservasi lunak yang hanya
 * menyebut item dan gudang dipecah menjadi alokasi **keras** per bin, lot,
 * serial, atau potongan.
 *
 * Tiga sumber:
 * - **REQ** — baris bersumber stok, dan baris bersumber transfer yang barangnya
 *   sudah tiba dan direservasi ke REQ penunggu (BR-REQ-08, A-108);
 * - **TRF** — seluruh baris di gudang asal (alur 5 langkah 5, A-107);
 * - **RET** — SJ balik dari Gudang Site: alokasi persis bin/turunan yang
 *   disebut baris retur (A-111).
 *
 * Satu PCK untuk satu gudang. Baris REQ yang gudangnya berbeda melahirkan PCK
 * sendiri-sendiri, karena yang mengambil adalah orang yang berdiri di gudang itu.
 */
class CreatePickTask
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ManageReservation $reservasi,
        private readonly RequestLineOutstanding $sisa,
    ) {}

    /**
     * @return array<int, PickTask> satu PCK per gudang sumber
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
            ->whereIn('fulfillment_source', [FulfillmentSource::Stock->value, FulfillmentSource::Transfer->value])
            ->whereNotNull('source_warehouse_id')
            ->with('item')
            ->get();

        if ($baris->isEmpty()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Tidak ada baris bersumber stok yang siap dipetik di REQ ini.',
            );
        }

        /** @var array<int, array{line: MaterialRequestLine, qty: float}> $rencana */
        $rencana = [];

        foreach ($baris as $l) {
            if ($l->fulfillment_source === FulfillmentSource::Transfer) {
                // A-108: baris transfer baru bisa dipetik sebesar barang TRF yang
                // sudah tiba dan direservasi ke baris ini.
                $qty = $this->reservasiLunakBaris($l);

                if ($qty > 0) {
                    $rencana[] = ['line' => $l, 'qty' => $qty];
                }

                continue;
            }

            // A-204: hanya sisa yang belum dipetik/berjalan — baris yang sudah
            // punya PCK tidak diambil dua kali, tetapi short pick, `reship`, dan
            // keberatan "masih dibutuhkan" bisa dipetik ulang.
            $sisa = $this->sisa->qty($l);

            if ($sisa > 0) {
                $rencana[] = ['line' => $l, 'qty' => $sisa];
            }
        }

        if ($rencana === []) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'Seluruh baris REQ ini sudah punya tugas picking atau barang transfernya belum tiba.');
        }

        return DB::transaction(function () use ($request, $rencana, $actor) {
            $hasil = [];

            foreach (collect($rencana)->groupBy(fn (array $r) => $r['line']->source_warehouse_id) as $warehouseId => $kelompok) {
                $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail((int) $warehouseId);
                $tugas = $this->buatTugas($gudang, 'material_request', (int) $request->id);

                foreach ($kelompok as $r) {
                    /** @var MaterialRequestLine $line */
                    $line = $r['line'];

                    // BR-STK-04: alokasi keras MENGGANTIKAN janji lunak, tidak
                    // menumpuk di atasnya.
                    $this->lepasReservasiLunak('material_request', (int) $line->material_request_id, (int) $line->id, $actor);

                    $this->alokasikan($tugas, $gudang, $line->item, $r['qty'], (int) $line->id, $line->displayName(), $actor);
                }

                $this->catat($tugas, $actor, ['req' => $request->number, 'baris' => $kelompok->count()]);
                $hasil[] = $tugas->refresh();
            }

            if ($request->status === MaterialRequestStatus::Approved) {
                // Katalog Status §2.1: PCK pertama menggeser REQ ke `in_progress`.
                $request->forceFill(['status' => MaterialRequestStatus::InProgress])->save();
            }

            return $hasil;
        });
    }

    /**
     * TRF `approved` → PCK di gudang asal, TRF `in_progress` (Katalog §2.7,
     * alur 5 langkah 5). Baris yang sudah teralokasi di PCK yang masih hidup
     * tidak dialokasikan lagi, sehingga PCK bisa dibuat ulang setelah dibatalkan.
     */
    public function forTransfer(Transfer $transfer, ?User $actor = null): PickTask
    {
        if (! in_array($transfer->status, [TransferStatus::Approved, TransferStatus::InProgress], true)) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Hanya TRF yang sudah disetujui yang bisa dipetik; TRF ini berstatus '.$transfer->status->label().'.',
            );
        }

        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($transfer->from_warehouse_id);
        $baris = $transfer->lines()->with('item')->orderBy('id')->get();

        /** @var array<int, array{line: TransferLine, qty: float}> $rencana */
        $rencana = [];

        foreach ($baris as $l) {
            $sisa = round((float) $l->qty_base - $this->sudahDialokasikan('transfer', (int) $transfer->id, (int) $l->id), 4);

            if ($sisa > 0) {
                $rencana[] = ['line' => $l, 'qty' => $sisa];
            }
        }

        if ($rencana === []) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'Seluruh baris TRF ini sudah punya tugas picking.');
        }

        return DB::transaction(function () use ($transfer, $gudang, $rencana, $actor) {
            $tugas = $this->buatTugas($gudang, 'transfer', (int) $transfer->id);

            foreach ($rencana as $r) {
                /** @var TransferLine $line */
                $line = $r['line'];

                $this->lepasReservasiLunak('transfer', (int) $transfer->id, (int) $line->id, $actor);
                $this->alokasikan($tugas, $gudang, $line->item, $r['qty'], (int) $line->id, $line->item->code, $actor);
            }

            if ($transfer->status === TransferStatus::Approved) {
                $transfer->forceFill(['status' => TransferStatus::InProgress])->save();

                activity('transfer')->performedOn($transfer)->causedBy($actor)
                    ->withProperties(['pck' => $tugas->number])
                    ->log('TRF diproses: tugas picking dibuat');
            }

            $this->catat($tugas, $actor, ['trf' => $transfer->number, 'baris' => count($rencana)]);

            return $tugas->refresh();
        });
    }

    /**
     * RET `approved` dengan SJ balik → PCK di Gudang Site asal (A-111).
     *
     * Alokasinya bukan hasil strategi pengambilan: baris retur sudah menyebut
     * bin dan lot/serial/potongan yang dikembalikan, jadi itulah yang dipetik.
     */
    public function forGoodsReturn(GoodsReturn $return, ?User $actor = null): PickTask
    {
        if ($return->status !== GoodsReturnStatus::Approved || $return->self_delivered || $return->from_warehouse_id === null) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Tugas picking retur hanya untuk RET disetujui yang dikirim balik dengan SJ dari Gudang Site.',
            );
        }

        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($return->from_warehouse_id);
        $baris = $return->lines()->with('item')->whereNull('split_from_line_id')->orderBy('id')->get();

        if ($this->sudahDialokasikan('goods_return', (int) $return->id, null) > 0) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'RET ini sudah punya tugas picking.');
        }

        return DB::transaction(function () use ($return, $gudang, $baris, $actor) {
            $tugas = $this->buatTugas($gudang, 'goods_return', (int) $return->id);

            // Cadangan keras RET sejak disetujui berpindah ke PCK.
            $this->reservasi->releaseForDocument('goods_return', (int) $return->id, 'PICK_ALLOCATED', $actor);

            foreach ($baris as $l) {
                /** @var GoodsReturnLine $l */
                PickTaskLine::create([
                    'pick_task_id' => $tugas->id,
                    'source_line_id' => $l->id,
                    'item_id' => $l->item_id,
                    'bin_id' => $l->from_bin_id,
                    'suggested_bin_id' => $l->from_bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                    'qty_allocated' => $l->qty_base,
                ]);

                $this->cadangkanKeras($l->item, $gudang, (float) $l->qty_base, [
                    'bin_id' => $l->from_bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                ], $tugas, $l->item->code, $actor);
            }

            $this->catat($tugas, $actor, ['ret' => $return->number, 'baris' => $baris->count()]);

            return $tugas->refresh();
        });
    }

    // ---------------------------------------------------------------- privat

    private function buatTugas(Warehouse $gudang, string $sourceType, int $sourceId): PickTask
    {
        return PickTask::create([
            'number' => $this->nomor->next('PCK', (string) $gudang->code),
            'warehouse_id' => $gudang->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => PickTaskStatus::Pending,
        ]);
    }

    /** @param  array<string, mixed>  $props */
    private function catat(PickTask $tugas, ?User $actor, array $props): void
    {
        activity('shipment')
            ->performedOn($tugas)
            ->causedBy($actor)
            ->withProperties($props)
            ->log('Tugas picking dibuat');
    }

    /** Jumlah reservasi lunak aktif satu baris REQ (barang transfer yang sudah tiba). */
    private function reservasiLunakBaris(MaterialRequestLine $line): float
    {
        return round((float) StockReservation::query()
            ->active()
            ->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', (int) $line->material_request_id)
            ->where('document_line_id', $line->id)
            ->sum('qty_base'), 4);
    }

    /** Jumlah yang sudah dialokasikan PCK hidup untuk satu dokumen (atau satu barisnya). */
    private function sudahDialokasikan(string $sourceType, int $sourceId, ?int $sourceLineId): float
    {
        return (float) PickTaskLine::query()
            ->whereHas('pickTask', fn (Builder $q) => $q
                ->withoutGlobalScopes()
                ->forSource($sourceType, $sourceId)
                ->whereNot('status', PickTaskStatus::Cancelled->value))
            ->when($sourceLineId !== null, fn (Builder $q) => $q->where('source_line_id', $sourceLineId))
            ->sum('qty_allocated');
    }

    /** Melepas reservasi lunak satu baris dokumen sebelum alokasi keras dibuat. */
    private function lepasReservasiLunak(string $documentType, int $documentId, int $lineId, ?User $actor): void
    {
        $reservasi = StockReservation::query()
            ->active()
            ->where('level', ReservationLevel::Soft->value)
            ->forDocument($documentType, $documentId)
            ->where('document_line_id', $lineId)
            ->get();

        foreach ($reservasi as $r) {
            $this->reservasi->release($r, 'PICK_ALLOCATED', $actor);
        }
    }

    /**
     * Memecah satu baris menjadi alokasi per bin.
     *
     * Bin diambil menurut kode — pendekatan yang cukup untuk Fase 1. Strategi
     * pengambilan sesungguhnya (FEFO, FIFO, potongan terdekat) menyusul di
     * modul yang memilikinya; yang penting sekarang alokasinya benar-benar
     * menunjuk barang yang ada.
     */
    private function alokasikan(PickTask $tugas, Warehouse $gudang, Item $item, float $qty, int $sourceLineId, string $label, ?User $actor): void
    {
        $sisa = $qty;

        $saldo = StockBalance::query()->withoutGlobalScopes()
            ->with('bin')
            ->where('stock_balances.item_id', $item->id)
            ->where('stock_balances.stock_status', StockStatus::Available->value)
            ->where('stock_balances.qty_base', '>', 0)
            ->whereHas('bin', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('warehouse_id', $gudang->id)
                // Hanya bin penyimpanan: barang di Penerimaan, Karantina,
                // Loading Area, atau Dalam Perjalanan belum/tidak lagi boleh
                // dipetik (19-receipt-putaway §13, 15-picking-shipment §13.1).
                ->where('bin_type', BinType::Storage->value)
                ->where('bin_status', BinStatus::Active->value))
            ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
            ->select('stock_balances.*');

        $saldo = app(RemovalOrder::class)->apply($saldo, $item)->get();

        // Baris saldo yang sudah dialokasikan keras ke PCK lain tidak boleh
        // dijanjikan dua kali; ketersediaan per gudang saja tidak cukup (BR-STK-03).
        $terpakai = StockReservation::query()->active()
            ->where('level', ReservationLevel::Hard->value)
            ->where('item_id', $item->id)
            ->where('warehouse_id', $gudang->id)
            ->get(['bin_id', 'lot_id', 'serial_id', 'piece_id', 'qty_base'])
            ->groupBy(fn ($r) => $r->bin_id.'|'.$r->lot_id.'|'.$r->serial_id.'|'.$r->piece_id)
            ->map(fn ($g) => (float) $g->sum('qty_base'));

        foreach ($saldo as $s) {
            if ($sisa <= 0) {
                break;
            }

            $bebas = (float) $s->qty_base - (float) ($terpakai[$s->bin_id.'|'.$s->lot_id.'|'.$s->serial_id.'|'.$s->piece_id] ?? 0);

            if ($bebas <= 0.00005) {
                continue;
            }

            $ambil = min($sisa, $bebas);

            PickTaskLine::create([
                'pick_task_id' => $tugas->id,
                'source_line_id' => $sourceLineId,
                'item_id' => $item->id,
                'bin_id' => $s->bin_id,
                'suggested_bin_id' => $s->bin_id,
                'lot_id' => $s->lot_id,
                'serial_id' => $s->serial_id,
                'piece_id' => $s->piece_id,
                'qty_allocated' => $ambil,
            ]);

            $this->cadangkanKeras($item, $gudang, $ambil, [
                'bin_id' => $s->bin_id,
                'lot_id' => $s->lot_id,
                'serial_id' => $s->serial_id,
                'piece_id' => $s->piece_id,
            ], $tugas, $label, $actor);

            $sisa -= $ambil;
        }

        if ($sisa > 0.00005) {
            // Stok tidak cukup meski dokumen menjanjikannya: reservasi lunak
            // dibuat saat approval dan barangnya berpindah sejak itu. Ini bukan
            // kesalahan staf, jadi disebut apa adanya.
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Stok '.$label.' di gudang '.$gudang->code.' tinggal '
                .round($qty - $sisa, 4).' dari '.round($qty, 4).' yang dijanjikan.',
            );
        }
    }

    /** @param  array<string, mixed>  $target */
    private function cadangkanKeras(Item $item, Warehouse $gudang, float $qty, array $target, PickTask $tugas, string $label, ?User $actor): void
    {
        try {
            // BR-STK-04: alokasi keras menggantikan janji lunak dokumen.
            $this->reservasi->reserveHard($item, $gudang, $qty, array_filter($target), 'pick_task', (int) $tugas->id, null, $actor);
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule($e->rule, 'Baris '.$label.' tidak bisa dialokasikan: '.$e->getMessage());
        }
    }
}

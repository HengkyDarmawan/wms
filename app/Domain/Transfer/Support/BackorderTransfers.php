<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Actions\CancelTransfer;
use App\Domain\Transfer\Actions\CreateTransfer;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Sambungan REQ ↔ TRF (BR-REQ-05, BR-REQ-08, BR-REQ-15; A-106, A-108).
 *
 * - **Saat REQ disetujui**: baris bersumber transfer melahirkan TRF `backorder`
 *   dari gudang lain ke gudang sumber baris (gudang pemenuh). Gudang asal
 *   dipilih sistem; tanpa gudang yang stoknya cukup, approval REQ tertahan.
 * - **Saat barang TRF ditaruh** di bin penyimpanan gudang tujuan: jumlahnya
 *   direservasi lunak ke baris REQ penunggu, sehingga baris itu bisa dipetik
 *   dan dikirim ke proyek seperti baris bersumber stok.
 * - **Saat REQ/baris dibatalkan atau ditutup**: TRF backorder yang belum
 *   berjalan ikut dibatalkan.
 */
class BackorderTransfers
{
    public function __construct(
        private readonly CreateTransfer $buat,
        private readonly CancelTransfer $batal,
        private readonly StockLedger $ledger,
        private readonly ManageReservation $reservasi,
    ) {}

    /**
     * @return array<int, Transfer>
     */
    public function createFor(MaterialRequest $request, ?User $actor = null): array
    {
        $baris = $request->lines()->open()
            ->where('fulfillment_source', FulfillmentSource::Transfer->value)
            ->with('item')
            ->orderBy('required_date')->orderBy('id')
            ->get();

        if ($baris->isEmpty()) {
            return [];
        }

        /** @var array<string, float> $terpakai  "gudang|item" => jumlah yang sudah direncanakan */
        $terpakai = [];
        /** @var array<string, array{from: int, to: int, lines: array<int, array<string, mixed>>}> $rencana */
        $rencana = [];

        foreach ($baris as $l) {
            /** @var MaterialRequestLine $l */
            $tujuan = Warehouse::query()->withoutGlobalScopes()->with('type')->find($l->source_warehouse_id);

            if ($tujuan === null || $l->item === null) {
                continue;
            }

            $asal = $this->pilihAsal($l->item, $tujuan, (float) $l->qty_base, $terpakai);

            if ($asal === null) {
                throw RequestRuleException::rule(
                    'BR-REQ-05',
                    'Baris '.$l->displayName().': tidak ada gudang lain dengan stok tersedia cukup ('
                    .round((float) $l->qty_base, 4).') untuk ditransfer ke '.$tujuan->code
                    .'. Ubah cara pemenuhan ke pembelian atau pecah baris.',
                );
            }

            $kunciStok = $asal->id.'|'.$l->item_id;
            $terpakai[$kunciStok] = ($terpakai[$kunciStok] ?? 0) + (float) $l->qty_base;

            $kunci = $asal->id.'|'.$tujuan->id;
            $rencana[$kunci] ??= ['from' => (int) $asal->id, 'to' => (int) $tujuan->id, 'lines' => []];
            $rencana[$kunci]['lines'][] = [
                'item_id' => $l->item_id,
                'qty_base' => (float) $l->qty_base,
                'material_request_line_id' => $l->id,
            ];
        }

        $hasil = [];

        foreach ($rencana as $r) {
            try {
                $hasil[] = $this->buat->handle(
                    ['from_warehouse_id' => $r['from'], 'to_warehouse_id' => $r['to'], 'notes' => 'Backorder '.$request->number],
                    $r['lines'],
                    $actor,
                    TransferOrigin::Backorder,
                    $request,
                );
            } catch (TransferRuleException $e) {
                // Pesan dibawa ke layar REQ: yang ditolak adalah approval REQ-nya.
                throw RequestRuleException::rule($e->rule, 'TRF backorder gagal dibuat: '.$e->getMessage());
            }
        }

        return $hasil;
    }

    /**
     * BR-REQ-08 (A-108): barang TRF backorder yang sudah di bin penyimpanan
     * gudang tujuan direservasi lunak ke baris REQ penunggunya.
     */
    public function reserveArrivals(PutawayTask $task, ?User $actor = null): void
    {
        $task->loadMissing('lines.receiptLine.shipmentLine.pickTaskLine');

        foreach ($task->lines as $putLine) {
            $trfLine = app(TransferProgress::class)->sumber($putLine->receiptLine?->shipmentLine?->pickTaskLine);

            if ($trfLine === null || $trfLine->material_request_line_id === null) {
                continue;
            }

            $reqLine = MaterialRequestLine::query()->with('request', 'item')->find($trfLine->material_request_line_id);

            if ($reqLine === null || ! $this->menunggu($reqLine) || (int) $reqLine->source_warehouse_id !== (int) $task->warehouse_id) {
                continue;
            }

            $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($task->warehouse_id);
            $butuh = (float) $reqLine->qty_base - (float) $reqLine->qty_shipped - $this->reservasiAktif($reqLine);
            $qty = round(min((float) $putLine->qty_base, $butuh, $this->ledger->availableQty((int) $reqLine->item_id, (int) $gudang->id)), 4);

            if ($qty <= 0) {
                continue;
            }

            try {
                $this->reservasi->reserveSoft($reqLine->item, $gudang, $qty, 'material_request', (int) $reqLine->material_request_id, (int) $reqLine->id, $actor);
            } catch (LedgerException) {
                continue;
            }

            $reqLine->forceFill(['qty_reserved' => (float) $reqLine->qty_reserved + $qty])->save();

            activity('request')->performedOn($reqLine->request)->causedBy($actor)
                ->withProperties(['baris' => $reqLine->id, 'qty' => $qty, 'trf' => $trfLine->transfer?->number])
                ->log('Barang transfer tiba dan direservasi ke REQ');
        }
    }

    /**
     * BR-REQ-15 (A-108): TRF backorder yang seluruh baris REQ-nya tidak lagi
     * terbuka dibatalkan bila masih bisa; yang sudah berjalan diteruskan dan
     * barangnya menjadi stok biasa gudang tujuan.
     *
     * @param  array<int, int>  $requestLineIds
     */
    public function releaseForRequestLines(array $requestLineIds, ?int $reasonCodeId, ?User $actor = null): int
    {
        if ($requestLineIds === [] || $reasonCodeId === null) {
            return 0;
        }

        $trfIds = TransferLine::query()->whereIn('material_request_line_id', $requestLineIds)->pluck('transfer_id')->unique();
        $dibatalkan = 0;

        foreach (Transfer::withoutGlobalScopes()->whereIn('id', $trfIds)->get() as $trf) {
            if (! $trf->status->isCancellable() || $trf->hasLivePickTask()) {
                continue;
            }

            $masihDibutuhkan = MaterialRequestLine::query()
                ->whereIn('id', $trf->lines()->pluck('material_request_line_id')->filter())
                ->where('status', RequestLineStatus::Open->value)
                ->exists();

            if ($masihDibutuhkan) {
                continue;
            }

            try {
                $this->batal->handle($trf, $this->alasanBatal($reasonCodeId), 'REQ asal dibatalkan/ditutup', $actor);
                $dibatalkan++;
            } catch (TransferRuleException) {
                // TRF yang tak bisa dibatalkan diteruskan; barangnya menjadi stok biasa.
                continue;
            }
        }

        return $dibatalkan;
    }

    /**
     * A-106: induk gudang tujuan dulu bila stoknya cukup, lalu gudang non-site
     * lain dengan stok tersedia terbanyak (urut kode). Gudang Site tidak
     * dipilih otomatis — memindah stok proyek lain adalah keputusan manusia.
     *
     * @param  array<string, float>  $terpakai
     */
    private function pilihAsal(Item $item, Warehouse $tujuan, float $qty, array $terpakai): ?Warehouse
    {
        /** @var Collection<int, Warehouse> $calon */
        $calon = Warehouse::query()->withoutGlobalScopes()->with('type')
            ->where('is_active', true)
            ->where('id', '!=', $tujuan->id)
            ->orderBy('code')
            ->get()
            ->reject(fn (Warehouse $w) => $w->isSite());

        $sisa = fn (Warehouse $w) => $this->ledger->availableQty((int) $item->id, (int) $w->id)
            - ($terpakai[$w->id.'|'.$item->id] ?? 0);

        $cukup = $calon->filter(fn (Warehouse $w) => $sisa($w) + 0.00005 >= $qty);

        if ($tujuan->parent_id !== null && ($induk = $cukup->firstWhere('id', $tujuan->parent_id)) !== null) {
            return $induk;
        }

        return $cukup->sortByDesc(fn (Warehouse $w) => $sisa($w))->first();
    }

    /** Alasan REQ dipakai bila memang alasan pembatalan; selain itu "Kebutuhan berubah". */
    private function alasanBatal(int $reasonCodeId): ?int
    {
        $konteks = ReasonCode::query()->whereKey($reasonCodeId)->value('context');

        if ($konteks === ReasonContext::Cancel->value || $konteks === ReasonContext::Cancel) {
            return $reasonCodeId;
        }

        $id = ReasonCode::query()->where('context', ReasonContext::Cancel->value)
            ->orderByRaw("case when code = 'NOT_NEEDED' then 0 else 1 end")->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function menunggu(MaterialRequestLine $line): bool
    {
        return $line->status === RequestLineStatus::Open
            && $line->fulfillment_source === FulfillmentSource::Transfer
            && in_array($line->request?->status, [
                MaterialRequestStatus::Approved,
                MaterialRequestStatus::InProgress,
                MaterialRequestStatus::PartiallyFulfilled,
            ], true);
    }

    private function reservasiAktif(MaterialRequestLine $line): float
    {
        return (float) StockReservation::query()->active()
            ->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', (int) $line->material_request_id)
            ->where('document_line_id', $line->id)
            ->sum('qty_base');
    }
}

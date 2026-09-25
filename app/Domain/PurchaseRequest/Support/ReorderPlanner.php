<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * BR-REQ-11 — **titik pesan ulang** (job harian): draf PRQ `reorder_point` per
 * gudang untuk item yang Stok Tersedia-nya < titik pesan ulang; jumlah =
 * stok minimum − tersedia, minimal sebesar titik pesan ulang. Tidak dibuat
 * ulang selama masih ada PRQ terbuka (draf s.d. sebagian terpenuhi) untuk
 * item & gudang yang sama.
 *
 * Gudang yang diperiksa (A-175): gudang aktif bukan Gudang Site yang pernah
 * menyimpan item itu (punya baris saldo), supaya gudang yang tidak pernah
 * menyetok item tidak dibanjiri draf.
 */
class ReorderPlanner
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly DocumentNumber $nomor,
    ) {}

    /** @return array<int, PurchaseRequest> draf yang dibuat */
    public function run(): array
    {
        $items = Item::query()->where('status', ItemStatus::Active->value)
            ->whereNotNull('reorder_point')->where('reorder_point', '>', 0)
            ->get(['id', 'code', 'reorder_point', 'min_stock']);

        if ($items->isEmpty()) {
            return [];
        }

        $gudang = Warehouse::query()->withoutGlobalScopes()->with('type')->where('is_active', true)->get()
            ->reject(fn (Warehouse $w) => $w->isSite())->keyBy('id');

        $terbuka = PurchaseRequestLine::query()
            ->whereHas('purchaseRequest', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', PurchaseRequestStatus::openValues()))
            ->with('purchaseRequest:id,warehouse_id')
            ->get()
            ->mapWithKeys(fn (PurchaseRequestLine $l) => [$l->purchaseRequest->warehouse_id.'|'.$l->item_id => true]);

        $rencana = [];

        foreach ($items as $item) {
            $gudangItem = StockBalance::query()->where('item_id', $item->id)
                ->join('bins', 'bins.id', '=', 'stock_balances.bin_id')
                ->distinct()->pluck('bins.warehouse_id')->map(fn ($id) => (int) $id);

            foreach ($gudangItem as $gid) {
                if (! $gudang->has($gid) || isset($terbuka[$gid.'|'.$item->id])) {
                    continue;
                }

                $tersedia = $this->ledger->availableQty((int) $item->id, $gid);
                $titik = (float) $item->reorder_point;

                if ($tersedia + 0.00005 >= $titik) {
                    continue;
                }

                $jumlah = round(max((float) ($item->min_stock ?? 0) - $tersedia, $titik), 4);
                $rencana[$gid][] = ['item_id' => (int) $item->id, 'qty_base' => $jumlah];
            }
        }

        $hasil = [];

        foreach ($rencana as $gid => $baris) {
            $hasil[] = DB::transaction(function () use ($gid, $baris) {
                $prq = PurchaseRequest::create([
                    'number' => $this->nomor->next('PRQ', 'ALL'),
                    'warehouse_id' => $gid,
                    'origin' => PurchaseRequestOrigin::ReorderPoint,
                    'status' => PurchaseRequestStatus::Draft,
                    'notes' => 'Titik pesan ulang '.now()->format('d/m/Y'),
                ]);

                foreach ($baris as $b) {
                    PurchaseRequestLine::create($b + ['purchase_request_id' => $prq->id]);
                }

                activity('purchase_request')->performedOn($prq)
                    ->withProperties(['baris' => count($baris)])
                    ->log('Draf PRQ titik pesan ulang dibuat sistem; tinjau lalu ajukan atau batalkan');

                return $prq;
            });
        }

        foreach ($hasil as $prq) {
            app(DomainNotifications::class)->reorderDraftCreated($prq);
        }

        return $hasil;
    }
}

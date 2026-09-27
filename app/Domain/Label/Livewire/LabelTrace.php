<?php

declare(strict_types=1);

namespace App\Domain\Label\Livewire;

use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Models\PackageLabelMove;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Support\ScanCode;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shared\Support\DocumentLineage;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Telusuri label (A-302, 18-template-dokumen-label §6.6): ketik/pindai kode
 * label induk/isi → asal (GRN, vendor, tanggal, catatan pemesanan/PO/PRQ),
 * lot & batch vendor, lokasi terakhir, induk/isi, dan riwayat keluar-masuk
 * dengan tautan dokumen. Kode item, nomor lot, atau nomor GRN menampilkan
 * daftar labelnya.
 */
class LabelTrace extends Component
{
    public const DOKUMEN = [
        'goods_receipt' => GoodsReceipt::class,
        'putaway_task' => PutawayTask::class,
        'pick_task' => PickTask::class,
        'material_issue' => MaterialIssue::class,
        'goods_return' => GoodsReturn::class,
    ];

    #[Url(as: 'code', except: '')]
    public string $code = '';

    public function mount(): void
    {
        $this->authorize('item.view');
    }

    public function cari(): void
    {
        $this->code = ScanCode::normalize($this->code);
    }

    public function render(DocumentLineage $lineage): View
    {
        $kode = ScanCode::normalize($this->code);
        $label = $kode === '' ? null : PackageLabel::query()
            ->with('item.baseUom:id,code', 'lot', 'packageUom:id,code', 'warehouse:id,code,name', 'bin:id,code', 'parent:id,code,status',
                'children:id,parent_id,code,qty,qty_remaining,status', 'cancelReason:id,label', 'receiptLine')
            ->where('code', $kode)->first();

        return view('livewire.label.label-trace', [
            'kode' => $kode,
            'label' => $label,
            'asal' => $label ? $this->asal($label, $lineage) : null,
            'riwayat' => $label ? $this->riwayat($label, $lineage) : collect(),
            'daftar' => $label === null && $kode !== '' ? $this->daftar($kode) : collect(),
        ]);
    }

    /** @return array<string, mixed> */
    private function asal(PackageLabel $label, DocumentLineage $lineage): array
    {
        $grn = GoodsReceipt::query()->withoutGlobalScopes()->with('vendor:id,code,name')->find($label->goods_receipt_id);
        $olId = $label->receiptLine?->purchase_request_order_line_id;
        $ol = $olId === null ? null : PurchaseRequestOrderLine::query()->with('order.vendor:id,name')->find($olId);
        $order = $ol?->order;

        return [
            'grn' => $grn,
            'grnEntry' => $lineage->entry($grn),
            'vendor' => $grn?->vendor?->name ?? $order?->vendor?->name,
            'diterima' => $grn?->received_at,
            'order' => $order,
            'dokumen' => collect([
                $order?->purchase_request_id ? PurchaseRequest::query()->withoutGlobalScopes()->find($order->purchase_request_id) : null,
                $order?->purchase_order_id ? PurchaseOrder::query()->withoutGlobalScopes()->find($order->purchase_order_id) : null,
            ])->map(fn ($m) => $lineage->entry($m))->filter()->values(),
            'batch' => $label->lot?->attributes['vendor_batch'] ?? $label->receiptLine?->vendor_batch_no,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function riwayat(PackageLabel $label, DocumentLineage $lineage): Collection
    {
        $moves = PackageLabelMove::query()->with('warehouse:id,code', 'fromBin:id,code', 'toBin:id,code', 'performer:id,name', 'reason:id,label')
            ->where('package_label_id', $label->id)->orderBy('occurred_at')->orderBy('id')->get();

        return $moves->map(function (PackageLabelMove $m) use ($lineage) {
            $kelas = self::DOKUMEN[$m->document_type] ?? null;
            $dok = $kelas === null || $m->document_id === null ? null : $kelas::query()->withoutGlobalScopes()->find($m->document_id);

            return [
                'move' => $m,
                'kejadian' => $m->eventLabel(),
                'dokumen' => $lineage->entry($dok),
                'sj' => $m->shipment_id === null ? null : $lineage->entry(Shipment::query()->withoutGlobalScopes()->find($m->shipment_id)),
            ];
        });
    }

    /** Kode item / nomor lot / nomor GRN → label yang terkait (maks. 100, terbaru dulu). */
    private function daftar(string $kode): Collection
    {
        $q = PackageLabel::query()->with('item:id,code,base_uom_id', 'item.baseUom:id,code', 'warehouse:id,code', 'bin:id,code', 'receipt:id,number')
            ->orderByDesc('id')->limit(100);

        $item = Item::query()->where('code', $kode)->orWhere('barcode', $kode)->value('id');

        if ($item !== null) {
            return $q->where('item_id', $item)->get();
        }

        $lot = Lot::query()->where('lot_no', $kode)->pluck('id');

        if ($lot->isNotEmpty()) {
            return $q->whereIn('lot_id', $lot)->get();
        }

        $grn = GoodsReceipt::query()->where('number', $kode)->value('id');

        return $grn === null ? collect() : $q->where('goods_receipt_id', $grn)->get();
    }
}

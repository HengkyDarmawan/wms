<?php

declare(strict_types=1);

namespace App\Domain\Return\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Return\Actions\ApproveGoodsReturn;
use App\Domain\Return\Actions\CancelGoodsReturn;
use App\Domain\Return\Actions\SortGoodsReturn;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Transfer\Livewire\Concerns\HandlesTransferRules;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 22-retur-transfer §6 — detail RET: baris per asal barang, setujui/
 * tolak, batal, PCK & SJ balik (A-111), GRN retur, pemilahan per baris dengan
 * beberapa bagian (A-113), riwayat approval dan riwayat dokumen. Klien melihat
 * versi portal tanpa tautan back-office.
 */
class ReturnDetail extends Component
{
    use HandlesTransferRules;

    #[Locked]
    public int $returnId;

    #[Locked]
    public bool $portal = false;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    /** @var array<int|string, array<int, array<string, string>>> line_id => bagian */
    public array $pilah = [];

    public function mount(GoodsReturn $goodsReturn): void
    {
        $this->authorize('view', $goodsReturn);

        $this->returnId = (int) $goodsReturn->id;
        $this->portal = request()->routeIs('portal.*');
        $this->siapkanPilah();
    }

    public function render(): View
    {
        $ret = $this->ret();
        $gudang = Warehouse::query()->withoutGlobalScopes()->find($ret->to_warehouse_id);
        $bins = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $ret->to_warehouse_id)
            ->where('bin_status', BinStatus::Active->value)
            ->whereIn('bin_type', [BinType::Storage->value, BinType::Return->value, BinType::Quarantine->value])
            ->orderBy('code')->get(['id', 'code', 'bin_type']);

        return view('livewire.return.return-detail', [
            'ret' => $ret,
            'gudang' => $gudang,
            'lines' => $ret->lines()->with('item:id,code,name,tracking_mode', 'lot', 'serial', 'piece', 'fromBin:id,code,bin_type',
                'targetBin:id,code', 'reason:id,label', 'newPiece:id,piece_no,length', 'splitParent')->orderBy('id')->get(),
            'pck' => $ret->livePickTask(),
            'grn' => $ret->activeReceipt(),
            'binPenyimpanan' => $bins->where('bin_type', BinType::Storage)->values(),
            'binRusak' => $bins->values(),
            'hasilPilah' => ReturnSorting::options(),
            'alasanRusak' => ReasonCode::query()->where('context', ReasonContext::Damage->value)->where('is_active', true)->pluck('label', 'id'),
            'alasanWaste' => ReasonCode::query()->where('context', ReasonContext::Waste->value)->where('is_active', true)->pluck('label', 'id'),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'rute' => $this->portal ? 'portal.returns' : 'returns',
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'return')
                ->where('subject_type', $ret->getMorphClass())->where('subject_id', $ret->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::GoodsReturn, (int) $ret->id),
        ]);
    }

    public function setujui(ApproveGoodsReturn $action): void
    {
        $ret = $this->ret();
        $this->authorize('approve', $ret);

        if ($this->jalankan(fn () => $action->approve($ret, auth()->user()))) {
            $this->dispatch('pesan', teks: $ret->refresh()->status === GoodsReturnStatus::PendingApproval
                ? __('Persetujuan Anda tercatat; RET menunggu lapis berikutnya.')
                : __('RET disetujui.'));
        }
    }

    public function buatPicking(CreatePickTask $action): void
    {
        $ret = $this->ret();
        $this->authorize('createPick', $ret);

        if ($this->jalankan(fn () => $action->forGoodsReturn($ret, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas picking SJ balik dibuat di Gudang Site.'));
        }
    }

    /** GRN retur (receipt.create) dengan jumlah bawaan = yang dikirim; diterima dari layar GRN. */
    public function terima(SaveGoodsReceipt $action): void
    {
        $ret = $this->ret();
        $this->authorize('receive', $ret);

        $grn = null;

        $ok = $this->jalankan(function () use ($action, $ret, &$grn) {
            $grn = $action->handle(null, [
                'receipt_type' => 'return',
                'warehouse_id' => $ret->to_warehouse_id,
                'goods_return_id' => $ret->id,
            ], [], auth()->user());
        });

        if ($ok && $grn !== null) {
            $this->redirectRoute('receipts.show', $grn, navigate: true);
        }
    }

    public function tambahBagian(int $lineId): void
    {
        $this->pilah[$lineId][] = ['sorting' => 'damaged', 'qty' => '', 'target_bin_id' => '', 'reason_code_id' => '', 'offcut_length' => ''];
    }

    public function hapusBagian(int $lineId, int $i): void
    {
        unset($this->pilah[$lineId][$i]);
        $this->pilah[$lineId] = array_values($this->pilah[$lineId] ?? []);
    }

    public function simpanPilah(SortGoodsReturn $action): void
    {
        $ret = $this->ret();
        $this->authorize('sort', $ret);

        if ($this->jalankan(fn () => $action->handle($ret, $this->pilah, null, auth()->user()), 'pilah')) {
            $this->dispatch('pesan', teks: __('Retur dipilah; stok masuk ke bin hasil pilah.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->ret());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveGoodsReturn $action): void
    {
        $ret = $this->ret();
        $this->authorize('approve', $ret);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject($ret, $this->alasanId($this->form['reason'], ReasonContext::Reject), $this->form['notes'] ?: null, auth()->user()));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('RET ditolak.'));
        }
    }

    public function batalkan(CancelGoodsReturn $action): void
    {
        $ret = $this->ret();
        $this->authorize('cancel', $ret);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle($ret, $this->alasanId($this->form['reason'], ReasonContext::Cancel), $this->form['notes'] ?: null, auth()->user()));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('RET dibatalkan.'));
        }
    }

    /** Satu bagian bawaan per baris: seluruh jumlah diterima, layak, di bin saran put-away. */
    private function siapkanPilah(): void
    {
        $ret = $this->ret();

        if ($ret->status !== GoodsReturnStatus::Received) {
            return;
        }

        $gudang = Warehouse::query()->withoutGlobalScopes()->find($ret->to_warehouse_id);

        foreach ($ret->requestedLines()->with('item.category')->where('qty_received', '>', 0)->get() as $l) {
            /** @var GoodsReturnLine $l */
            $saran = $gudang !== null ? app(PutawaySuggester::class)->suggest($l->item, $gudang, (float) $l->qty_received) : null;

            $this->pilah[$l->id] = [[
                'sorting' => 'good',
                'qty' => (string) (float) $l->qty_received,
                'target_bin_id' => (string) ($saran?->id ?? ''),
                'reason_code_id' => '',
                'offcut_length' => '',
            ]];
        }
    }

    private function ret(): GoodsReturn
    {
        return GoodsReturn::query()
            ->with('project:id,code,name', 'fromWarehouse:id,code', 'toWarehouse:id,code,name', 'requester:id,name,client_id',
                'approver:id,name', 'sorter:id,name', 'rejectReason:id,label', 'cancelReason:id,label',
                'originShipment:id,number', 'returnShipment:id,number,status')
            ->findOrFail($this->returnId);
    }
}

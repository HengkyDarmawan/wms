<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Label\Actions\CreatePackageLabels;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CancelGoodsReceipt;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\RecordQcResult;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Support\CrossDockCandidates;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 19-receipt-putaway §6.3 — detail GRN: terima, QC per baris, selesai,
 * batal, dan tautan ke PUT/RTV yang lahir darinya.
 */
class ReceiptDetail extends Component
{
    use HandlesReceiptRules;

    #[Locked]
    public int $receiptId;

    /** '', 'batal', 'qc', 'selesai' */
    public string $dialog = '';

    /**
     * A-296: rencana label induk saat Selesaikan — per baris GRN: jumlah dus
     * dan isi per dus (satuan dasar). 0 dus = baris tidak dilabeli.
     *
     * @var array<int|string, array<string, string>>
     */
    public array $labelRencana = [];

    #[Locked]
    public ?int $qcLineId = null;

    /** @var array<string, string> */
    public array $form = [
        'reason' => '',
        'notes' => '',
        'qc_result' => 'passed',
        'qc_reason' => '',
        'qc_note' => '',
    ];

    public function mount(GoodsReceipt $goodsReceipt): void
    {
        $this->authorize('view', $goodsReceipt);

        $this->receiptId = (int) $goodsReceipt->id;
    }

    public function render(CrossDockCandidates $crossDock): View
    {
        $grn = $this->grn();
        $lines = $grn->lines()->with('item:id,code,name,tracking_mode,base_uom_id', 'item.baseUom:id,code', 'item.activeConversions.uom:id,code', 'uom:id,code', 'receivingBin:id,code,bin_type', 'damagedBin:id,code', 'damageReason:id,label', 'lot', 'serial', 'piece', 'qcReason')->orderBy('id')->get();
        $vendor = $grn->receipt_type->value === 'vendor';

        return view('livewire.receipt.receipt-detail', [
            'grn' => $grn,
            'lines' => $lines,
            'putaways' => $grn->putawayTasks()->withoutGlobalScopes()->orderBy('id')->get(),
            'returns' => $grn->vendorReturns()->withoutGlobalScopes()->orderBy('id')->get(),
            'crossDock' => $grn->status->value === 'draft' ? collect() : $lines->pluck('item_id')->unique()
                ->mapWithKeys(fn ($id) => [$id => $crossDock->waitingFor((int) $id, (int) $grn->warehouse_id)])
                ->filter(fn ($c) => $c->isNotEmpty()),
            'qcOptions' => QcResult::options(),
            // A-296: tombol Selesaikan membuka dialog label bila ada baris yang bisa dilabeli.
            'bisaLabel' => $vendor && $lines->contains(fn (GoodsReceiptLine $l) => app(CreatePackageLabels::class)->labelable($l)),
            'adaLabel' => $vendor && $grn->status->value === 'completed',
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'bisaReplan' => $grn->status->value === 'completed'
                && $lines->contains(fn (GoodsReceiptLine $l) => $l->isPutawayEligible()
                    && ! $l->putawayLines()->whereHas('task', fn ($q) => $q->where('status', '!=', 'cancelled'))->exists()),
            'riwayat' => $this->riwayat($grn),
            // A-287: kolom Dikirim vendor / Rusak / Kurang hanya untuk GRN vendor.
            'kondisi' => $vendor && $lines->contains(fn (GoodsReceiptLine $l) => $l->qty_vendor !== null || (float) $l->qty_damaged > 0),
            // A-290: tombol Retur ke vendor hanya bila masih ada yang bisa diretur.
            'bisaRetur' => $vendor && in_array($grn->status->value, ['received', 'completed'], true)
                && $lines->contains(fn (GoodsReceiptLine $l) => $l->damagedReturnable() > 0
                    || ($l->receivingBinIsQuarantine() && in_array($l->qc_result, [QcResult::Rejected, QcResult::Quarantined], true))),
        ]);
    }

    public function terima(ReceiveGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('receive', $grn);

        if ($this->jalankan(fn () => $action->handle($grn, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Barang diterima dan tercatat di kartu stok.'));
        }
    }

    /**
     * A-296: GRN vendor dengan barang yang bisa dilabeli membuka dialog
     * "berapa dus" dulu; bawaan dari satuan diketik atau kemasan item.
     */
    public function mintaSelesai(CreatePackageLabels $labels): void
    {
        $grn = $this->grn();
        $this->authorize('complete', $grn);

        $this->labelRencana = $grn->lines()->with('item.activeConversions')->orderBy('id')->get()
            ->filter(fn (GoodsReceiptLine $l) => $labels->labelable($l))
            ->mapWithKeys(function (GoodsReceiptLine $l) use ($labels) {
                $d = $labels->defaults($l);

                return [$l->id => ['packages' => (string) $d['packages'], 'per_package' => PackageLabelLedger::angka($d['per_package']), 'uom_id' => (string) ($d['uom_id'] ?? '')]];
            })->all();

        if ($this->labelRencana === []) {
            $this->selesaikan(app(CompleteGoodsReceipt::class));

            return;
        }

        $this->dialog = 'selesai';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function selesaikan(CompleteGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('complete', $grn);

        // Tanpa dialog (pemanggil lama, GRN non-vendor) = tanpa label.
        $rencana = $this->dialog === 'selesai' ? $this->labelRencana : null;

        if ($this->jalankan(fn () => $action->handle($grn, auth()->user(), $rencana), 'labelRencana')) {
            $jumlah = $rencana === null ? 0 : array_sum(array_map(fn ($r) => (int) $r['packages'], $rencana));
            $this->dialog = '';
            $this->labelRencana = [];
            $this->dispatch('pesan', teks: $jumlah > 0
                ? __('Penerimaan selesai; :n label kemasan dibuat dan tugas put-away dibuat.', ['n' => $jumlah])
                : __('Penerimaan selesai; tugas put-away dibuat.'));
            $this->dispatch('label-dibuat');
        }
    }

    public function buatUlangPutaway(CompleteGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('complete', $grn);

        if ($this->jalankan(fn () => $action->replan($grn, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas put-away dibuat ulang.'));
        }
    }

    public function mintaDialog(string $dialog, ?int $lineId = null): void
    {
        $grn = $this->grn();
        $this->authorize($dialog === 'qc' ? 'qc' : 'cancel', $grn);

        $this->dialog = $dialog;
        $this->qcLineId = $dialog === 'qc' ? $lineId : null;
        $this->form = ['reason' => '', 'notes' => '', 'qc_result' => 'passed', 'qc_reason' => '', 'qc_note' => ''];
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->qcLineId = null;
        $this->labelRencana = [];
        $this->resetValidation();
    }

    public function batalkan(CancelGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('cancel', $grn);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $grn,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Penerimaan dibatalkan.'));
        }
    }

    public function simpanQc(RecordQcResult $action): void
    {
        $grn = $this->grn();
        $this->authorize('qc', $grn);

        $line = $grn->lines()->findOrFail((int) $this->qcLineId);
        $hasil = QcResult::tryFrom($this->form['qc_result']);

        if ($hasil === null) {
            $this->addError('form.qc_result', __('Hasil QC wajib dipilih.'));

            return;
        }

        $ok = $this->jalankan(fn () => $action->handle(
            $line,
            $hasil,
            $this->alasanId($this->form['qc_reason'], ReasonContext::Reject),
            $this->form['qc_note'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Hasil QC tersimpan.'));
        }
    }

    private function grn(): GoodsReceipt
    {
        return GoodsReceipt::query()
            ->with('warehouse:id,code,name', 'vendor:id,name', 'shipment:id,number', 'cancelReason:id,label', 'receiver:id,name')
            ->findOrFail($this->receiptId);
    }

    /** @return Collection<int, Activity> */
    private function riwayat(GoodsReceipt $grn): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where('log_name', 'receipt')
            ->where('subject_type', $grn->getMorphClass())
            ->where('subject_id', $grn->id)
            ->latest('id')
            ->limit(30)
            ->get();
    }
}

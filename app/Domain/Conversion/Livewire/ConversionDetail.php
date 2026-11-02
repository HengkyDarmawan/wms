<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Conversion\Actions\ApproveConversion;
use App\Domain\Conversion\Actions\CancelConversion;
use App\Domain\Conversion\Actions\CompleteConversion;
use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Actions\SubmitConversion;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Livewire\Concerns\HandlesConversionRules;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionApprovalRoute;
use App\Domain\Conversion\Support\ConversionPlanner;
use App\Domain\Master\Enums\ReasonContext;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 24-konversi-waste §6 — detail CNV: selesaikan (tanpa aturan) atau
 * ajukan ke approval (ada aturan), setujui/tolak, batal, buat CNV pembalik
 * (Alasan `*`), silsilah hasil, riwayat approval dan riwayat.
 */
class ConversionDetail extends Component
{
    use HandlesConversionRules;

    #[Locked]
    public int $conversionId;

    /** '', 'tolak', 'batal', 'balik' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(Conversion $conversion): void
    {
        $this->authorize('view', $conversion);

        $this->conversionId = (int) $conversion->id;
    }

    public function render(): View
    {
        $cnv = $this->cnv();

        $inputs = $cnv->inputs()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'bin:id,code', 'lot:id,lot_no', 'piece:id,piece_no,length')->orderBy('id')->get();
        $outputs = $cnv->outputs()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'bin:id,code', 'lot:id,lot_no', 'newPiece:id,piece_no,length,parent_piece_id', 'parentInput.piece:id,piece_no', 'parentInput.item:id,code', 'reason:id,label')->orderBy('id')->get();

        return view('livewire.conversion.conversion-detail', [
            'cnv' => $cnv,
            'kalimat' => ConversionPlanner::kalimatDokumen($inputs, $outputs),
            'uom' => $inputs->first()?->item?->baseUom?->code,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'menunggu' => $cnv->isAwaitingApproval(),
            'aturan' => $cnv->status === ConversionStatus::Draft ? app(ConversionApprovalRoute::class)->rule($cnv)?->name : null,
            'pembalik' => $cnv->reversals()->orderBy('id')->get(['id', 'number', 'status']),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'conversion')
                ->where('subject_type', $cnv->getMorphClass())->where('subject_id', $cnv->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::Conversion, (int) $cnv->id),
        ]);
    }

    public function ajukan(SubmitConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('submit', $cnv);

        if ($this->jalankan(fn () => $action->handle($cnv, auth()->user()))) {
            $this->dispatch('pesan', teks: $cnv->refresh()->status === ConversionStatus::Completed
                ? __('CNV disetujui otomatis dan selesai.')
                : __('CNV diajukan ke approval.'));
        }
    }

    public function selesaikan(CompleteConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('complete', $cnv);

        if ($this->jalankan(fn () => $action->handle($cnv, auth()->user()))) {
            $this->dispatch('pesan', teks: __('CNV selesai; stok diperbarui.'));
        }
    }

    public function setujui(ApproveConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('approve', $cnv);

        if ($this->jalankan(fn () => $action->approve($cnv, auth()->user()))) {
            $this->dispatch('pesan', teks: $cnv->refresh()->status === ConversionStatus::Completed
                ? __('CNV disetujui dan selesai; stok diperbarui.')
                : __('Persetujuan Anda tercatat; menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize(match ($dialog) {
            'tolak' => 'approve',
            'balik' => 'reverse',
            default => 'cancel',
        }, $this->cnv());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('approve', $cnv);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $cnv,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('CNV ditolak; kembali Draf.'));
        }
    }

    public function batalkan(CancelConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('cancel', $cnv);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $cnv,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('CNV dibatalkan.'));
        }
    }

    public function buatPembalik(CreateConversion $action): void
    {
        $cnv = $this->cnv();
        $this->authorize('reverse', $cnv);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $pembalik = null;

        $ok = $this->jalankan(function () use ($action, $cnv, &$pembalik) {
            $pembalik = $action->reverse(
                $cnv,
                $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
                $this->form['notes'] ?: null,
                auth()->user(),
            );
        });

        if ($ok && $pembalik !== null) {
            $this->redirectRoute('conversions.show', $pembalik, navigate: true);
        }
    }

    private function cnv(): Conversion
    {
        return Conversion::query()
            ->with('project:id,code,name,status,pic_user_id', 'warehouse:id,code,name', 'preparer:id,name', 'submitter:id,name', 'completer:id,name',
                'approver:id,name', 'reason:id,label', 'rejectReason:id,label', 'cancelReason:id,label', 'reversalOf:id,number')
            ->findOrFail($this->conversionId);
    }
}

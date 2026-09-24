<?php

declare(strict_types=1);

namespace App\Domain\Issue\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Issue\Actions\ApproveMaterialIssue;
use App\Domain\Issue\Actions\CancelMaterialIssue;
use App\Domain\Issue\Actions\ConfirmMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Livewire\Concerns\HandlesIssueRules;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Enums\ReasonContext;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 23-pemakaian §6 — detail ISU: konfirmasi (keluar dari stok), batal
 * (draf), buat ISU pembalik (baris terpilih + Alasan `*`), ajukan pembalik,
 * setujui/tolak (pemegang tugas approval), riwayat approval dan riwayat.
 */
class IssueDetail extends Component
{
    use HandlesIssueRules;

    #[Locked]
    public int $issueId;

    /** '', 'tolak', 'batal', 'balik' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['reason' => '', 'notes' => ''];

    /** @var array<int|string, bool> baris ISU asal yang dibalik */
    public array $balik = [];

    public function mount(MaterialIssue $materialIssue): void
    {
        $this->authorize('view', $materialIssue);

        $this->issueId = (int) $materialIssue->id;
    }

    public function render(): View
    {
        $isu = $this->isu();
        $sudahDibalik = $isu->reversalOf === null ? $isu->reversedLineIds() : [];

        return view('livewire.issue.issue-detail', [
            'isu' => $isu,
            'lines' => $isu->lines()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'bin:id,code', 'lot', 'serial', 'piece', 'reversalOfLine')->orderBy('id')->get(),
            'sudahDibalik' => $sudahDibalik,
            'menunggu' => $isu->isAwaitingApproval(),
            'pembalik' => $isu->reversals()->orderBy('id')->get(['id', 'number', 'status']),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'issue')
                ->where('subject_type', $isu->getMorphClass())->where('subject_id', $isu->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => $isu->isReversal()
                ? app(ApprovalHistory::class)->for(ApprovalDocumentType::MaterialIssue, (int) $isu->id)
                : collect(),
        ]);
    }

    public function konfirmasi(ConfirmMaterialIssue $action): void
    {
        $isu = $this->isu();
        $this->authorize('confirm', $isu);

        if ($this->jalankan(fn () => $action->handle($isu, auth()->user()))) {
            $isu->refresh();
            $this->dispatch('pesan', teks: match (true) {
                $isu->status === MaterialIssueStatus::Confirmed => __('Pemakaian dikonfirmasi; barang keluar dari stok Gudang Site.'),
                default => __('ISU pembalik diajukan ke approval.'),
            });
        }
    }

    public function setujui(ApproveMaterialIssue $action): void
    {
        $isu = $this->isu();
        $this->authorize('approve', $isu);

        if ($this->jalankan(fn () => $action->approve($isu, auth()->user()))) {
            $this->dispatch('pesan', teks: $isu->refresh()->status === MaterialIssueStatus::Confirmed
                ? __('ISU pembalik disetujui; pemakaian asal dibalik ke stok.')
                : __('Persetujuan Anda tercatat; menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $isu = $this->isu();
        $this->authorize(match ($dialog) {
            'tolak' => 'approve',
            'balik' => 'reverse',
            default => 'cancel',
        }, $isu);

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->balik = [];

        if ($dialog === 'balik') {
            $sudah = $isu->reversedLineIds();

            foreach ($isu->lines()->whereNotNull('movement_id')->get() as $l) {
                if (! in_array((int) $l->id, $sudah, true)) {
                    $this->balik[(int) $l->id] = true;
                }
            }
        }

        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveMaterialIssue $action): void
    {
        $isu = $this->isu();
        $this->authorize('approve', $isu);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $isu,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('ISU pembalik ditolak; tetap Draf.'));
        }
    }

    public function batalkan(CancelMaterialIssue $action): void
    {
        $isu = $this->isu();
        $this->authorize('cancel', $isu);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $isu,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('ISU dibatalkan.'));
        }
    }

    public function buatPembalik(CreateMaterialIssue $action): void
    {
        $isu = $this->isu();
        $this->authorize('reverse', $isu);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $baris = array_keys(array_filter($this->balik));

        if ($baris === []) {
            $this->addError('form.lines', __('Pilih minimal satu baris yang dibalik.'));

            return;
        }

        $pembalik = null;

        $ok = $this->jalankan(function () use ($action, $isu, $baris, &$pembalik) {
            $pembalik = $action->reverse(
                $isu,
                $baris,
                $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
                $this->form['notes'] ?: null,
                auth()->user(),
            );
        });

        if ($ok && $pembalik !== null) {
            $this->redirectRoute('issues.show', $pembalik, navigate: true);
        }
    }

    private function isu(): MaterialIssue
    {
        return MaterialIssue::query()
            ->with('project:id,code,name,status,pic_user_id', 'warehouse:id,code,name', 'issuer:id,name', 'confirmer:id,name', 'submitter:id,name',
                'approver:id,name', 'reason:id,label', 'rejectReason:id,label', 'cancelReason:id,label', 'reversalOf:id,number')
            ->findOrFail($this->issueId);
    }
}

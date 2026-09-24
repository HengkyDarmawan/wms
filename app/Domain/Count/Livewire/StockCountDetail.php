<?php

declare(strict_types=1);

namespace App\Domain\Count\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Count\Actions\ApproveStockCount;
use App\Domain\Count\Actions\AssignCounter;
use App\Domain\Count\Actions\CancelStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCountRootCause;
use App\Domain\Count\Actions\StartStockCount;
use App\Domain\Count\Enums\RootCauseCategory;
use App\Domain\Count\Livewire\Concerns\HandlesCountRules;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Support\CountVisibility;
use App\Domain\Master\Enums\ReasonContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 21-opname-penyesuaian §6.3 — detail sesi: mulai, penugasan dan
 * penghitung ulang, tampilan rekonsiliasi (angka disembunyikan bagi yang
 * masih menghitung, A-103), akar masalah, ajukan ke approval, setujui/tolak,
 * batal, laporan PDF, ADJ yang lahir, riwayat approval dan riwayat sesi.
 */
class StockCountDetail extends Component
{
    use HandlesCountRules;

    #[Locked]
    public int $countId;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    /** @var array<int|string, array<string, string>> line_id => [root_cause, note] */
    public array $akar = [];

    /** @var array<int|string, string> assignment_id => user_id */
    public array $penghitung = [];

    public function mount(StockCount $stockCount): void
    {
        $this->authorize('view', $stockCount);

        $this->countId = (int) $stockCount->id;
    }

    public function render(): View
    {
        $count = $this->sesi();
        $bolehLihat = app(CountVisibility::class)->canSeeNumbers($count, auth()->user());

        // Hitung buta: baris berselisih pun tidak dikirim ke peramban penghitung (A-103).
        if ($bolehLihat) {
            $this->isiAkar();
        }

        return view('livewire.count.count-detail', [
            'count' => $count,
            'assignments' => CountAssignment::query()->with('bin:id,code,count_flag', 'counter:id,name')
                ->where('stock_count_id', $count->id)->orderBy('round')->orderBy('id')->get(),
            'lines' => $bolehLihat ? $this->baris() : collect(),
            'bolehLihat' => $bolehLihat,
            'ringkasan' => $this->ringkasan($count),
            'adjustments' => $count->adjustments()->with('warehouse:id,code')->orderBy('id')->get(),
            'akarOptions' => RootCauseCategory::options(),
            'counters' => $this->penghitungLayak($count),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'bisaUbahAkar' => $this->bisaUbahAkar($count),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'count')
                ->where('subject_type', $count->getMorphClass())->where('subject_id', $count->id)
                ->latest('id')->limit(40)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::StockCount, (int) $count->id),
        ]);
    }

    public function mulai(StartStockCount $action): void
    {
        $count = $this->sesi();
        $this->authorize('start', $count);

        if ($this->jalankan(fn () => $action->handle($count, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Sesi dimulai; penugasan hitung dibagikan ke tim.'));
        }
    }

    public function tugaskan(int $assignmentId, AssignCounter $action): void
    {
        $count = $this->sesi();
        $this->authorize('assign', $count);

        $tugas = CountAssignment::query()->where('stock_count_id', $count->id)->findOrFail($assignmentId);
        $userId = (int) ($this->penghitung[$assignmentId] ?? 0);

        if ($this->jalankan(fn () => $action->handle($tugas, $userId, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Penghitung ditetapkan.'));
        }
    }

    public function simpanAkar(int $lineId, RecordCountRootCause $action): void
    {
        $count = $this->sesi();
        abort_unless(auth()->user()->hasPermission('count.reconcile'), 403);

        $baris = CountLine::query()->where('stock_count_id', $count->id)->findOrFail($lineId);
        $isi = $this->akar[$lineId] ?? [];

        if ($this->jalankan(fn () => $action->handle($baris, $isi['root_cause'] ?? null, $isi['note'] ?? null, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Akar masalah disimpan.'));
        }
    }

    public function rekonsiliasi(ReconcileStockCount $action): void
    {
        $count = $this->sesi();
        $this->authorize('reconcile', $count);

        if ($this->jalankan(fn () => $action->handle($count, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Hasil opname diajukan ke approval.'));
        }
    }

    public function setujui(ApproveStockCount $action): void
    {
        $count = $this->sesi();
        $this->authorize('approve', $count);

        if ($this->jalankan(fn () => $action->approve($count, auth()->user()))) {
            $this->dispatch('pesan', teks: $count->refresh()->status->value === 'closed'
                ? __('Sesi disetujui dan ditutup; selisih diposting.')
                : __('Persetujuan Anda tercatat; sesi menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->sesi());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveStockCount $action): void
    {
        $count = $this->sesi();
        $this->authorize('approve', $count);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $count,
            $this->alasanId($this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Hasil opname ditolak; sesi kembali ke rekonsiliasi.'));
        }
    }

    public function batalkan(CancelStockCount $action): void
    {
        $count = $this->sesi();
        $this->authorize('cancel', $count);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $count,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Sesi dibatalkan.'));
        }
    }

    private function sesi(): StockCount
    {
        return StockCount::query()
            ->with('warehouses:id,code,name', 'creator:id,name', 'submitter:id,name', 'approver:id,name', 'rejectReason:id,label', 'cancelReason:id,label')
            ->findOrFail($this->countId);
    }

    /** @return Collection<int, CountLine> */
    private function baris(): Collection
    {
        return CountLine::query()->with('bin:id,code', 'item:id,code,name', 'lot', 'serial', 'piece')
            ->where('stock_count_id', $this->countId)
            ->orderByRaw("case variance_class when 'major' then 0 when 'moderate' then 1 when 'minor' then 2 else 3 end")
            ->orderBy('bin_id')->orderBy('id')->get();
    }

    private function isiAkar(): void
    {
        foreach (CountLine::query()->where('stock_count_id', $this->countId)->whereNotNull('variance_class')->get() as $l) {
            $this->akar[$l->id] ??= ['root_cause' => (string) ($l->root_cause?->value ?? ''), 'note' => (string) ($l->note ?? '')];
        }
    }

    private function bisaUbahAkar(StockCount $count): bool
    {
        return auth()->user()->hasPermission('count.reconcile')
            && ($count->status->isCounting()
                || ($count->status->value === 'reconciling'
                    && app(\App\Domain\Approval\Support\ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::StockCount, (int) $count->id) === null));
    }

    /** @return array<string, int> */
    private function ringkasan(StockCount $count): array
    {
        $tugas = CountAssignment::query()->where('stock_count_id', $count->id);

        return [
            'bin' => (clone $tugas)->where('round', 1)->count(),
            'selesai' => (clone $tugas)->where('status', 'done')->count(),
            'tugas' => (clone $tugas)->count(),
            'ulang' => (clone $tugas)->where('round', 2)->count(),
        ];
    }

    /** @return Collection<int, User> */
    private function penghitungLayak(StockCount $count): Collection
    {
        if (! auth()->user()->hasPermission('count.assign') || ! $count->status->isCounting()) {
            return collect();
        }

        return User::query()->active()->internal()->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('count.record')
                && collect($count->warehouseIds())->contains(fn (int $g) => $u->canAccessWarehouse($g)))
            ->values();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Actions\CancelRequestLine;
use App\Domain\Request\Actions\CloseRequestShort;
use App\Domain\Request\Actions\ReviewRequest;
use App\Domain\Request\Actions\SplitRequestLine;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Livewire\Concerns\HandlesRequestRules;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 14-request §6 — detail REQ dan seluruh aksinya.
 *
 * Satu layar, bukan beberapa: peninjau perlu melihat baris, riwayat, dan
 * tombol keputusan sekaligus. Setiap aksi memeriksa izinnya sendiri di dalam
 * metode, bukan hanya saat komponen dipasang, karena status dokumen berubah
 * di antara dua permintaan.
 */
class RequestDetail extends Component
{
    use HandlesRequestRules;

    #[Locked]
    public int $requestId;

    /** Dialog yang sedang terbuka: '', 'tolak', 'batal', 'tutup', 'pecah', 'petakan'. */
    public string $dialog = '';

    #[Locked]
    public ?int $lineId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    /** @var array<string, mixed> */
    public array $form = [
        'item_id' => '',
        'item_code' => '',
        'item_name' => '',
        'base_uom_id' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $splits = [];

    public function mount(MaterialRequest $request): void
    {
        $this->authorize('view', $request);

        $this->requestId = (int) $request->id;
    }

    public function render(): View
    {
        $request = $this->request();

        return view('livewire.request.request-detail', [
            'req' => $request,
            'lines' => $request->lines()
                ->with('item:id,code,name', 'sourceWarehouse:id,code,name', 'splitParent:id')
                ->orderBy('id')
                ->get(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'sources' => FulfillmentSource::options(),
            'items' => Item::query()
                ->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value])
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'uoms' => Uom::query()->orderBy('code')->get(['id', 'code', 'name']),
            'alasan' => $this->pilihanAlasan($this->dialog === 'tolak' ? ReasonContext::Reject : ReasonContext::Cancel),
            'riwayat' => $this->riwayat($request),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::MaterialRequest, (int) $request->id),
            // BR-REQ-05: TRF backorder untuk baris bersumber transfer (A-106).
            'transferBackorder' => \App\Domain\Transfer\Models\Transfer::withoutGlobalScopes()
                ->with('fromWarehouse:id,code', 'toWarehouse:id,code')
                ->where('source_type', 'material_request')->where('source_id', $request->id)
                ->orderBy('id')->get(),
            'supplements' => $request->supplements()->orderBy('id')->get(['id', 'number', 'status']),
        ]);
    }

    // ---------------------------------------------------------------- tinjau

    /** Menetapkan gudang sumber, cara pemenuhan, dan tanggal janji satu baris. */
    public function simpanSumber(int $id, ?string $warehouseId, ?string $source, ?string $promised, ReviewRequest $action): void
    {
        $line = $this->line($id);

        $this->authorize('review', $line->request);

        if ($this->jalankan(fn () => $action->setSource(
            $line,
            $warehouseId === null || $warehouseId === '' ? null : (int) $warehouseId,
            $source,
            $promised === '' ? null : $promised,
            auth()->user(),
        ))) {
            $this->dispatch('pesan', teks: __('Baris diperbarui.'));
        }
    }

    public function mintaPetakan(int $id): void
    {
        $line = $this->line($id);

        $this->authorize('review', $line->request);

        $this->dialog = 'petakan';
        $this->lineId = $line->id;
        $this->form = ['item_id' => '', 'item_code' => '', 'item_name' => '', 'base_uom_id' => ''];
        $this->ruleError = '';
    }

    public function petakan(ReviewRequest $action): void
    {
        $line = $this->line($this->lineId);

        $this->authorize('review', $line->request);

        $itemId = (string) ($this->form['item_id'] ?? '');

        $berhasil = $this->jalankan(function () use ($action, $line, $itemId) {
            if ($itemId !== '') {
                $action->mapLine($line, (int) $itemId, auth()->user());

                return;
            }

            // BR-REQ-03: bila tidak ada item yang cocok, staf membuat item
            // sementara supaya REQ tidak tertahan menunggu Admin.
            $action->mapToProvisionalItem(
                $line,
                (string) ($this->form['item_code'] ?? ''),
                (string) ($this->form['item_name'] ?? ''),
                (int) ($this->form['base_uom_id'] ?? 0),
                auth()->user(),
            );
        });

        if (! $berhasil) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Baris dipetakan ke item.'));
    }

    public function kirimKeApproval(ReviewRequest $action): void
    {
        $request = $this->request();

        $this->authorize('review', $request);

        if ($this->jalankan(fn () => $action->submitToApproval($request, auth()->user()))) {
            $this->dispatch('pesan', teks: $request->refresh()->status->value === 'approved'
                ? __('Tidak ada aturan approval: REQ langsung disetujui dan stok direservasi.')
                : __('REQ dikirim ke persetujuan.'));
        }
    }

    // -------------------------------------------------------------- approval

    public function setujui(ApproveRequest $action): void
    {
        $request = $this->request();

        $this->authorize('approve', $request);

        if ($this->jalankan(fn () => $action->handle($request, auth()->user()))) {
            $this->dispatch('pesan', teks: $request->refresh()->status->value === 'approved'
                ? __('REQ disetujui; reservasi dibuat.')
                : __('Persetujuan Anda tercatat; REQ menunggu lapis berikutnya.'));
        }
    }

    // ---------------------------------------------------------------- dialog

    public function mintaDialog(string $dialog): void
    {
        $request = $this->request();

        $izin = match ($dialog) {
            'tolak' => $request->status->value === 'pending_approval' ? 'approve' : 'review',
            'batal' => 'cancel',
            'tutup' => 'closeShort',
            default => 'view',
        };

        $this->authorize($izin, $request);

        $this->dialog = $dialog;
        $this->reasonCode = '';
        $this->reasonNotes = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->lineId = null;
        $this->splits = [];
        $this->ruleError = '';
        $this->resetValidation();
    }

    /** Tolak, batal, dan tutup-dengan-sisa: tiga aksi, satu bentuk dialog. */
    public function jalankanDialog(
        ReviewRequest $tinjau,
        ApproveRequest $setujui,
        CancelRequest $batal,
        CloseRequestShort $tutup,
    ): void {
        $request = $this->request();

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        $alasanId = $this->alasanId();
        $catatan = $this->reasonNotes ?: null;

        $berhasil = match ($this->dialog) {
            'tolak' => $this->tolak($request, $alasanId, $catatan, $tinjau, $setujui),
            'batal' => $this->authorizeThen('cancel', $request, fn () => $this->jalankan(
                fn () => $batal->handle($request, $alasanId, $catatan, auth()->user()),
            )),
            'tutup' => $this->authorizeThen('closeShort', $request, fn () => $this->jalankan(
                fn () => $tutup->handle($request, $alasanId, $catatan, auth()->user()),
            )),
            default => false,
        };

        if (! $berhasil) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Status REQ diperbarui.'));
    }

    // ------------------------------------------------------------ pecah baris

    public function mintaPecah(int $id): void
    {
        $line = $this->line($id);

        $this->authorize('splitLine', $line->request);

        $this->dialog = 'pecah';
        $this->lineId = $line->id;
        $this->splits = [['warehouse_id' => '', 'qty_base' => '']];
        $this->ruleError = '';
    }

    public function tambahPecahan(): void
    {
        $this->splits[] = ['warehouse_id' => '', 'qty_base' => ''];
    }

    public function pecah(SplitRequestLine $action): void
    {
        $line = $this->line($this->lineId);

        $this->authorize('splitLine', $line->request);

        if (! $this->jalankan(fn () => $action->handle($line, $this->splits, auth()->user()))) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Baris dipecah antar gudang.'));
    }

    // ------------------------------------------- permintaan pembatalan klien

    public function konfirmasiBatalBaris(int $id, CancelRequestLine $action): void
    {
        $line = $this->line($id);

        $this->authorize('confirmCancel', $line->request);

        if ($this->jalankan(fn () => $action->confirm($line, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Pembatalan baris dikonfirmasi.'));
        }
    }

    public function tolakBatalBaris(int $id, CancelRequestLine $action): void
    {
        $line = $this->line($id);

        $this->authorize('confirmCancel', $line->request);

        if ($this->jalankan(fn () => $action->refuse($line, null, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Permintaan pembatalan ditolak.'));
        }
    }

    // ----------------------------------------------------------------- bantu

    private function tolak(
        MaterialRequest $request,
        ?int $alasanId,
        ?string $catatan,
        ReviewRequest $tinjau,
        ApproveRequest $setujui,
    ): bool {
        // Penolakan punya dua pintu: saat tinjau dan saat approval. Yang
        // berlaku ditentukan status dokumen, bukan tombol yang ditekan.
        if ($request->status->value === 'pending_approval') {
            $this->authorize('approve', $request);

            return $this->jalankan(fn () => $setujui->reject($request, $alasanId, $catatan, auth()->user()));
        }

        $this->authorize('review', $request);

        return $this->jalankan(fn () => $tinjau->reject($request, $alasanId, $catatan, auth()->user()));
    }

    private function authorizeThen(string $ability, MaterialRequest $request, callable $aksi): bool
    {
        $this->authorize($ability, $request);

        return $aksi();
    }

    /**
     * Dialog memakai kode alasan, sedangkan dokumen menyimpan idnya.
     *
     * Penerjemahan terjadi di sini, bukan di layar: yang dilihat pengguna
     * adalah kode yang bermakna baginya, yang disimpan adalah baris master
     * yang bisa berubah labelnya tanpa mengubah dokumen lama.
     */
    private function alasanId(): ?int
    {
        if ($this->reasonCode === '') {
            return null;
        }

        $id = ReasonCode::query()->where('code', $this->reasonCode)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function request(): MaterialRequest
    {
        return MaterialRequest::query()
            ->with('project:id,code,name,client_id', 'requester:id,name', 'reviewer:id,name', 'approver:id,name', 'parent:id,number')
            ->findOrFail($this->requestId);
    }

    private function line(?int $id): MaterialRequestLine
    {
        return MaterialRequestLine::query()
            ->with('request')
            ->where('material_request_id', $this->requestId)
            ->findOrFail($id);
    }

    /** @return \Illuminate\Support\Collection<int, Activity> */
    private function riwayat(MaterialRequest $request): \Illuminate\Support\Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where('log_name', 'request')
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->latest('id')
            ->limit(50)
            ->get();
    }
}

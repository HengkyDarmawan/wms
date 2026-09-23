<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Actions\AddRequestLines;
use App\Domain\Request\Actions\CancelRequestLine;
use App\Domain\Request\Actions\RespondSubstitution;
use App\Domain\Request\Livewire\Concerns\HandlesRequestRules;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 14-request §6 — detail REQ di portal klien.
 *
 * Tiga hal yang hanya bisa dilakukan klien: menambah baris (BR-REQ-12),
 * menanggapi penggantian item (BR-REQ-13), dan meminta pembatalan baris
 * (BR-REQ-15). Ketiganya menuntut alasan bila bersifat menolak, dan semuanya
 * tercatat di timeline REQ.
 */
class PortalRequestDetail extends Component
{
    use HandlesRequestRules;

    #[Locked]
    public int $requestId;

    /** '', 'tambah', 'tolak-ganti', 'minta-batal' */
    public string $dialog = '';

    #[Locked]
    public ?int $lineId = null;

    public string $reasonCode = '';

    /**
     * Baris yang sedang diketik di dialog tambah — bukan baris REQ yang sudah
     * ada. Sengaja bernama lain agar tidak bertabrakan dengan `lines` yang
     * dikirim `render()` ke Blade.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $barisBaru = [];

    public function mount(MaterialRequest $request): void
    {
        $this->authorize('view', $request);

        $this->requestId = (int) $request->id;
    }

    public function render(): View
    {
        $request = $this->request();

        return view('livewire.request.portal-request-detail', [
            'req' => $request,
            'lines' => $request->lines()->with('item:id,code,name')->orderBy('id')->get(),
            'items' => Item::query()
                ->where('status', ItemStatus::Active->value)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
            'supplements' => $request->supplements()->orderBy('id')->get(['id', 'number', 'status']),
        ]);
    }

    // ----------------------------------------------------------- tambah baris

    public function mintaTambah(): void
    {
        $request = $this->request();

        $this->authorize('addLines', $request);

        $this->dialog = 'tambah';
        $this->barisBaru = [['item_id' => '', 'non_catalog_text' => '', 'qty_base' => '']];
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function tambahBaris(): void
    {
        $this->barisBaru[] = ['item_id' => '', 'non_catalog_text' => '', 'qty_base' => ''];
    }

    public function simpanTambahan(AddRequestLines $action): void
    {
        $request = $this->request();

        $this->authorize('addLines', $request);

        $tujuan = null;

        $berhasil = $this->jalankan(function () use ($action, $request, &$tujuan) {
            $tujuan = $action->handle($request, $this->barisBaru, auth()->user());
        });

        if (! $berhasil || $tujuan === null) {
            return;
        }

        $this->tutupDialog();

        // REQ yang sudah disetujui melahirkan REQ Tambahan; klien dibawa ke
        // dokumen barunya supaya tahu tambahannya punya nomor sendiri.
        if ((int) $tujuan->id !== $this->requestId) {
            $this->redirectRoute('portal.requests.show', $tujuan, navigate: true);

            return;
        }

        $this->dispatch('pesan', teks: __('Baris ditambahkan.'));
    }

    // ------------------------------------------------------ penggantian item

    public function setujuiPenggantian(int $id, RespondSubstitution $action): void
    {
        $line = $this->line($id);

        $this->authorize('respondSubstitution', $line->request);

        if ($this->jalankan(fn () => $action->accept($line, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Penggantian disetujui.'));
        }
    }

    public function mintaTolakPenggantian(int $id): void
    {
        $line = $this->line($id);

        $this->authorize('respondSubstitution', $line->request);

        $this->dialog = 'tolak-ganti';
        $this->lineId = $line->id;
        $this->reasonCode = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    // ------------------------------------------------ permintaan pembatalan

    public function mintaBatalBaris(int $id): void
    {
        $line = $this->line($id);

        $this->authorize('requestCancel', $line->request);

        $this->dialog = 'minta-batal';
        $this->lineId = $line->id;
        $this->reasonCode = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    /** Menolak penggantian dan meminta pembatalan: dua aksi, satu dialog alasan. */
    public function jalankanDialogAlasan(RespondSubstitution $penggantian, CancelRequestLine $pembatalan): void
    {
        $line = $this->line($this->lineId);

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        $alasanId = $this->alasanId();

        $berhasil = match ($this->dialog) {
            'tolak-ganti' => $this->authorizeThen('respondSubstitution', $line, fn () => $this->jalankan(
                fn () => $penggantian->reject($line, $alasanId, auth()->user()),
            )),
            'minta-batal' => $this->authorizeThen('requestCancel', $line, fn () => $this->jalankan(
                fn () => $pembatalan->request($line, $alasanId, auth()->user()),
            )),
            default => false,
        };

        if (! $berhasil) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Tanggapan Anda tercatat.'));
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->lineId = null;
        $this->barisBaru = [];
        $this->ruleError = '';
        $this->resetValidation();
    }

    private function authorizeThen(string $ability, MaterialRequestLine $line, callable $aksi): bool
    {
        $this->authorize($ability, $line->request);

        return $aksi();
    }

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
            ->with('project:id,code,name,client_id', 'parent:id,number')
            ->findOrFail($this->requestId);
    }

    private function line(?int $id): MaterialRequestLine
    {
        return MaterialRequestLine::query()
            ->with('request.project')
            ->where('material_request_id', $this->requestId)
            ->findOrFail($id);
    }
}

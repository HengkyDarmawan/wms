<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\LineOwnership;
use App\Domain\Request\Livewire\Concerns\HandlesRequestRules;
use App\Domain\Request\Models\MaterialRequest;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 14-request §6 — pembuatan dan pengubahan REQ.
 *
 * Baris boleh menyebut item katalog **atau** teks bebas (BR-REQ-03): pemohon di
 * lapangan sering tidak tahu kode barang, dan memaksanya memilih dari katalog
 * hanya akan melahirkan baris yang salah.
 */
class RequestForm extends Component
{
    use HandlesRequestRules;

    #[Locked]
    public ?int $requestId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'project_id' => '',
        'required_date' => '',
        'notes' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public function mount(?MaterialRequest $request = null): void
    {
        if ($request?->exists) {
            $this->authorize('update', $request);

            $this->requestId = (int) $request->id;
            $this->form = [
                'project_id' => (string) $request->project_id,
                'required_date' => $request->required_date?->toDateString() ?? '',
                'notes' => (string) ($request->notes ?? ''),
            ];

            $this->lines = $request->openLines()->orderBy('id')->get()->map(fn ($l) => [
                'id' => (string) $l->id,
                'item_id' => (string) ($l->item_id ?? ''),
                'non_catalog_text' => (string) ($l->non_catalog_text ?? ''),
                'qty_base' => (string) (float) $l->qty_base,
                'line_ownership' => $l->line_ownership->value,
                'required_date' => $l->required_date?->toDateString() ?? '',
                'notes' => (string) ($l->notes ?? ''),
            ])->all();

            return;
        }

        $this->authorize('create', MaterialRequest::class);

        $this->form['required_date'] = now()->addDays(3)->toDateString();
        $this->tambahBaris();
    }

    public function tambahBaris(): void
    {
        $this->lines[] = [
            'id' => '',
            'item_id' => '',
            'non_catalog_text' => '',
            'qty_base' => '',
            'line_ownership' => LineOwnership::Buy->value,
            'required_date' => '',
            'notes' => '',
        ];
    }

    /**
     * Baris dilepas dari form, bukan dihapus dari basis data. Yang sudah
     * tersimpan dibatalkan saat disimpan (P-03).
     */
    public function hapusBaris(int $index): void
    {
        unset($this->lines[$index]);

        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->tambahBaris();
        }
    }

    public function simpan(SaveRequest $action): void
    {
        $request = $this->requestModel();

        $this->authorize($request === null ? 'create' : 'update', $request ?? MaterialRequest::class);

        $this->validate([
            'form.required_date' => ['required', 'date'],
            'lines' => ['array', 'min:1'],
        ], attributes: [
            'form.required_date' => __('Tanggal dibutuhkan'),
        ]);

        $tersimpan = null;

        $berhasil = $this->jalankan(function () use ($action, $request, &$tersimpan) {
            $tersimpan = $action->handle($request, $this->form, $this->lines, auth()->user());
        });

        if (! $berhasil || $tersimpan === null) {
            return;
        }

        $this->redirectRoute('requests.show', $tersimpan, navigate: true);
    }

    /** Simpan lalu ajukan sekaligus: dua tombol terpisah hanya menambah langkah. */
    public function simpanDanAjukan(SaveRequest $simpan, SubmitRequest $ajukan): void
    {
        $request = $this->requestModel();

        $this->authorize($request === null ? 'create' : 'update', $request ?? MaterialRequest::class);

        $this->validate([
            'form.required_date' => ['required', 'date'],
        ], attributes: ['form.required_date' => __('Tanggal dibutuhkan')]);

        $tersimpan = null;

        $berhasil = $this->jalankan(function () use ($simpan, $ajukan, $request, &$tersimpan) {
            $tersimpan = $simpan->handle($request, $this->form, $this->lines, auth()->user());
            $tersimpan = $ajukan->handle($tersimpan, auth()->user());
        });

        if (! $berhasil || $tersimpan === null) {
            return;
        }

        $this->redirectRoute('requests.show', $tersimpan, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.request.request-form', [
            'projects' => Project::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'items' => Item::query()
                ->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value])
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'tracking_mode']),
            'ownerships' => LineOwnership::options(),
            'isBaru' => $this->requestId === null,
        ]);
    }

    private function requestModel(): ?MaterialRequest
    {
        return $this->requestId === null ? null : MaterialRequest::findOrFail($this->requestId);
    }
}

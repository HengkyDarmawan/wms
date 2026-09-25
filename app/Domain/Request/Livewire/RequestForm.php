<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ScanCode;
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

    public string $kodePindai = '';

    /** Indeks baris yang terakhir terisi lewat pindai, untuk disorot. */
    public ?int $sorot = null;

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

        // Dari hub proyek (A-228): proyek langsung terisi bila boleh diakses.
        $proyek = request()->query('project');

        if (is_numeric($proyek) && Project::query()->active()->whereKey((int) $proyek)->exists() && auth()->user()->canAccessProject((int) $proyek)) {
            $this->form['project_id'] = (string) (int) $proyek;
        }

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
        $this->sorot = null;

        if ($this->lines === []) {
            $this->tambahBaris();
        }
    }

    /**
     * Pindai label item (A-206): item yang sudah ada di baris ditambah satu,
     * item baru mengisi baris kosong pertama atau baris baru dengan jumlah 1.
     * Jumlah tetap bisa diubah manual sesudahnya.
     */
    public function pindai(): void
    {
        $kode = ScanCode::normalize($this->kodePindai);
        $this->kodePindai = '';
        $this->resetErrorBag('kodePindai');

        if ($kode === '') {
            return;
        }

        $ids = Item::query()->whereKey(ScanCode::itemIds($kode))
            ->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            $this->addError('kodePindai', __('":kode" tidak cocok dengan item aktif mana pun.', ['kode' => $kode]));

            return;
        }

        if (count($ids) > 1) {
            $this->addError('kodePindai', __('":kode" cocok dengan lebih dari satu item; pilih itemnya dari daftar.', ['kode' => $kode]));

            return;
        }

        $itemId = (string) $ids[0];

        foreach ($this->lines as $i => $baris) {
            if ((string) $baris['item_id'] === $itemId) {
                $this->lines[$i]['qty_base'] = (string) ((is_numeric($baris['qty_base']) ? (float) $baris['qty_base'] : 0) + 1);
                $this->sorot = $i;

                return;
            }
        }

        $kosong = collect($this->lines)->search(fn ($b) => (string) $b['item_id'] === '' && trim((string) $b['non_catalog_text']) === '');

        if ($kosong === false) {
            $this->tambahBaris();
            $kosong = array_key_last($this->lines);
        }

        $this->lines[$kosong]['item_id'] = $itemId;
        $this->lines[$kosong]['qty_base'] = is_numeric($this->lines[$kosong]['qty_base']) && (float) $this->lines[$kosong]['qty_base'] > 0
            ? $this->lines[$kosong]['qty_base'] : '1';
        $this->sorot = (int) $kosong;
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

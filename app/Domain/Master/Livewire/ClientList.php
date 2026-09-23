<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\DeactivateClient;
use App\Domain\Master\Actions\SaveClient;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 11-master §6 — daftar dan form klien.
 *
 * Klien tidak pernah dihapus (P-03); menonaktifkan butuh alasan `*` dan ditolak
 * bila klien masih punya proyek aktif (BR-MST-05).
 */
class ClientList extends Component
{
    use HandlesMasterRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [
        'code' => '',
        'name' => '',
        'tax_id' => '',
        'address' => '',
        'contact_name' => '',
        'phone' => '',
        'email' => '',
    ];

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    public function buat(): void
    {
        $this->authorize('create', Client::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = array_map(fn () => '', $this->form);
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $client = Client::findOrFail($id);

        $this->authorize('update', $client);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $client->id;
        $this->form = [
            'code' => (string) $client->code,
            'name' => (string) $client->name,
            'tax_id' => (string) $client->tax_id,
            'address' => (string) $client->address,
            'contact_name' => (string) $client->contact_name,
            'phone' => (string) $client->phone,
            'email' => (string) $client->email,
        ];
        $this->showForm = true;
    }

    public function simpan(SaveClient $action): void
    {
        $client = $this->editingId === null ? null : Client::findOrFail($this->editingId);

        $this->authorize($client === null ? 'create' : 'update', $client ?? Client::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:30'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.tax_id' => ['nullable', 'string', 'max:30'],
            'form.contact_name' => ['nullable', 'string', 'max:100'],
            'form.phone' => ['nullable', 'string', 'max:20'],
            'form.email' => ['nullable', 'email', 'max:150'],
        ], attributes: [
            'form.code' => __('Kode'),
            'form.name' => __('Nama klien'),
            'form.email' => __('Email'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($client, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Klien disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function mintaNonaktif(int $id): void
    {
        $client = Client::findOrFail($id);

        $this->authorize('deactivate', $client);

        $this->ruleError = '';
        $this->deactivatingId = $client->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function nonaktifkan(DeactivateClient $action): void
    {
        $client = Client::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $client);

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        $berhasil = $this->jalankan(
            fn () => $action->handle($client, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('Klien dinonaktifkan.'));
    }

    public function batalNonaktif(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function aktifkan(int $id, DeactivateClient $action): void
    {
        $client = Client::findOrFail($id);

        $this->authorize('update', $client);

        $action->reactivate($client, auth()->user());

        $this->dispatch('pesan', teks: __('Klien diaktifkan kembali.'));
    }

    public function render(): View
    {
        return view('livewire.master.client-list', [
            'clients' => $this->clients(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    private function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->withCount(['projects as active_projects_count' => fn (Builder $q) => $q->where('status', 'active')])
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)
                    ->orWhere('code', 'like', $cari)
                    ->orWhere('contact_name', 'like', $cari));
            })
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('is_active', $this->statusFilter === 'aktif'))
            ->orderBy('name')
            ->paginate(15);
    }
}

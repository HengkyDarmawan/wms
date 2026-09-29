<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\ChangeProjectStatus;
use App\Domain\Master\Actions\SaveProject;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 11-master §6 — daftar, form, dan perubahan status proyek (§4).
 *
 * Proyek Internal (A-06) tidak punya klien dan tidak bisa ditutup, karena
 * dipakai konversi dan peminjaman non-klien.
 */
class ProjectList extends Component
{
    use CariPilihan;
    use HandlesMasterRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $clientFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'name' => '',
        'client_id' => '',
        'is_internal' => false,
        'pic_user_id' => '',
        'start_date' => '',
        'target_end_date' => '',
        'address' => '',
        'lat' => '',
        'lng' => '',
    ];

    #[Locked]
    public ?int $statusChangingId = null;

    public string $targetStatus = '';

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);

        // Tombol "Ubah" di hub proyek membuka form ubah di daftar ini.
        $ubah = request()->query('ubah');

        if (is_numeric($ubah) && Project::query()->whereKey((int) $ubah)->exists() && auth()->user()->can('update', Project::query()->find((int) $ubah))) {
            $this->ubah((int) $ubah);

            return;
        }

        // A-327: tombol "Tambah proyek" di halaman klien membuka form baru
        // dengan kliennya sudah terisi.
        $klien = request()->query('klien');

        if (is_numeric($klien) && Client::query()->whereKey((int) $klien)->active()->exists()
            && auth()->user()->can('create', Project::class)) {
            $this->buat();
            $this->form['client_id'] = (string) (int) $klien;
            $this->clientFilter = (string) (int) $klien;
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }

        // Proyek Internal tidak boleh punya klien (BR-MST-04).
        if ($property === 'form.is_internal' && $this->form['is_internal']) {
            $this->form['client_id'] = '';
        }
    }

    public function buat(): void
    {
        $this->authorize('create', Project::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'client_id' => '',
            'is_internal' => false,
            'pic_user_id' => '',
            'start_date' => '',
            'target_end_date' => '',
            'address' => '',
            'lat' => '',
            'lng' => '',
        ];
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $project = Project::findOrFail($id);

        $this->authorize('update', $project);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $project->id;
        $this->form = [
            'code' => (string) $project->code,
            'name' => (string) $project->name,
            'client_id' => (string) $project->client_id,
            'is_internal' => (bool) $project->is_internal,
            'pic_user_id' => (string) $project->pic_user_id,
            'start_date' => $project->start_date?->toDateString() ?? '',
            'target_end_date' => $project->target_end_date?->toDateString() ?? '',
            'address' => (string) $project->address,
            'lat' => (string) $project->lat,
            'lng' => (string) $project->lng,
        ];
        $this->showForm = true;
    }

    public function simpan(SaveProject $action): void
    {
        $project = $this->editingId === null ? null : Project::findOrFail($this->editingId);

        $this->authorize($project === null ? 'create' : 'update', $project ?? Project::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:30'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.start_date' => ['nullable', 'date'],
            'form.target_end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'form.lng' => ['nullable', 'numeric', 'between:-180,180'],
            // A-389: PIC dari daftar (pengguna internal aktif); PIC tersimpan yang tidak diubah tetap boleh.
            'form.pic_user_id' => (string) $this->form['pic_user_id'] === (string) $project?->pic_user_id
                ? ['nullable'] : ['nullable', $this->pilihanPic()->aturan()],
        ], attributes: [
            'form.code' => __('Kode'),
            'form.pic_user_id' => __('PIC proyek'),
            'form.name' => __('Nama proyek'),
            'form.target_end_date' => __('Target selesai'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($project, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Proyek disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function mintaUbahStatus(int $id): void
    {
        $project = Project::findOrFail($id);

        $this->authorize('close', $project);

        $this->ruleError = '';
        $this->statusChangingId = $project->id;
        $this->targetStatus = '';
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function ubahStatus(ChangeProjectStatus $action): void
    {
        $project = Project::findOrFail($this->statusChangingId);

        $this->authorize('close', $project);

        $this->validate([
            'targetStatus' => ['required', Rule::enum(ProjectStatus::class)],
            'reasonCode' => ['required', 'string'],
        ], attributes: [
            'targetStatus' => __('Status tujuan'),
            'reasonCode' => __('Alasan'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle(
            $project,
            ProjectStatus::tryFrom($this->targetStatus) ?? ProjectStatus::Closed,
            $this->reasonCode,
            $this->reasonNotes ?: null,
            auth()->user(),
        ));

        if (! $berhasil) {
            return;
        }

        $this->statusChangingId = null;
        $this->dispatch('pesan', teks: __('Status proyek diperbarui.'));
    }

    public function batalUbahStatus(): void
    {
        $this->statusChangingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function render(ChangeProjectStatus $action): View
    {
        $project = $this->statusChangingId === null ? null : Project::find($this->statusChangingId);

        return view('livewire.master.project-list', [
            'projects' => $this->projects(),
            'clients' => Client::query()->active()->orderBy('name')->get(['id', 'name']),
            'opsiPic' => $this->showForm ? $this->pilihanPic()->awalDengan($this->form['pic_user_id']) : [],
            'statuses' => ProjectStatus::options(),
            'targetOptions' => $project === null ? [] : $action->availableTargets($project),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    /** PIC proyek = pengguna internal aktif, dicari ke server (A-384, A-389). */
    private function pilihanPic(): Pilihan
    {
        return SumberPilihan::pengguna();
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if ($model !== 'form.pic_user_id') {
            return null;
        }

        // Izin layar diulang: hanya pembuat/pengubah proyek yang boleh melihat daftar pengguna.
        $proyek = $this->editingId === null ? null : Project::find($this->editingId);
        $boleh = $proyek === null
            ? $this->editingId === null && auth()->user()?->can('create', Project::class)
            : auth()->user()?->can('update', $proyek);

        return $boleh ? $this->pilihanPic() : null;
    }

    private function projects(): LengthAwarePaginator
    {
        $cakupan = auth()->user()?->accessibleProjectIds();

        return Project::query()
            ->with('client:id,name', 'pic:id,name')
            ->when($cakupan !== null, fn (Builder $q) => $q->whereIn('id', $cakupan))
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)->orWhere('code', 'like', $cari));
            })
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->clientFilter !== '', fn (Builder $q) => $q->where('client_id', (int) $this->clientFilter))
            ->orderBy('name')
            ->paginate(15);
    }
}

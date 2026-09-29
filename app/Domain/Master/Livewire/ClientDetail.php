<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Master\Actions\CreatePortalAccountForContact;
use App\Domain\Master\Actions\DeactivateClientContact;
use App\Domain\Master\Actions\SaveClientContact;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\ClientContact;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 11-master §6 — halaman detail klien (A-327): kepala klien dan tiga tab
 * Proyek · PIC · Akun portal. PIC di sini orang dari **pihak klien**
 * (`client_contacts`), bukan PIC proyek dari pihak kita (`projects.pic_user_id`).
 */
class ClientDetail extends Component
{
    use HandlesMasterRules;
    use WithPagination;

    public const TABS = ['proyek', 'pic', 'akun'];

    #[Locked]
    public int $clientId;

    #[Url(except: 'proyek')]
    public string $tab = 'proyek';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $formPic = [
        'name' => '',
        'position' => '',
        'phone' => '',
        'email' => '',
        'notes' => '',
        'projects' => [],
    ];

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public bool $nonaktifkanAkun = true;

    /** Hasil penyelarasan terakhir, hanya untuk pesan di layar. */
    public int $selaras = 0;

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);

        $this->clientId = (int) $client->id;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'proyek';
        }
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'proyek';
    }

    // ------------------------------------------------------------- PIC klien

    public function buatPic(): void
    {
        $this->authorize('create', Client::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->formPic = ['name' => '', 'position' => '', 'phone' => '', 'email' => '', 'notes' => '', 'projects' => []];
        $this->showForm = true;
        $this->tab = 'pic';
    }

    public function ubahPic(int $id): void
    {
        $this->authorize('update', $this->client());

        $contact = $this->contact($id);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $contact->id;
        $this->formPic = [
            'name' => (string) $contact->name,
            'position' => (string) $contact->position,
            'phone' => (string) $contact->phone,
            'email' => (string) $contact->email,
            'notes' => (string) $contact->notes,
            'projects' => $contact->projects()->pluck('projects.id')->map(fn ($pid) => (string) $pid)->all(),
        ];
        $this->showForm = true;
        $this->tab = 'pic';
    }

    public function simpanPic(SaveClientContact $action): void
    {
        $client = $this->client();

        $this->authorize($this->editingId === null ? 'create' : 'update', $this->editingId === null ? Client::class : $client);

        $this->validate([
            'formPic.name' => ['required', 'string', 'max:100'],
            'formPic.position' => ['nullable', 'string', 'max:100'],
            'formPic.phone' => ['nullable', 'string', 'max:20'],
            'formPic.email' => ['nullable', 'email', 'max:150'],
            'formPic.notes' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'formPic.name' => __('Nama PIC'),
            'formPic.phone' => __('No. WA'),
            'formPic.email' => __('Email'),
        ]);

        $contact = $this->editingId === null ? null : $this->contact($this->editingId);

        $berhasil = $this->jalankan(
            fn () => $action->handle($client, $contact, $this->formPic, auth()->user()),
            'formPic',
        );

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('PIC klien disimpan.'));
    }

    public function batalPic(): void
    {
        $this->showForm = false;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function mintaNonaktifPic(int $id): void
    {
        $this->authorize('deactivate', $this->client());

        $this->ruleError = '';
        $this->deactivatingId = $this->contact($id)->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
        $this->nonaktifkanAkun = true;
        $this->tab = 'pic';
    }

    public function nonaktifkanPic(DeactivateClientContact $action): void
    {
        $this->authorize('deactivate', $this->client());

        $contact = $this->contact((int) $this->deactivatingId);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(fn () => $action->handle(
            $contact,
            $this->reasonCode,
            $this->reasonNotes ?: null,
            $this->nonaktifkanAkun,
            auth()->user(),
        ), 'formPic');

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('PIC klien dinonaktifkan.'));
    }

    public function batalNonaktifPic(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function aktifkanPic(int $id, DeactivateClientContact $action): void
    {
        $this->authorize('update', $this->client());

        $action->reactivate($this->contact($id), auth()->user());

        $this->dispatch('pesan', teks: __('PIC klien diaktifkan kembali.'));
    }

    // -------------------------------------------------------- akun portal PIC

    /** A-328: akun portal role Klien dibuat dari data PIC, lalu masuk Tim site. */
    public function buatAkunPortal(int $id, CreatePortalAccountForContact $action)
    {
        $this->authorize('create', User::class);
        $this->authorize('assignRole', User::class);

        $contact = $this->contact($id);

        $user = null;
        $berhasil = $this->jalankan(function () use ($action, $contact, &$user): void {
            $user = $action->handle($contact, true, auth()->user());
        }, 'formPic');

        if (! $berhasil || $user === null) {
            $this->tab = 'pic';

            return null;
        }

        session()->flash('status', __('Akun portal dibuat. Salin tautan undangannya di bawah ini.'));

        return $this->redirectRoute('users.show', ['user' => $user->id], navigate: false);
    }

    /** A-339: proyek PIC bertambah → tawarkan menambahkannya ke Tim site. */
    public function selaraskanTimSite(int $id, CreatePortalAccountForContact $action): void
    {
        $this->authorize('assignRole', User::class);

        $contact = $this->contact($id);

        $berhasil = $this->jalankan(
            fn () => $this->selaras = $action->selaraskan($contact, auth()->user()),
            'formPic',
        );

        if (! $berhasil) {
            return;
        }

        $this->tab = 'pic';
        $this->dispatch('pesan', teks: __('Cakupan akun portal diselaraskan dengan proyek PIC.'));
    }

    // ---------------------------------------------------------------- render

    public function render(): View
    {
        $client = $this->client();

        return view('livewire.master.client-detail', [
            'client' => $client,
            'ringkas' => $this->ringkas(),
            'tabs' => ['proyek' => __('Proyek'), 'pic' => __('PIC'), 'akun' => __('Akun portal')],
            'data' => $this->dataTab(),
            'proyekPilihan' => Project::query()->where('client_id', $client->id)->orderBy('code')->get(['id', 'code', 'name']),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
            'picNonaktif' => $this->deactivatingId === null ? null : $this->contact($this->deactivatingId),
            'perluSelaras' => $this->perluSelaras(),
        ]);
    }

    /**
     * PIC ber-akun yang proyeknya bertambah sejak akunnya dibuat (A-339).
     *
     * @return array<int, string> id PIC => daftar kode proyek
     */
    private function perluSelaras(): array
    {
        if ($this->tab !== 'pic') {
            return [];
        }

        $action = app(CreatePortalAccountForContact::class);

        return ClientContact::query()->where('client_id', $this->clientId)->active()
            ->whereNotNull('user_id')->with('projects:id,code', 'portalUser:id,name')->get()
            ->mapWithKeys(fn (ClientContact $c) => [$c->id => $action->belumJadiAnggota($c)->pluck('code')->implode(', ')])
            ->filter(fn (string $kode) => $kode !== '')
            ->all();
    }

    private function client(): Client
    {
        return Client::query()->findOrFail($this->clientId);
    }

    private function contact(int $id): ClientContact
    {
        return ClientContact::query()->where('client_id', $this->clientId)->findOrFail($id);
    }

    /** @return array<string, int> */
    private function ringkas(): array
    {
        return [
            'proyek' => Project::query()->where('client_id', $this->clientId)->where('status', 'active')->count(),
            'pic' => ClientContact::query()->where('client_id', $this->clientId)->active()->count(),
            'akun' => User::query()->where('client_id', $this->clientId)->where('is_active', true)->count(),
        ];
    }

    /** Data hanya untuk tab yang sedang dibuka. */
    private function dataTab(): mixed
    {
        return match ($this->tab) {
            'pic' => ClientContact::query()->where('client_id', $this->clientId)
                ->with('projects:id,code,name', 'portalUser:id,name,email,is_active')
                ->orderByDesc('is_active')->orderBy('name')->paginate(20, pageName: 'pic'),
            'akun' => $this->akunPortal(),
            default => $this->proyek(),
        };
    }

    private function proyek(): mixed
    {
        $daftar = Project::query()->where('client_id', $this->clientId)
            ->with('pic:id,name')->orderBy('code')->paginate(20, pageName: 'proyek');

        // withoutGlobalScopes: nama Gudang Site tetap tampil walau di luar cakupan
        // gudang pembacanya — sama dengan cara ProjectListReport menghitungnya.
        $site = Warehouse::withoutGlobalScopes()
            ->whereIn('project_id', $daftar->pluck('id'))
            ->orderBy('code')->get(['id', 'code', 'project_id'])
            ->groupBy('project_id')
            ->map(fn (Collection $g) => $g->pluck('code')->implode(', '));

        $daftar->getCollection()->each(fn (Project $p) => $p->setAttribute('site_codes', $site[$p->id] ?? null));

        return $daftar;
    }

    private function akunPortal(): mixed
    {
        $daftar = User::query()->where('client_id', $this->clientId)
            ->with('roleAssignments.role:id,name')
            ->orderBy('name')->paginate(20, pageName: 'akun');

        $dariPic = ClientContact::query()->where('client_id', $this->clientId)
            ->whereIn('user_id', $daftar->pluck('id'))->pluck('name', 'user_id');

        $daftar->getCollection()->each(fn (User $u) => $u->setAttribute('dari_pic', $dariPic[$u->id] ?? null));

        return $daftar;
    }
}

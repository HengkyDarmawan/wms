<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\CreateUser;
use App\Domain\Access\Actions\SetInitialPassword;
use App\Domain\Access\Actions\UpdateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Livewire\Concerns\ComposesRoleAssignments;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\RoleGuide;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 10-access §6.3 — form tambah/ubah pengguna, urutan "pilih peran dulu"
 * (A-331): data diri → peran → pertanyaan sesuai peran → pengaturan lanjutan →
 * cara masuk pertama kali.
 *
 * **Tanpa isian tanggal** (A-337): akun dan peran berlaku sampai dinonaktifkan;
 * penempatan berbatas waktu diatur lewat Tim site di hub proyek. Tanggal pada
 * penugasan lama tetap ditegakkan dan dibawa apa adanya saat disimpan ulang,
 * supaya tidak diam-diam menjadi permanen.
 */
class UserForm extends Component
{
    use ComposesRoleAssignments;

    /**
     * Terkunci: properti publik Livewire bisa ditimpa dari browser. Tanpa ini,
     * pemegang `user.create` dapat menyulap form "tambah pengguna" menjadi form
     * ubah pengguna lain hanya dengan mengirim `userId` lain.
     */
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /** Peran utama: id role yang dipilih lewat kartu. */
    public string $peranUtama = '';

    /** @var array<int, string> id gudang tetap untuk peran gudang */
    public array $gudangDipilih = [];

    /** @var array<int, string> id proyek untuk peran berbasis proyek */
    public array $proyekDipilih = [];

    public ?int $clientId = null;

    public bool $lanjutanTerbuka = false;

    public ?int $orgUnitId = null;

    public ?int $positionId = null;

    public ?int $managerId = null;

    /** Peran tambahan (baris peran × cakupan yang lama), tanpa isian tanggal. */
    public array $assignments = [];

    /** `undangan` | `password` — hanya saat membuat pengguna baru (A-334). */
    public string $caraMasuk = 'undangan';

    public string $passwordAwal = '';

    /** Seluruh cakupan pengguna ini berasal dari Tim site; form tidak mengubahnya. */
    #[Locked]
    public bool $penugasanSiteSaja = false;

    public function mount(?int $userId = null): void
    {
        $this->userId = $userId;

        if ($userId === null) {
            $this->authorize('create', User::class);

            return;
        }

        $user = User::with('roleAssignments')->findOrFail($userId);
        $this->authorize('update', $user);

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->orgUnitId = $user->org_unit_id;
        $this->positionId = $user->position_id;
        $this->managerId = $user->manager_id;
        $this->clientId = $user->client_id;

        $this->muatPenugasan($user);
    }

    public function pilihPeran(int $roleId): void
    {
        $this->peranUtama = (string) $roleId;
        $this->gudangDipilih = [];
        $this->proyekDipilih = [];
        $this->resetValidation();

        $role = Role::query()->find($roleId);

        if ($role === null) {
            return;
        }

        if (RoleGuide::pertanyaan($role) !== RoleGuide::KLIEN) {
            $this->clientId = null;
        }

        // A-332: unit organisasi disarankan dari peran, hanya bila Admin belum memilih.
        $saran = RoleGuide::saranUnit($role);

        if ($this->orgUnitId === null && $saran !== null) {
            $this->orgUnitId = OrgUnit::query()->where('is_active', true)->where('name', $saran)->value('id');
        }
    }

    /** A-21: proyek yang sudah dicentang milik klien lama tidak boleh ikut terbawa. */
    public function updatedClientId(): void
    {
        $this->proyekDipilih = [];
    }

    public function addAssignment(): void
    {
        $this->lanjutanTerbuka = true;
        $this->assignments[] = [
            'role_id' => null,
            'scope_type' => ScopeType::All->value,
            'scope_id' => null,
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function removeAssignment(int $index): void
    {
        unset($this->assignments[$index]);
        $this->assignments = array_values($this->assignments);
    }

    public function acakPassword(): void
    {
        $kata = ['Palu', 'Semen', 'Pipa', 'Baja', 'Kabel', 'Beton', 'Gudang', 'Proyek'];

        $this->passwordAwal = $kata[array_rand($kata)].'-'.strtoupper(bin2hex(random_bytes(2))).'-'.now()->year;
    }

    public function save(CreateUser $createUser, UpdateUser $updateUser, SetInitialPassword $setPassword, SiteTeam $siteTeam)
    {
        // Otorisasi diulang di sini, bukan hanya di mount(): satu permintaan
        // Livewire bisa memanggil metode ini langsung tanpa pernah melewati mount.
        $target = $this->userId === null ? null : User::findOrFail($this->userId);

        $this->authorize($target === null ? 'create' : 'update', $target ?? User::class);

        $data = $this->validate();

        $peran = $this->peranUtama === '' ? null : Role::query()->find((int) $this->peranUtama);
        $pertanyaan = $peran === null ? null : RoleGuide::pertanyaan($peran);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $this->phone !== '' ? $this->phone : null,
            'org_unit_id' => $this->orgUnitId,
            'position_id' => $this->positionId,
            'manager_id' => $this->managerId,
            'client_id' => $pertanyaan === RoleGuide::KLIEN ? $this->clientId : null,
        ];

        $assignments = $this->penugasanSiteSaja ? null : $this->susunPenugasan($peran, $pertanyaan);

        // BR-GEN-09: mengubah penugasan role menuntut `role.assign` tersendiri.
        // Tanpa pemeriksaan ini, pemegang `user.update` bisa menambahkan role
        // Admin Company untuk dirinya sendiri lewat form ini.
        if ($assignments !== null && ($target === null || $this->assignmentsChanged($target, $assignments))) {
            $this->authorize('assignRole', $target ?? User::class);
        }

        try {
            if ($this->userId === null) {
                $user = $createUser->handle($attributes, $assignments ?? [], $this->caraMasuk === 'undangan', auth()->user());
                $pesan = $this->caraMasuk === 'undangan'
                    ? 'Pengguna dibuat. Salin tautan undangannya di kartu di bawah.'
                    : 'Pengguna dibuat dengan password yang Anda tentukan.';

                if ($this->caraMasuk === 'password') {
                    $setPassword->handle($user, $this->passwordAwal, auth()->user());
                    session()->flash('passwordDibuat', $this->passwordAwal);
                }
            } else {
                $user = $updateUser->handle(User::findOrFail($this->userId), $attributes, $assignments, auth()->user());
                $pesan = 'Perubahan disimpan.';
            }

            // A-339: proyek peran Klien dicatat sebagai penempatan Tim site,
            // supaya periodenya terlihat dan bisa diperpanjang di hub proyek.
            if ($pertanyaan === RoleGuide::KLIEN && $assignments !== null) {
                $siteTeam->catatPenempatanAwal($user, $peran, $this->proyekTerpilih(), auth()->user());
            }
        } catch (AccessRuleException $e) {
            // Ditempel di dua tempat supaya terlihat baik saat perannya dipilih
            // lewat kartu maupun lewat baris Pengaturan lanjutan.
            $this->addError('peranUtama', $e->getMessage());
            $this->addError('assignments', $e->getMessage());

            return null;
        }

        session()->flash('status', $pesan);

        return $this->redirectRoute('users.show', ['user' => $user->id], navigate: false);
    }

    /**
     * A-358: pilihan Atasan langsung — nama + badge jabatan + unit, bisa dicari
     * ketiganya. Tidak menawarkan diri sendiri, pengguna nonaktif, atau akun
     * Klien. Atasan yang sudah tersimpan tetapi kini tidak memenuhi syarat
     * tetap ditampilkan (bertanda) supaya nilainya tidak tampak hilang.
     *
     * @return array<int, array{value: int, text: string, badge: ?string, sub: ?string}>
     */
    private function opsiAtasan(): array
    {
        $opsi = fn (User $u, string $tanda = '') => [
            'value' => (int) $u->id,
            'text' => $u->name.$tanda,
            'badge' => $u->position?->name,
            'sub' => $u->orgUnit?->name,
        ];

        $daftar = User::query()
            ->active()->internal()
            ->when($this->userId !== null, fn ($q) => $q->whereKeyNot($this->userId))
            ->with(['position:id,name', 'orgUnit:id,name'])
            ->orderBy('name')->get(['id', 'name', 'position_id', 'org_unit_id'])
            ->map(fn (User $u) => $opsi($u))->all();

        if ($this->managerId !== null && ! collect($daftar)->contains('value', (int) $this->managerId)) {
            $tersimpan = User::query()->with(['position:id,name', 'orgUnit:id,name'])->find($this->managerId, ['id', 'name', 'position_id', 'org_unit_id', 'is_active']);

            if ($tersimpan !== null) {
                array_unshift($daftar, $opsi($tersimpan, ' ('.($tersimpan->is_active ? __('tidak berlaku') : __('nonaktif')).')'));
            }
        }

        return $daftar;
    }

    /** @return array<int, string> nama pemegang jabatan atasan dari jabatan terpilih */
    private function atasanDariJabatan(): array
    {
        $jabatanAtasan = $this->positionId === null ? null
            : Position::query()->whereKey($this->positionId)->value('reports_to_position_id');

        if ($jabatanAtasan === null) {
            return [];
        }

        return User::query()->active()->internal()->where('position_id', $jabatanAtasan)
            ->when($this->userId !== null, fn ($q) => $q->whereKeyNot($this->userId))
            ->orderBy('name')->pluck('name')->all();
    }

    /** Pertanyaan cakupan untuk peran yang sedang dipilih. */
    public function pertanyaanPeran(): ?string
    {
        if ($this->peranUtama === '') {
            return null;
        }

        $role = Role::query()->find((int) $this->peranUtama);

        return $role === null ? null : RoleGuide::pertanyaan($role);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'orgUnitId' => ['nullable', 'integer', 'exists:org_units,id'],
            'positionId' => ['nullable', 'integer', 'exists:positions,id'],
            'managerId' => ['nullable', 'integer', 'different:userId', 'exists:users,id'],
            'assignments' => ['array'],
            'assignments.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'assignments.*.scope_type' => ['required', Rule::enum(ScopeType::class)],
            'assignments.*.scope_id' => ['nullable', 'integer', 'min:1'],
        ];

        if ($this->penugasanSiteSaja) {
            return $rules;
        }

        $pertanyaan = $this->pertanyaanPeran();

        if ($pertanyaan === null) {
            // Tanpa peran utama, minimal harus ada satu baris di Pengaturan
            // lanjutan. Bila keduanya kosong, galatnya menempel di kartu peran
            // supaya terlihat — bukan di bagian yang sedang terlipat.
            if ($this->assignments === []) {
                $rules['peranUtama'] = ['required'];
            } else {
                $rules['assignments'] = ['required', 'array', 'min:1'];
            }
        } else {
            $rules['peranUtama'] = ['required', 'integer', 'exists:roles,id'];

            if ($pertanyaan === RoleGuide::GUDANG) {
                $rules['gudangDipilih'] = ['required', 'array', 'min:1'];
            }

            if ($pertanyaan === RoleGuide::PROYEK) {
                $rules['proyekDipilih'] = ['required', 'array', 'min:1'];
            }

            if ($pertanyaan === RoleGuide::KLIEN) {
                $rules['clientId'] = ['required', 'integer', 'exists:clients,id'];
                $rules['proyekDipilih'] = ['required', 'array', 'min:1'];
            }
        }

        if ($this->userId === null && $this->caraMasuk === 'password') {
            $rules['passwordAwal'] = ['required', 'string', 'min:10', 'max:100'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'peranUtama.required' => __('Pilih satu peran dulu.'),
            'gudangDipilih.required' => __('Pilih minimal satu gudang.'),
            'proyekDipilih.required' => __('Pilih minimal satu proyek.'),
            'clientId.required' => __('Pilih klien dulu.'),
            'assignments.required' => __('Pilih satu peran dulu.'),
            'assignments.min' => __('Pilih satu peran dulu.'),
            'passwordAwal.required' => __('Isi passwordnya, atau pilih kirim undangan.'),
            'passwordAwal.min' => __('Password minimal 10 karakter.'),
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor WhatsApp',
            'orgUnitId' => 'Unit organisasi',
            'positionId' => 'Jabatan',
            'managerId' => 'Atasan langsung',
            'clientId' => 'Klien',
            'peranUtama' => 'Peran',
            'gudangDipilih' => 'Gudang',
            'proyekDipilih' => 'Proyek',
            'passwordAwal' => 'Password',
            'assignments' => 'Peran',
            'assignments.*.role_id' => 'Peran',
            'assignments.*.scope_type' => 'Cakupan',
            'assignments.*.scope_id' => 'ID cakupan',
        ];
    }

    public function render(): View
    {
        $user = $this->userId !== null ? User::find($this->userId) : null;
        $dipakai = array_filter(array_map(fn ($a) => (int) ($a['role_id'] ?? 0), $this->assignments));
        $dipakai[] = (int) $this->peranUtama;

        return view('livewire.access.user-form', [
            'user' => $user,
            'emailTerkunci' => $user?->email_verified_at !== null,
            // A-314: role lama (Driver) hanya tampil bagi user yang sudah memilikinya.
            'roles' => Role::query()->where('is_active', true)
                ->where(fn ($q) => $q->whereNotIn('code', Role::NOT_OFFERED)->orWhereIn('id', array_filter($dipakai)))
                ->orderBy('name')->get(),
            'pertanyaan' => $this->pertanyaanPeran(),
            'units' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::query()
                ->when($this->orgUnitId !== null, fn ($q) => $q->where('org_unit_id', $this->orgUnitId))
                ->orderBy('level')->orderBy('name')->get(),
            // A-345: siapa atasan bila isian manual dikosongkan.
            'atasanDariJabatan' => $this->atasanDariJabatan(),
            'managers' => $this->opsiAtasan(),
            'scopeTypes' => ScopeType::cases(),
            // Cakupan dipilih lewat nama, bukan id angka (11-master §12, 12-warehouse §12).
            'projects' => Project::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'clients' => Client::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            // Peran Klien hanya boleh melihat proyek kliennya sendiri (A-21).
            'proyekKlien' => $this->clientId === null
                ? collect()
                : Project::query()->active()->where('client_id', $this->clientId)
                    ->orderBy('code')->get(['id', 'code', 'name']),
            // withoutGlobalScopes: yang memberi cakupan harus melihat seluruh gudang,
            // termasuk yang di luar cakupannya sendiri.
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
            // Gudang Site tidak ditawarkan di sini: penempatan di site lewat Tim site (A-337).
            'gudangTetap' => Warehouse::withoutGlobalScopes()
                ->whereHas('type', fn ($q) => $q->where('code', '!=', WarehouseType::SITE))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}

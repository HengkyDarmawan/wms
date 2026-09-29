<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\AdoptAssignmentIntoSiteTeam;
use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Actions\SetInitialPassword;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Support\Atasan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 10-access §6.3 — detail pengguna: ringkasan, penugasan role, perangkat,
 * dan riwayat dari audit log (BR-GEN-05).
 */
class UserDetail extends Component
{
    /**
     * Terkunci: UserPolicy::view meluluskan user melihat dirinya sendiri, jadi
     * tanpa kunci ini siapa pun bisa mengganti id dari browser dan membaca
     * email, penugasan role, perangkat, serta riwayat pengguna lain.
     */
    #[Locked]
    public int $userId;

    public string $tab = 'ringkasan';

    public string $ruleError = '';

    /** Tautan undangan yang sedang ditampilkan; kosong = belum dibuka (A-333). */
    public string $tautan = '';

    public bool $formPassword = false;

    public string $passwordBaru = '';

    /** Password yang baru dibuatkan Admin; ditampilkan sekali (A-334). */
    public string $passwordDibuat = '';

    public function mount(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('view', $user);

        $this->userId = $userId;

        // A-334: password yang baru dibuatkan dari form pengguna ditampilkan
        // sekali di sini supaya bisa disalin atau dikirim lewat WhatsApp.
        $this->passwordDibuat = (string) session('passwordDibuat', '');
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['ringkasan', 'role', 'site', 'perangkat', 'riwayat'], true)
            ? $tab
            : 'ringkasan';
    }

    // ------------------------------------------------- undangan & password awal

    /** Undangan yang masih bisa dipakai, bila ada. */
    private function undangan(): ?UserInvitation
    {
        return UserInvitation::query()->where('user_id', $this->userId)
            ->pending()->latest('id')->first();
    }

    /**
     * A-333: tautan sengaja tidak langsung terpampang. Membukanya adalah
     * tindakan tersendiri supaya tercatat siapa yang pernah melihatnya.
     */
    public function tampilkanTautan(): void
    {
        $this->authorize('create', User::class);

        $undangan = $this->undangan();

        if ($undangan === null || ! $undangan->isUsable() || $undangan->url() === null) {
            $this->ruleError = __('Tautan undangan sudah tidak berlaku. Kirim ulang untuk membuat yang baru.');

            return;
        }

        $this->tautan = (string) $undangan->url();

        activity('access')
            ->performedOn(User::query()->findOrFail($this->userId))
            ->causedBy(auth()->user())
            ->log('Tautan undangan dilihat');
    }

    public function kirimUlangUndangan(InviteUser $action): void
    {
        $user = User::query()->findOrFail($this->userId);

        $this->authorize('invite', User::class);

        $this->ruleError = '';

        try {
            $this->tautan = (string) ($action->handle($user, auth()->user())->url() ?? '');
        } catch (AccessRuleException $e) {
            $this->ruleError = $e->getMessage();

            return;
        }

        $this->dispatch('pesan', teks: __('Undangan baru dibuat; tautan lama tidak berlaku lagi.'));
    }

    public function mintaPassword(): void
    {
        $user = User::query()->findOrFail($this->userId);

        $this->authorize('resetPassword', $user);

        $this->ruleError = '';
        $this->resetValidation();
        $this->formPassword = true;
        $this->passwordDibuat = '';
        $this->acakPassword();
    }

    /** Saran password yang mudah dibacakan lewat telepon. */
    public function acakPassword(): void
    {
        $kata = ['Palu', 'Semen', 'Pipa', 'Baja', 'Kabel', 'Beton', 'Gudang', 'Proyek'];

        $this->passwordBaru = $kata[array_rand($kata)].'-'.Str::upper(Str::random(4)).'-'.now()->year;
    }

    public function simpanPassword(SetInitialPassword $action): void
    {
        $user = User::query()->findOrFail($this->userId);

        $this->authorize('resetPassword', $user);

        $this->validate(
            ['passwordBaru' => ['required', 'string', 'min:10', 'max:100']],
            attributes: ['passwordBaru' => __('Password')],
        );

        $this->ruleError = '';

        try {
            $action->handle($user, $this->passwordBaru, auth()->user());
        } catch (AccessRuleException $e) {
            $this->ruleError = $e->getMessage();

            return;
        }

        $this->passwordDibuat = $this->passwordBaru;
        $this->passwordBaru = '';
        $this->formPassword = false;
        $this->tautan = '';

        $this->dispatch('pesan', teks: __('Password dibuat. Serahkan ke pemiliknya, lalu minta ia menggantinya.'));
    }

    /**
     * A-343: penugasan bertanggal peninggalan form lama dipindahkan ke Tim site
     * supaya periodenya terlihat dan bisa diperpanjang di tempat yang benar.
     */
    public function jadikanTimSite(int $assignmentId, AdoptAssignmentIntoSiteTeam $action): void
    {
        $this->authorize('role.assign');

        $assignment = RoleAssignment::query()->where('user_id', $this->userId)
            ->with('role')->findOrFail($assignmentId);

        $this->ruleError = '';

        try {
            $action->handle($assignment, auth()->user());
        } catch (AccessRuleException $e) {
            $this->ruleError = $e->getMessage();

            return;
        }

        $this->tab = 'site';
        $this->dispatch('pesan', teks: __('Penugasan dipindahkan ke Tim site.'));
    }

    public function render(): View
    {
        $user = User::with(['orgUnit', 'position', 'manager', 'devices', 'roleAssignments.role', 'roleAssignments.assignedBy'])
            ->findOrFail($this->userId);

        $atasan = app(Atasan::class);
        $atasanIds = $atasan->dari((int) $user->id);

        return view('livewire.access.user-detail', [
            'user' => $user,
            'atasanNama' => $atasanIds === [] ? [] : User::query()->whereIn('id', $atasanIds)->orderBy('name')->pluck('name')->all(),
            'atasanSumber' => $atasan->sumber($user),
            'riwayat' => $this->riwayat($user),
            // A-337: penempatan di site, hanya-lihat — diatur dari hub proyek.
            'penugasanSite' => ProjectTeamMember::query()->where('user_id', $user->id)
                ->with('project:id,code,name', 'role:id,name', 'endReason:id,label')
                ->orderByRaw('ended_at is null desc')->orderByDesc('ends_on')->get(),
            'bolehAtur' => auth()->user()?->hasPermission('role.assign') ?? false,
            'undangan' => auth()->user()?->hasPermission('user.create') ? $this->undangan() : null,
        ]);
    }

    /** @return Collection<int, Activity> */
    private function riwayat(User $user): Collection
    {
        if ($this->tab !== 'riwayat') {
            return collect();
        }

        return Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->latest('id')
            ->limit(50)
            ->get();
    }
}

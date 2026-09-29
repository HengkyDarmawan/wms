<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Actions\AddProjectTeamMember;
use App\Domain\Access\Actions\CreateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ClientContact;
use App\Domain\Master\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.create` + `role.assign`.
 *
 * Membuat akun portal dari data PIC klien (A-328). Akunnya sendiri **tanpa
 * tanggal**; yang berbatas waktu adalah keanggotaan Tim site per proyek, jadi
 * hak lihat portal mengikuti penempatan di site (A-339).
 *
 * BR-ACC-03 (role Klien tidak digabung role internal) dan BR-ACC-04 (cakupan
 * `all` tidak untuk role klien) ditegakkan `AssignRole` yang dipanggil di dalam.
 */
class CreatePortalAccountForContact
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly AddProjectTeamMember $addMember,
        private readonly SiteTeam $siteTeam,
    ) {}

    public function handle(ClientContact $contact, bool $kirimUndangan = true, ?User $actor = null): User
    {
        if ($contact->hasPortalAccount()) {
            throw MasterRuleException::rule('BR-ACC-03', 'PIC ini sudah punya akun portal.');
        }

        if (! $contact->is_active) {
            throw MasterRuleException::rule('BR-GEN-11', 'PIC nonaktif tidak bisa diberi akun portal.');
        }

        $email = trim((string) $contact->email);

        if ($email === '') {
            throw MasterRuleException::fields(
                ['email' => 'Email PIC wajib diisi dulu sebelum akun portal dibuat.'],
                'BR-GEN-11',
            );
        }

        if (User::query()->where('email', $email)->exists()) {
            throw MasterRuleException::fields(
                ['email' => 'Email ini sudah dipakai pengguna lain.'],
                'BR-SUB-06',
            );
        }

        $proyek = $contact->projects()->get();

        if ($proyek->isEmpty()) {
            throw MasterRuleException::fields(
                ['projects' => 'PIC ini belum mengurus proyek mana pun, jadi belum ada yang bisa ia lihat di portal.'],
                'BR-ACC-04',
            );
        }

        $role = Role::findByCode('client_user')
            ?? throw new AccessRuleException('Role Klien tidak ada di company ini.');

        $user = DB::transaction(function () use ($contact, $email, $proyek, $role, $kirimUndangan, $actor): User {
            $user = $this->createUser->handle(
                [
                    'name' => $contact->name,
                    'email' => $email,
                    'phone' => $contact->phone,
                    'client_id' => $contact->client_id,
                ],
                $proyek->map(fn (Project $p) => [
                    'role_id' => $role->id,
                    'scope_type' => ScopeType::Project->value,
                    'scope_id' => (int) $p->id,
                    'valid_from' => now()->toDateString(),
                    'valid_until' => self::selesai($p),
                ])->all(),
                $kirimUndangan,
                $actor,
            );

            $contact->forceFill(['user_id' => $user->id, 'updated_by' => $actor?->id])->save();

            $this->siteTeam->catatPenempatanAwal($user, $role, $proyek, $actor);

            return $user;
        });

        activity('master')
            ->performedOn($contact)
            ->causedBy($actor)
            ->withProperties(['user_id' => $user->id, 'proyek' => $proyek->pluck('code')->all()])
            ->log('Akun portal dibuat dari PIC klien');

        return $user->refresh();
    }

    /**
     * Menyelaraskan Tim site dengan proyek yang kini diurus PIC (A-339):
     * proyek baru ditambahkan, yang lama dibiarkan berjalan sampai tanggalnya.
     *
     * @return int jumlah keanggotaan baru
     */
    public function selaraskan(ClientContact $contact, ?User $actor = null): int
    {
        $user = $contact->portalUser;

        if ($user === null) {
            return 0;
        }

        $role = Role::findByCode('client_user');

        if ($role === null) {
            return 0;
        }

        $jumlah = 0;

        foreach ($this->belumJadiAnggota($contact) as $proyek) {
            $this->addMember->handle($proyek, $user, $role, now()->toDateString(), self::selesai($proyek), $actor);
            $jumlah++;
        }

        return $jumlah;
    }

    /**
     * Proyek yang diurus PIC tetapi belum punya keanggotaan Tim site berjalan.
     *
     * @return Collection<int, Project>
     */
    public function belumJadiAnggota(ClientContact $contact): Collection
    {
        $user = $contact->portalUser;

        if ($user === null) {
            return collect();
        }

        $sudah = ProjectTeamMember::query()->running()->where('user_id', $user->id)->pluck('project_id')
            ->map(fn ($id) => (int) $id)->all();

        return $contact->projects()->get()->reject(fn (Project $p) => in_array((int) $p->id, $sudah, true))->values();
    }

    /** Bawaan tanggal selesai: target selesai proyek, atau setahun dari sekarang. */
    private static function selesai(Project $project): string
    {
        return SiteTeam::selesaiBawaan($project);
    }
}

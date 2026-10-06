<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Atasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dijalankan sistem saat provisioning company (A-407), bukan oleh pengguna.
 *
 * Membuat **peta jabatan dasar** dan **satu akun dasar per role internal
 * utama**, agar aturan dasar approval (A-405) langsung punya sasaran:
 * pemohon/staf → Kepala Gudang → Manajemen.
 *
 * Akun dasar bernama sama dengan rolenya, tanpa password, dan **nonaktif**.
 * Mesin approval dan {@see Atasan} mengabaikan user
 * nonaktif, jadi tugas tidak nyangkut di akun yang belum dipegang orang.
 * Selama itu approval jatuh ke Admin Company (BR-APR-06). Admin mengganti
 * nama/email ke orang sebenarnya, mengaktifkan, lalu membuatkan password
 * (A-334) atau mengirim undangan.
 *
 * Idempoten: unit, jabatan, dan akun dengan kode/email yang sama dilewati.
 */
class InstallBasicOrganization
{
    public const UNIT = ['code' => 'PST', 'name' => 'Perusahaan'];

    /** kode jabatan => [nama, kode jabatan atasan, kode role]; urut atasan dulu. */
    public const JABATAN = [
        'MGT' => ['Manajemen', null, 'management'],
        'KGD' => ['Kepala Gudang', 'MGT', 'warehouse_head'],
        'SGD' => ['Staf Gudang', 'KGD', 'warehouse_staff'],
        'PMI' => ['Pemohon Internal', 'KGD', 'internal_requester'],
        'PPR' => ['Penindak Lanjut PR', 'MGT', 'pr_follow_up'],
        'AUI' => ['Auditor Internal', 'MGT', 'internal_auditor'],
    ];

    public function __construct(
        private readonly SaveOrgUnit $saveOrgUnit,
        private readonly SavePosition $savePosition,
        private readonly CreateUser $createUser,
    ) {}

    /** Email akun dasar, mis. `kepala-gudang@prv.wms`. */
    public static function email(string $namaRole, string $kodeCompany): string
    {
        return Str::slug($namaRole).'@'.Str::slug($kodeCompany).'.wms';
    }

    /** @return array<int, User> akun dasar yang baru dibuat */
    public function handle(string $kodeCompany): array
    {
        return DB::transaction(function () use ($kodeCompany): array {
            $unit = OrgUnit::query()->where('code', self::UNIT['code'])->first()
                ?? $this->saveOrgUnit->handle(null, self::UNIT);

            $jabatan = [];
            $dibuat = [];

            foreach (self::JABATAN as $kode => [$nama, $atasan, $kodeRole]) {
                $jabatan[$kode] = Position::query()->where('code', $kode)->first()
                    ?? $this->savePosition->handle(null, $unit, [
                        'code' => $kode,
                        'name' => $nama,
                        'reports_to_position_id' => $atasan !== null ? $jabatan[$atasan]->id : null,
                    ]);

                $role = Role::findByCode($kodeRole);
                $email = self::email($role?->name ?? $nama, $kodeCompany);

                if ($role === null || User::query()->where('email', $email)->exists()) {
                    continue;
                }

                $user = $this->createUser->handle([
                    'name' => $role->name,
                    'email' => $email,
                    'org_unit_id' => $unit->id,
                    'position_id' => $jabatan[$kode]->id,
                ], [['role_id' => $role->id, 'scope_type' => 'all', 'scope_id' => null]], false);

                $user->forceFill(['is_active' => false])->save();

                activity('access')->performedOn($user)->log('Akun dasar dibuat (nonaktif)');

                $dibuat[] = $user;
            }

            return $dibuat;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Actions\ImpersonateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Closure;
use Illuminate\Support\Collection;

/**
 * Panduan alur demo di layar "Masuk sebagai" (A-260): urutan peran per alur
 * utama, memakai istilah glosarium. Langkah ditulis dengan **kode role**, bukan
 * email, lalu dipetakan ke user aktif yang memenuhi syarat — sehingga tetap
 * berjalan di company mana pun, bukan hanya company DEMO.
 */
class DemoFlows
{
    /** @var array<int, array{key: string, title: string, icon: string, summary: string, steps: array<int, array{role: string, action: string}>}> */
    private const FLOWS = [
        [
            'key' => 'permintaan',
            'title' => 'Permintaan Material ke proyek',
            'icon' => 'bi-box-seam',
            'summary' => 'Dari pengajuan barang oleh pemohon sampai barang diterima di site.',
            'steps' => [
                ['role' => 'internal_requester', 'action' => 'Membuat & mengirim Permintaan Material (REQ)'],
                ['role' => 'warehouse_head', 'action' => 'Meninjau & menyetujui REQ'],
                ['role' => 'warehouse_staff', 'action' => 'Mengerjakan Tugas Picking (PCK) & Surat Jalan (SJ)'],
                ['role' => 'driver', 'action' => 'Mengantar barang & mengisi Bukti Terima'],
                ['role' => 'internal_requester', 'action' => 'Mengonfirmasi barang diterima'],
            ],
        ],
        [
            'key' => 'pembelian',
            'title' => 'Pembelian ke vendor',
            'icon' => 'bi-cart3',
            'summary' => 'Kebutuhan stok menjadi Purchase Request, dipesan, lalu diterima di gudang.',
            'steps' => [
                ['role' => 'warehouse_head', 'action' => 'Membuat Purchase Request (PRQ)'],
                ['role' => 'management', 'action' => 'Menyetujui PRQ'],
                ['role' => 'pr_follow_up', 'action' => 'Membuat Purchase Order (PO) ke vendor'],
                ['role' => 'warehouse_staff', 'action' => 'Mencatat Penerimaan Barang (GRN) & Put-away'],
            ],
        ],
        [
            'key' => 'opname',
            'title' => 'Stock Opname',
            'icon' => 'bi-clipboard-check',
            'summary' => 'Penghitungan fisik berkala sampai selisih disetujui.',
            'steps' => [
                ['role' => 'warehouse_head', 'action' => 'Membuat & memulai Stock Opname (OPN)'],
                ['role' => 'warehouse_staff', 'action' => 'Menghitung fisik (Hitung Buta)'],
                ['role' => 'warehouse_head', 'action' => 'Merekonsiliasi & menyetujui selisih'],
                ['role' => 'internal_auditor', 'action' => 'Memeriksa hasil opname'],
            ],
        ],
        [
            'key' => 'portal',
            'title' => 'Portal Klien',
            'icon' => 'bi-person-badge',
            'summary' => 'Pemilik proyek memesan dan memantau barang lewat /portal.',
            'steps' => [
                ['role' => 'client_user', 'action' => 'Mengajukan Permintaan Material dari portal'],
                ['role' => 'warehouse_head', 'action' => 'Meninjau & menyetujui REQ klien'],
                ['role' => 'client_user', 'action' => 'Mengonfirmasi Bukti Terima & memantau stok on-site'],
            ],
        ],
    ];

    /** Warna avatar per role (kelas `nx-imp-role-*` di pages.css). */
    public const ROLE_TONES = [
        'company_admin' => 'indigo',
        'management' => 'violet',
        'warehouse_head' => 'blue',
        'warehouse_staff' => 'teal',
        'driver' => 'orange',
        'internal_requester' => 'green',
        'pr_follow_up' => 'pink',
        'internal_auditor' => 'slate',
        'external_auditor' => 'slate',
        'client_user' => 'amber',
    ];

    public const ROLE_ICONS = [
        'company_admin' => 'bi-shield-check',
        'management' => 'bi-graph-up-arrow',
        'warehouse_head' => 'bi-person-gear',
        'warehouse_staff' => 'bi-box-seam',
        'driver' => 'bi-truck',
        'internal_requester' => 'bi-person-raised-hand',
        'pr_follow_up' => 'bi-cart3',
        'internal_auditor' => 'bi-search',
        'external_auditor' => 'bi-search',
        'client_user' => 'bi-person-badge',
    ];

    public static function tone(?string $roleCode): string
    {
        return self::ROLE_TONES[$roleCode] ?? 'slate';
    }

    public static function icon(?string $roleCode): string
    {
        return self::ROLE_ICONS[$roleCode] ?? 'bi-person';
    }

    /** Inisial avatar, mis. "Rina Admin" → "RA", "Lina (PT Klien Satu)" → "LP". */
    public static function initials(string $name): string
    {
        $kata = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(implode('', array_map(fn (string $k) => mb_substr($k, 0, 1), array_slice($kata, 0, 2))));
    }

    /**
     * Alur beserta user yang dipakai tiap langkah (wakil per role, perRole()).
     *
     * @param  Collection<int, array{user: User, codes: array<int, string>}>  $eligible  user yang boleh dipilih
     * @param  array<string, string>  $roleNames  kode role → nama
     * @return array<int, array{key: string, title: string, icon: string, summary: string, steps: array<int, array{role: string, roleName: string, action: string, user: User|null}>}>
     */
    public static function resolve(Collection $eligible, array $roleNames): array
    {
        $perRole = self::perRole($eligible);

        return array_map(fn (array $flow) => array_merge($flow, [
            'steps' => array_map(fn (array $step) => array_merge($step, [
                'roleName' => $roleNames[$step['role']] ?? $step['role'],
                'user' => $perRole[$step['role']] ?? null,
            ]), $flow['steps']),
        ]), self::FLOWS);
    }

    /**
     * Semua user company sebagai kartu pilihan: role berlaku beserta
     * cakupannya dan alasan bila tidak bisa dipilih (null = bisa).
     *
     * @return Collection<int, array{user: User, codes: array<int, string>, roles: array<int, array{code: string, name: string, scopes: string}>, reason: string|null}>
     */
    public static function candidates(User $actor): Collection
    {
        $action = app(ImpersonateUser::class);
        $users = User::query()->with('roleAssignments.role')->orderBy('name')->get();
        $label = self::scopeLabeler($users->flatMap->roleAssignments);

        return $users->map(function (User $user) use ($actor, $action, $label): array {
            $berlaku = $user->roleAssignments
                ->filter(fn (RoleAssignment $a) => $a->isValid() && $a->role !== null && $a->role->is_active);
            $codes = $berlaku->map(fn (RoleAssignment $a) => $a->role->code)->unique()->values()->all();

            return [
                'user' => $user,
                'codes' => $codes,
                'roles' => $berlaku->groupBy(fn (RoleAssignment $a) => $a->role->code)
                    ->map(fn (Collection $items, string $code) => [
                        'code' => $code,
                        'name' => $items->first()->role->name,
                        'scopes' => $items->map($label)->unique()->implode(', '),
                    ])->values()->all(),
                'reason' => $action->ineligibleReason($actor, $user, $codes),
            ];
        });
    }

    /**
     * Satu user wakil per kode role, diisi berurutan menurut urutan role
     * bawaan: tiap role memilih user ber-id terkecil yang belum mewakili role
     * lain. Jadi Kepala Gudang CKG yang juga staf BKS tetap mewakili Kepala
     * Gudang, dan langkah "Staf Gudang" jatuh ke staf murni — alur demo tetap
     * di gudang yang sama seperti urutan akun di 00-akun-uji.
     *
     * @param  Collection<int, array{user: User, codes: array<int, string>}>  $eligible
     * @return array<string, User>
     */
    public static function perRole(Collection $eligible): array
    {
        $calon = $eligible->sortBy(fn (array $c) => $c['user']->id)->values();
        $kodeRole = $calon->flatMap(fn (array $c) => $c['codes'])->unique()
            ->sortBy(function (string $code): int {
                $urutan = array_search($code, array_keys(self::ROLE_TONES), true);

                return $urutan === false ? PHP_INT_MAX : $urutan;
            });

        $perRole = [];
        $terpakai = [];

        foreach ($kodeRole as $code) {
            $pemegang = $calon->filter(fn (array $c) => in_array($code, $c['codes'], true));
            $pilih = $pemegang->first(fn (array $c) => ! isset($terpakai[$c['user']->id])) ?? $pemegang->first();

            $perRole[$code] = $pilih['user'];
            $terpakai[$pilih['user']->id] = true;
        }

        return $perRole;
    }

    /**
     * Pemberi label cakupan dengan kode gudang/proyek (mis. "CKG", "PRJ-001")
     * untuk sekumpulan penugasan — dua kueri untuk semuanya, bukan per baris.
     *
     * @param  Collection<int, RoleAssignment>  $assignments
     * @return Closure(RoleAssignment): string
     */
    public static function scopeLabeler(Collection $assignments): Closure
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()
            ->whereIn('id', $assignments->where('scope_type', ScopeType::Warehouse)->pluck('scope_id')->unique())
            ->pluck('code', 'id');
        $proyek = Project::query()->withoutGlobalScopes()
            ->whereIn('id', $assignments->where('scope_type', ScopeType::Project)->pluck('scope_id')->unique())
            ->pluck('code', 'id');

        return fn (RoleAssignment $a): string => match ($a->scope_type) {
            ScopeType::Warehouse => $gudang[$a->scope_id] ?? $a->describeScope(),
            ScopeType::Project => $proyek[$a->scope_id] ?? $a->describeScope(),
            default => ScopeType::All->label(),
        };
    }

    /**
     * Penugasan berlaku milik satu user sebagai "Role · cakupan" untuk spanduk.
     *
     * @return array<int, string>
     */
    public static function describe(User $user): array
    {
        $assignments = $user->validAssignments();
        $label = self::scopeLabeler($assignments);

        return $assignments->groupBy(fn (RoleAssignment $a) => $a->role->name)
            ->map(fn (Collection $items, string $role) => $role.' · '.$items->map($label)->unique()->implode(', '))
            ->values()->all();
    }
}

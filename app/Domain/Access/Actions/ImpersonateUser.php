<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\CompanyAdminGuard;
use App\Domain\Access\Support\Impersonation;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsureSubscriptionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Masuk sebagai" (permission `user.impersonate`, A-260): Admin Company bekerja
 * atas nama user lain untuk menunjukkan alur lintas peran tanpa keluar-masuk.
 * Admin asli disimpan di sesi; setiap audit log selama itu diberi
 * `impersonated_by` (AccessServiceProvider). Ganti langsung ke user lain
 * boleh — izinnya selalu diperiksa terhadap Admin asli, bukan user yang
 * sedang dipakai. `stop()` mengembalikan sesi ke Admin (pola A-73).
 */
class ImpersonateUser
{
    public function __construct(private readonly CompanyAdminGuard $adminGuard) {}

    /**
     * Alasan user tidak bisa dipilih, atau null bila boleh. Dipakai juga oleh
     * layar pemilih supaya kartu yang tidak memenuhi syarat tampil pudar.
     *
     * @param  array<int, string>|null  $roleCodes  kode role berlaku yang sudah dimuat (hindari kueri ulang)
     */
    public function ineligibleReason(User $actor, User $target, ?array $roleCodes = null): ?string
    {
        $roleCodes ??= $target->roleCodes();

        return match (true) {
            $actor->is($target) => __('Ini akun Anda sendiri.'),
            in_array(CompanyAdminGuard::ROLE_CODE, $roleCodes, true) => __('Sesama Admin Company.'),
            ! $target->is_active => __('User nonaktif.'),
            $target->isLocked() => __('Akun sedang terkunci.'),
            $roleCodes === [] => __('Belum punya penugasan role yang berlaku.'),
            default => null,
        };
    }

    public function handle(Request $request, User $target): User
    {
        $actor = Impersonation::impersonator() ?? $request->user();

        if (! $actor instanceof User || ! $actor->is_active || ! $actor->hasPermission('user.impersonate')) {
            throw AccessRuleException::rule('A-260', __('Hanya Admin Company yang bisa masuk sebagai user lain.'));
        }

        if ($request->session()->has(StartSupportSession::SESSION_KEY)) {
            throw AccessRuleException::rule('BR-SUB-04', __('Sesi akses dukungan tidak bisa masuk sebagai user lain.'));
        }

        if ($this->subscriptionTerminated()) {
            throw AccessRuleException::rule('BR-SUB-03', __('Langganan sudah diakhiri: hanya Admin Company yang boleh masuk.'));
        }

        if ($request->user()?->is($target)) {
            throw AccessRuleException::rule('A-260', __('Anda sudah masuk sebagai :nama.', ['nama' => $target->name]));
        }

        $alasan = $this->ineligibleReason($actor, $target);

        if ($alasan !== null) {
            throw AccessRuleException::rule($target->validAssignments()->isEmpty() ? 'BR-ACC-01' : 'A-260', $alasan);
        }

        $sebelumnya = $request->user();

        if ($sebelumnya instanceof User && ! $sebelumnya->is($actor)) {
            activity('access')->performedOn($sebelumnya)->causedBy($actor)
                ->log('Admin Company berpindah dari pengguna ini (masuk sebagai)');
        }

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put(Impersonation::SESSION_IMPERSONATOR_ID, $actor->id);
        $request->session()->put(Impersonation::SESSION_IMPERSONATOR_NAME, $actor->name);

        activity('access')->performedOn($target)->causedBy($actor)
            ->log('Admin Company masuk sebagai pengguna ini');

        return $target;
    }

    /**
     * Kembali ke Admin asli. Bila Admin itu sudah tidak aktif atau bukan lagi
     * Admin Company, sesi diakhiri seluruhnya (null).
     */
    public function stop(Request $request): ?User
    {
        if (! Impersonation::active()) {
            return null;
        }

        $admin = Impersonation::impersonator();
        $sekarang = $request->user();

        if ($sekarang instanceof User) {
            activity('access')->performedOn($sekarang)->causedBy($admin)
                ->log('Admin Company kembali dari masuk sebagai pengguna ini');
        }

        $request->session()->forget([Impersonation::SESSION_IMPERSONATOR_ID, Impersonation::SESSION_IMPERSONATOR_NAME]);

        if ($admin === null || ! $admin->is_active || ! $this->adminGuard->isCompanyAdmin($admin)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return $admin;
    }

    private function subscriptionTerminated(): bool
    {
        $company = tenant();

        return $company !== null
            && app(EnsureSubscriptionState::class)->effectiveStatus($company) === SubscriptionStatus::Terminated;
    }
}

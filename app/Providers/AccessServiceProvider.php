<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Livewire\DeviceList;
use App\Domain\Access\Livewire\OrgTree;
use App\Domain\Access\Livewire\RoleForm;
use App\Domain\Access\Livewire\RoleList;
use App\Domain\Access\Livewire\SupportAccessManager;
use App\Domain\Access\Livewire\UserDetail;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Livewire\UserList;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Policies\DevicePolicy;
use App\Domain\Access\Policies\OrgUnitPolicy;
use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Access\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Modul Access: sumber migrasi pusat, integrasi permission ke Gate (AD-06),
 * dan pendaftaran policy.
 */
class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Migrasi database pusat; migrasi tenant dijalankan lewat `tenants:migrate`
        // dengan --path dari config/tenancy.php (Arsitektur §4).
        $this->loadMigrationsFrom(database_path('migrations/central'));

        $this->registerPermissionGate();
        $this->registerPolicies();
        $this->registerRateLimiters();
        $this->registerLivewireComponents();
    }

    /**
     * Komponen Livewire modul Access. Didaftarkan manual karena kelasnya berada
     * di folder domain, bukan App\Livewire (AD-02).
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('access.user-list', UserList::class);
        Livewire::component('access.user-form', UserForm::class);
        Livewire::component('access.user-detail', UserDetail::class);
        Livewire::component('access.role-list', RoleList::class);
        Livewire::component('access.role-form', RoleForm::class);
        Livewire::component('access.org-tree', OrgTree::class);
        Livewire::component('access.device-list', DeviceList::class);
        Livewire::component('access.support-access', SupportAccessManager::class);
    }

    /**
     * NFR-04: batas laju percobaan masuk per menit (email + IP).
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute((int) config('access.login.throttle_per_minute', 5))->by($key);
        });
    }

    /**
     * Permission `<modul>.<aksi>` diambil dari penugasan role yang berlaku
     * (BR-GEN-09, BR-ACC-05). Mengembalikan null agar Policy tetap dievaluasi
     * ketika user tidak punya permission tersebut.
     */
    protected function registerPermissionGate(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }

    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(OrgUnit::class, OrgUnitPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
    }
}

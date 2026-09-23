<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Platform\Models\Company;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

/**
 * Tenancy multi-database (D-03, AD-01). Nama database tenant diambil dari
 * kolom `companies.db_name`; identifikasi tenant lewat subdomain ditangani
 * App\Http\Middleware\InitializeTenancyBySubdomain (A-01, Arsitektur §3).
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nama database tenant = companies.db_name; cadangan memakai prefiks konfigurasi.
        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant) {
            /** @var Company $tenant */
            return $tenant->db_name
                ?: config('tenancy.database.prefix').$tenant->getTenantKey();
        });

        $this->bootEvents();
    }

    protected function events(): array
    {
        return [
            // Pembuatan company: buat database lalu jalankan migrasi tenant (Arsitektur §3.5).
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                ])->send(fn (Events\TenantCreated $event) => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(fn (Events\TenantDeleted $event) => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            // Siklus tenancy.
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],
        ];
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }
}

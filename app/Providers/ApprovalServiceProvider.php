<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Console\EscalateApprovalsCommand;
use App\Domain\Approval\Livewire\ApprovalSimulation;
use App\Domain\Approval\Livewire\DelegationManager;
use App\Domain\Approval\Livewire\RuleForm;
use App\Domain\Approval\Livewire\RuleList;
use App\Domain\Approval\Livewire\TaskInbox;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Policies\ApprovalDelegationPolicy;
use App\Domain\Approval\Policies\ApprovalRulePolicy;
use App\Domain\Approval\Policies\ApprovalTaskPolicy;
use App\Domain\Approval\Support\ApprovalRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Modul Approval — mesin bersama (D-28, A-92). Registry penangan dipasang
 * sebagai singleton di `register()` supaya modul dokumen (Request, Receipt,
 * dan kelak Adjustment, Count, PurchaseRequest, Conversion, Purchasing) bisa
 * mendaftar dari `boot()` provider masing-masing tanpa urutan tertentu.
 */
class ApprovalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApprovalRegistry::class);
    }

    public function boot(): void
    {
        Gate::policy(ApprovalRule::class, ApprovalRulePolicy::class);
        Gate::policy(ApprovalTask::class, ApprovalTaskPolicy::class);
        Gate::policy(ApprovalDelegation::class, ApprovalDelegationPolicy::class);

        // Kotak tugas: siapa pun yang memegang permission approve salah satu
        // jenis dokumen, yang sedang memegang tugas (delegat/eskalasi), atau
        // yang boleh mengeskalasi (A-86).
        Gate::define('approval-inbox', function (User $user): bool {
            if ($user->client_id !== null) {
                return false;
            }

            foreach (app(ApprovalRegistry::class)->approvePermissions() as $izin) {
                if ($user->hasPermission($izin)) {
                    return true;
                }
            }

            return $user->hasPermission('approval.escalate')
                || ApprovalTask::query()->open()->where('approver_user_id', $user->id)->exists();
        });

        Livewire::component('approval.task-inbox', TaskInbox::class);
        Livewire::component('approval.rule-list', RuleList::class);
        Livewire::component('approval.rule-form', RuleForm::class);
        Livewire::component('approval.delegations', DelegationManager::class);
        Livewire::component('approval.simulation', ApprovalSimulation::class);

        if ($this->app->runningInConsole()) {
            $this->commands([EscalateApprovalsCommand::class]);
        }
    }
}

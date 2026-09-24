<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Issue\Livewire\IssueDetail;
use App\Domain\Issue\Livewire\IssueForm;
use App\Domain\Issue\Livewire\IssueList;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Policies\MaterialIssuePolicy;
use App\Domain\Issue\Support\MaterialIssueApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Issue (ISU, pemakaian material di site): policy, penangan approval ISU pembalik, komponen Livewire (AD-02, 23-pemakaian). */
class IssueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(MaterialIssue::class, MaterialIssuePolicy::class);

        // Hanya ISU pembalik yang lewat mesin approval, minimal satu lapis (BR-GEN-04, A-150).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::MaterialIssue, MaterialIssueApprovalHandler::class);

        Livewire::component('issue.issue-list', IssueList::class);
        Livewire::component('issue.issue-form', IssueForm::class);
        Livewire::component('issue.issue-detail', IssueDetail::class);
    }
}

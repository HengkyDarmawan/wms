<?php

declare(strict_types=1);

namespace App\Http\Controllers\Approval;

use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Models\ApprovalRule;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman modul Approval (20-approval §6). Semua GET; keputusan lewat Livewire (POST). */
class ApprovalController extends Controller
{
    public function inbox(): View
    {
        $this->authorize('approval-inbox');

        return view('approval.inbox');
    }

    public function rules(): View
    {
        $this->authorize('viewAny', ApprovalRule::class);

        return view('approval.rules');
    }

    public function createRule(): View
    {
        $this->authorize('create', ApprovalRule::class);

        return view('approval.rule-form', ['rule' => null]);
    }

    public function editRule(ApprovalRule $approvalRule): View
    {
        $this->authorize('update', $approvalRule);

        return view('approval.rule-form', ['rule' => $approvalRule]);
    }

    public function delegations(): View
    {
        $this->authorize('viewAny', ApprovalDelegation::class);

        return view('approval.delegations');
    }

    public function simulation(): View
    {
        $this->authorize('approval.simulate');

        return view('approval.simulation');
    }
}

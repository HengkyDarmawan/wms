<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issue;

use App\Domain\Issue\Models\MaterialIssue;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman pemakaian material (23-pemakaian §6). Semua GET; transisi lewat Livewire (POST). */
class IssueController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MaterialIssue::class);

        return view('issue.index');
    }

    public function create(): View
    {
        $this->authorize('create', MaterialIssue::class);

        return view('issue.create');
    }

    public function edit(MaterialIssue $materialIssue): View
    {
        $this->authorize('update', $materialIssue);

        return view('issue.edit', ['isu' => $materialIssue]);
    }

    public function show(MaterialIssue $materialIssue): View
    {
        $this->authorize('view', $materialIssue);

        return view('issue.show', ['isu' => $materialIssue]);
    }
}

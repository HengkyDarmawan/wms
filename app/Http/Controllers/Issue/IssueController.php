<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issue;

use App\Domain\Issue\Actions\AttachIssuePhoto;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman pemakaian material (23-pemakaian §6). Halaman lewat GET; transisi
 * lewat Livewire (POST). Foto pemakaian lewat POST form biasa karena memuat
 * berkas (seperti pemeriksaan aset — unggahan Livewire di luar middleware tenant).
 */
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

    public function attachPhoto(Request $request, MaterialIssue $materialIssue, AttachIssuePhoto $action): RedirectResponse
    {
        $this->authorize('attachPhoto', $materialIssue);

        $request->validate([
            'photo' => ['required', ...StoreUpload::ATURAN_FOTO],
        ], attributes: ['photo' => __('Foto')]);

        try {
            $action->handle($materialIssue, $request->file('photo'), $request->user());
        } catch (IssueRuleException|\RuntimeException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        return redirect()->route('issues.show', $materialIssue)->with('pesan', __('Foto pemakaian ditambahkan.'));
    }
}

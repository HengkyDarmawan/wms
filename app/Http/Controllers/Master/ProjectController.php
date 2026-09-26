<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Project;
use App\Domain\Transfer\Models\Transfer;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman proyek (11-master §6). */
class ProjectController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Project::class);

        return view('master.projects.index');
    }

    /** Hub proyek (Blueprint §6.9, A-228). Cakupan proyek di luar jangkauan = 404 lewat global scope. */
    public function show(Project $project): View
    {
        // BR-ACC-05: di luar cakupan proyek pengguna = tidak ada (404), sama dengan dokumen berlingkup.
        abort_unless(request()->user()->canAccessProject((int) $project->id), 404);
        $this->authorize('view', $project);

        return view('master.projects.show', ['project' => $project]);
    }

    /** Pindahkan sisa proyek ke proyek lain (A-250): TRF aset On-site + TRF stok Gudang Site. */
    public function move(Project $project): View
    {
        abort_unless(request()->user()->canAccessProject((int) $project->id), 404);
        $this->authorize('create', Transfer::class);

        return view('master.projects.move', ['project' => $project]);
    }
}

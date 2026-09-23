<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Project;
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
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Master\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Portal klien: rencana material proyek (A-433, 34-rencana-material). */
class PortalProjectPlanController extends Controller
{
    public function __invoke(Project $project): View
    {
        $this->authorize('viewPlan', $project);

        return view('portal.projects.plan', ['project' => $project]);
    }
}

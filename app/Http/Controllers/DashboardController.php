<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Master\Support\SetupWizard;
use App\Domain\Shared\Support\WorkQueue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Beranda back-office: kartu pekerjaan menunggu sesuai izin & cakupan user
 * (A-186), konteks company, dan penugasan role.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, WorkQueue $queue, SetupWizard $wizard): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'assignments' => $user->validAssignments(),
            'company' => tenant(),
            'cards' => $queue->for($user),
            // A-191: pengingat wizard setup untuk pengelola pengaturan company.
            'setup' => $user->can('company_setting.manage') && ! $wizard->isCompleted() ? $wizard->progress() : null,
        ]);
    }
}

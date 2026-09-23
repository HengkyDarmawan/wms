<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\OrgUnit;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman struktur organisasi (10-access §6.5).
 */
class OrgController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', OrgUnit::class);

        return view('access.org.index');
    }
}

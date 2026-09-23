<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman akses dukungan Super Admin (10-access §6.7, A-27, BR-SUB-04).
 */
class SupportAccessController extends Controller
{
    public function index(): View
    {
        $this->authorize('support_access.grant');

        return view('access.support-access.index');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Request;

use App\Domain\Request\Models\MaterialRequest;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman REQ di portal klien (14-request §6). */
class PortalRequestController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        return view('portal.requests.index');
    }

    public function show(MaterialRequest $materialRequest): View
    {
        $this->authorize('view', $materialRequest);

        return view('portal.requests.show', ['req' => $materialRequest]);
    }
}

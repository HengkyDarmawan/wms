<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman klien (11-master §6). */
class ClientController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Client::class);

        return view('master.clients.index');
    }

    /** Halaman detail klien (A-327): proyek, PIC klien, dan akun portalnya. */
    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        return view('master.clients.show', ['client' => $client]);
    }
}

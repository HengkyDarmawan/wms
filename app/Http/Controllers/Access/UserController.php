<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman pengelolaan pengguna (10-access §6.3). Isinya komponen Livewire;
 * controller hanya menyiapkan halaman dan otorisasi awal.
 */
class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('access.users.index');
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('access.users.form', ['userId' => null]);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('access.users.show', ['userId' => $user->id]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('access.users.form', ['userId' => $user->id]);
    }
}

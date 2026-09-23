<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman pengelolaan role (10-access §6.4).
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('access.roles.index');
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('access.roles.form', ['roleId' => null]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('access.roles.form', ['roleId' => $role->id]);
    }
}

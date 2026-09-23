<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\Device;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman perangkat terdaftar (10-access §6.6).
 */
class DeviceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Device::class);

        return view('access.devices.index');
    }
}

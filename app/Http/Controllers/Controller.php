<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    /** Policy & Gate dipakai di semua modul (AD-06, BR-GEN-09). */
    use AuthorizesRequests;
    use ValidatesRequests;
}

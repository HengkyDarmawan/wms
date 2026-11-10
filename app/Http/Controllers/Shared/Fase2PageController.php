<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman pembungkus komponen Fase 2 (35-pendukung-f2-lanjutan). Izin diperiksa
 * di komponen Livewire masing-masing (mount).
 */
class Fase2PageController extends Controller
{
    public function consolidation(): View
    {
        return view('shared.consolidation');
    }

    public function rfidTags(): View
    {
        return view('rfid-tags');
    }

    public function whatsappSimulator(): View
    {
        return view('whatsapp-simulator');
    }

    public function offlineReview(): View
    {
        return view('offline.review');
    }
}

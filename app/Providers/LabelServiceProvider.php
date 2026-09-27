<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Label\Livewire\LabelTrace;
use App\Domain\Label\Livewire\ReceiptLabels;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Label kemasan induk/isi & penelusuran (A-296–A-303): komponen Livewire (AD-02). */
class LabelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('label.receipt-labels', ReceiptLabels::class);
        Livewire::component('label.label-trace', LabelTrace::class);
    }
}

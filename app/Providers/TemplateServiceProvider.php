<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Template\Livewire\DocumentLayoutForm;
use App\Domain\Template\Livewire\LabelPrint;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Template dokumen & label: komponen Livewire (AD-02, 18-template-dokumen-label). */
class TemplateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('template.document-layout-form', DocumentLayoutForm::class);
        Livewire::component('template.label-print', LabelPrint::class);
    }
}

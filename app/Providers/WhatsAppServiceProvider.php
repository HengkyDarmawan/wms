<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\WhatsApp\Console\SimulateWhatsAppReplyCommand;
use App\Domain\WhatsApp\Livewire\WhatsAppCompanySettings;
use App\Domain\WhatsApp\Livewire\WhatsAppNumber;
use App\Domain\WhatsApp\Transport\CloudTransport;
use App\Domain\WhatsApp\Transport\LogTransport;
use App\Domain\WhatsApp\Transport\NullTransport;
use App\Domain\WhatsApp\Transport\WhatsAppTransport;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul WhatsApp Fase 2a (31-whatsapp, AD-02): transport dari konfigurasi, komponen Livewire, perintah simulasi. */
class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppTransport::class, fn () => match (config('wms.whatsapp.driver')) {
            'cloud' => new CloudTransport((array) config('wms.whatsapp')),
            'log' => new LogTransport,
            default => new NullTransport,
        });
    }

    public function boot(): void
    {
        Livewire::component('whatsapp.number', WhatsAppNumber::class);
        Livewire::component('whatsapp.company-settings', WhatsAppCompanySettings::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SimulateWhatsAppReplyCommand::class]);
        }
    }
}

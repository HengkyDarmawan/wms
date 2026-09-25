<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Platform\Console\RunSubscriptionCycleCommand;
use Illuminate\Support\ServiceProvider;

/** Modul Platform (17-platform-login): perintah siklus langganan (AD-02). */
class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunSubscriptionCycleCommand::class]);
        }
    }
}

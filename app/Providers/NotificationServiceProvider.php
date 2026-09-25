<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notification\Console\SendDailyNotificationsCommand;
use Illuminate\Support\ServiceProvider;

/** Modul notifikasi in-app & email (27-pendukung-f1 §2): perintah pengingat harian. */
class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendDailyNotificationsCommand::class]);
        }
    }
}

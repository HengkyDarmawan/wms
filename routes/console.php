<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Approval: eskalasi tugas lewat batas waktu / approver nonaktif dan
// pemindahan tugas ke delegat, semua company (BR-APR-05/06/08, A-90).
Schedule::command('approval:escalate')->hourly()->withoutOverlapping();

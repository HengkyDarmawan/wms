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

// Purchase Request: draf PRQ titik pesan ulang tiap pagi, semua company (BR-REQ-11).
Schedule::command('purchase-requests:reorder')->dailyAt('06:00')->withoutOverlapping();

// Platform: siklus langganan harian — tagihan, jatuh tempo, penangguhan, pengakhiran (BR-SUB-01, A-177).
Schedule::command('subscriptions:cycle')->dailyAt('00:30')->withoutOverlapping();

// Request: bukti terima tanpa tanggapan pemohon lewat batas → dikonfirmasi otomatis (BR-REQ-10).
Schedule::command('deliveries:auto-confirm')->dailyAt('01:00')->withoutOverlapping();

// Notifikasi: pengingat harian aset lewat jatuh tempo (BR-AST-06, A-189).
Schedule::command('notifications:daily')->dailyAt('07:00')->withoutOverlapping();

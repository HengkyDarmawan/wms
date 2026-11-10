<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-432 (BR-AST-07): pemicu servis dari meter — jadwal boleh berinterval meter
 * (jam/km). Jatuh tempo meter = meter saat dijadwalkan/selesai + interval;
 * yang lebih dulu tercapai (tanggal atau meter) yang berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table) {
            $table->decimal('interval_meter', 12, 1)->nullable()->after('interval_days');
            $table->decimal('due_meter', 12, 1)->nullable()->after('interval_meter');
            $table->decimal('meter_done', 12, 1)->nullable()->after('performed_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', fn (Blueprint $table) => $table->dropColumn(['interval_meter', 'due_meter', 'meter_done']));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-OPN-02, A-240: override Kepala Gudang untuk SJ mendesak — PCK boleh
 * mengambil dari bin yang dibeku opname. Siapa, kapan, dan alasannya melekat
 * pada PCK supaya tercatat di dokumen, bukan hanya di jejak audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_tasks', function (Blueprint $table) {
            $table->foreignId('freeze_override_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('freeze_override_at')->nullable()->after('freeze_override_by');
            $table->string('freeze_override_reason', 255)->nullable()->after('freeze_override_at');
        });
    }

    public function down(): void
    {
        Schema::table('pick_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('freeze_override_by');
            $table->dropColumn(['freeze_override_at', 'freeze_override_reason']);
        });
    }
};

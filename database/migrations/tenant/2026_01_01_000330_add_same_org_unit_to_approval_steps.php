<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-269: lapis approval berjenis Role/Jabatan bisa dibatasi pada divisi
 * pemohon (unit organisasinya atau unit induknya). Bawaan menyala.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->boolean('same_org_unit')->default(true)->after('decision_mode');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('same_org_unit');
        });
    }
};

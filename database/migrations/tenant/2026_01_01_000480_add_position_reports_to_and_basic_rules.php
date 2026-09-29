<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atasan ditentukan jabatan & approval sederhana (A-344–A-350).
 *
 * - `positions.reports_to_position_id` — peta jabatan: jabatan ini melapor ke
 *   jabatan mana. Level jabatan dihitung dari peta ini (tanpa atasan = 1).
 * - `approval_steps.manager_levels` — lapis "Atasan langsung" 1 atau 2 tingkat;
 *   kosong = 1 (data lama tidak berubah makna).
 * - `approval_rules.is_basic` — aturan hasil tombol *Pasang aturan dasar*;
 *   aturan biasa yang tetap bisa diubah/dinonaktifkan, hanya diberi tanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('reports_to_position_id')->nullable()->after('org_unit_id')
                ->constrained('positions')->restrictOnDelete();
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('manager_levels')->nullable()->after('approver_ref_id');
        });

        Schema::table('approval_rules', function (Blueprint $table) {
            $table->boolean('is_basic')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('approval_rules', function (Blueprint $table) {
            $table->dropColumn('is_basic');
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('manager_levels');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_position_id');
        });
    }
};

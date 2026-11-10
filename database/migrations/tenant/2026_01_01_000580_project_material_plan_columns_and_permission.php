<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-428 (34-rencana-material): rencana material proyek dibangun di atas tabel
 * stub `project_material_plans`, plus izin `project.plan` untuk company yang
 * sudah ada. Izin hanya DITAMBAHKAN ke role bawaan (tanpa sync).
 */
return new class extends Migration
{
    private const ROLE_PENERIMA = ['company_admin', 'management', 'internal_requester'];

    public function up(): void
    {
        Schema::table('project_material_plans', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('is_current')->constrained('users')->nullOnDelete();
            $table->string('source_file', 150)->nullable()->after('created_by');
            $table->index(['project_id', 'is_current']);
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'project.plan')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'project.plan',
                'guard_name' => 'web',
                'module' => 'master',
                'label' => 'Kelola rencana material proyek',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->whereIn('code', self::ROLE_PENERIMA)->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::table('project_material_plans', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'is_current']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source_file');
        });
        // Baris izin dibiarkan (P-03).
    }
};

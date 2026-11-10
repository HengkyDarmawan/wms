<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-431 (35-pendukung-f2-lanjutan §1): resep konversi dibangun di atas tabel
 * stub `conversion_recipes`; izin `conversion.recipe` untuk company lama hanya
 * DITAMBAHKAN ke role bawaan (tanpa sync).
 */
return new class extends Migration
{
    private const ROLE_PENERIMA = ['company_admin', 'warehouse_head'];

    public function up(): void
    {
        Schema::table('conversion_recipes', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'conversion.recipe')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'conversion.recipe',
                'guard_name' => 'web',
                'module' => 'conversion',
                'label' => 'Kelola resep konversi',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->whereIn('code', self::ROLE_PENERIMA)->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::table('conversion_recipes', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
    }
};

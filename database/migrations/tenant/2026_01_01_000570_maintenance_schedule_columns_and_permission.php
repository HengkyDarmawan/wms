<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-423 (33-pendukung-f2): jadwal servis aset dibangun di atas tabel stub
 * `maintenance_schedules`, plus izin `asset.maintain` untuk company yang sudah
 * ada. Izin hanya DITAMBAHKAN ke role bawaan (tanpa sync), supaya perubahan
 * role oleh Admin Company tidak tertimpa.
 */
return new class extends Migration
{
    private const ROLE_PENERIMA = ['company_admin', 'warehouse_head', 'warehouse_staff'];

    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status');   // maintenance_source
            $table->foreignId('origin_bin_id')->nullable()->after('source')->constrained('bins')->nullOnDelete();
            $table->foreignId('started_movement_id')->nullable()->after('origin_bin_id')->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('finished_movement_id')->nullable()->after('started_movement_id')->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->index(['serial_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'asset.maintain')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'asset.maintain',
                'guard_name' => 'web',
                'module' => 'asset',
                'label' => 'Atur servis aset',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->whereIn('code', self::ROLE_PENERIMA)->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table) {
            $table->dropIndex(['serial_id', 'status']);
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropConstrainedForeignId('origin_bin_id');
            $table->dropConstrainedForeignId('started_movement_id');
            $table->dropConstrainedForeignId('finished_movement_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source');
        });
        // Baris izin dibiarkan (P-03); izin tak terpakai tidak memberi akses apa pun.
    }
};

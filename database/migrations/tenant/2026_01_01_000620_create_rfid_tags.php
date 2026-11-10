<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-437 (Blueprint §6.10 RFID `[F2]`): tag RFID dipetakan ke item, serial,
 * potongan, atau bin. Reader mode keyboard wedge mengetik EPC seperti pemindai
 * barcode; EPC diterjemahkan di ScanCode & BinCode. Izin `rfid.manage` untuk
 * company lama hanya DITAMBAHKAN (tanpa sync).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_tags', function (Blueprint $table) {
            $table->id();
            $table->string('epc', 64)->unique();                 // kode tag (heksadesimal), huruf besar
            $table->string('target_type', 10);                   // rfid_target: item|serial|piece|bin
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'rfid.manage')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => 'rfid.manage', 'guard_name' => 'web', 'module' => 'label', 'label' => 'Kelola tag RFID', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table('roles')->whereIn('code', ['company_admin', 'warehouse_head'])->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_tags');
    }
};

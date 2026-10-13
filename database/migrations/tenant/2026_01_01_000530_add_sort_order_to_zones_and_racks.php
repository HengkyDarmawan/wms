<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tab Zona & rak (A-410): urutan tampil zona & rak bisa digeser manual (↑↓)
 * mengikuti kenyataan di gudang. Kolom baru berisi nomor urut; data lama
 * diberi urutan sesuai kode supaya tampilan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['zones', 'racks'] as $tabel) {
            if (! Schema::hasColumn($tabel, 'sort_order')) {
                Schema::table($tabel, function (Blueprint $table) {
                    $table->unsignedInteger('sort_order')->default(0)->after('code');
                });
            }
        }

        foreach (DB::table('zones')->orderBy('warehouse_id')->orderBy('code')->get(['id', 'warehouse_id'])->groupBy('warehouse_id') as $zona) {
            foreach ($zona->values() as $i => $z) {
                DB::table('zones')->where('id', $z->id)->update(['sort_order' => $i + 1]);
            }
        }

        foreach (DB::table('racks')->orderBy('zone_id')->orderBy('code')->get(['id', 'zone_id'])->groupBy('zone_id') as $rak) {
            foreach ($rak->values() as $i => $r) {
                DB::table('racks')->where('id', $r->id)->update(['sort_order' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        foreach (['zones', 'racks'] as $tabel) {
            if (Schema::hasColumn($tabel, 'sort_order')) {
                Schema::table($tabel, function (Blueprint $table) {
                    $table->dropColumn('sort_order');
                });
            }
        }
    }
};

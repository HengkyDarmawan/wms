<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A-294: satuan kemasan DUS dan PACK (kategori Hitung, faktor 1 — isinya
 * ditentukan per item lewat kemasan) dan alasan kerusakan "Cacat dari vendor"
 * untuk company yang sudah ada; company baru mendapatkannya dari seeder acuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hitung = DB::table('uom_categories')->where('code', 'COUNT')->value('id');

        if ($hitung !== null) {
            foreach (['DUS' => 'Dus', 'PACK' => 'Pack'] as $kode => $nama) {
                DB::table('uoms')->insertOrIgnore([
                    'uom_category_id' => $hitung,
                    'code' => $kode,
                    'name' => $nama,
                    'factor_to_reference' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (DB::table('reason_codes')->where('context', 'damage')->exists()) {
            DB::table('reason_codes')->insertOrIgnore([
                'context' => 'damage',
                'code' => 'VENDOR',
                'label' => 'Cacat dari vendor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data acuan tidak dihapus (P-03): bisa sudah dipakai dokumen.
    }
};

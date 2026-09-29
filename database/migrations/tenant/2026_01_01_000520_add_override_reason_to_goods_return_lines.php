<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tata letak gudang Bagian 4 (keputusan #11, A-378): pilah retur memakai
 * urutan pindai put-away — menaruh hasil pilah di bin selain saran tempat
 * simpan menuntut alasan, disimpan di baris RET. Kolom baru nullable; data
 * lama tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('goods_return_lines', 'override_reason')) {
            return;
        }

        Schema::table('goods_return_lines', function (Blueprint $table) {
            $table->string('override_reason', 255)->nullable()->after('target_bin_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('goods_return_lines', 'override_reason')) {
            Schema::table('goods_return_lines', function (Blueprint $table) {
                $table->dropColumn('override_reason');
            });
        }
    }
};

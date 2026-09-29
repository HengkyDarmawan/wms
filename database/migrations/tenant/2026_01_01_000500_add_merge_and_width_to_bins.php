<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tata letak gudang Bagian 2 (K-B, K-D; A-359, A-361).
 *
 * - Gabung bin: penunjuk bin utama tetap `occupied_by_bin_id` (A-255); kini
 *   ditambah arah (`bin_merge_direction`: side/above) dan sifat
 *   (`bin_merge_type`: temporary/permanent). Data lama "ikut terpakai" diisi
 *   balik menjadi gabung **sementara** ke **samping** — tidak ada yang dihapus.
 * - Lebar bin: `width_m` opsional (meter); kosong = rata bagi lebar rak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->string('merge_direction', 10)->nullable()->after('occupied_by_bin_id');
            $table->string('merge_type', 10)->nullable()->after('merge_direction');
            $table->decimal('width_m', 8, 2)->nullable()->after('capacity_length');
        });

        $this->isiBalik();
    }

    /**
     * A-360: "bin ikut terpakai" lama → gabung **sementara** ke **samping**.
     * Aman diulang (hanya baris yang belum punya sifat); dipanggil juga oleh uji.
     */
    public function isiBalik(): int
    {
        return DB::table('bins')->whereNotNull('occupied_by_bin_id')->whereNull('merge_type')
            ->update(['merge_direction' => 'side', 'merge_type' => 'temporary']);
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropColumn(['merge_direction', 'merge_type', 'width_m']);
        });
    }
};

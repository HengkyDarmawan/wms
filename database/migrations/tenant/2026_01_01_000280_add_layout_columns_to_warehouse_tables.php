<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-254, A-255: denah gudang 2D.
 *
 * - Ukuran & posisi **opsional** (meter): zona panjang × lebar; rak nama,
 *   posisi (x, y) di dalam zona, panjang × lebar × tinggi, arah; tinggi level.
 *   Tanpa isian, denah menata otomatis.
 * - Rak area (`racks.is_area`): satu bin mewakili seluruh rak atau seluruh
 *   zona untuk barang besar (alat berat).
 * - `bins.capacity_mode`: mode kapasitas per bin (menimpa kategori
 *   penyimpanan); bin area bawaan `block`.
 * - Bin ikut terpakai: `occupied_by_bin_id` menunjuk bin utama tempat barang
 *   besar dicatat; dilepas otomatis saat bin utama kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->decimal('length_m', 8, 2)->nullable()->after('name');
            $table->decimal('width_m', 8, 2)->nullable()->after('length_m');
        });

        Schema::table('racks', function (Blueprint $table) {
            $table->string('name', 60)->nullable()->after('code');
            $table->boolean('is_area')->default(false)->after('name');
            $table->decimal('pos_x', 8, 2)->nullable()->after('is_area');
            $table->decimal('pos_y', 8, 2)->nullable()->after('pos_x');
            $table->decimal('length_m', 8, 2)->nullable()->after('pos_y');
            $table->decimal('width_m', 8, 2)->nullable()->after('length_m');
            $table->decimal('height_m', 8, 2)->nullable()->after('width_m');
            $table->char('orientation', 1)->nullable()->after('height_m'); // h = memanjang ke samping, v = ke bawah
        });

        Schema::table('rack_levels', function (Blueprint $table) {
            $table->decimal('height_m', 8, 2)->nullable()->after('code');
        });

        Schema::table('bins', function (Blueprint $table) {
            $table->string('capacity_mode', 5)->nullable()->after('capacity_length'); // warn|block, menimpa kategori
            $table->foreignId('occupied_by_bin_id')->nullable()->after('count_flag')->constrained('bins')->nullOnDelete();
            $table->string('occupied_reason', 255)->nullable()->after('occupied_by_bin_id');
            $table->dateTime('occupied_at')->nullable()->after('occupied_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('occupied_by_bin_id');
            $table->dropColumn(['capacity_mode', 'occupied_reason', 'occupied_at']);
        });

        Schema::table('rack_levels', function (Blueprint $table) {
            $table->dropColumn('height_m');
        });

        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn(['name', 'is_area', 'pos_x', 'pos_y', 'length_m', 'width_m', 'height_m', 'orientation']);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['length_m', 'width_m']);
        });
    }
};

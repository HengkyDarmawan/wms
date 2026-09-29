<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kemasan sebagai kalimat (A-355): "1 DUS berisi 40 PACK". Kolom baru
 * menyimpan kalimat yang diketik supaya form menampilkannya lagi apa adanya;
 * `qty_base` tetap satu-satunya angka yang dipakai hitungan stok dan tampilan
 * lain. Null = isi dalam satuan dasar, jadi data lama terbaca tanpa isi balik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_uom_conversions', function (Blueprint $table) {
            $table->decimal('content_qty', 18, 4)->nullable()->after('qty_base');
            $table->foreignId('content_uom_id')->nullable()->after('content_qty')->constrained('uoms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('item_uom_conversions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_uom_id');
            $table->dropColumn('content_qty');
        });
    }
};

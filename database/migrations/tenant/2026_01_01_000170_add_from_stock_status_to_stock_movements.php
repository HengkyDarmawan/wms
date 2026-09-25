<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-STK-01 / BR-LED-02 (A-194): kondisi di bin asal ikut dicatat pada baris
 * kartu stok bila berbeda dari kondisi tujuan (QC lolos Karantina → Tersedia,
 * barang ditandai rusak, pemilahan retur, waste dipakai ulang). Tanpa kolom
 * ini perubahan kondisi tidak bisa direkonstruksi dari kartu stok dan
 * pembaliknya menambah/mengurangi kondisi yang salah.
 *
 * Baris lama bernilai null (= sama dengan `stock_status`). Menambah kolom
 * bukan mengubah baris, jadi trigger append-only tidak terganggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('from_stock_status', 20)->nullable()->after('stock_status');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('from_stock_status');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-265: tanda "harga sudah termasuk PPN" per PO (bawaan ya). Hanya
 * keterangan untuk vendor dan cetakan — nilai PO tidak menghitung pajak
 * (perhitungan pajak milik Akuntansi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('price_includes_tax')->default(true)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('price_includes_tax');
        });
    }
};

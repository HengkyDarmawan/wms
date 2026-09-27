<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-267: barang bonus dari vendor (mis. promo beli 2 gratis 1) diterima di
 * GRN vendor sebagai baris bertanda bonus — masuk stok seperti biasa, tidak
 * merujuk pesanan PRQ/PO sehingga tidak dibatasi sisa pesanan (BR-GRN-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('qty_excess');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
};

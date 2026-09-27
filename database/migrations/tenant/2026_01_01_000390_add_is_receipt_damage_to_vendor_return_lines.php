<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-290: baris RTV bisa berasal dari barang yang dicatat Rusak saat GRN
 * (tanpa QC). Penanda ini memisahkan jatah retur bagian rusak dari bagian
 * hasil QC pada baris GRN yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_return_lines', function (Blueprint $table) {
            $table->boolean('is_receipt_damage')->default(false)->after('stock_status');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_return_lines', function (Blueprint $table) {
            $table->dropColumn('is_receipt_damage');
        });
    }
};

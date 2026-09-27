<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-287/A-288: baris GRN vendor mencatat jumlah menurut surat jalan vendor,
 * Rusak (+ alasan & bin tujuannya), dan Kurang; `qty_received` tetap berarti
 * jumlah Baik. A-291: satuan yang diketik staf (mis. 10 DUS) disimpan di
 * samping satuan dasar, beserta salinan faktornya saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->decimal('qty_vendor', 18, 4)->nullable()->after('qty_received');
            $table->decimal('qty_damaged', 18, 4)->default(0)->after('qty_vendor');
            $table->decimal('qty_short', 18, 4)->default(0)->after('qty_damaged');
            $table->foreignId('damage_reason_id')->nullable()->after('qty_short')->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('damaged_bin_id')->nullable()->after('damage_reason_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('uom_id')->nullable()->after('damaged_bin_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('qty_input', 18, 4)->nullable()->after('uom_id');
            $table->decimal('uom_qty_base', 18, 4)->nullable()->after('qty_input');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_reason_id');
            $table->dropConstrainedForeignId('damaged_bin_id');
            $table->dropConstrainedForeignId('uom_id');
            $table->dropColumn(['qty_vendor', 'qty_damaged', 'qty_short', 'qty_input', 'uom_qty_base']);
        });
    }
};

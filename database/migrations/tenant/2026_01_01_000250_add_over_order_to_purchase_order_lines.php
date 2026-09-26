<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-246: baris PO boleh memesan lebih dari sisa PRQ (MOQ vendor, tambah stok)
 * dengan alasan wajib. `qty_over_request` = bagian di atas sisa saat PO
 * disimpan; kelebihannya menjadi stok gudang biasa setelah GRN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('qty_over_request', 18, 4)->default(0)->after('qty_base');
            $table->string('over_order_reason', 255)->nullable()->after('qty_over_request');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['qty_over_request', 'over_order_reason']);
        });
    }
};

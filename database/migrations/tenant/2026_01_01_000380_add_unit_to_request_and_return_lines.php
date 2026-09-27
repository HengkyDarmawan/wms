<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-291: baris Permintaan material dan Retur menyimpan satuan yang diketik
 * (mis. 10 DUS) di samping `qty_base`; stok tetap satuan dasar.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['material_request_lines', 'goods_return_lines'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('uom_id')->nullable()->after('qty_base')->constrained('uoms')->restrictOnDelete();
                $table->decimal('qty_input', 18, 4)->nullable()->after('uom_id');
                $table->decimal('uom_qty_base', 18, 4)->nullable()->after('qty_input');
            });
        }
    }

    public function down(): void
    {
        foreach (['material_request_lines', 'goods_return_lines'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('uom_id');
                $table->dropColumn(['qty_input', 'uom_qty_base']);
            });
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ukuran per satuan dasar (A-413): isian berat item menjadi **ukuran** bebas
 * kategori (6 M per batang, 12 KG per batang, 5 L per kaleng). Kolom
 * `weight`/`weight_uom_id` diganti `measure_qty`/`measure_uom_id`; data lama
 * disalin. Ditulis tambah–salin–buang (bukan rename) supaya aman di MariaDB 10.4
 * yang belum mengenal RENAME COLUMN pada kolom ber-foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'measure_qty')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('measure_qty', 18, 4)->nullable()->after('dimensions');
                $table->foreignId('measure_uom_id')->nullable()->after('measure_qty')->constrained('uoms')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('items', 'weight')) {
            DB::table('items')->update(['measure_qty' => DB::raw('weight'), 'measure_uom_id' => DB::raw('weight_uom_id')]);

            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign(['weight_uom_id']);
                $table->dropColumn(['weight', 'weight_uom_id']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'weight')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('weight', 18, 4)->nullable();
                $table->foreignId('weight_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            });
            DB::table('items')->update(['weight' => DB::raw('measure_qty'), 'weight_uom_id' => DB::raw('measure_uom_id')]);
        }

        if (Schema::hasColumn('items', 'measure_qty')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign(['measure_uom_id']);
                $table->dropColumn(['measure_qty', 'measure_uom_id']);
            });
        }
    }
};

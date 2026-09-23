<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyempurnaan modul Master setelah audit 24 September 2026.
 *
 * 1. `item_uom_conversions.is_active` — konversi kemasan tidak lagi dihapus
 *    fisik saat barisnya dilepas dari form (P-03). Baris lama tetap ada
 *    supaya dokumen yang memakainya tidak kehilangan dasar perhitungan.
 * 2. Indeks pada kolom yang rutin dicari dan difilter di layar Master.
 * 3. Indeks polimorfik `pieces(origin_type, origin_id)` dan
 *    `project_material_plans.import_batch_id`.
 *
 * Kolom foreign key tidak diberi indeks tambahan karena MySQL sudah membuatnya.
 *
 * Migrasi aditif saja (Arsitektur §11): tidak ada rename maupun drop kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_uom_conversions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_nominal_piece');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index('barcode');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->index('status');
            $table->index('vendor_type');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('pieces', function (Blueprint $table) {
            $table->index(['origin_type', 'origin_id']);
            $table->index('is_consumed');
        });

        Schema::table('project_material_plans', function (Blueprint $table) {
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_material_plans', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id']);
        });

        Schema::table('pieces', function (Blueprint $table) {
            $table->dropIndex(['origin_type', 'origin_id']);
            $table->dropIndex(['is_consumed']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['vendor_type']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
        });

        Schema::table('item_uom_conversions', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

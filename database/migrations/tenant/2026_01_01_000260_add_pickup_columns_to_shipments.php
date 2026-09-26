<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-247: SJ tanpa PCK. SJ jemput retur (barang di tangan klien, aset On-site)
 * dan SJ antar site (aset antar proyek) berangkat dari proyek, bukan dari bin
 * gudang, sehingga barisnya tidak punya baris PCK.
 *
 * - `shipments.source_type/source_id` (`goods_return` | `transfer`) menandai
 *   SJ tanpa PCK; `origin_project_id` = proyek tempat barang dijemput;
 *   `vehicle_plate` = plat bila kendaraan bukan master (sopir bebas memakai
 *   `carried_by_name` yang sudah ada).
 * - `shipment_lines.pick_task_line_id` boleh kosong; identitas barang (item,
 *   lot, serial, potongan) disimpan di baris SJ sendiri dan diisi balik dari
 *   baris PCK untuk SJ lama; `source_line_id` = baris RET/TRF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('source_type', 20)->nullable()->after('destination_vendor_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('origin_project_id')->nullable()->after('source_id')->constrained('projects')->nullOnDelete();
            $table->string('vehicle_plate', 20)->nullable()->after('vehicle_id');

            $table->index(['source_type', 'source_id']);
        });

        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('pick_task_line_id')->nullable()->change();
            $table->unsignedBigInteger('source_line_id')->nullable()->after('pick_task_line_id');
            $table->foreignId('item_id')->nullable()->after('source_line_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->after('item_id')->constrained('lots')->nullOnDelete();
            $table->foreignId('serial_id')->nullable()->after('lot_id')->constrained('serials')->nullOnDelete();
            $table->foreignId('piece_id')->nullable()->after('serial_id')->constrained('pieces')->nullOnDelete();

            $table->index('source_line_id');
        });

        // SJ lama: identitas barang dari baris PCK-nya.
        DB::statement('UPDATE shipment_lines sl JOIN pick_task_lines p ON p.id = sl.pick_task_line_id
            SET sl.item_id = p.item_id, sl.lot_id = p.lot_id, sl.serial_id = p.serial_id, sl.piece_id = p.piece_id');
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->dropIndex(['source_line_id']);
            $table->dropConstrainedForeignId('piece_id');
            $table->dropConstrainedForeignId('serial_id');
            $table->dropConstrainedForeignId('lot_id');
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('source_line_id');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropConstrainedForeignId('origin_project_id');
            $table->dropColumn(['source_type', 'source_id', 'vehicle_plate']);
        });
    }
};

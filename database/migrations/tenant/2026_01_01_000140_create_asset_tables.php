<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Aset dipinjamkan — AST serah terima, pemeriksaan aset, jadwal
 * maintenance `[F2]` (25-aset §3, ERD 08c area Konversi & aset).
 *
 * Identitas aset tetap `serials` (Master); tabel di sini adalah catatan per
 * kejadian. Stok aset tetap hanya bergerak lewat `stock_movements` (P-01).
 * Kolom di luar ERD dicatat di A-165.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                            // AST/<gudang>/<yymm>/<urut>
            $table->foreignId('serial_id')->constrained('serials')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete(); // gudang asal SJ
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('shipment_line_id')->nullable()->constrained('shipment_lines')->nullOnDelete(); // keluar
            $table->foreignId('goods_return_id')->nullable()->constrained('goods_returns')->nullOnDelete();
            $table->foreignId('goods_return_line_id')->nullable()->constrained('goods_return_lines')->nullOnDelete(); // kembali
            $table->string('status', 20)->default('checked_out');              // Katalog §2.11
            $table->dateTime('checked_out_at');
            $table->date('due_return_date')->nullable();
            $table->char('condition_out', 1)->nullable();                      // condition_grade saat keluar
            $table->unsignedBigInteger('photo_out_id')->nullable();            // tabel lampiran belum ada (A-68)
            $table->decimal('meter_out', 12, 1)->nullable();                   // pembacaan meter keluar (A-66)
            $table->dateTime('returned_at')->nullable();
            $table->unsignedInteger('usage_days')->nullable();                 // BR-AST-05
            $table->decimal('meter_in', 12, 1)->nullable();
            $table->decimal('usage_hours', 12, 1)->nullable();                 // meter jam
            $table->decimal('usage_km', 12, 1)->nullable();                    // meter km (A-165)
            $table->dateTime('lost_at')->nullable();                           // asset.mark_lost
            $table->foreignId('lost_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete(); // ADJ asset_lost
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['serial_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['status', 'due_return_date']);
        });

        Schema::create('asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_handover_id')->nullable()->constrained('asset_handovers')->nullOnDelete();
            $table->foreignId('serial_id')->constrained('serials')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inspected_at');
            $table->char('condition_grade', 1);                                // A–D
            $table->unsignedTinyInteger('condition_score');                    // 0–100 % (BR-AST-08)
            $table->json('component_notes')->nullable();                       // [{component, note}]
            $table->unsignedBigInteger('photo_id')->nullable();                // tabel lampiran belum ada (A-68)
            $table->string('photo_path', 255)->nullable();                     // foto pemeriksaan (A-166)
            $table->decimal('meter_in', 12, 1)->nullable();
            $table->string('meter_reset_reason', 255)->nullable();             // meter diganti (BR-AST-08)
            $table->string('notes', 255)->nullable();
            $table->string('resulting_state', 20);                             // available|maintenance|damaged
            $table->timestamps();

            $table->index(['serial_id', 'inspected_at']);
        });

        // Jadwal maintenance [F2] — stub (BR-GEN-10, BR-AST-07): tabel ada, belum ada layar.
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_id')->constrained('serials')->restrictOnDelete();
            $table->date('scheduled_at');
            $table->unsignedInteger('interval_days')->nullable();
            $table->date('performed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('status', 20)->default('planned');                  // planned|in_progress|done
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('asset_inspections');
        Schema::dropIfExists('asset_handovers');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Count/Adjustment — sesi stock opname (OPN) dan penyesuaian stok (ADJ)
 * (21-opname-penyesuaian §3, ERD 08c area "Stock opname & penyesuaian").
 *
 * Tidak ada kolom saldo di sini. Sesi menyimpan snapshot angka sistem per
 * baris saat dimulai (BR-OPN-01) dan hasil hitung buta; selisihnya menjadi
 * baris ADJ yang baru menggerakkan stok lewat `StockLedger` saat disetujui
 * (P-01). Kolom di luar ERD dicatat di 21-opname-penyesuaian §13.1 dan
 * digenerate ulang ke ERD.
 *
 * `report_attachment_id` belum ber-FK: tabel `attachments` belum ada
 * (A-68, BR-GEN-10). `bins.frozen_by_count_id` yang sejak modul Warehouse
 * menunggu tabel ini kini diberi foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('count_type', 20);                                  // count_type
            $table->string('status', 20)->default('planned');
            $table->boolean('freeze_bins')->default(true);
            $table->json('scope');                                             // gudang/zona/bin/item
            $table->json('team_user_ids')->nullable();                         // tim penghitung
            $table->boolean('is_audit')->default(false);                       // dibuat Auditor (BR-OPN-09)
            $table->date('planned_start')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete(); // perekonsiliasi
            $table->dateTime('reconciled_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->date('lock_date_set')->nullable();                         // BR-STK-15
            $table->unsignedBigInteger('report_attachment_id')->nullable();    // stub: attachments
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'count_type']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('origin', 20)->default('manual');                  // adjustment_origin
            $table->foreignId('stock_count_id')->nullable()->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_adjustments')->restrictOnDelete();
            $table->foreignId('reason_code_id')->constrained('reason_codes')->restrictOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index('origin');
        });

        Schema::create('stock_count_warehouses', function (Blueprint $table) {
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();

            $table->primary(['stock_count_id', 'warehouse_id']);
        });

        Schema::create('count_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('counter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('round')->default(1);                 // 1 = pertama, 2 = hitung ulang
            $table->string('status', 10)->default('pending');                 // pending|done
            $table->dateTime('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'bin_id', 'round']);
            $table->index(['counter_user_id', 'status']);
        });

        Schema::create('count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('stock_status', 12)->default('available');
            $table->boolean('is_unexpected')->default(false);                 // ditemukan, tidak ada di snapshot
            $table->decimal('system_qty', 18, 4)->default(0);                 // snapshot fisik (BR-OPN-01)
            $table->decimal('counted_qty_r1', 18, 4)->nullable();
            $table->decimal('counted_qty_r2', 18, 4)->nullable();
            $table->boolean('is_recount')->default(false);                    // masuk hitung ulang (BR-OPN-05)
            $table->decimal('final_qty', 18, 4)->nullable();
            $table->decimal('variance_qty', 18, 4)->nullable();
            $table->decimal('variance_pct', 8, 4)->nullable();
            $table->string('variance_class', 10)->nullable();                 // minor|moderate|major
            $table->string('root_cause', 20)->nullable();                     // root_cause_category
            $table->string('note', 255)->nullable();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_count_id', 'bin_id']);
            $table->index('variance_class');
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            // Isian pelacakan untuk barang masuk yang turunannya belum ada;
            // lot/serial/potongan baru dibuat saat diposting.
            $table->string('lot_no', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('serial_no', 80)->nullable();
            $table->decimal('piece_length', 18, 4)->nullable();
            $table->decimal('qty_delta', 18, 4);                              // ± dalam satuan dasar
            $table->string('stock_status', 12)->default('available');
            $table->foreignId('count_line_id')->nullable()->constrained('count_lines')->restrictOnDelete();
            $table->foreignId('reversal_of_line_id')->nullable()->constrained('stock_adjustment_lines')->restrictOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['stock_adjustment_id', 'item_id']);
        });

        Schema::table('bins', function (Blueprint $table) {
            $table->foreign('frozen_by_count_id')->references('id')->on('stock_counts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropForeign(['frozen_by_count_id']);
        });

        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('count_lines');
        Schema::dropIfExists('count_assignments');
        Schema::dropIfExists('stock_count_warehouses');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_counts');
    }
};

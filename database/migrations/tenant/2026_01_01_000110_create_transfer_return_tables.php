<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Transfer & Retur — TRF dan RET (22-retur-transfer §3).
 *
 * Keduanya dokumen **niat** (A-33, BR-RET-01): tidak ada kolom saldo di sini.
 * Pergerakan fisik tetap lewat PCK/SJ/GRN dan hanya tercatat di
 * `stock_movements` (P-01). Kolom di luar ERD dicatat di A-115.
 *
 * Rujukan GRN ke RET yang dulu dibuat tanpa foreign key (19-receipt-putaway §3,
 * BR-GEN-10) kini diberi foreign key karena tabelnya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                         // TRF/<gudang asal>/<yymm>/<urut>
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('from_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('to_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('origin', 10)->default('manual');                // transfer_origin: manual|backorder
            $table->string('status', 20)->default('submitted');             // Katalog §2.7
            $table->string('source_type', 30)->nullable();                  // material_request bila dari backorder
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['from_warehouse_id', 'status']);
            $table->index(['to_warehouse_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('material_request_line_id')->nullable()->constrained('material_request_lines')->nullOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('qty_shipped', 18, 4)->default(0);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'item_id']);
        });

        Schema::create('goods_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                         // RET/<gudang tujuan>/<yymm>/<urut>
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('origin_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete(); // Gudang Site asal
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->boolean('self_delivered')->default(true);                // tanpa SJ balik
            $table->foreignId('return_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('status', 20)->default('submitted');              // Katalog §2.8
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('sorted_at')->nullable();
            $table->foreignId('sorted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['to_warehouse_id', 'status']);
        });

        Schema::create('goods_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_return_id')->constrained('goods_returns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->restrictOnDelete();  // Gudang Site / On-site
            $table->foreignId('origin_shipment_line_id')->nullable()->constrained('shipment_lines')->nullOnDelete();
            $table->foreignId('origin_discrepancy_line_id')->nullable()->constrained('delivery_discrepancy_lines')->nullOnDelete();
            $table->string('ownership', 10)->default('company');             // return_ownership: sold|company
            $table->string('stock_status', 12)->default('available');        // kondisi saat tiba di bin Retur
            $table->decimal('qty_base', 18, 4);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->foreignId('split_from_line_id')->nullable()->constrained('goods_return_lines')->nullOnDelete();
            $table->string('sorting', 10)->nullable();                       // return_sorting
            $table->decimal('sorted_qty', 18, 4)->nullable();
            $table->foreignId('new_piece_id')->nullable()->constrained('pieces')->nullOnDelete();
            $table->foreignId('target_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['goods_return_id', 'item_id']);
        });

        // Titik sambung 19-receipt-putaway §3.1–§3.2 kini punya tabelnya.
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreign('goods_return_id')->references('id')->on('goods_returns')->nullOnDelete();
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->foreign('goods_return_line_id')->references('id')->on('goods_return_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['goods_return_line_id']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['goods_return_id']);
        });

        Schema::dropIfExists('goods_return_lines');
        Schema::dropIfExists('goods_returns');
        Schema::dropIfExists('transfer_lines');
        Schema::dropIfExists('transfers');
    }
};

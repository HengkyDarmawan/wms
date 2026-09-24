<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Receipt/Putaway — GRN, QC, PUT, dan RTV (19-receipt-putaway §3).
 *
 * Sama seperti modul pengiriman, tidak ada kolom saldo di sini: yang tercatat
 * adalah apa yang diterima, diperiksa, ditaruh, dan dikembalikan. Stok hanya
 * berubah lewat `stock_movements` (P-01).
 *
 * Rujukan ke tabel yang belum ada — baris catatan pemesanan PRQ, RET, dan baris
 * RET — dibuat tanpa foreign key sampai modulnya lahir (BR-GEN-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('receipt_type', 10);                          // vendor|transfer|return
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('vendor_doc_no', 60)->nullable();             // surat jalan vendor
            $table->string('po_ref', 60)->nullable();                    // F3: po_id
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->unsignedBigInteger('goods_return_id')->nullable();   // stub: modul return
            $table->string('source_type', 30)->nullable();               // vendor_return (barang pengganti, BR-GRN-04)
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 12)->default('draft');
            $table->dateTime('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index('receipt_type');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedBigInteger('purchase_request_order_line_id')->nullable(); // stub: A-51
            $table->foreignId('shipment_line_id')->nullable()->constrained('shipment_lines')->restrictOnDelete();
            $table->unsignedBigInteger('goods_return_line_id')->nullable();           // stub: modul return
            // Isian pelacakan saat draf; turunannya dibuat saat Diterima.
            $table->string('lot_no', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('serial_no', 80)->nullable();
            $table->decimal('piece_length', 18, 4)->nullable();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->foreignId('receiving_bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->decimal('qty_received', 18, 4);
            $table->string('qc_result', 12)->nullable();                 // passed|quarantined|rejected
            $table->foreignId('qc_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('qc_at')->nullable();
            $table->foreignId('qc_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('qc_note', 255)->nullable();
            $table->boolean('is_cross_dock')->default(false);            // BR-SJ-03
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'item_id']);
            $table->index('qc_result');
        });

        Schema::create('putaway_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 12)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('putaway_task_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('putaway_task_id')->constrained('putaway_tasks')->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->foreignId('from_bin_id')->constrained('bins')->restrictOnDelete();       // bin Penerimaan
            $table->foreignId('suggested_bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->restrictOnDelete(); // aktual
            $table->decimal('qty_base', 18, 4);
            $table->string('override_reason', 255)->nullable();
            $table->dateTime('scanned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('vendor_confirmed_at')->nullable();
            $table->foreignId('replacement_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('vendor_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_return_id')->constrained('vendor_returns')->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();             // bin Karantina asal
            $table->string('stock_status', 12);                                               // kondisi yang keluar
            $table->decimal('qty_base', 18, 4);
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_return_lines');
        Schema::dropIfExists('vendor_returns');
        Schema::dropIfExists('putaway_task_lines');
        Schema::dropIfExists('putaway_tasks');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};

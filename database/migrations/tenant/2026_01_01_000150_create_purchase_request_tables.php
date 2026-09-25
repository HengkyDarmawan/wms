<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Purchase Request (PRQ) Fase 1 manual — PRQ, baris, catatan pemesanan
 * per vendor dan barisnya (26-purchase-request §3, ERD 08b, A-47, A-51).
 *
 * `goods_receipt_lines.purchase_request_order_line_id` sudah ada sejak modul
 * Receipt (stub A-51); di sini hanya diberi indeks. Tidak ada kolom harga
 * (D-07) — nilai uang milik modul Purchasing (D-08, D-28). Kolom di luar ERD
 * dicatat di A-172.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                               // PRQ/ALL/<yymm>/<urut> (BR-GEN-06)
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete(); // tujuan
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('origin', 20)->default('manual');                     // purchase_request_origin
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete(); // backorder
            $table->string('status', 20)->default('draft');                      // Katalog §2.15
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete(); // Penindak Lanjut PR
            $table->dateTime('forwarded_at')->nullable();
            $table->dateTime('fulfilled_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['origin', 'status']);
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('material_request_line_id')->nullable()->constrained('material_request_lines')->nullOnDelete(); // REQ penunggu
            $table->date('required_date')->nullable();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('qty_ordered', 18, 4)->default(0);                    // Σ baris catatan pemesanan
            $table->decimal('qty_received', 18, 4)->default(0);                   // Σ GRN
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['item_id', 'purchase_request_id']);
        });

        Schema::create('purchase_request_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete(); // boleh provisional (A-53)
            $table->string('external_po_no', 60)->nullable();
            $table->string('marketplace_order_no', 60)->nullable();
            $table->string('tracking_no', 60)->nullable();
            $table->date('eta_date')->nullable();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ordered_at');
            $table->string('vendor_note', 255)->nullable();                       // alasan vendor di luar saran (A-52)
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_order_id')->constrained('purchase_request_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->constrained('purchase_request_lines')->restrictOnDelete();
            $table->decimal('qty_ordered', 18, 4);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->string('po_line_ref', 60)->nullable();                        // F3
            $table->timestamps();
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->index('purchase_request_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropIndex(['purchase_request_order_line_id']);
        });

        Schema::dropIfExists('purchase_request_order_lines');
        Schema::dropIfExists('purchase_request_orders');
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing inti Fase 1b (purchasing/02 §3, D-29): harga beli vendor, PO, dan
 * baris PO. Satu-satunya tempat nilai uang di database company (A-208) —
 * tabel WMS hanya mendapat rujukan ke PO, tanpa harga (D-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('unit_price', 18, 2);                                  // per satuan dasar item (A-211)
            $table->char('currency', 3)->default('IDR');
            $table->date('valid_from');
            $table->boolean('is_active')->default(true);                          // harga lama dinonaktifkan, tidak dihapus (P-03)
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'vendor_id', 'is_active', 'valid_from']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                               // PO/<gudang>/<yymm>/<urut> (BR-GEN-06)
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete(); // tujuan
            $table->string('status', 20)->default('draft');                       // Katalog §2.17
            $table->date('order_date');
            $table->date('eta_date')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('payment_terms', 60)->nullable();                      // salinan teks termin vendor
            $table->string('notes', 255)->nullable();
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('close_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->constrained('purchase_request_lines')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_amount', 18, 2);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->decimal('qty_cancelled', 18, 4)->default(0);                  // sisa yang ditutup/dibatalkan
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['purchase_request_line_id']);
        });

        Schema::table('purchase_request_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('purchase_request_id')->constrained('purchase_orders')->nullOnDelete();
        });

        Schema::table('purchase_request_order_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->after('purchase_request_line_id')->constrained('purchase_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_line_id');
        });

        Schema::table('purchase_request_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });

        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendor_prices');
    }
};

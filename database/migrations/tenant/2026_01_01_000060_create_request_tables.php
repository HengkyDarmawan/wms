<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Request — REQ Permintaan Material (14-request §3).
 *
 * REQ adalah dokumen niat: tidak ada kolom jumlah stok di sini, hanya jumlah
 * yang diminta dan cerminan pemenuhannya. Stok yang sesungguhnya tetap hanya
 * berubah lewat `stock_movements` (P-01).
 *
 * Kolom yang menunjuk modul yang belum ada — `approval_snapshot_id` — sengaja
 * dibuat tanpa foreign key (BR-GEN-10); kuncinya dipasang saat modul itu lahir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('requester_type', 10)->default('internal');   // internal|client
            $table->string('status', 25)->default('draft');
            $table->date('required_date');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedBigInteger('approval_snapshot_id')->nullable();  // stub: modul approval
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('origin', 12)->default('regular');            // regular|supplement
            $table->foreignId('parent_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'required_date']);
            $table->index(['project_id', 'status']);
            $table->index('requester_id');
        });

        Schema::create('material_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();

            // Null selama baris non-katalog belum dipetakan staf (BR-REQ-03).
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('non_catalog_text', 255)->nullable();
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('mapped_at')->nullable();

            $table->string('line_ownership', 6)->default('buy');         // buy|loan
            $table->date('required_date');
            $table->date('promised_date')->nullable();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('fulfillment_source', 10)->nullable();        // stock|transfer|purchase

            $table->decimal('qty_base', 18, 4);
            $table->decimal('qty_reserved', 18, 4)->default(0);
            $table->decimal('qty_shipped', 18, 4)->default(0);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->decimal('qty_backorder', 18, 4)->default(0);
            $table->decimal('nominal_length', 18, 4)->nullable();        // item per potong

            $table->foreignId('split_from_line_id')->nullable()
                ->constrained('material_request_lines')->nullOnDelete();

            // Penggantian item (A-55, BR-REQ-13).
            $table->string('original_item_text', 255)->nullable();
            $table->dateTime('substituted_at')->nullable();
            $table->dateTime('substitution_deadline_at')->nullable();
            $table->string('substitution_response', 10)->nullable();     // accepted|rejected|expired

            // Permintaan pembatalan oleh klien (A-61, BR-REQ-15).
            $table->dateTime('cancel_requested_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancel_confirmed_at')->nullable();

            $table->string('status', 12)->default('open');               // open|closed|cancelled
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['material_request_id', 'status']);
            $table->index(['item_id', 'source_warehouse_id']);
            $table->index('substitution_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_lines');
        Schema::dropIfExists('material_requests');
    }
};

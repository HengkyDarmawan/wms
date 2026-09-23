<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Picking & Shipment — PCK, SJ, bukti terima, dan DSC (15-picking-shipment §3).
 *
 * Tidak ada satu pun kolom saldo di sini: jumlah yang tercatat adalah apa yang
 * dialokasikan, diambil, dikirim, dan diterima. Stok yang sesungguhnya hanya
 * berubah lewat `stock_movements` (P-01).
 *
 * `return_receipt_id` pada baris DSC menunjuk GRN retur yang belum ada
 * tabelnya; dibuat tanpa foreign key sampai modul `receipt` lahir (BR-GEN-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('source_type', 30);                      // material_request|transfer
            $table->unsignedBigInteger('source_id');
            $table->string('status', 15)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('pick_task_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_task_id')->constrained('pick_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('source_line_id');           // baris REQ/TRF yang dipenuhi
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('suggested_bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->decimal('qty_allocated', 18, 4);
            $table->decimal('qty_picked', 18, 4)->default(0);
            $table->foreignId('short_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('override_reason', 255)->nullable();
            $table->dateTime('scanned_at')->nullable();
            $table->timestamps();

            $table->index(['pick_task_id', 'item_id']);
            $table->index('source_line_id');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('destination_type', 20);                 // project_client|site_warehouse|warehouse|vendor
            $table->foreignId('destination_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('destination_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('shipment_method', 15);                  // own_fleet|carrier|self_delivered
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('tracking_no', 60)->nullable();
            $table->string('carried_by_name', 100)->nullable();
            $table->string('status', 22)->default('prepared');
            $table->dateTime('loaded_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['destination_type', 'destination_project_id']);
            $table->index('shipped_at');
        });

        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('pick_task_line_id')->constrained('pick_task_lines')->restrictOnDelete();
            $table->decimal('qty_shipped', 18, 4);
            $table->decimal('qty_delivered', 18, 4)->default(0);
            $table->string('ownership_effect', 10)->default('sold');  // sold|transfer|loan
            $table->timestamps();

            $table->unique(['shipment_id', 'pick_task_line_id']);
        });

        Schema::create('proofs_of_delivery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->cascadeOnDelete();
            $table->string('received_by_name', 100);
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_path', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('confirmed_at');
            $table->string('channel', 12)->default('driver_pwa');     // driver_pwa|token_link
            $table->dateTime('requester_confirmed_at')->nullable();
            $table->string('confirmation', 15)->nullable();           // confirmed|disputed|auto_confirmed
            $table->dateTime('confirm_deadline_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('confirm_deadline_at');
        });

        Schema::create('proof_of_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proof_of_delivery_id')->constrained('proofs_of_delivery')->cascadeOnDelete();
            $table->foreignId('shipment_line_id')->constrained('shipment_lines')->restrictOnDelete();
            $table->decimal('qty_good', 18, 4)->default(0);
            $table->decimal('qty_damaged', 18, 4)->default(0);
            $table->decimal('qty_missing', 18, 4)->default(0);
            $table->string('damage_photo_path', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            // Nama kunci dipendekkan: MySQL membatasi pengenal 64 karakter.
            $table->unique(['proof_of_delivery_id', 'shipment_line_id'], 'pod_lines_unik');
        });

        Schema::create('proof_of_delivery_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proof_of_delivery_line_id', 'pod_units_line_fk')->constrained('proof_of_delivery_lines')->cascadeOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('condition', 10);                          // good|damaged|missing
            $table->timestamps();
        });

        Schema::create('delivery_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('otp_hash', 255);
            $table->string('phone', 20)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('delivery_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('origin', 20);                             // partial_delivery|client_dispute
            $table->string('status', 12)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('delivery_discrepancy_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_discrepancy_id')->constrained('delivery_discrepancies')->cascadeOnDelete();
            $table->foreignId('shipment_line_id')->constrained('shipment_lines')->restrictOnDelete();
            $table->string('discrepancy_type', 10);                   // missing|damaged
            $table->decimal('qty_base', 18, 4);
            $table->string('disposition', 25)->nullable();            // returned_to_warehouse|adjusted|claimed|reship
            $table->string('client_decision', 15)->default('still_needed');
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('claim_ref', 60)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->unsignedBigInteger('return_receipt_id')->nullable();  // stub: modul receipt
            $table->timestamps();

            $table->index(['delivery_discrepancy_id', 'discrepancy_type'], 'dsc_lines_jenis_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_discrepancy_lines');
        Schema::dropIfExists('delivery_discrepancies');
        Schema::dropIfExists('delivery_tokens');
        Schema::dropIfExists('proof_of_delivery_units');
        Schema::dropIfExists('proof_of_delivery_lines');
        Schema::dropIfExists('proofs_of_delivery');
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('pick_task_lines');
        Schema::dropIfExists('pick_tasks');
    }
};

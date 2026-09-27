<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-296: label kemasan bertingkat — label **induk** per kemasan (BAUT-M12-0001)
 * dan label **isi** di dalamnya (BAUT-M12-0001-0001). Label adalah lapisan
 * penelusuran di atas stok: menyimpan asal GRN (vendor, pesanan, tanggal) dan
 * status Di gudang / Keluar / Batal; stok tetap hanya lewat kartu stok (P-01).
 *
 * `package_label_moves` = riwayat label (append-only) untuk layar Telusuri label.
 * Klaim pindai disimpan di baris PCK/ISU (json) dan baru diposting saat stok
 * bergerak (A-299). Batch vendor di baris GRN (A-297).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_labels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('package_labels')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('package_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('qty_remaining', 18, 4);
            $table->string('status', 12)->default('in_stock');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'item_id', 'lot_id', 'status']);
            $table->index('goods_receipt_line_id');
        });

        Schema::create('package_label_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_label_id')->constrained('package_labels')->restrictOnDelete();
            $table->decimal('qty_change', 18, 4);
            $table->string('document_type', 30)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('document_line_id')->nullable();
            $table->string('document_number', 40)->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('to_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['package_label_id', 'occurred_at']);
            // Nama eksplisit: nama otomatis 68 karakter melewati batas 64 MySQL/MariaDB.
            $table->index(['document_type', 'document_id', 'document_line_id'], 'package_label_moves_document_idx');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->string('vendor_batch_no', 60)->nullable()->after('lot_no');
        });

        Schema::table('pick_task_lines', function (Blueprint $table) {
            $table->json('labels')->nullable()->after('scanned_at');
        });

        Schema::table('material_issue_lines', function (Blueprint $table) {
            $table->json('labels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('material_issue_lines', fn (Blueprint $table) => $table->dropColumn('labels'));
        Schema::table('pick_task_lines', fn (Blueprint $table) => $table->dropColumn('labels'));
        Schema::table('goods_receipt_lines', fn (Blueprint $table) => $table->dropColumn('vendor_batch_no'));
        Schema::dropIfExists('package_label_moves');
        Schema::dropIfExists('package_labels');
    }
};

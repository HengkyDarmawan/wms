<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Issue — ISU pemakaian material di Gudang Site (23-pemakaian §3, A-32).
 *
 * ISU mengeluarkan barang habis pakai dari bin Gudang Site saat dipakai proyek.
 * Tidak ada kolom saldo di sini: stok hanya bergerak lewat `stock_movements`
 * (P-01), dan baris ISU menyimpan rujukan pergerakannya. ISU pembalik memakai
 * jumlah negatif dan menunjuk baris asal (BR-GEN-03). Kolom di luar ERD
 * dicatat di A-118.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                         // ISU/<Gudang Site>/<yymm>/<urut>
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete(); // Gudang Site proyek
            $table->string('status', 20)->default('draft');                 // Katalog §2.9
            $table->foreignId('reversal_of_id')->nullable()->constrained('material_issues')->nullOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete(); // alasan pembalik
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();  // pembuat
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete(); // pengaju pembalik
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('material_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->decimal('qty_base', 18, 4);                              // negatif pada ISU pembalik
            $table->string('work_note', 255)->nullable();                    // untuk apa dipakai
            $table->foreignId('reversal_of_line_id')->nullable()->constrained('material_issue_lines')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_issue_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_issue_lines');
        Schema::dropIfExists('material_issues');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Konversi & Waste — CNV konversi material dan WST Berita Acara Waste
 * (24-konversi-waste §3, ERD 08c area Konversi).
 *
 * Tidak ada kolom saldo di sini: stok hanya bergerak lewat `stock_movements`
 * (P-01); baris dokumen menyimpan rujukan pergerakannya. CNV pembalik menunjuk
 * CNV asal dan menyalin barisnya (BR-GEN-03, BR-CNV-05). Kolom di luar ERD
 * dicatat di A-155.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Resep konversi [F2] — stub (BR-GEN-10): tabel ada, belum ada layar.
        Schema::create('conversion_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->json('definition')->nullable();                           // input → output + sisa
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                          // CNV/<gudang>/<yymm>/<urut>
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();   // BR-CNV-01
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('conversion_type', 20)->default('cut');            // conversion_type
            $table->foreignId('recipe_id')->nullable()->constrained('conversion_recipes')->nullOnDelete(); // F2
            $table->string('status', 20)->default('draft');                  // Katalog §2.10
            $table->foreignId('reversal_of_id')->nullable()->constrained('conversions')->nullOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete(); // alasan pembalik
            $table->decimal('total_input', 18, 4)->default(0);
            $table->decimal('total_output', 18, 4)->default(0);
            $table->decimal('total_offcut', 18, 4)->default(0);
            $table->decimal('total_waste', 18, 4)->default(0);
            $table->decimal('total_kerf', 18, 4)->default(0);
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();   // pembuat
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('conversion_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->constrained('conversions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();          // bin penyimpanan
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete(); // dipakai utuh
            $table->decimal('qty_base', 18, 4);
            $table->foreignId('reversal_of_line_id')->nullable()->constrained('conversion_inputs')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['conversion_id', 'item_id']);
        });

        Schema::create('conversion_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->constrained('conversions')->cascadeOnDelete();
            $table->string('output_kind', 10);                                // output|offcut|waste|kerf
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete(); // output bisa item lain
            $table->foreignId('bin_id')->nullable()->constrained('bins')->restrictOnDelete(); // waste → bin Waste; kerf kosong
            $table->string('stock_status', 20)->nullable();                   // Tersedia; waste Rusak
            $table->decimal('qty_base', 18, 4);                               // potongan: panjang satu potong
            $table->string('lot_no', 60)->nullable();                         // output berlot
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->foreignId('new_piece_id')->nullable()->constrained('pieces')->nullOnDelete(); // dibuat saat selesai
            $table->foreignId('parent_input_id')->nullable()->constrained('conversion_inputs')->nullOnDelete(); // silsilah
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete(); // alasan waste
            $table->boolean('auto_waste')->default(false);                    // offcut < minimum → waste (BR-CNV-03)
            $table->foreignId('reversal_of_line_id')->nullable()->constrained('conversion_outputs')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['conversion_id', 'output_kind']);
        });

        Schema::create('waste_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();                          // WST/<gudang>/<yymm>/<urut>
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('disposition', 20);                                // waste_disposition
            $table->foreignId('target_bin_id')->nullable()->constrained('bins')->nullOnDelete(); // reused → bin penyimpanan
            $table->string('status', 20)->default('submitted');              // Katalog §2.14
            $table->unsignedBigInteger('evidence_attachment_id')->nullable(); // tabel lampiran belum ada (A-68)
            $table->string('evidence_path', 255)->nullable();                 // foto berita acara
            $table->string('evidence_note', 255)->nullable();                 // nomor/keterangan BA bertanda tangan
            $table->foreignId('approval_snapshot_id')->nullable()->constrained('approval_snapshots')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete(); // pengaju
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reject_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('cancel_reason_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('waste_disposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_disposal_id')->constrained('waste_disposals')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();          // bin Waste
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('stock_status', 20)->default('damaged');
            $table->decimal('qty_base', 18, 4);
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['waste_disposal_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_disposal_lines');
        Schema::dropIfExists('waste_disposals');
        Schema::dropIfExists('conversion_outputs');
        Schema::dropIfExists('conversion_inputs');
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('conversion_recipes');
    }
};

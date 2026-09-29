<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tata letak gudang Bagian 3 (K-A; A-365–A-368).
 *
 * - `item_storage_locations` — **Tempat Simpan**: satu baris = satu item di satu
 *   gudang pada satu bin tertentu (`bin_id`), seluruh rak, atau area lantai
 *   (`rack_id`; rak `is_area` = area lantai). Jenisnya dibaca dari kolom yang
 *   terisi — tanpa enum baru. `sequence` = urutan saran; `is_dedicated` =
 *   **Khusus Barang Ini**.
 * - `storage_dedication_overrides` — catatan **Buka Tempat Khusus** oleh Kepala
 *   Gudang: siapa, alasan, barang, bin, dokumen, kapan.
 *
 * Tabel baru saja; data lama tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('bin_id')->nullable()->constrained('bins');
            $table->foreignId('rack_id')->nullable()->constrained('racks');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->boolean('is_dedicated')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unik per (item, bin) dan (item, rak); gudang ikut dari bin/rak.
            $table->unique(['item_id', 'bin_id']);
            $table->unique(['item_id', 'rack_id']);
            $table->index(['warehouse_id', 'item_id', 'sequence']);
        });

        Schema::create('storage_dedication_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_id')->constrained('bins');
            $table->foreignId('item_id')->constrained('items');
            $table->string('reason', 255);
            $table->string('document_type', 40)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_number', 40)->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_dedication_overrides');
        Schema::dropIfExists('item_storage_locations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Stock — ERD 08b area "Stok: ledger, saldo, reservasi, kejadian"
 * dan docs/wms/13-stock.md §3.
 *
 * Catatan schema:
 * - `stock_movements` append-only (P-01, BR-STK-01). Larangan UPDATE/DELETE
 *   ditegakkan di lapisan aplikasi lewat model; MySQL 8.4 tidak punya cara
 *   menolak DELETE tanpa trigger, jadi trigger dipasang di sini juga.
 * - `stock_balances` dikunci FOR UPDATE saat mutasi (AD-04, NFR-13).
 * - Enum disimpan VARCHAR + validasi aplikasi (AD-14).
 * - `document_sequences` melayani penomoran seluruh modul dokumen (AD-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            // Null pada asal = masuk dari luar; null pada tujuan = keluar.
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->foreignId('to_bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            // BR-LED-02: selalu positif; arah dari pasangan bin.
            $table->decimal('qty_base', 18, 4);
            $table->string('stock_status', 20)->default('available');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('document_type', 30)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('document_line_id')->nullable();
            $table->string('document_number', 40)->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->dateTime('occurred_at');                 // UTC (BR-GEN-07)
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['item_id', 'to_bin_id']);
            $table->index(['item_id', 'from_bin_id']);
            $table->index(['document_type', 'document_id']);
            $table->index('occurred_at');
            $table->index('serial_id');
            // BR-LED-05: satu baris hanya boleh dibalik sekali.
            $table->unique('reverses_movement_id');
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('stock_status', 20)->default('available');
            $table->decimal('qty_base', 18, 4)->default(0);
            $table->integer('piece_count')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'stock_status']);
            $table->index('bin_id');
        });

        // MySQL memperlakukan NULL sebagai tidak sama dengan NULL pada indeks unik,
        // sehingga kunci gabungan dengan kolom nullable tidak mencegah duplikat.
        // Kolom bayangan ini menggantikan NULL dengan 0 supaya kuncinya benar-benar unik.
        DB::statement('ALTER TABLE stock_balances ADD COLUMN lot_key BIGINT AS (COALESCE(lot_id, 0)) STORED');
        DB::statement('ALTER TABLE stock_balances ADD COLUMN serial_key BIGINT AS (COALESCE(serial_id, 0)) STORED');
        DB::statement('ALTER TABLE stock_balances ADD COLUMN piece_key BIGINT AS (COALESCE(piece_id, 0)) STORED');
        DB::statement(
            'ALTER TABLE stock_balances ADD UNIQUE KEY stock_balances_unik '
            .'(item_id, bin_id, lot_key, serial_key, piece_key, stock_status)',
        );

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Terisi hanya pada alokasi keras (BR-STK-04).
            $table->foreignId('bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('serials')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->string('level', 10)->default('soft');        // soft|hard
            $table->string('status', 20)->default('active');     // active|consumed|released
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_line_id')->nullable();
            $table->string('released_reason', 60)->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'status']);
            $table->index(['document_type', 'document_id']);
            $table->index('status');
        });

        Schema::create('stock_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('schema_version', 10)->default('1.0');
            $table->string('event_type', 40);
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 40)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->json('payload');
            $table->uuid('reverses_event_id')->nullable();
            $table->dateTime('published_at')->nullable();      // null = belum dikonsumsi
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index('published_at');
            $table->index(['source_type', 'source_id']);
        });

        // AD-13 / BR-GEN-06: nomor dokumen dikunci baris saat diambil.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->string('segment', 20);                     // kode gudang atau ALL
            $table->string('period', 7);                       // YYYY-MM
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'segment', 'period']);
        });

        // P-01 ditegakkan juga di database, bukan hanya di aplikasi.
        DB::unprepared(
            'CREATE TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements FOR EACH ROW '
            .'BEGIN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = '
            .'\'Kartu stok bersifat append-only (P-01): koreksi lewat baris pembalik.\'; END',
        );

        DB::unprepared(
            'CREATE TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements FOR EACH ROW '
            .'BEGIN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = '
            .'\'Kartu stok tidak bisa dihapus (P-01).\'; END',
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_no_update');

        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('stock_events');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
    }
};

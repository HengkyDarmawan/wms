<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Master — ERD 08a area "Master data (tenant)" dan docs/wms/11-master.md §3.
 *
 * Catatan schema:
 * - Enum disimpan VARCHAR + validasi aplikasi (AD-14); nilainya dari Katalog Status.
 * - `projects.site_warehouse_id` TIDAK dibuat: satu proyek boleh punya beberapa
 *   Gudang Site (A-40), jadi relasinya lewat `warehouses.project_id` (modul warehouse).
 * - `uom_categories.reference_uom_id` diberi foreign key setelah tabel `uoms` ada
 *   karena keduanya saling menunjuk.
 * - Foreign key `users.client_id` yang ditunda di modul Access dipasang di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();          // NPWP
            $table->text('address')->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('storage_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('capacity_mode', 10)->default('warn'); // warn|block (A-37)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('uom_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();               // count|length|weight|volume|area
            $table->string('name', 60);
            $table->unsignedBigInteger('reference_uom_id')->nullable(); // FK ditambah di bawah
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uom_category_id')->constrained('uom_categories')->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name', 60);
            // BR-MST-03: satuan acuan punya faktor 1.
            $table->decimal('factor_to_reference', 18, 8)->default(1);
            $table->decimal('rounding', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('uom_categories', function (Blueprint $table) {
            $table->foreign('reference_uom_id')->references('id')->on('uoms')->nullOnDelete();
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->foreignId('storage_category_id')->nullable()->constrained('storage_categories')->nullOnDelete();
            $table->string('removal_strategy', 20)->nullable();  // default kategori
            $table->decimal('tolerance_pct', 5, 2)->nullable();  // BR-OPN-04
            $table->decimal('tolerance_abs', 18, 4)->nullable();
            $table->char('abc_class', 1)->nullable();            // F2
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('item_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->string('status', 20)->default('active');     // item_status
            $table->string('ownership_model', 20)->default('consumable');
            $table->string('default_line_ownership', 10)->nullable(); // buy|loan (A-38)
            $table->string('tracking_mode', 10)->default('none');
            $table->boolean('has_expiry')->default(false);
            $table->foreignId('base_uom_id')->constrained('uoms')->restrictOnDelete();
            $table->boolean('is_cuttable')->default(false);
            $table->decimal('min_offcut_length', 18, 4)->nullable(); // wajib bila is_cuttable (A-19)
            $table->decimal('kerf', 18, 4)->nullable();
            $table->boolean('requires_qc')->default(false);
            $table->string('removal_strategy', 20)->nullable();   // override kategori
            $table->decimal('reorder_point', 18, 4)->nullable();  // BR-REQ-11
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('qr_payload', 120)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->json('dimensions')->nullable();
            $table->decimal('weight', 18, 4)->nullable();
            $table->foreignId('weight_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
            $table->index('tracking_mode');
        });

        Schema::create('item_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->decimal('qty_base', 18, 4);                  // 1 batang = 6 m
            $table->boolean('is_nominal_piece')->default(false); // BR-STK-09
            $table->timestamps();
            $table->unique(['item_id', 'uom_id']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms', 60)->nullable();     // teks, tanpa nilai (D-07)
            $table->string('vendor_type', 20)->default('company'); // A-52
            $table->string('status', 20)->default('active');     // vendor_status (A-53)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A-52: vendor tetap per item, tanpa harga.
        Schema::create('item_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1); // 1 = utama
            $table->boolean('is_preferred')->default(false);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'vendor_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->boolean('is_internal')->default(false);      // A-06
            $table->string('status', 20)->default('active');     // project_status (A-40)
            $table->text('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->string('close_reason', 255)->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('lot_no', 60);
            $table->date('expiry_date')->nullable();
            $table->date('received_at')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->json('attributes')->nullable();              // mis. heat number
            $table->timestamps();
            $table->unique(['item_id', 'lot_no']);
        });

        Schema::create('serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('serial_no', 80);
            $table->string('asset_state', 20)->default('available'); // BR-AST-01
            $table->char('condition_grade', 1)->nullable();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->date('expiry_date')->nullable();
            $table->string('rfid_tag', 64)->nullable();          // F2
            $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->date('due_return_date')->nullable();
            $table->date('acquired_at')->nullable();             // A-66
            $table->string('meter_unit', 10)->default('none');   // hour|km|none
            $table->decimal('meter_total', 12, 1)->default(0);
            $table->integer('expected_life_days')->nullable();
            $table->decimal('expected_life_hours', 12, 1)->nullable();
            $table->unsignedTinyInteger('condition_score')->nullable(); // BR-AST-08
            $table->timestamps();
            $table->unique(['item_id', 'serial_no']);
            $table->index('asset_state');
        });

        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('piece_no', 40)->unique();            // P-000123
            $table->decimal('length', 18, 4);                    // dalam base_uom (panjang)
            $table->boolean('is_offcut')->default(false);
            $table->foreignId('parent_piece_id')->nullable()->constrained('pieces')->nullOnDelete();
            $table->string('origin_type', 30)->nullable();       // grn|conversion|return
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->boolean('is_consumed')->default(false);
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_no', 15)->unique();
            $table->string('type', 40)->nullable();
            $table->foreignId('default_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // BR-GEN-02: alasan baku untuk tolak, batal, penyesuaian, waste, dan seterusnya.
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('context', 20);
            $table->string('code', 30);
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['context', 'code']);
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Lapis 1 dari P-08.
        Schema::create('feature_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();                // lot|serial|piece|expiry|fefo|rfid|qc
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        // Stub F2 (BR-PRJ-09, A-62).
        Schema::create('project_material_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('planned_qty_base', 18, 4);
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'item_id', 'version']);
        });

        // Foreign key users.client_id yang ditunda di modul Access.
        // Nilai lama yang tidak menunjuk klien nyata dikosongkan dulu supaya FK bisa dipasang.
        DB::table('users')->whereNotNull('client_id')->update(['client_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::dropIfExists('project_material_plans');
        Schema::dropIfExists('feature_settings');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('reason_codes');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('pieces');
        Schema::dropIfExists('serials');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('item_vendors');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('item_uom_conversions');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');

        Schema::table('uom_categories', function (Blueprint $table) {
            $table->dropForeign(['reference_uom_id']);
        });

        Schema::dropIfExists('uoms');
        Schema::dropIfExists('uom_categories');
        Schema::dropIfExists('storage_categories');
        Schema::dropIfExists('clients');
    }
};

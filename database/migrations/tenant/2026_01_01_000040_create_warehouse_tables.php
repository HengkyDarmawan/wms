<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Warehouse — ERD 08a area "Gudang & lokasi (tenant)" dan docs/wms/12-warehouse.md §3.
 *
 * Catatan schema:
 * - Enum disimpan VARCHAR + validasi aplikasi (AD-14); nilainya dari Katalog Status §3.
 * - `bins.count_flag` adalah tambahan terhadap ERD, dicatat sebagai A-67
 *   (menunggu validasi): istilahnya sudah ada di glosarium dan dipakai BR-SJ-02.
 * - `bins.frozen_by_count_id` menunjuk `stock_counts` yang lahir di modul `count`,
 *   jadi kolomnya dibuat tanpa foreign key dulu dan diberi indeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();      // main|branch|site|…
            $table->string('name', 60);
            $table->boolean('is_builtin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();      // segmen {GUDANG} nomor dokumen (BR-GEN-06)
            $table->string('name', 100);
            $table->foreignId('warehouse_type_id')->constrained('warehouse_types')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouses')->nullOnDelete();
            // Wajib bila tipe = site; satu proyek boleh punya beberapa (A-40).
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('code', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['zone_id', 'code']);
        });

        Schema::create('rack_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('racks')->cascadeOnDelete();
            $table->string('code', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['rack_id', 'code']);
        });

        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            // Denormalisasi: hampir setiap query saldo menyaring per gudang.
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('rack_level_id')->nullable()->constrained('rack_levels')->nullOnDelete();
            $table->string('code', 40)->unique();      // CKG-A-R03-L2-B05 (BR-WH-01)
            $table->string('bin_type', 20)->default('storage');
            $table->string('bin_status', 20)->default('active');
            $table->foreignId('storage_category_id')->nullable()->constrained('storage_categories')->nullOnDelete();
            $table->decimal('capacity_qty', 18, 4)->nullable();
            $table->decimal('capacity_weight', 18, 4)->nullable();
            $table->decimal('capacity_volume', 18, 4)->nullable();
            $table->decimal('capacity_length', 18, 4)->nullable();
            // Hanya untuk bin on_site (BR-WH-03).
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->boolean('is_virtual')->default(false);
            // FK ke stock_counts ditambahkan modul `count`.
            $table->unsignedBigInteger('frozen_by_count_id')->nullable();
            $table->string('freeze_reason', 255)->nullable();
            // A-67: bin ditandai perlu dihitung setelah short pick / selisih kirim.
            $table->boolean('count_flag')->default(false);
            $table->timestamps();
            $table->index(['warehouse_id', 'bin_type']);
            $table->index('bin_status');
            $table->index('frozen_by_count_id');
            $table->index('count_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bins');
        Schema::dropIfExists('rack_levels');
        Schema::dropIfExists('racks');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('warehouse_types');
    }
};

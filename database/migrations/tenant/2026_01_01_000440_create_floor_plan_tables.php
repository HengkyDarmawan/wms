<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denah gedung (A-320–A-325): ukuran gedung, posisi zona di dalam gedung, dan
 * objek denah tanpa stok (pintu, dock, jalur forklift, pilar, kantor, area
 * bebas). Semua opsional — kosong = denah menata otomatis seperti A-254.
 * Objek tidak dihapus fisik, cukup dinonaktifkan (P-03), dan tidak pernah
 * menyentuh kartu stok (P-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->decimal('length_m', 8, 2)->nullable()->after('address');
            $table->decimal('width_m', 8, 2)->nullable()->after('length_m');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->decimal('pos_x', 8, 2)->nullable()->after('width_m');
            $table->decimal('pos_y', 8, 2)->nullable()->after('pos_x');
        });

        Schema::create('floor_plan_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('object_type', 20);                  // floor_plan_object_type
            $table->string('name', 60);
            $table->decimal('pos_x', 8, 2)->default(0);
            $table->decimal('pos_y', 8, 2)->default(0);
            $table->decimal('length_m', 8, 2);
            $table->decimal('width_m', 8, 2);
            $table->unsignedSmallInteger('rotation')->default(0); // 0|90|180|270
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['warehouse_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_plan_objects');

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['length_m', 'width_m']);
        });
    }
};

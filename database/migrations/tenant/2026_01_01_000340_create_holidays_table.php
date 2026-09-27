<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-270: kalender libur company. Libur nasional & cuti bersama diisi otomatis
 * dari SKB 3 Menteri (Support\NationalHolidays) lalu boleh dinonaktifkan atau
 * ditambah libur company. Dipakai menghitung hari kerja (SLA tinjau).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 120);
            $table->string('kind', 20);                 // holiday_kind: national|joint_leave|company
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};

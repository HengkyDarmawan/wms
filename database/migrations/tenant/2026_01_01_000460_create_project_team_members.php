<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tim site (A-337): tanggal berlaku dicabut dari form pengguna dan hidup di
 * sini — siapa ditempatkan di site proyek mana, sejak kapan sampai kapan.
 * Baris ini yang mengelola `role_assignments` bertanggal, ditandai lewat
 * `role_assignments.project_team_member_id`, supaya form pengguna tidak pernah
 * ikut menghapus atau mempermanenkannya.
 *
 * Tidak ada hapus fisik (P-03): keanggotaan yang selesai tetap tercatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();   // peran di site
            $table->date('starts_on');
            $table->date('ends_on');
            $table->dateTime('ended_at')->nullable();                                 // diakhiri lebih awal
            $table->foreignId('end_reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('end_notes', 255)->nullable();
            $table->dateTime('reminded_at')->nullable();                              // H-7 dikirim sekali
            // false = orangnya sudah punya penugasan tetap dengan cakupan yang sama,
            // jadi keanggotaan ini hanya catatan dan tidak boleh mencabut akses itu.
            $table->boolean('grants_access')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'ends_on']);
            $table->index(['user_id', 'ends_on']);
        });

        Schema::table('role_assignments', function (Blueprint $table) {
            $table->foreignId('project_team_member_id')->nullable()->after('assigned_by')
                ->constrained('project_team_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_team_member_id');
        });

        Schema::dropIfExists('project_team_members');
    }
};

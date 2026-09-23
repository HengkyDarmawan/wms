<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-GEN-05: dua catatan resmi. `audit_logs` = catatan teknis (nilai lama -> baru, IP),
 * hanya untuk Admin; keduanya append-only. `document_timelines` dibuat modul Shared
 * saat dokumen pertama ada.
 *
 * Skema mengikuti spatie/laravel-activitylog 4.x dengan nama tabel `audit_logs`
 * (lihat config/activitylog.php) ditambah kolom `ip_address`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

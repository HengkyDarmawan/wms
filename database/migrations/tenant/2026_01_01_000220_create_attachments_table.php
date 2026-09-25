<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran generik (08c `attachments`, A-68, A-238): foto pemakaian ISU, foto
 * serah terima aset keluar, dan arsip laporan PDF opname. Berkasnya di disk
 * `local` per company; baris ini hanya menunjuk path-nya. Kolom rujukan yang
 * sudah ada (`asset_handovers.photo_out_id`, `stock_counts.report_attachment_id`)
 * tetap tanpa FK seperti kolom stub lain (A-74).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->string('kind', 20); // attachment_kind: photo|document|signature|report
            $table->string('disk', 20)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 150)->nullable();
            $table->string('mime', 60);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};

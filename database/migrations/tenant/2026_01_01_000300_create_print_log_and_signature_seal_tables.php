<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-263: riwayat cetak dokumen — setiap cetak dicatat (siapa, kapan, cetakan
 * ke berapa); cetakan kedua dst. SJ, Bukti Terima, dan PO bertanda
 * "CETAK ULANG ke-n".
 *
 * A-264: segel tanda tangan — setiap kotak tanda tangan yang pelakunya
 * diketahui mendapat token acak; QR di cetakan membuka halaman verifikasi
 * publik (dokumen apa, milik company mana, siapa, kapan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);                 // document_template_type
            $table->unsignedBigInteger('document_id');
            $table->string('document_number', 60);
            $table->unsignedInteger('copy_no');                  // cetakan ke-n per dokumen × jenis
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('printed_at');
            $table->string('ip', 45)->nullable();
            $table->index(['document_type', 'document_id']);
        });

        Schema::create('signature_seals', function (Blueprint $table) {
            $table->id();
            $table->char('token', 40)->unique();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->string('document_number', 60);
            $table->unsignedTinyInteger('block_no');
            $table->string('block_label', 40);
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name', 120);
            $table->string('signer_title', 120)->nullable();     // jabatan saat disegel
            $table->dateTime('acted_at')->nullable();            // waktu tindakan di dokumen, bila tercatat
            $table->dateTime('sealed_at');                       // segel pertama kali diterbitkan (saat dicetak)
            $table->char('fingerprint', 64);
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_seals');
        Schema::dropIfExists('print_logs');
    }
};

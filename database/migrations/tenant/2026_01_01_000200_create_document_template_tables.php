<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Template dokumen & label (18-template-dokumen-label §3, ERD 08c).
 * Kolom di luar ERD: `document_layouts.logo_path`, `document_templates.paper`
 * varchar(20), timestamps (A-123).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedBigInteger('logo_attachment_id')->nullable(); // tanpa FK sampai tabel lampiran ada
            $table->string('logo_path', 255)->nullable();
            $table->text('header_html')->nullable();   // F1 teks biasa (A-122)
            $table->text('footer_html')->nullable();
            $table->json('colors')->nullable();
            $table->json('signature_blocks')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);       // document_template_type
            $table->string('name', 80);
            $table->foreignId('layout_id')->nullable()->constrained('document_layouts')->nullOnDelete();
            $table->text('body_html')->nullable();     // F1 kosong = Blade bawaan
            $table->string('paper', 20);               // paper_size
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['document_type', 'name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_layouts');
    }
};

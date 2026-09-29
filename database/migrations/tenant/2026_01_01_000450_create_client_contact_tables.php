<?php

declare(strict_types=1);

use App\Domain\Master\Support\LegacyClientContacts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIC Klien ([A-326]): satu klien boleh punya banyak orang dari **pihak klien**,
 * masing-masing mengurus proyek tertentu dan sebagian punya akun portal.
 * Kontak tunggal lama di `clients` (`contact_name`, `phone`, `email`) diisi
 * balik menjadi PIC pertama; kolomnya tidak dihapus (P-03), hanya berhenti
 * ditampilkan di form klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('position', 100)->nullable();   // jabatan di company klien
            $table->string('phone', 20)->nullable();       // No. WA, dibakukan `62…` (A-315)
            $table->string('email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();      // akun portal
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        // Proyek yang diurus PIC — info kontak; hak lihat portal diatur Tim site (A-337).
        Schema::create('client_contact_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_contact_id')->constrained('client_contacts')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['client_contact_id', 'project_id'], 'client_contact_project_unik');
        });

        LegacyClientContacts::isiBalik();
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contact_project');
        Schema::dropIfExists('client_contacts');
    }
};

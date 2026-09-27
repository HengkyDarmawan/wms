<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-261, A-262: ukuran label sebagai master per company dan desain label
 * yang bisa ditata (menggantikan dua kertas tetap A-120).
 *
 * - `label_formats`: gulungan thermal (satu label per halaman) atau lembar
 *   (kolom × baris dengan margin dan jarak antar label), dalam milimeter.
 * - `label_designs`: tata letak elemen (judul, sub, detail, barcode, QR,
 *   logo) per jenis label × format, plus pilihan kode yang dicetak.
 * - `document_templates.label_format_id`: format bawaan per jenis label.
 *
 * Preset ikut dibuat di sini supaya company yang sudah ada langsung punya
 * format; template label lama dipetakan dari kolom `paper`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_formats', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('media', 10);                       // label_media: roll | sheet
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            $table->decimal('page_width_mm', 6, 2)->nullable();  // sheet saja
            $table->decimal('page_height_mm', 6, 2)->nullable();
            $table->unsignedTinyInteger('columns')->default(1);
            $table->unsignedTinyInteger('rows')->default(1);
            $table->decimal('margin_top_mm', 6, 2)->default(0);
            $table->decimal('margin_left_mm', 6, 2)->default(0);
            $table->decimal('gap_x_mm', 6, 2)->default(0);
            $table->decimal('gap_y_mm', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('label_designs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);                // document_template_type label_*
            $table->foreignId('label_format_id')->constrained('label_formats');
            $table->string('code_mode', 10)->default('both');   // label_code_mode
            $table->json('elements');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_type', 'label_format_id']);
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreignId('label_format_id')->nullable()->after('paper')->constrained('label_formats')->nullOnDelete();
        });

        $sekarang = now();
        $preset = [
            ['THERMAL-50X30', 'Thermal 50×30 mm', 'roll', 50, 30, null, null, 1, 1, 0, 0, 0, 0],
            ['THERMAL-40X30', 'Thermal 40×30 mm', 'roll', 40, 30, null, null, 1, 1, 0, 0, 0, 0],
            ['THERMAL-100X50', 'Thermal 100×50 mm', 'roll', 100, 50, null, null, 1, 1, 0, 0, 0, 0],
            ['THERMAL-100X150', 'Thermal 100×150 mm (palet/resi)', 'roll', 100, 150, null, null, 1, 1, 0, 0, 0, 0],
            ['A4-3X8', 'Lembar A4 3×8 (70×37 mm)', 'sheet', 70, 37, 210, 297, 3, 8, 0.5, 0, 0, 0],
            ['A4-2X7', 'Lembar A4 2×7 (99,1×38,1 mm)', 'sheet', 99.1, 38.1, 210, 297, 2, 7, 15.15, 4.65, 2.5, 0],
        ];

        foreach ($preset as [$kode, $nama, $media, $w, $h, $pw, $ph, $kol, $bar, $mt, $ml, $gx, $gy]) {
            DB::table('label_formats')->insert([
                'code' => $kode, 'name' => $nama, 'media' => $media,
                'width_mm' => $w, 'height_mm' => $h, 'page_width_mm' => $pw, 'page_height_mm' => $ph,
                'columns' => $kol, 'rows' => $bar, 'margin_top_mm' => $mt, 'margin_left_mm' => $ml,
                'gap_x_mm' => $gx, 'gap_y_mm' => $gy, 'is_active' => true,
                'created_at' => $sekarang, 'updated_at' => $sekarang,
            ]);
        }

        $petakan = [
            'label_50x30' => DB::table('label_formats')->where('code', 'THERMAL-50X30')->value('id'),
            'label_a4_3x8' => DB::table('label_formats')->where('code', 'A4-3X8')->value('id'),
        ];

        foreach ($petakan as $kertas => $formatId) {
            DB::table('document_templates')->where('document_type', 'like', 'label\_%')
                ->where('paper', $kertas)->update(['label_format_id' => $formatId]);
        }
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_format_id');
        });
        Schema::dropIfExists('label_designs');
        Schema::dropIfExists('label_formats');
    }
};

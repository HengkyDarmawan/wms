<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PIL-01 — komponen `<x-pilih>` (A-383): kelompok berurutan, teks kedua,
 * badge, galat validasi + aria, label terhubung ke kotak cari, mode server,
 * dialog, ukuran kecil, dan disabled.
 */
class PilihKomponenTest extends TenantTestCase
{
    #[Test]
    public function tc_pil_01_render_kelompok_sub_badge_galat_dan_mode(): void
    {
        $opsi = [
            ['value' => 3, 'text' => 'Cici', 'group' => 'Unit lain'],
            ['value' => 1, 'text' => 'Andi', 'badge' => 'Kepala Gudang', 'sub' => 'Gudang', 'group' => 'Unit ini'],
            ['value' => 9, 'text' => 'Tersimpan <lama>'],
        ];

        $html = (string) $this->withViewErrors(['managerId' => 'Pilih atasan dari daftar.'])->blade(
            '<x-pilih model="managerId" id="atasan" label="Atasan" wajib kosong="— Ikuti jabatan —" server kunci="7"
                      :kelompok="[\'Unit ini\', \'Unit induk\', \'Unit lain\']" :options="$opsi" />',
            ['opsi' => $opsi],
        );

        // Label terhubung ke kotak cari Tom Select; tanda wajib.
        $this->assertStringContainsString('id="atasan-label" for="atasan-ts-control"', $html);
        $this->assertStringContainsString('<span class="wajib">*</span>', $html);
        // Galat: pembungkus bertanda, pesan beridentitas untuk aria-describedby.
        $this->assertStringContainsString('class="nx-pilih is-invalid"', $html);
        $this->assertStringContainsString('id="atasan-galat">Pilih atasan dari daftar.', $html);
        // Mode server: model & batas huruf untuk cariPilihan.
        $this->assertStringContainsString('data-model="managerId"', $html);
        $this->assertStringContainsString('data-server="1" data-min="2"', $html);
        // Pilihan kosong paling atas, opsi tanpa kelompok sebelum kelompok, teks di-escape.
        $this->assertLessThan(strpos($html, 'Tersimpan'), strpos($html, '<option value="">— Ikuti jabatan —</option>'));
        $this->assertStringContainsString('<option value="9">Tersimpan &lt;lama&gt;</option>', $html);
        // Kelompok mengikuti urutan `kelompok` (Unit ini → Unit induk → Unit lain), walau kosong.
        $ini = strpos($html, '<optgroup label="Unit ini">');
        $induk = strpos($html, '<optgroup label="Unit induk">');
        $lain = strpos($html, '<optgroup label="Unit lain">');
        $this->assertTrue($ini < $induk && $induk < $lain, 'Urutan kelompok terkunci.');
        $this->assertStringContainsString('<option value="1" data-badge="Kepala Gudang" data-sub="Gudang">Andi</option>', $html);

        // Mode biasa: tanpa data-server; kecil, dialog, disabled; tanpa galat → tanpa is-invalid.
        $biasa = (string) $this->withViewErrors([])->blade(
            '<x-pilih model="lines.3.item_id" kecil dialog disabled :options="[5 => \'Baut\']" />',
        );
        $this->assertStringNotContainsString('data-server', $biasa);
        $this->assertStringContainsString('class="nx-pilih nx-pilih-sm"', $biasa);
        $this->assertStringContainsString('id="pilih-lines-3-item-id"', $biasa);
        $this->assertStringContainsString('data-dialog="1"', $biasa);
        $this->assertMatchesRegularExpression('/<select[^>]*disabled/', $biasa);
        $this->assertStringContainsString('$wire.entangle(\'lines.3.item_id\')', $biasa);
        $this->assertStringContainsString('<option value="5">Baut</option>', $biasa);

        // Kunci wire:key: mode biasa ikut isi daftar, mode server hanya ikut `kunci`.
        $a = (string) $this->withViewErrors([])->blade('<x-pilih model="x" :options="[1 => \'A\']" />');
        $b = (string) $this->withViewErrors([])->blade('<x-pilih model="x" :options="[2 => \'B\']" />');
        $this->assertNotSame($this->kunci($a), $this->kunci($b), 'Daftar berganti → kotak dibuat ulang.');
        $s1 = (string) $this->withViewErrors([])->blade('<x-pilih model="x" server kunci="1" :options="[1 => \'A\']" />');
        $s2 = (string) $this->withViewErrors([])->blade('<x-pilih model="x" server kunci="1" :options="[2 => \'B\']" />');
        $this->assertSame($this->kunci($s1), $this->kunci($s2), 'Mode server: memilih nilai tidak membuat ulang kotak.');
    }

    /**
     * TC-PIL-10 — opsi nonaktif (batang di bin beku, Konversi) dan kunci galat yang berbeda
     * dari model (`batang` ↔ `rencana.batang`); baris dinamis ber-model path mendapat id unik.
     */
    #[Test]
    public function tc_pil_10_opsi_nonaktif_dan_kunci_galat_berbeda(): void
    {
        $html = (string) $this->withViewErrors(['rencana.batang' => 'Pilih batang dulu.'])->blade(
            '<x-pilih id="cnv-batang" model="batang" galat="rencana.batang" live :options="$opsi" />',
            ['opsi' => [
                ['value' => 'p_1', 'text' => 'BESI · P-1'],
                ['value' => 'p_2', 'text' => 'BESI · P-2 · dibeku', 'disabled' => true],
            ]],
        );

        // Opsi nonaktif dibawa ke <option disabled> (Tom Select membacanya → tidak bisa dipilih).
        $this->assertStringContainsString('<option value="p_1">BESI · P-1</option>', $html);
        $this->assertStringContainsString('<option value="p_2" disabled>BESI · P-2 · dibeku</option>', $html);
        // Galat dibaca dari kunci `galat`, bukan dari model.
        $this->assertStringContainsString('class="nx-pilih is-invalid"', $html);
        $this->assertStringContainsString('id="cnv-batang-galat">Pilih batang dulu.', $html);
        $this->assertStringContainsString('$wire.entangle(\'batang\').live', $html);

        // Tanpa galat pada kunci itu → tidak bertanda walau model punya galat lain.
        $bersih = (string) $this->withViewErrors(['batang' => 'x'])->blade(
            '<x-pilih model="batang" galat="rencana.batang" :options="[1 => \'A\']" />',
        );
        $this->assertStringNotContainsString('is-invalid', $bersih);

        // Baris dinamis (Put-away, Picking, Pilah): id & kunci unik per baris.
        $a = (string) $this->withViewErrors([])->blade('<x-pilih model="isian.7.bin_id" kecil :options="[1 => \'A\']" />');
        $b = (string) $this->withViewErrors([])->blade('<x-pilih model="isian.8.bin_id" kecil :options="[1 => \'A\']" />');
        $this->assertStringContainsString('id="pilih-isian-7-bin-id"', $a);
        $this->assertNotSame($this->kunci($a), $this->kunci($b));
    }

    private function kunci(string $html): string
    {
        preg_match('/wire:key="([^"]+)"/', $html, $m);

        return $m[1] ?? '';
    }
}

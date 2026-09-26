<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Attachments\Support\AttachmentStore;
use App\Domain\Shared\Files\ImageCompressor;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ProofFiles;
use Database\Seeders\Tenant\MasterDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * TC-FIL-03 s.d. TC-FIL-03e — kompresi foto otomatis (A-23, A-257): unggahan
 * mentah ≤ 20 MB, tersimpan ≤ 5 MB dan sisi terpanjang ≤ 1920 px; orientasi
 * EXIF; PNG transparan & tanda tangan tetap PNG; PDF tidak disentuh.
 */
class ImageCompressionTest extends TenantTestCase
{
    /** @var array<int, string> berkas sementara yang dibuat uji */
    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    /** Foto "kamera HP" sungguhan: noise per piksel, JPEG > 5 MB dan ≤ 20 MB. */
    private function fotoBesar(int $lebar = 3000, int $tinggi = 2250, bool $wajibLebihLimaMb = true): UploadedFile
    {
        $ubin = imagecreatetruecolor(500, 375);

        for ($x = 0; $x < 500; $x++) {
            for ($y = 0; $y < 375; $y++) {
                imagesetpixel($ubin, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }

        $gambar = imagecreatetruecolor($lebar, $tinggi);

        for ($x = 0; $x < $lebar; $x += 500) {
            for ($y = 0; $y < $tinggi; $y += 375) {
                imagecopy($gambar, $ubin, $x, $y, 0, 0, 500, 375);
            }
        }

        foreach ([95, 90, 85, 75] as $q) {
            $path = $this->berkasSementara('.jpg');
            imagejpeg($gambar, $path, $q);

            if (filesize($path) > 5.5 * 1024 * 1024 && filesize($path) < 19 * 1024 * 1024) {
                break;
            }
        }

        if ($wajibLebihLimaMb) {
            $this->assertGreaterThan(StoreUpload::MAKSIMUM_BYTE, filesize($path), 'Foto uji harus lebih besar dari 5 MB.');
        }

        return new UploadedFile($path, 'kamera-hp.jpg', 'image/jpeg', null, true);
    }

    private function berkasSementara(string $ekstensi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wmsfoto').$ekstensi;
        $this->temp[] = $path;

        return $path;
    }

    /** @return array{0: int, 1: int, mime: string} */
    private function info(string $path): array
    {
        $info = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertNotFalse($info);

        return $info;
    }

    #[Test]
    public function tc_fil_03_foto_besar_dari_kamera_dikompres_saat_unggah_foto_item(): void
    {
        (new MasterDemoSeeder)->run();
        $item = Item::query()->where('code', 'BAUT-M12')->firstOrFail();
        $foto = $this->fotoBesar();

        $this->actingAs($this->makeUser('company_admin'))
            ->post($this->tenantUrl('items/'.$item->id.'/photo'), ['photo' => $foto])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $path = (string) $item->refresh()->photo_path;
        $info = $this->info($path);

        $this->assertStringEndsWith('.jpg', $path);
        $this->assertLessThanOrEqual(StoreUpload::MAKSIMUM_BYTE, Storage::disk('local')->size($path), 'A-23: tersimpan ≤ 5 MB.');
        $this->assertSame([1920, 1440], [$info[0], $info[1]], 'A-257: sisi terpanjang 1920 px, proporsi tetap 4:3.');
        $this->assertSame('image/jpeg', $info['mime']);
    }

    #[Test]
    public function tc_fil_03b_lampiran_dan_bukti_terima_ikut_dikompres(): void
    {
        // Lampiran (foto ISU, foto serah terima AST): ukuran & MIME tercatat = berkas tersimpan.
        $isu = (new MaterialIssue)->forceFill(['id' => 987654]);
        $foto = $this->fotoBesar(2400, 3200);
        $mentah = $foto->getSize();

        $lampiran = app(AttachmentStore::class)->photo($isu, $foto);

        $this->assertSame(Storage::disk('local')->size($lampiran->path), (int) $lampiran->size_bytes);
        $this->assertLessThan($mentah, (int) $lampiran->size_bytes);
        $this->assertLessThanOrEqual(StoreUpload::MAKSIMUM_BYTE, (int) $lampiran->size_bytes);
        $this->assertSame('image/jpeg', $lampiran->mime);
        $this->assertSame([1440, 1920], array_slice($this->info($lampiran->path), 0, 2), 'Foto potret: tinggi 1920 px.');

        // Bukti terima (halaman penerima / layar driver): foto besar diterima, tanda tangan tetap PNG.
        $sj = (new Shipment)->forceFill(['id' => 424242]);
        $ttd = imagecreatetruecolor(300, 100);
        imagesavealpha($ttd, true);
        imagefill($ttd, 0, 0, imagecolorallocatealpha($ttd, 0, 0, 0, 127));
        imageline($ttd, 10, 50, 290, 50, imagecolorallocate($ttd, 0, 0, 0));
        ob_start();
        imagepng($ttd);
        $dataUrl = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $hasil = app(ProofFiles::class)->simpan($sj, $this->fotoBesar(), $dataUrl, [7 => $this->fotoBesar()]);

        foreach ([$hasil['photo_path'], $hasil['lines'][7]] as $p) {
            $this->assertLessThanOrEqual(StoreUpload::MAKSIMUM_BYTE, Storage::disk('local')->size($p));
            $this->assertLessThanOrEqual(1920, max(array_slice($this->info($p), 0, 2)));
        }

        $this->assertStringEndsWith('.png', (string) $hasil['signature_path']);
        $this->assertSame('image/png', $this->info((string) $hasil['signature_path'])['mime'], 'Tanda tangan kanvas tidak dijadikan JPEG.');
    }

    #[Test]
    public function tc_fil_03c_foto_diputar_sesuai_orientasi_exif(): void
    {
        $g = imagecreatetruecolor(400, 200);
        imagefill($g, 0, 0, imagecolorallocate($g, 200, 30, 30));
        ob_start();
        imagejpeg($g, null, 90);
        $jpeg = (string) ob_get_clean();

        // APP1 Exif, TIFF little-endian, IFD0 berisi Orientation = 6 (putar 90° searah jarum jam).
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', 6).pack('v', 0).pack('V', 0);
        $isi = "Exif\0\0".$tiff;
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($isi) + 2).$isi.substr($jpeg, 2);

        $path = $this->berkasSementara('.jpg');
        file_put_contents($path, $jpeg);

        $this->assertSame(6, app(ImageCompressor::class)->orientation($path), 'Dibaca tanpa ekstensi exif.');

        $simpan = app(StoreUpload::class)->handle(new UploadedFile($path, 'miring.jpg', 'image/jpeg', null, true), 'uji', 'exif');

        $this->assertSame([200, 400], array_slice($this->info($simpan), 0, 2), 'Lebar ↔ tinggi tertukar setelah diputar.');
    }

    #[Test]
    public function tc_fil_03d_png_transparan_tetap_png_dan_png_foto_menjadi_jpeg(): void
    {
        // PNG transparan besar: diperkecil, tetap PNG, alpha terjaga.
        $g = imagecreatetruecolor(2500, 1000);
        imagealphablending($g, false);
        imagesavealpha($g, true);
        imagefill($g, 0, 0, imagecolorallocatealpha($g, 0, 0, 0, 127));
        imagefilledrectangle($g, 1000, 300, 1500, 700, imagecolorallocatealpha($g, 0, 0, 200, 0));
        $path = $this->berkasSementara('.png');
        imagepng($g, $path);

        $simpan = app(StoreUpload::class)->handle(new UploadedFile($path, 'logo.png', 'image/png', null, true), 'uji', 'transparan');
        $hasil = imagecreatefromstring(Storage::disk('local')->get($simpan));

        $this->assertStringEndsWith('.png', $simpan);
        $this->assertSame([1920, 768], [imagesx($hasil), imagesy($hasil)]);
        $this->assertSame(127, imagecolorsforindex($hasil, imagecolorat($hasil, 5, 5))['alpha'], 'Pojok tetap transparan.');

        // Tanda tangan lewat Profil (keepFormat): PNG tetap PNG.
        $user = $this->makeUser('warehouse_staff');
        $this->actingAs($user)->post($this->tenantUrl('profile/signature'), [
            'signature' => new UploadedFile($path, 'ttd.png', 'image/png', null, true),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.png', (string) $user->refresh()->signature_path);

        // PNG buram besar (tangkapan layar/foto): menjadi JPEG.
        $foto = imagecreatefromjpeg($this->fotoBesar(2400, 1800, false)->getRealPath());
        $png = $this->berkasSementara('.png');
        imagepng($foto, $png);

        $simpan = app(StoreUpload::class)->handle(new UploadedFile($png, 'layar.png', 'image/png', null, true), 'uji', 'buram');

        $this->assertStringEndsWith('.jpg', $simpan);
        $this->assertSame('image/jpeg', $this->info($simpan)['mime']);
        $this->assertLessThanOrEqual(StoreUpload::MAKSIMUM_BYTE, Storage::disk('local')->size($simpan));
    }

    #[Test]
    public function tc_fil_03e_pdf_dan_foto_kecil_tidak_diubah_mentah_di_atas_20_mb_ditolak(): void
    {
        $files = app(StoreUpload::class);

        // PDF (bila pemanggil mengizinkan): disimpan byte-identik.
        $pdf = $this->berkasSementara('.pdf');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $simpan = $files->handle(new UploadedFile($pdf, 'nota.pdf', 'application/pdf', null, true), 'uji', 'nota', ['application/pdf']);

        $this->assertStringEndsWith('.pdf', $simpan);
        $this->assertSame(file_get_contents($pdf), Storage::disk('local')->get($simpan));

        // Foto kecil (≤ 1920 px, ≤ 2 MB, tanpa EXIF): disimpan apa adanya.
        $kecil = $this->berkasSementara('.jpg');
        imagejpeg(imagecreatetruecolor(800, 600), $kecil, 85);
        $simpan = $files->handle(new UploadedFile($kecil, 'kecil.jpg', 'image/jpeg', null, true), 'uji', 'kecil');
        $this->assertSame(file_get_contents($kecil), Storage::disk('local')->get($simpan));

        // Mentah > 20 MB ditolak sebelum diproses.
        try {
            $files->handle(UploadedFile::fake()->image('raksasa.jpg')->size(21000), 'uji', 'raksasa');
            $this->fail('Berkas > 20 MB seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('20 MB', $e->getMessage());
        }

        // Batas unggahan sementara Livewire (layar driver) mengikuti 20 MB.
        $this->assertContains('max:20480', config('livewire.temporary_file_upload.rules'));
        $this->assertContains('max:20480', StoreUpload::ATURAN_FOTO);
    }
}

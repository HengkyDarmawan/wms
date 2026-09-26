<?php

declare(strict_types=1);

namespace App\Domain\Shared\Files;

use GdImage;
use RuntimeException;

/**
 * Kompresi foto unggahan di server (A-23, A-257) dengan GD — tanpa paket baru.
 *
 * - Dikompres hanya bila perlu: sisi terpanjang > 1920 px, berkas > 2 MB,
 *   atau orientasi EXIF ≠ normal. Foto kecil disimpan apa adanya supaya tidak
 *   kehilangan mutu karena disimpan ulang.
 * - JPEG → JPEG, WEBP → WEBP, kualitas 80 (turun ke 70, lalu 60 bila hasilnya
 *   masih > 5 MB). Sisi terpanjang diperkecil proporsional ke 1920 px.
 * - PNG bertransparansi, atau pemanggil meminta format tetap (tanda tangan,
 *   logo), tetap PNG. PNG buram (foto/tangkapan layar) menjadi JPEG berlatar
 *   putih.
 * - Orientasi EXIF dibaca dari segmen APP1 JPEG langsung (ekstensi `exif` tidak
 *   wajib) dan fotonya diputar sebelum disimpan, karena GD membuang EXIF.
 * - Gambar > 50 MP ditolak; batas memori PHP dinaikkan sementara seperlunya.
 */
class ImageCompressor
{
    public const SISI_MAKS = 1920;

    public const KUALITAS = 80;

    /** @var array<int, int> kualitas cadangan bila hasil masih > 5 MB */
    public const KUALITAS_CADANGAN = [70, 60];

    /** Berkas di atas ini selalu dikompres walau dimensinya kecil. */
    public const AMBANG_BYTE = 2 * 1024 * 1024;

    public const PIKSEL_MAKS = 50_000_000;

    private const MEMORI_MAKS = 768 * 1024 * 1024;

    /**
     * @return array{bytes: string, mime: string}|null null = simpan berkas asli apa adanya
     */
    public function compress(string $path, string $mime, bool $keepFormat = false): ?array
    {
        if (! in_array($mime, StoreUpload::TIPE_GAMBAR, true)) {
            return null; // PDF dan berkas lain tidak disentuh.
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('Gambar tidak terbaca.');
        }

        [$lebar, $tinggi] = [(int) $info[0], (int) $info[1]];

        if ($lebar * $tinggi > self::PIKSEL_MAKS) {
            throw new RuntimeException('Gambar terlalu besar ('.round($lebar * $tinggi / 1_000_000).' MP); maksimal 50 MP.');
        }

        $orientasi = $mime === 'image/jpeg' ? $this->orientation($path) : 1;
        $ukuran = (int) filesize($path);
        $sisi = max($lebar, $tinggi);

        if ($sisi <= self::SISI_MAKS && $ukuran <= self::AMBANG_BYTE && $orientasi === 1) {
            return null;
        }

        $transparan = $mime === 'image/png' && $this->pngHasAlpha($path);
        $tujuan = match (true) {
            $mime === 'image/png' && ($transparan || $keepFormat) => 'image/png',
            $mime === 'image/webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $memoriLama = $this->reserveMemory($lebar * $tinggi);

        try {
            $gambar = match ($mime) {
                'image/png' => @imagecreatefrompng($path),
                'image/webp' => @imagecreatefromwebp($path),
                default => @imagecreatefromjpeg($path),
            };

            if (! $gambar instanceof GdImage) {
                throw new RuntimeException('Gambar tidak terbaca.');
            }

            $gambar = $this->orient($gambar, $orientasi);
            $gambar = $this->resize($gambar, $tujuan === 'image/png');

            return ['bytes' => $this->encode($gambar, $tujuan), 'mime' => $tujuan];
        } finally {
            if ($memoriLama !== null) {
                ini_set('memory_limit', $memoriLama);
            }
        }
    }

    /**
     * Orientasi EXIF (1–8) dari segmen APP1 JPEG; 1 bila tidak ada.
     */
    public function orientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $nilai = (int) ($exif['Orientation'] ?? 1);

            return $nilai >= 1 && $nilai <= 8 ? $nilai : 1;
        }

        $data = (string) @file_get_contents($path, false, null, 0, 256 * 1024);

        if (strlen($data) < 4 || ord($data[0]) !== 0xFF || ord($data[1]) !== 0xD8) {
            return 1;
        }

        $pos = 2;

        while ($pos + 4 <= strlen($data) && ord($data[$pos]) === 0xFF) {
            $penanda = ord($data[$pos + 1]);

            if ($penanda === 0xDA || $penanda === 0xD9) {
                break; // awal data gambar: EXIF sudah lewat
            }

            $panjang = unpack('n', substr($data, $pos + 2, 2))[1];

            if ($penanda === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
                return $this->orientationFromTiff(substr($data, $pos + 10, $panjang - 8));
            }

            $pos += 2 + $panjang;
        }

        return 1;
    }

    private function orientationFromTiff(string $tiff): int
    {
        if (strlen($tiff) < 8) {
            return 1;
        }

        $kecil = substr($tiff, 0, 2) === 'II';
        $u16 = fn (int $o) => $o + 2 <= strlen($tiff) ? unpack($kecil ? 'v' : 'n', substr($tiff, $o, 2))[1] : 0;
        $u32 = fn (int $o) => $o + 4 <= strlen($tiff) ? unpack($kecil ? 'V' : 'N', substr($tiff, $o, 4))[1] : 0;

        $ifd = $u32(4);
        $jumlah = $u16($ifd);

        for ($i = 0; $i < $jumlah; $i++) {
            $entri = $ifd + 2 + $i * 12;

            if ($u16($entri) === 0x0112) {
                $nilai = $u16($entri + 8);

                return $nilai >= 1 && $nilai <= 8 ? $nilai : 1;
            }
        }

        return 1;
    }

    /** PNG ber-alpha: color type 4/6 di IHDR, atau chunk tRNS sebelum IDAT. */
    private function pngHasAlpha(string $path): bool
    {
        $data = (string) @file_get_contents($path, false, null, 0, 64 * 1024);

        if (strlen($data) < 26) {
            return false;
        }

        $tipeWarna = ord($data[25]);

        if ($tipeWarna === 4 || $tipeWarna === 6) {
            return true;
        }

        $idat = strpos($data, 'IDAT');
        $trns = strpos($data, 'tRNS');

        return $trns !== false && ($idat === false || $trns < $idat);
    }

    private function orient(GdImage $g, int $orientasi): GdImage
    {
        $putar = fn (GdImage $x, int $sudut) => imagerotate($x, $sudut, 0) ?: $x;

        switch ($orientasi) {
            case 2: imageflip($g, IMG_FLIP_HORIZONTAL);

                return $g;
            case 3: return $putar($g, 180);
            case 4: imageflip($g, IMG_FLIP_VERTICAL);

                return $g;
            case 5: $g = $putar($g, -90);
                imageflip($g, IMG_FLIP_HORIZONTAL);

                return $g;
            case 6: return $putar($g, -90);
            case 7: $g = $putar($g, 90);
                imageflip($g, IMG_FLIP_HORIZONTAL);

                return $g;
            case 8: return $putar($g, 90);
            default: return $g;
        }
    }

    private function resize(GdImage $g, bool $alpha): GdImage
    {
        $w = imagesx($g);
        $h = imagesy($g);
        $sisi = max($w, $h);

        if ($sisi <= self::SISI_MAKS) {
            return $g;
        }

        $skala = self::SISI_MAKS / $sisi;
        $nw = max(1, (int) round($w * $skala));
        $nh = max(1, (int) round($h * $skala));

        $baru = imagecreatetruecolor($nw, $nh);

        if ($alpha) {
            imagealphablending($baru, false);
            imagesavealpha($baru, true);
            imagefill($baru, 0, 0, imagecolorallocatealpha($baru, 0, 0, 0, 127));
        }

        imagecopyresampled($baru, $g, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $baru;
    }

    private function encode(GdImage $g, string $mime): string
    {
        if ($mime === 'image/png') {
            imagealphablending($g, false);
            imagesavealpha($g, true);

            return $this->capture(fn () => imagepng($g, null, 9));
        }

        if ($mime === 'image/jpeg') {
            // JPEG tanpa alpha: piksel transparan menjadi putih, bukan hitam.
            $latar = imagecreatetruecolor(imagesx($g), imagesy($g));
            imagefill($latar, 0, 0, imagecolorallocate($latar, 255, 255, 255));
            imagecopy($latar, $g, 0, 0, 0, 0, imagesx($g), imagesy($g));
            $g = $latar;
        }

        foreach (array_merge([self::KUALITAS], self::KUALITAS_CADANGAN) as $kualitas) {
            $isi = $this->capture(fn () => $mime === 'image/webp' ? imagewebp($g, null, $kualitas) : imagejpeg($g, null, $kualitas));

            if (strlen($isi) <= StoreUpload::MAKSIMUM_BYTE) {
                return $isi;
            }
        }

        return $isi;
    }

    private function capture(callable $tulis): string
    {
        ob_start();

        try {
            $tulis();
        } finally {
            $isi = (string) ob_get_clean();
        }

        return $isi;
    }

    /**
     * Naikkan memory_limit sementara bila GD butuh lebih (±8 byte/piksel untuk
     * gambar + salinan putar). Mengembalikan nilai lama untuk dipulihkan.
     */
    private function reserveMemory(int $piksel): ?string
    {
        $lama = (string) ini_get('memory_limit');
        $sekarang = $this->bytes($lama);

        if ($sekarang < 0) {
            return null; // tanpa batas
        }

        $butuh = memory_get_usage(true) + $piksel * 8 + 64 * 1024 * 1024;

        if ($butuh <= $sekarang) {
            return null;
        }

        ini_set('memory_limit', (string) min($butuh, self::MEMORI_MAKS));

        return $lama;
    }

    private function bytes(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '' || $nilai === '-1') {
            return -1;
        }

        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024 ** 3,
            'm' => $angka * 1024 ** 2,
            'k' => $angka * 1024,
            default => $angka,
        };
    }
}

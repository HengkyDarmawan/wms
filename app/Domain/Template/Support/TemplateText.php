<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Template\Enums\DocumentTemplateType;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Database\Eloquent\Model;

/**
 * Editor template dokumen (18-template §1 `[F2]`, A-436): teks kustom di atas
 * dan/atau di bawah isi bawaan dokumen, dengan variabel `{nomor_dokumen}` dkk.
 *
 * Keamanan: teks TIDAK pernah diproses sebagai Blade/PHP. HTML disaring ke
 * daftar tag aman tanpa atribut selain `colspan`/`rowspan`/`style` (tanpa
 * `url(`, `expression`, `@import`), lalu nilai variabel di-escape saat diisi.
 * Penanda `{isi_bawaan}` = letak tabel & isi dokumen bawaan.
 */
class TemplateText
{
    public const MAKS = 20000;

    public const ISI_BAWAAN = 'isi_bawaan';

    /** @var array<string, string> variabel => keterangan */
    public const VARIABEL = [
        'nomor_dokumen' => 'Nomor dokumen, mis. SJ/CKG/2611/0001',
        'jenis_dokumen' => 'Nama jenis dokumen, mis. Surat Jalan',
        'status' => 'Status dokumen saat dicetak',
        'tanggal_dokumen' => 'Tanggal dokumen dibuat (dd/mm/yyyy)',
        'tanggal_cetak' => 'Tanggal dicetak (dd/mm/yyyy)',
        'nama_perusahaan' => 'Nama perusahaan Anda',
        'gudang' => 'Kode · nama gudang dokumen',
        'proyek' => 'Kode · nama proyek dokumen',
        'klien' => 'Nama klien pemilik proyek',
        'cetak_ke' => 'Cetakan ke-berapa',
        self::ISI_BAWAAN => 'Letak isi & tabel bawaan dokumen',
    ];

    private const TAG = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span', 'hr', 'small'];

    private const ATRIBUT = ['colspan', 'rowspan', 'style'];

    /** HTML aman: tag di luar daftar dibuang (isinya dipertahankan), atribut lain dihapus. */
    public static function sanitize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $lama = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="akar">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);

        $akar = $doc->getElementById('akar');

        if ($akar === null) {
            return '';
        }

        self::bersihkan($akar);

        $hasil = '';
        foreach (iterator_to_array($akar->childNodes) as $anak) {
            $hasil .= $doc->saveHTML($anak);
        }

        return trim($hasil);
    }

    /** @return array<int, string> variabel `{...}` yang tidak dikenal */
    public static function variabelAsing(string $teks): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $teks, $m);

        return array_values(array_unique(array_diff($m[1], array_keys(self::VARIABEL))));
    }

    /**
     * Isi variabel (di-escape) lalu pecah di `{isi_bawaan}`.
     *
     * @param  array<string, string|int|null>  $nilai
     * @return array{sebelum: string, sesudah: string}
     */
    public static function render(?string $body, array $nilai): array
    {
        $body = (string) $body;

        if (trim($body) === '') {
            return ['sebelum' => '', 'sesudah' => ''];
        }

        $isi = preg_replace_callback('/\{([a-z_]+)\}/', function (array $m) use ($nilai) {
            if ($m[1] === self::ISI_BAWAAN) {
                return '{'.self::ISI_BAWAAN.'}';
            }

            return array_key_exists($m[1], $nilai) ? e((string) ($nilai[$m[1]] ?? '-')) : $m[0];
        }, $body);

        $bagian = explode('{'.self::ISI_BAWAAN.'}', (string) $isi, 2);

        return ['sebelum' => $bagian[0], 'sesudah' => $bagian[1] ?? ''];
    }

    /** @return array<string, string> nilai variabel untuk satu dokumen */
    public static function nilai(DocumentTemplateType $type, Model $model, string $nomor, ?int $cetakKe): array
    {
        $gudang = $model->getAttribute('warehouse_id') !== null ? $model->warehouse ?? null : null;
        $proyek = $model->getAttribute('project_id') !== null ? $model->project ?? null : null;

        return [
            'nomor_dokumen' => $nomor,
            'jenis_dokumen' => $type->label(),
            'status' => (string) ($model->status?->label() ?? '-'),
            'tanggal_dokumen' => $model->created_at?->lokal()->format('d/m/Y') ?? '-',
            'tanggal_cetak' => now()->lokal()->format('d/m/Y'),
            'nama_perusahaan' => (string) (tenant()?->name ?? config('app.name')),
            'gudang' => $gudang ? $gudang->code.' · '.$gudang->name : '-',
            'proyek' => $proyek ? $proyek->code.' · '.$proyek->name : '-',
            'klien' => (string) ($proyek?->client?->name ?? '-'),
            'cetak_ke' => $cetakKe !== null ? (string) $cetakKe : '-',
        ];
    }

    private static function bersihkan(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $anak) {
            if ($anak instanceof DOMElement) {
                $tag = strtolower($anak->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'img', 'svg', 'form', 'input', 'button'], true)) {
                    $node->removeChild($anak);

                    continue;
                }

                self::bersihkan($anak);

                if (! in_array($tag, self::TAG, true)) {
                    while ($anak->firstChild !== null) {
                        $node->insertBefore($anak->firstChild, $anak);
                    }
                    $node->removeChild($anak);

                    continue;
                }

                foreach (iterator_to_array($anak->attributes) as $atribut) {
                    $nama = strtolower($atribut->nodeName);
                    $nilai = (string) $atribut->nodeValue;

                    if (! in_array($nama, self::ATRIBUT, true)
                        || ($nama === 'style' && preg_match('/url\s*\(|expression|@import|javascript:|behavior/i', $nilai))
                        || ($nama !== 'style' && ! ctype_digit($nilai))) {
                        $anak->removeAttribute($atribut->nodeName);
                    }
                }
            } elseif ($anak->nodeType === XML_COMMENT_NODE || $anak->nodeType === XML_PI_NODE) {
                $node->removeChild($anak);
            }
        }
    }
}

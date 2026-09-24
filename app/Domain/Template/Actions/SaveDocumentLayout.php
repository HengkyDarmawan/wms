<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ubah layout induk cetak dan kertas per template (18 §6).
 *
 * Permission: `document_layout.manage`. Otorisasi di layar/controller,
 * mengikuti pola aksi lain. Kop dan footer disimpan sebagai teks biasa
 * (A-122); logo PNG/JPEG ≤ 5 MB (NFR-14).
 */
class SaveDocumentLayout
{
    private const FOLDER_LOGO = 'document-layout';

    public function __construct(private readonly StoreUpload $files) {}

    /**
     * @param  array{name?: string, header?: ?string, footer?: ?string, accent?: ?string}  $data
     * @param  array<string, string|array<int, string>>  $signatureBlocks  jenis => label (baris teks atau daftar)
     * @param  array<string, string>  $papers  jenis => paper_size
     */
    public function handle(array $data, array $signatureBlocks, array $papers, User $actor): DocumentLayout
    {
        $blok = $this->validate($data, $signatureBlocks, $papers);
        $layout = DocumentLayout::current();

        DB::transaction(function () use ($layout, $data, $blok, $papers): void {
            $layout->fill([
                'name' => trim((string) ($data['name'] ?? '')) ?: 'Standar',
                'header_html' => $this->teks($data['header'] ?? null),
                'footer_html' => $this->teks($data['footer'] ?? null),
                'colors' => ['accent' => strtolower(trim((string) ($data['accent'] ?? ''))) ?: DocumentLayout::WARNA_BAWAAN],
                'signature_blocks' => $blok,
            ])->save();

            foreach ($papers as $jenis => $kertas) {
                $template = DocumentTemplate::forType(DocumentTemplateType::from($jenis));
                $template->forceFill(['paper' => PaperSize::from($kertas), 'layout_id' => $layout->id])->save();
            }
        });

        activity('template')->performedOn($layout)->causedBy($actor)
            ->withProperties(['kertas' => $papers])
            ->log('Layout dokumen diubah');

        return $layout->refresh();
    }

    public function storeLogo(UploadedFile $file, User $actor): DocumentLayout
    {
        $layout = DocumentLayout::current();

        try {
            // dompdf hanya andal untuk PNG/JPEG, jadi WebP tidak diterima di sini.
            $path = $this->files->handle($file, self::FOLDER_LOGO, 'logo-'.$layout->id, ['image/png', 'image/jpeg']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }

        $layout->forceFill(['logo_path' => $path])->save();

        activity('template')->performedOn($layout)->causedBy($actor)->log('Logo dokumen diunggah');

        return $layout;
    }

    public function removeLogo(User $actor): DocumentLayout
    {
        $layout = DocumentLayout::current();
        $this->files->delete($layout->logo_path);
        $layout->forceFill(['logo_path' => null])->save();

        activity('template')->performedOn($layout)->causedBy($actor)->log('Logo dokumen dihapus');

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|array<int, string>>  $signatureBlocks
     * @param  array<string, string>  $papers
     * @return array<string, array<int, string>>
     */
    private function validate(array $data, array $signatureBlocks, array $papers): array
    {
        $galat = [];

        if (mb_strlen(trim((string) ($data['name'] ?? ''))) > 80) {
            $galat['name'] = __('Nama layout paling banyak 80 karakter.');
        }
        if (mb_strlen((string) ($data['header'] ?? '')) > 500) {
            $galat['header'] = __('Teks kop paling banyak 500 karakter.');
        }
        if (mb_strlen((string) ($data['footer'] ?? '')) > 300) {
            $galat['footer'] = __('Footer paling banyak 300 karakter.');
        }
        $warna = trim((string) ($data['accent'] ?? ''));
        if ($warna !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
            $galat['accent'] = __('Warna aksen harus kode hex, mis. #1f4e79.');
        }

        $blok = [];
        foreach ($signatureBlocks as $jenis => $isi) {
            $type = DocumentTemplateType::tryFrom((string) $jenis);
            if ($type === null || $type->isLabel()) {
                $galat['signatureBlocks.'.$jenis] = __('Jenis dokumen tidak dikenal.');

                continue;
            }

            $baris = is_array($isi) ? $isi : preg_split('/\R/', (string) $isi);
            $baris = array_values(array_filter(array_map(fn ($b) => trim((string) $b), $baris), fn ($b) => $b !== ''));

            if (count($baris) > DocumentLayout::MAKS_BLOK) {
                $galat['signatureBlocks.'.$jenis] = __('Paling banyak :maks kotak tanda tangan.', ['maks' => DocumentLayout::MAKS_BLOK]);
            } elseif (collect($baris)->contains(fn ($b) => mb_strlen($b) > 40)) {
                $galat['signatureBlocks.'.$jenis] = __('Label kotak paling banyak 40 karakter.');
            } elseif ($baris !== [] && $baris !== $type->defaultSignatureBlocks()) {
                // Hanya simpan yang berbeda dari bawaan, supaya perubahan bawaan ikut berlaku.
                $blok[$type->value] = $baris;
            }
        }

        foreach ($papers as $jenis => $kertas) {
            $type = DocumentTemplateType::tryFrom((string) $jenis);
            $paper = PaperSize::tryFrom((string) $kertas);
            $cocok = $type !== null && $paper !== null
                && in_array($paper, $type->isLabel() ? PaperSize::forLabels() : PaperSize::forDocuments(), true);

            if (! $cocok) {
                $galat['papers.'.$jenis] = __('Kertas tidak cocok untuk jenis ini.');
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $blok;
    }

    private function teks(?string $isi): ?string
    {
        $isi = trim((string) $isi);

        return $isi === '' ? null : $isi;
    }
}

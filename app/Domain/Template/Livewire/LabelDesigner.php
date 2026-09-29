<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Support\StockFeatures;
use App\Domain\Template\Actions\SaveLabelDesign;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelCodeMode;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\BarcodeImage;
use App\Domain\Template\Support\LabelDesignRules;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Template\Support\PrintAssets;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar 18 §6 — desainer label (A-262): pilih jenis label dan ukuran, geser
 * dan ubah ukuran elemen di kanvas (interact.js, tanpa CDN), pilih barcode /
 * QR / keduanya, simpan. Kanvas diisi contoh data sungguhan bila ada.
 * Permission `document_layout.manage`.
 */
class LabelDesigner extends Component
{
    #[Url(as: 'type')]
    public string $type = 'label_bin';

    #[Url(as: 'format')]
    public string $formatId = '';

    /** Naik setiap simpan supaya kanvas memuat ulang data tersimpan. */
    public int $versi = 0;

    public function mount(): void
    {
        $this->authorize('document_layout.manage');
        $this->rapikanPilihan();
    }

    public function updated(string $property): void
    {
        if ($property === 'type') {
            $this->formatId = '';
        }

        $this->rapikanPilihan();
        $this->resetErrorBag();
    }

    /**
     * Dipanggil kanvas: `$wire.simpan(elemen, kode, jadikanBawaan)`.
     *
     * @param  array<string, mixed>  $elements
     */
    public function simpan(array $elements, string $codeMode, bool $makeDefault, SaveLabelDesign $action): void
    {
        $this->authorize('document_layout.manage');
        $format = LabelFormat::query()->findOrFail((int) $this->formatId);

        try {
            $action->handle(DocumentTemplateType::from($this->type), $format, $codeMode, $elements, $makeDefault, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->versi++;
        $this->dispatch('pesan', teks: __('Desain label disimpan.'));
    }

    public function render(): View
    {
        $jenis = DocumentTemplateType::from($this->type);
        $format = LabelFormat::query()->find((int) $this->formatId);
        $cfg = null;

        if ($format !== null) {
            $desain = LabelDesign::resolve($jenis, $format);
            $contoh = $this->contoh($jenis);
            $gambar = app(BarcodeImage::class);

            $cfg = [
                'width' => $format->width_mm,
                'height' => $format->height_mm,
                'elements' => $desain->elements,
                'codeMode' => $desain->code_mode->value,
                'defaults' => collect(LabelCodeMode::cases())->mapWithKeys(fn (LabelCodeMode $m) => [$m->value => LabelDesignRules::defaults($format, $m)])->all(),
                'names' => LabelDesignRules::names(),
                'sample' => [
                    'title' => $contoh['title'],
                    'subtitle' => $contoh['subtitle'],
                    'detail' => $contoh['detail'],
                    'code' => $contoh['code128'],
                    'barcode' => $gambar->code128($contoh['code128'], 2, 60),
                    'qr' => $gambar->qr($contoh['qr'], 6),
                    'logo' => app(PrintAssets::class)->kop()['logo'],
                ],
                'isDefault' => (int) LabelFormat::defaultFor($jenis)?->id === (int) $format->id,
                'saved' => $desain->exists,
            ];
        }

        return view('livewire.template.label-designer', [
            'jenisLabel' => DocumentTemplateType::labels(StockFeatures::piece()),
            'formats' => LabelFormat::query()->active()->orderBy('media')->orderBy('code')->get(),
            'format' => $format,
            'cfg' => $cfg,
            'sumber' => LabelDesignRules::sources($jenis),
            'kodeOpsi' => LabelCodeMode::options(),
        ]);
    }

    private function rapikanPilihan(): void
    {
        if (DocumentTemplateType::tryFrom($this->type)?->isLabelAvailable(StockFeatures::piece()) !== true) {
            $this->type = DocumentTemplateType::LabelBin->value;
        }

        $aktif = LabelFormat::query()->active()->find((int) $this->formatId);
        $this->formatId = (string) ($aktif?->id ?? LabelFormat::defaultFor(DocumentTemplateType::from($this->type))?->id ?? '');
    }

    /**
     * Isi contoh untuk kanvas: data pertama yang terlihat pengguna, atau contoh tetap.
     *
     * @return array{title: string, subtitle: string, detail: string, code128: string, qr: string}
     */
    private function contoh(DocumentTemplateType $jenis): array
    {
        $model = match ($jenis) {
            DocumentTemplateType::LabelBin => Bin::query()->with('warehouse')->orderBy('code')->first(),
            DocumentTemplateType::LabelItem => Item::query()->with('baseUom')->orderBy('code')->first(),
            DocumentTemplateType::LabelLot => Lot::query()->with('item')->orderByDesc('id')->first(),
            DocumentTemplateType::LabelPackage => PackageLabel::query()->with('item.baseUom', 'lot', 'packageUom', 'receipt.vendor')->orderByDesc('id')->first(),
            DocumentTemplateType::LabelSerial => Serial::query()->with('item')->orderByDesc('id')->first(),
            default => Piece::query()->with('item.baseUom')->where('is_consumed', false)->orderBy('piece_no')->first(),
        };

        if ($model !== null) {
            return match ($jenis) {
                DocumentTemplateType::LabelBin => LabelPayload::bin($model),
                DocumentTemplateType::LabelItem => LabelPayload::item($model),
                DocumentTemplateType::LabelLot => LabelPayload::lot($model),
                DocumentTemplateType::LabelPackage => LabelPayload::package($model),
                DocumentTemplateType::LabelSerial => LabelPayload::serial($model),
                default => LabelPayload::piece($model),
            };
        }

        return self::contohTetap($jenis);
    }

    /** @return array{title: string, subtitle: string, detail: string, code128: string, qr: string} */
    public static function contohTetap(DocumentTemplateType $jenis): array
    {
        return match ($jenis) {
            DocumentTemplateType::LabelBin => ['title' => 'R01 · L1 · 01', 'subtitle' => 'GDG-A-R01-L1-B01 · Gudang Contoh', 'detail' => 'Penyimpanan', 'code128' => 'GDG-A-R01-L1-B01', 'qr' => BinCode::tautan('GDG-A-R01-L1-B01')],
            DocumentTemplateType::LabelItem => ['title' => 'ITEM-001', 'subtitle' => 'Contoh nama item', 'detail' => 'PCS', 'code128' => 'ITEM-001', 'qr' => 'ITEM-001'],
            DocumentTemplateType::LabelLot => ['title' => 'LOT-0001', 'subtitle' => 'ITEM-001 Contoh nama item', 'detail' => 'Masuk 01/10/2026 · Kedaluwarsa 01/10/2027', 'code128' => 'LOT-0001', 'qr' => 'ITEM-001|LOT-0001'],
            DocumentTemplateType::LabelPackage => ['title' => 'PAKU-0001', 'subtitle' => 'PAKU Paku 5 cm', 'detail' => 'Isi 12 BOX (1 DUS) · Masuk 01/10/2026 · PT Vendor Contoh · GRN/GDG/2610/0001', 'code128' => 'PAKU-0001', 'qr' => 'PAKU-0001'],
            DocumentTemplateType::LabelSerial => ['title' => 'SN-0001', 'subtitle' => 'GENSET Genset 5 kVA', 'detail' => 'Masuk 01/10/2026 · PT Vendor Contoh · GRN/GDG/2610/0001', 'code128' => 'SN-0001', 'qr' => 'SN-0001'],
            default => ['title' => 'P-000001', 'subtitle' => 'BESI-12 Besi beton 12 mm', 'detail' => 'Panjang 6 M · Masuk 01/10/2026', 'code128' => 'P-000001', 'qr' => 'P-000001'],
        };
    }
}

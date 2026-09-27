<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\BarcodeImage;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Template\Support\PdfRenderer;
use App\Domain\Template\Support\PrintAssets;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cetak label bin, item, lot, potongan sebagai PDF (18 §5.3).
 *
 * Ukuran kertas dari master ukuran label (A-261) dan tata letak dari desain
 * label jenis × ukuran itu, termasuk pilihan barcode/QR/keduanya (A-262).
 * Isi barcode dan QR tetap dari LabelPayload (A-121).
 *
 * Permission: `label.print`, ditambah izin lihat datanya (`bin.view` /
 * `item.view`). Bin dicari lewat query bercakupan (BR-ACC-05), jadi bin di
 * luar gudang pengguna tidak ikut tercetak.
 */
class PrintLabels
{
    public const MAKS_LABEL = 200; // ±90 MB, ±14 detik; 500 label melewati batas memori 128 MB

    public const MAKS_SALINAN = 10;

    public function __construct(
        private readonly BarcodeImage $barcode,
        private readonly PrintAssets $assets,
    ) {}

    /** @param  array<int, int|string>  $ids */
    public function handle(DocumentTemplateType $type, array $ids, ?LabelFormat $format, int $copies, User $actor): Response
    {
        $format ??= $type->isLabel() ? LabelFormat::defaultFor($type) : null;
        $html = $this->view($type, $ids, $format, $copies, $actor)->render();
        [$lebar, $tinggi] = $format->pageSize();

        return PdfRenderer::makeCustom($html, $lebar, $tinggi)
            ->stream('label-'.str_replace('label_', '', $type->value).'.pdf');
    }

    /** @param  array<int, int|string>  $ids */
    public function view(DocumentTemplateType $type, array $ids, ?LabelFormat $format, int $copies, User $actor, ?LabelDesign $design = null): View
    {
        $this->authorize($type, $actor);
        $ids = $this->validate($type, $ids, $format, $copies);

        $isi = $this->load($type, $ids)
            ->map(fn ($model) => $this->payload($type, $model))
            ->flatMap(fn (array $label) => array_fill(0, $copies, $label))
            ->values();

        if ($isi->isEmpty()) {
            throw new HttpException(404, __('Tidak ada data yang bisa dicetak.'));
        }

        return $this->sheet($type, $format, $design ?? LabelDesign::resolve($type, $format), $isi);
    }

    /**
     * Lembar PDF dari isi label yang sudah jadi; dipakai juga contoh cetak desain.
     *
     * @param  Collection<int, array<string, string>>  $isi
     */
    public function sheet(DocumentTemplateType $type, LabelFormat $format, LabelDesign $design, Collection $isi): View
    {
        $mode = $design->code_mode;
        $elemen = $design->elements;
        $logo = ($elemen['logo']['visible'] ?? false) ? $this->assets->kop()['logo'] : null;

        $isi = $isi->map(fn (array $label) => $label + [
            'barcode' => $mode->hasBarcode() ? $this->barcode->code128($label['code128'], 2, 60) : null,
            'qr_image' => $mode->hasQr() ? $this->barcode->qr($label['qr'], 6) : null,
            'logo' => $logo,
        ]);

        return view('print.labels.sheet', [
            'labels' => $isi,
            'format' => $format,
            'elements' => $elemen,
            'mode' => $mode,
            'type' => $type,
        ]);
    }

    private function authorize(DocumentTemplateType $type, User $actor): void
    {
        $lihat = $type === DocumentTemplateType::LabelBin ? 'bin.view' : 'item.view';

        if (! $actor->hasPermission('label.print') || ! $actor->hasPermission($lihat)) {
            throw new HttpException(403);
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function validate(DocumentTemplateType $type, array $ids, ?LabelFormat $format, int $copies): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));

        $galat = [];
        if (! $type->isLabel()) {
            $galat['type'] = __('Jenis label tidak dikenal.');
        }
        if ($ids === []) {
            $galat['ids'] = __('Pilih minimal satu data untuk dicetak.');
        } elseif (count($ids) * $copies > self::MAKS_LABEL) {
            $galat['ids'] = __('Paling banyak :maks label per cetak.', ['maks' => self::MAKS_LABEL]);
        }
        if ($format === null || ! $format->is_active) {
            $galat['format'] = __('Pilih ukuran label yang aktif.');
        }
        if ($copies < 1 || $copies > self::MAKS_SALINAN) {
            $galat['copies'] = __('Salinan 1–:maks.', ['maks' => self::MAKS_SALINAN]);
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $ids;
    }

    /** @param  array<int, int>  $ids */
    private function load(DocumentTemplateType $type, array $ids): Collection
    {
        return match ($type) {
            DocumentTemplateType::LabelBin => Bin::query()->with('warehouse')->whereIn('id', $ids)->orderBy('code')->get(),
            DocumentTemplateType::LabelItem => Item::query()->with('baseUom')->whereIn('id', $ids)->orderBy('code')->get(),
            DocumentTemplateType::LabelLot => Lot::query()->with('item')->whereIn('id', $ids)->orderBy('item_id')->orderBy('lot_no')->get(),
            DocumentTemplateType::LabelPiece => Piece::query()->with('item.baseUom')->whereIn('id', $ids)->orderBy('piece_no')->get(),
            DocumentTemplateType::LabelPackage => PackageLabel::query()->with('item.baseUom', 'lot', 'packageUom', 'receipt.vendor')->whereIn('id', $ids)->orderBy('id')->get(),
            DocumentTemplateType::LabelSerial => Serial::query()->with('item')->whereIn('id', $ids)->orderBy('serial_no')->get(),
            default => collect(),
        };
    }

    /** @return array{title: string, subtitle: string, detail: string, code128: string, qr: string} */
    private function payload(DocumentTemplateType $type, mixed $model): array
    {
        return match ($type) {
            DocumentTemplateType::LabelBin => LabelPayload::bin($model),
            DocumentTemplateType::LabelItem => LabelPayload::item($model),
            DocumentTemplateType::LabelLot => LabelPayload::lot($model),
            DocumentTemplateType::LabelPackage => LabelPayload::package($model),
            DocumentTemplateType::LabelSerial => LabelPayload::serial($model),
            default => LabelPayload::piece($model),
        };
    }
}

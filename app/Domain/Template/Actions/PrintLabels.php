<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\BarcodeImage;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Template\Support\PdfRenderer;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cetak label bin, item, lot, potongan sebagai PDF (18 §5.3, A-120, A-121).
 *
 * Permission: `label.print`, ditambah izin lihat datanya (`bin.view` /
 * `item.view`). Bin dicari lewat query bercakupan (BR-ACC-05), jadi bin di
 * luar gudang pengguna tidak ikut tercetak.
 */
class PrintLabels
{
    public const MAKS_LABEL = 200; // ±90 MB, ±14 detik; 500 label melewati batas memori 128 MB

    public const MAKS_SALINAN = 10;

    public function __construct(private readonly BarcodeImage $barcode) {}

    /** @param  array<int, int|string>  $ids */
    public function handle(DocumentTemplateType $type, array $ids, ?PaperSize $paper, int $copies, User $actor): Response
    {
        $paper ??= DocumentTemplate::forType($type)->paper;
        return PdfRenderer::make($this->view($type, $ids, $paper, $copies, $actor)->render(), $paper)
            ->stream('label-'.str_replace('label_', '', $type->value).'.pdf');
    }

    /** @param  array<int, int|string>  $ids */
    public function view(DocumentTemplateType $type, array $ids, PaperSize $paper, int $copies, User $actor): View
    {
        $this->authorize($type, $actor);
        $ids = $this->validate($type, $ids, $paper, $copies);

        $isi = $this->load($type, $ids)
            ->map(fn ($model) => $this->payload($type, $model))
            ->flatMap(fn (array $label) => array_fill(0, $copies, $label))
            ->map(fn (array $label) => $label + [
                'barcode' => $this->barcode->code128($label['code128'], 1, 30),
                'qr_image' => $this->barcode->qr($label['qr'], 3),
            ])
            ->values();

        if ($isi->isEmpty()) {
            throw new HttpException(404, __('Tidak ada data yang bisa dicetak.'));
        }

        return view('print.labels.sheet', [
            'labels' => $isi,
            'paper' => $paper,
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
    private function validate(DocumentTemplateType $type, array $ids, PaperSize $paper, int $copies): array
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
        if (! $paper->isLabel()) {
            $galat['paper'] = __('Pilih kertas label.');
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
    private function load(DocumentTemplateType $type, array $ids): \Illuminate\Support\Collection
    {
        return match ($type) {
            DocumentTemplateType::LabelBin => Bin::query()->with('warehouse')->whereIn('id', $ids)->orderBy('code')->get(),
            DocumentTemplateType::LabelItem => Item::query()->with('baseUom')->whereIn('id', $ids)->orderBy('code')->get(),
            DocumentTemplateType::LabelLot => Lot::query()->with('item')->whereIn('id', $ids)->orderBy('item_id')->orderBy('lot_no')->get(),
            DocumentTemplateType::LabelPiece => Piece::query()->with('item.baseUom')->whereIn('id', $ids)->orderBy('piece_no')->get(),
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
            default => LabelPayload::piece($model),
        };
    }
}

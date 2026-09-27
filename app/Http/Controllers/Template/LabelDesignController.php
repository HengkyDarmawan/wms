<?php

declare(strict_types=1);

namespace App\Http\Controllers\Template;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Livewire\LabelDesigner;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Template\Support\PdfRenderer;
use App\Domain\Warehouse\Models\Bin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ukuran & desain label (A-261, A-262, 18 §6). Permission
 * `document_layout.manage`. Contoh cetak = GET, hanya membaca desain
 * tersimpan (atau tata letak otomatis) dengan contoh data satu halaman.
 */
class LabelDesignController extends Controller
{
    public function formats(): View
    {
        $this->authorize('document_layout.manage');

        return view('template.label-formats');
    }

    public function designs(): View
    {
        $this->authorize('document_layout.manage');

        return view('template.label-designs');
    }

    public function preview(Request $request, PrintLabels $action): Response
    {
        $this->authorize('document_layout.manage');

        $jenis = DocumentTemplateType::tryFrom((string) $request->query('type'));
        abort_if($jenis === null || ! $jenis->isLabel(), 404);
        $format = LabelFormat::query()->active()->find((int) $request->query('format')) ?? LabelFormat::defaultFor($jenis);
        abort_if($format === null, 404);

        $isi = $this->contoh($jenis, $format->perPage());
        [$lebar, $tinggi] = $format->pageSize();

        return PdfRenderer::makeCustom($action->sheet($jenis, $format, LabelDesign::resolve($jenis, $format), $isi)->render(), $lebar, $tinggi)
            ->stream('contoh-label.pdf');
    }

    /** Data sungguhan yang terlihat pengguna, diulang sampai satu halaman penuh. */
    private function contoh(DocumentTemplateType $jenis, int $jumlah): Collection
    {
        $jumlah = min($jumlah, PrintLabels::MAKS_LABEL);

        $data = match ($jenis) {
            DocumentTemplateType::LabelBin => Bin::query()->with('warehouse')->orderBy('code')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::bin($m)),
            DocumentTemplateType::LabelItem => Item::query()->with('baseUom')->orderBy('code')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::item($m)),
            DocumentTemplateType::LabelLot => Lot::query()->with('item')->orderByDesc('id')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::lot($m)),
            DocumentTemplateType::LabelPackage => PackageLabel::query()->with('item.baseUom', 'lot', 'packageUom', 'receipt.vendor')->orderByDesc('id')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::package($m)),
            DocumentTemplateType::LabelSerial => Serial::query()->with('item')->orderByDesc('id')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::serial($m)),
            default => Piece::query()->with('item.baseUom')->where('is_consumed', false)->orderBy('piece_no')->limit($jumlah)->get()->map(fn ($m) => LabelPayload::piece($m)),
        };

        if ($data->isEmpty()) {
            $data = collect([LabelDesigner::contohTetap($jenis)]);
        }

        return collect(range(0, $jumlah - 1))->map(fn (int $i) => $data[$i % $data->count()]);
    }
}

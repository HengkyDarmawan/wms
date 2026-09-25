<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Actions\ImportItems;
use App\Domain\Master\Actions\ImportProjects;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Impor data master dari Excel (A-192): item dan proyek (+klien) — templat, unggah (POST), galat per baris. */
class ImportController extends Controller
{
    /** @var array<string, array{permission: string, action: class-string, sheet: string, example: array<int, mixed>, route: string, label: string}> */
    private const JENIS = [
        'items' => [
            'permission' => 'item.create', 'action' => ImportItems::class, 'sheet' => 'Item', 'route' => 'items.index', 'label' => 'item',
            'example' => ['', 'Baut M12 x 50', '', 'PCS', 'none', 'tidak', 'consumable', 'fifo', 100, 300, 'tidak', ''],
        ],
        'projects' => [
            'permission' => 'project.create', 'action' => ImportProjects::class, 'sheet' => 'Proyek', 'route' => 'projects.index', 'label' => 'proyek',
            'example' => ['PRJ-101', 'Gedung Kantor Cikarang', 'KLN-01', 'PT Klien Baru', 'Jl. Industri 1, Cikarang', '2026-10-01', '2027-03-31'],
        ],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('item.create') || $request->user()->hasPermission('project.create'), 403);

        return view('import.index', [
            'items' => ImportItems::COLUMNS,
            'projects' => ImportProjects::COLUMNS,
            'max' => ['items' => ImportItems::MAX_ROWS, 'projects' => ImportProjects::MAX_ROWS],
        ]);
    }

    public function template(string $type): StreamedResponse
    {
        $j = $this->jenis($type);

        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle($j['sheet']);
        $kolom = array_values($j['action']::COLUMNS);
        $lembar->fromArray([$kolom, $j['example']]);
        $akhir = chr(ord('A') + count($kolom) - 1);
        $lembar->getStyle('A1:'.$akhir.'1')->getFont()->setBold(true);

        foreach (range('A', $akhir) as $k) {
            $lembar->getColumnDimension($k)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($buku))->save('php://output'), 'templat-impor-'.$j['label'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $j = $this->jenis($type);
        $berkas = $request->file('file');

        if ($berkas === null) {
            return back()->withErrors(['file' => __('Pilih berkas Excel.')]);
        }

        try {
            $jumlah = app($j['action'])->handle($berkas, $request->user());
        } catch (MasterRuleException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->with('rowErrors', $e->fieldErrors['rows'] ?? null)->with('ruleCode', $e->rule);
        }

        return redirect()->route($j['route'])->with('status', __(':n :jenis berhasil diimpor.', ['n' => $jumlah, 'jenis' => $j['label']]));
    }

    /** @return array{permission: string, action: class-string, sheet: string, example: array<int, mixed>, route: string, label: string} */
    private function jenis(string $type): array
    {
        $j = self::JENIS[$type] ?? abort(404);
        $this->authorize($j['permission']);

        return $j;
    }
}

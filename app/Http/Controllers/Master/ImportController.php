<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Adjustment\Actions\ImportOpeningStock;
use App\Domain\Master\Actions\ImportItems;
use App\Domain\Master\Actions\ImportProjects;
use App\Domain\Master\Actions\ImportVendors;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Warehouse\Actions\ImportWarehouseStructure;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Impor dari Excel (A-192, A-207, A-258): item, proyek (+klien), vendor, saldo awal, dan struktur gudang — templat, unggah (POST), galat per baris. */
class ImportController extends Controller
{
    /** @var array<string, array{permission: string, action: class-string, sheet: string, example: array<int, mixed>, route: string, label: string, message?: string}> */
    private const JENIS = [
        'items' => [
            'permission' => 'item.create', 'action' => ImportItems::class, 'sheet' => 'Item', 'route' => 'items.index', 'label' => 'item',
            'example' => ['', 'Baut M12 x 50', '', 'PCS', 'none', 'tidak', 'consumable', 'fifo', 100, 300, 'tidak', ''],
        ],
        'projects' => [
            'permission' => 'project.create', 'action' => ImportProjects::class, 'sheet' => 'Proyek', 'route' => 'projects.index', 'label' => 'proyek',
            'example' => ['PRJ-101', 'Gedung Kantor Cikarang', 'KLN-01', 'PT Klien Baru', 'Jl. Industri 1, Cikarang', '2026-10-01', '2027-03-31'],
        ],
        'vendors' => [
            'permission' => 'vendor.create', 'action' => ImportVendors::class, 'sheet' => 'Vendor', 'route' => 'vendors.index', 'label' => 'vendor',
            'example' => ['', 'CV Baja Makmur', 'company', 'active', '01.234.567.8-901.000', 'Budi', '081234567890', 'budi@bajamakmur.co.id', 'Jl. Raya Bekasi 10', '30 hari'],
        ],
        'opening-stock' => [
            'permission' => 'adjustment.create', 'action' => ImportOpeningStock::class, 'sheet' => 'Saldo awal', 'route' => 'adjustments.index', 'label' => 'baris saldo awal',
            'message' => ':n :jenis diajukan sebagai penyesuaian stok per gudang; stok masuk setelah disetujui.',
            'example' => ['CKG', 'CKG-A-R01-L1-B01', 'BAUT-M12', 250, 'tersedia', '', '', '', '', 'Hasil hitung pembukaan'],
        ],
        'bins' => [
            'permission' => 'bin.manage', 'action' => ImportWarehouseStructure::class, 'sheet' => 'Struktur gudang', 'route' => 'bins.index', 'label' => 'bin',
            'message' => ':n :jenis diimpor; zona, rak, dan level yang belum ada dibuat otomatis.',
            'example' => ['CKG', 'C', 'Zona C', 'R01', 'L1', 'B01', 'storage', 100],
        ],
    ];

    /** Izin tiap kartu di layar impor. */
    public const PERMISSIONS = ['items' => 'item.create', 'projects' => 'project.create', 'vendors' => 'vendor.create', 'opening-stock' => 'adjustment.create', 'bins' => 'bin.manage'];

    public function index(Request $request): View
    {
        abort_unless(collect(self::PERMISSIONS)->contains(fn ($izin) => $request->user()->hasPermission($izin)), 403);

        return view('import.index', [
            'items' => ImportItems::COLUMNS,
            'projects' => ImportProjects::COLUMNS,
            'vendors' => ImportVendors::COLUMNS,
            'opening' => ImportOpeningStock::COLUMNS,
            'bins' => ImportWarehouseStructure::COLUMNS,
            'max' => [
                'items' => ImportItems::MAX_ROWS,
                'projects' => ImportProjects::MAX_ROWS,
                'vendors' => ImportVendors::MAX_ROWS,
                'opening-stock' => ImportOpeningStock::MAX_ROWS,
                'bins' => ImportWarehouseStructure::MAX_ROWS,
            ],
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

        return redirect()->route($j['route'])->with('status', __($j['message'] ?? ':n :jenis berhasil diimpor.', ['n' => $jumlah, 'jenis' => $j['label']]));
    }

    /** @return array{permission: string, action: class-string, sheet: string, example: array<int, mixed>, route: string, label: string, message?: string} */
    private function jenis(string $type): array
    {
        $j = self::JENIS[$type] ?? abort(404);
        $this->authorize($j['permission']);

        return $j;
    }
}

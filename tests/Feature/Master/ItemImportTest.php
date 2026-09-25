<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\ImportItems;
use App\Domain\Master\Actions\ImportProjects;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-22/23 — impor item dan proyek (+klien) dari Excel: templat, semua-atau-tidak, galat per
 * baris, aturan sama dengan form (A-192, BR-MST-01, BR-STK-11).
 */
class ItemImportTest extends TenantTestCase
{
    /** @param  array<int, array<int, mixed>>  $baris */
    private function berkas(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values(ImportItems::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'item.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function tc_mst_22_impor_item_semua_atau_tidak(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('imports'))->assertOk()->assertSee(__('Unduh templat'));
        $this->actingAs($admin)->get($this->tenantUrl('imports/items/template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('imports'))->assertForbidden();

        // Satu baris salah (satuan tak dikenal, serial + fefo tidak sah): tidak ada yang tersimpan.
        $salah = $this->berkas([
            ['IMP-1', 'Baut impor', '', 'PCS', 'none', 'tidak', 'consumable', 'fifo', 10, 20, 'tidak', ''],
            ['IMP-2', 'Barang aneh', '', 'XYZ', 'none', 'tidak', 'consumable', 'fifo', '', '', 'tidak', ''],
            ['IMP-3', 'Genset impor', '', 'PCS', 'serial', 'tidak', 'asset', 'fefo', '', '', 'ya', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/items'), ['file' => $salah])
            ->assertSessionHasErrors('file')->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'Baris 3') && str_contains($t, 'Baris 4'));
        $this->assertSame(0, Item::query()->where('code', 'like', 'IMP-%')->count());

        $benar = $this->berkas([
            ['IMP-1', 'Baut impor', '', 'PCS', 'none', 'tidak', 'consumable', 'fifo', 10, 20, 'tidak', '8990001'],
            ['IMP-2', 'Genset impor', '', 'PCS', 'serial', 'tidak', 'asset', 'manual', '', '', 'ya', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/items'), ['file' => $benar])->assertSessionHasNoErrors()->assertRedirect();

        $baut = Item::query()->where('code', 'IMP-1')->sole();
        $this->assertSame(10.0, (float) $baut->reorder_point);
        $this->assertSame('8990001', $baut->barcode);
        $genset = Item::query()->where('name', 'Genset impor')->sole();
        $this->assertSame(TrackingMode::Serial, $genset->tracking_mode);
        $this->assertTrue($genset->requires_qc);

        // Kode yang sudah ada ditolak (impor hanya menambah).
        $this->actingAs($admin)->post($this->tenantUrl('imports/items'), ['file' => $this->berkas([['IMP-1', 'Ganda', '', 'PCS']])])
            ->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'IMP-1'));
    }

    #[Test]
    public function tc_mst_23_impor_proyek_membuat_klien_baru(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('imports/projects/template'))->assertOk();

        $kolom = array_values(ImportProjects::COLUMNS);
        $berkas = function (array $baris) use ($kolom): UploadedFile {
            $buku = new Spreadsheet;
            $buku->getActiveSheet()->fromArray(array_merge([$kolom], $baris));
            $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
            (new Xlsx($buku))->save($path);

            return new UploadedFile($path, 'proyek.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        };

        // Klien tak dikenal tanpa nama → seluruh impor batal.
        $this->actingAs($admin)->post($this->tenantUrl('imports/projects'), ['file' => $berkas([
            ['PRJ-IMP-1', 'Gedung A', 'KLN-IMP', 'PT Klien Impor', 'Cikarang', '2026-10-01', '2027-03-31'],
            ['PRJ-IMP-2', 'Gedung B', 'KLN-ASING', '', '', '', ''],
        ])])->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'Baris 3') && str_contains($t, 'KLN-ASING'));
        $this->assertSame(0, Project::query()->where('code', 'like', 'PRJ-IMP-%')->count());

        $this->actingAs($admin)->post($this->tenantUrl('imports/projects'), ['file' => $berkas([
            ['PRJ-IMP-1', 'Gedung A', 'KLN-IMP', 'PT Klien Impor', 'Cikarang', '2026-10-01', '2027-03-31'],
            ['PRJ-IMP-2', 'Gedung B', 'KLN-IMP', '', '', '', ''],
        ])])->assertSessionHasNoErrors()->assertRedirect();

        $proyek = Project::query()->where('code', 'PRJ-IMP-1')->sole();
        $this->assertSame('PT Klien Impor', $proyek->client->name);
        $this->assertSame('2027-03-31', $proyek->target_end_date->toDateString());
        $this->assertSame(1, Client::query()->where('code', 'KLN-IMP')->count(), 'Klien dibuat sekali, dipakai baris kedua.');

        // Staf tanpa izin proyek tidak bisa mengimpor proyek.
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('imports/projects/template'))->assertForbidden();
    }
}

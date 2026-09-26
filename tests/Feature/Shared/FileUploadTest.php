<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Files\StoreUpload;
use Database\Seeders\Tenant\MasterDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-FIL-01 — unggah tanda tangan dan foto item ke disk tenant (AD-10, NFR-14).
 *
 * Berkas disimpan di disk privat yang sudah dipisah per company oleh
 * `FilesystemTenancyBootstrapper`, dan hanya bisa dibuka lewat route
 * berotorisasi, bukan URL publik.
 */
class FileUploadTest extends TenantTestCase
{
    protected function tearDown(): void
    {
        // Berkas uji dibersihkan supaya tidak menumpuk di storage.
        $files = app(StoreUpload::class);

        foreach (['signatures', 'items'] as $folder) {
            foreach (Storage::disk('local')->files($folder) as $path) {
                $files->delete($path);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function tc_fil_01_tanda_tangan_bisa_diunggah_dan_dihapus(): void
    {
        $user = $this->makeUser('warehouse_staff');

        $this->actingAs($user)
            ->post($this->tenantUrl('profile/signature'), [
                'signature' => UploadedFile::fake()->image('ttd.png', 200, 80),
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->signature_path);
        $this->assertTrue(app(StoreUpload::class)->exists($user->signature_path));

        // Berkas hanya bisa dibuka lewat route berotorisasi.
        $this->actingAs($user)
            ->get($this->tenantUrl('files/signature/'.$user->id))
            ->assertOk();

        $this->actingAs($user)
            ->delete($this->tenantUrl('profile/signature'))
            ->assertRedirect();

        $this->assertNull($user->refresh()->signature_path);
    }

    #[Test]
    public function tc_fil_01b_tanda_tangan_orang_lain_menuntut_izin(): void
    {
        $pemilik = $this->makeUser('warehouse_staff');

        $this->actingAs($pemilik)->post($this->tenantUrl('profile/signature'), [
            'signature' => UploadedFile::fake()->image('ttd.png'),
        ]);

        // Driver tidak punya `user.view`.
        $driver = $this->makeUser('driver');

        $this->actingAs($driver)
            ->get($this->tenantUrl('files/signature/'.$pemilik->id))
            ->assertForbidden();

        // Admin Company punya `user.view`.
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)
            ->get($this->tenantUrl('files/signature/'.$pemilik->id))
            ->assertOk();
    }

    #[Test]
    public function tc_fil_01c_berkas_bukan_gambar_ditolak(): void
    {
        $user = $this->makeUser('warehouse_staff');

        $this->actingAs($user)
            ->post($this->tenantUrl('profile/signature'), [
                'signature' => UploadedFile::fake()->create('daftar.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('signature');

        $this->assertNull($user->refresh()->signature_path);
    }

    #[Test]
    public function tc_fil_01d_berkas_mentah_lebih_dari_dua_puluh_mb_ditolak(): void
    {
        $user = $this->makeUser('warehouse_staff');

        // A-257: unggahan mentah maks 20 MB (dikompres menjadi ≤ 5 MB, NFR-14/A-23).
        $this->actingAs($user)
            ->post($this->tenantUrl('profile/signature'), [
                'signature' => UploadedFile::fake()->image('besar.jpg')->size(21000),
            ])
            ->assertSessionHasErrors('signature');

        $this->assertNull($user->refresh()->signature_path);
    }

    #[Test]
    public function tc_fil_01e_foto_item_bisa_diunggah_dan_dilihat(): void
    {
        (new MasterDemoSeeder)->run();

        $item = Item::query()->where('code', 'BAUT-M12')->firstOrFail();
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)
            ->post($this->tenantUrl('items/'.$item->id.'/photo'), [
                'photo' => UploadedFile::fake()->image('baut.jpg', 400, 300),
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertNotNull($item->photo_path);

        $this->actingAs($admin)
            ->get($this->tenantUrl('files/item-photo/'.$item->id))
            ->assertOk();

        $this->actingAs($admin)
            ->delete($this->tenantUrl('items/'.$item->id.'/photo'))
            ->assertRedirect();

        $this->assertNull($item->refresh()->photo_path);
    }

    #[Test]
    public function tc_fil_01f_mengunggah_foto_item_menuntut_izin_ubah(): void
    {
        (new MasterDemoSeeder)->run();

        $item = Item::query()->where('code', 'BAUT-M12')->firstOrFail();

        // Staf gudang hanya punya item.view.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->actingAs($staf)
            ->post($this->tenantUrl('items/'.$item->id.'/photo'), [
                'photo' => UploadedFile::fake()->image('baut.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($item->refresh()->photo_path);
    }

    #[Test]
    public function tc_fil_01g_berkas_disimpan_di_folder_tenant(): void
    {
        $user = $this->makeUser('warehouse_staff');

        $path = app(StoreUpload::class)->handle(
            UploadedFile::fake()->image('ttd.png'),
            'signatures',
            (string) $user->id,
        );

        $penuh = Storage::disk('local')->path($path);

        // FilesystemTenancyBootstrapper menyisipkan pengenal company ke path.
        $this->assertStringContainsString('tenant', $penuh);
        $this->assertStringContainsString('signatures', $penuh);
    }
}

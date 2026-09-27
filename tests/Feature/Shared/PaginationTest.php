<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemList;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Notification\Models\Notification;
use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Livewire\WarehouseDetail;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Paginasi seluruh layar daftar: markup Bootstrap (D-05), teks Indonesia, dan
 * tab detail yang berpindah halaman di dalam komponen, bukan lewat tautan ke
 * endpoint update Livewire.
 */
class PaginationTest extends TenantTestCase
{
    #[Test]
    public function daftar_livewire_memakai_paginasi_bootstrap_berbahasa_indonesia(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        foreach (range(1, 25) as $i) {
            $this->makeItem(sprintf('PGN-%02d', $i));
        }

        Livewire::test(ItemList::class)
            ->assertSeeHtml('page-link')
            ->assertSeeHtml("gotoPage(2, 'page')")
            ->assertSee('Menampilkan')
            ->assertDontSeeHtml('relative inline-flex')
            ->assertDontSee('Showing')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->assertSeeHtml('<li class="page-item active" wire:key="paginator-page-page-2" aria-current="page"><span class="page-link">2</span></li>');
    }

    #[Test]
    public function tab_bin_gudang_berpindah_halaman_di_dalam_komponen(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'PGN',
            'name' => 'Gudang paginasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->firstOrFail()->id,
        ]);
        $lokasi = app(SaveLocation::class);
        $level = $lokasi->saveLevel($lokasi->saveRack($lokasi->saveZone($gudang, null, ['code' => 'A', 'name' => 'Zona A']), null, ['code' => 'R01']), null, ['code' => 'L1']);
        app(GenerateBins::class)->handle($level, 30);

        $terakhir = Bin::query()->where('warehouse_id', $gudang->id)->where('rack_level_id', $level->id)->orderBy('code')->get()->last();

        Livewire::test(WarehouseDetail::class, ['warehouse' => $gudang])
            ->call('pilihTab', 'bin')
            ->assertSeeHtml("gotoPage(2, 'bin')")
            ->assertDontSeeHtml('update?bin=')
            ->assertDontSee($terakhir->code)
            ->call('gotoPage', 2, 'bin')
            ->assertSet('paginators.bin', 2)
            ->assertSee($terakhir->code);
    }

    #[Test]
    public function tab_riwayat_item_berpindah_halaman_di_dalam_komponen(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        $item = $this->makeItem('PGN-RWY');
        foreach (range(1, 20) as $i) {
            activity()->performedOn($item)->log(sprintf('catatan-uji-%02d', $i));
        }

        Livewire::test(ItemDetail::class, ['item' => $item])
            ->call('pilihTab', 'riwayat')
            ->assertSeeHtml("gotoPage(2, 'riwayat')")
            ->assertDontSeeHtml('update?riwayat=')
            ->assertSee('catatan-uji-20')
            ->call('gotoPage', 2, 'riwayat')
            ->assertSet('paginators.riwayat', 2)
            ->assertDontSee('catatan-uji-20');
    }

    #[Test]
    public function halaman_controller_memakai_paginasi_berbahasa_indonesia(): void
    {
        $user = $this->makeUser('company_admin');

        foreach (range(1, 30) as $i) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'uji.paginasi',
                'channel' => 'in_app',
                'title' => "Notifikasi uji {$i}",
                'sent_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get($this->tenantUrl('notifications'))
            ->assertOk()
            ->assertSee('Menampilkan')
            ->assertSee('page=2', false)
            ->assertDontSee('Showing');
    }

    private function makeItem(string $code): Item
    {
        return Item::create([
            'code' => $code,
            'name' => "Item {$code}",
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ])->refresh();
    }
}

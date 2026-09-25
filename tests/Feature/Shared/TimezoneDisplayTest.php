<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-GEN-07 — waktu disimpan UTC tetapi tampil di zona company (BR-GEN-07,
 * NFR-07): macro `lokal()` dan riwayat dokumen di layar.
 */
class TimezoneDisplayTest extends TenantTestCase
{
    #[Test]
    public function tc_gen_07_waktu_tampil_di_zona_company(): void
    {
        $this->assertSame('25/09/2026 03:47', Carbon::parse('2026-09-24 20:47:00', 'UTC')->lokal()->format('d/m/Y H:i'));

        $this->company->forceFill(['timezone' => 'Asia/Jayapura'])->save();
        $this->assertSame('25/09/2026 05:47', Carbon::parse('2026-09-24 20:47:00', 'UTC')->lokal()->format('d/m/Y H:i'), 'WIT +9.');
        $this->company->forceFill(['timezone' => 'Asia/Jakarta'])->save();

        Carbon::setTestNow(Carbon::parse('2026-09-24 20:47:00', 'UTC'));

        try {
            $item = Item::create(['code' => 'BAUT', 'name' => 'Baut', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None,
                'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id')]);
            $pemohon = $this->makeUser('internal_requester');
            $req = app(SaveRequest::class)->handle(null, ['project_id' => $this->makeProject()->id, 'required_date' => '2026-09-30'],
                [['item_id' => $item->id, 'qty_base' => 1]], $pemohon);

            $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('requests/'.$req->id))->assertOk()
                ->assertSee('25/09/2026 03:47')->assertDontSee('24/09/2026 20:47');
        } finally {
            Carbon::setTestNow();
        }
    }
}

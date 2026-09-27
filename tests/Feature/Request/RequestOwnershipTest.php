<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership as ItemLineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Livewire\RequestForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-REQ-36 — Beli/Pinjam baris REQ mengikuti jenis barang dan ditetapkan
 * aksi simpan; pilihan hanya untuk item lama Keduanya (A-286, BR-REQ-06).
 */
class RequestOwnershipTest extends TenantTestCase
{
    private Project $proyek;

    private Item $baut;

    private Item $genset;

    private Item $bor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $baru = fn (string $kode, TrackingMode $mode, OwnershipModel $model, ?ItemLineOwnership $bawaan = null) => Item::create([
            'code' => $kode, 'name' => $kode, 'status' => ItemStatus::Active, 'tracking_mode' => $mode,
            'ownership_model' => $model, 'default_line_ownership' => $bawaan, 'base_uom_id' => $pcs,
        ]);

        $this->baut = $baru('BAUT-JNS', TrackingMode::None, OwnershipModel::Consumable);
        $this->genset = $baru('GENSET-JNS', TrackingMode::Serial, OwnershipModel::Asset);
        $this->bor = $baru('BOR-JNS', TrackingMode::Serial, OwnershipModel::Both, ItemLineOwnership::Loan);
    }

    #[Test]
    public function tc_req_36_beli_pinjam_mengikuti_jenis_barang(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        // Layar: alat & baut tampil sebagai teks; hanya item Keduanya yang bisa dipilih.
        Livewire::actingAs($pemohon)->test(RequestForm::class)
            // A-283: istilah Glosarium, bukan "Kepemilikan"/label teknis.
            ->assertSee(__('Beli/Pinjam'))->assertDontSee(__('Kepemilikan'))
            ->assertSee(__('Pindai atau ketik kode barang'))
            ->set('lines.0.item_id', (string) $this->genset->id)
            ->assertSet('lines.0.line_ownership', 'loan')
            ->assertSeeHtml('data-kepemilikan="loan"')
            ->assertDontSeeHtml('wire:model="lines.0.line_ownership"')
            ->set('lines.0.item_id', (string) $this->bor->id)
            ->assertSeeHtml('wire:model="lines.0.line_ownership"');

        // Aksi: nilai kiriman layar diabaikan kecuali untuk item Keduanya.
        $simpan = app(SaveRequest::class);
        $req = $simpan->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
        ], [
            ['item_id' => $this->genset->id, 'qty_base' => 1, 'line_ownership' => 'buy'],
            ['item_id' => $this->baut->id, 'qty_base' => 10, 'line_ownership' => 'loan'],
            ['item_id' => $this->bor->id, 'qty_base' => 1, 'line_ownership' => 'buy'],
        ], $pemohon);

        $baris = $req->lines()->orderBy('id')->get();
        $this->assertSame(['loan', 'buy', 'buy'], $baris->map(fn ($l) => $l->line_ownership->value)->all());

        // Baris lama yang itemnya tidak berganti dipertahankan (dokumen lama tidak berubah diam-diam).
        $lama = $baris[0];
        $lama->forceFill(['line_ownership' => 'buy'])->save();

        $simpan->handle($req->refresh(), ['required_date' => now()->addDays(3)->toDateString()], [
            ['id' => $lama->id, 'item_id' => $this->genset->id, 'qty_base' => 2, 'line_ownership' => 'loan'],
            ['id' => $baris[1]->id, 'item_id' => $this->baut->id, 'qty_base' => 10],
            ['id' => $baris[2]->id, 'item_id' => $this->bor->id, 'qty_base' => 1, 'line_ownership' => 'loan'],
        ], $pemohon);

        $this->assertSame('buy', $lama->refresh()->line_ownership->value);
        $this->assertSame(2.0, (float) $lama->qty_base);
        $this->assertSame('loan', $baris[2]->refresh()->line_ownership->value, 'Item Keduanya memakai pilihan pemohon.');
    }
}

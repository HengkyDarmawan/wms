<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shared\Messaging\HttpGateway;
use App\Domain\Shared\Messaging\MessageGateway;
use App\Domain\Shared\Messaging\MessageNotSent;
use App\Domain\Shared\Messaging\NullGateway;
use App\Domain\Shared\Messaging\PhoneNumber;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-SJ-20 s.d. TC-SJ-20d — OTP bukti terima otomatis (A-273, O-15): OTP
 * dikirim ke HP penerima dan tidak tampil ke staf/driver; gagal kirim kembali
 * ke jalur manual; saklar mati = perilaku lama; kirim ulang dari halaman
 * penerima dibatasi dan mematikan kode lama; gateway HTTP umum & nomor HP.
 */
class DeliveryOtpAutoTest extends TenantTestCase
{
    private FakeGateway $gateway;

    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->item = Item::create([
            'code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 100, toBinId: $bin->id));
    }

    private function sjDikirim(): Shipment
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()], [['item_id' => $this->item->id, 'qty_base' => 20]], $pemohon);
        $req->openLines()->first()->forceFill(['source_warehouse_id' => $this->gudang->id, 'fulfillment_source' => 'stock'])->save();
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);
        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }
        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet', 'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'TK'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $staf);

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    private function nyalakan(bool $gagal = false): void
    {
        $this->gateway = new FakeGateway($gagal);
        $this->app->instance(MessageGateway::class, $this->gateway);
        FeatureSetting::toggle('otp_auto', true);
    }

    /** OTP 6 digit dari pesan terakhir yang "terkirim". */
    private function otpTerkirim(): string
    {
        preg_match('/Kode OTP: (\d{6})/', (string) end($this->gateway->terkirim)['text'], $m);

        return $m[1] ?? '';
    }

    #[Test]
    public function tc_sj_20_otp_dikirim_ke_penerima_dan_tidak_tampil_ke_staf(): void
    {
        $this->nyalakan();
        $sj = $this->sjDikirim();

        $this->actingAs($this->makeUser('warehouse_head'));
        $c = Livewire::test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'tautan')->assertSee(__('Tautan dan kode OTP dikirim otomatis ke WhatsApp/SMS nomor ini; driver tidak melihat kodenya.'))
            ->set('form.phone', '0812-3456-7890')->call('terbitkanTautan')->assertHasNoErrors()
            ->assertSet('otpSekali', null)->assertSet('otpTerkirimKe', '62812****7890')
            ->assertSee('Kode tidak ditampilkan di sini');

        $this->assertCount(1, $this->gateway->terkirim);
        $pesan = $this->gateway->terkirim[0];
        $this->assertSame('6281234567890', $pesan['phone']);
        $this->assertStringContainsString($sj->number, $pesan['text']);
        $token = $sj->tokens()->sole();
        $this->assertStringContainsString(route('terima.show', $token->token), $pesan['text']);
        $this->assertNotNull($token->otp_sent_at);
        $this->assertSame(1, $token->otp_send_count);

        // Kode dari pesan membuka formulir; halaman menyebut nomor tujuan.
        $url = $this->tenantUrl('terima/'.$token->token);
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('62812****7890')->assertSee(__('Kode belum masuk? Kirim ulang'));
        $this->post($this->tenantUrl('terima/'.$token->token.'/otp'), ['otp' => $this->otpTerkirim()])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('received_by_name');
        $this->assertTrue(Activity::query()->where('description', 'OTP bukti terima dikirim otomatis')->exists());
    }

    #[Test]
    public function tc_sj_20b_gagal_kirim_atau_nomor_tidak_sah_kembali_ke_otp_manual(): void
    {
        $this->nyalakan(gagal: true);
        $hasil = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '08123456789');
        $this->assertFalse($hasil['sent']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $hasil['otp'], 'Jalur manual: OTP dikembalikan.');
        $this->assertNotNull($hasil['error']);
        $this->assertNull($hasil['token']->otp_sent_at);
        $this->assertTrue(Activity::query()->where('description', 'OTP bukti terima gagal dikirim otomatis')->exists());

        $this->nyalakan();
        $hasil = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '12345');
        $this->assertFalse($hasil['sent']);
        $this->assertNotNull($hasil['otp']);
        $this->assertSame([], $this->gateway->terkirim, 'Nomor tidak sah: tidak ada yang dikirim.');
    }

    #[Test]
    public function tc_sj_20c_saklar_mati_atau_kanal_none_perilaku_lama(): void
    {
        // Kanal ada, saklar company mati.
        $this->gateway = new FakeGateway;
        $this->app->instance(MessageGateway::class, $this->gateway);
        $hasil = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '08123456789');
        $this->assertNotNull($hasil['otp']);
        $this->assertSame([], $this->gateway->terkirim);

        // Saklar menyala, platform belum punya kanal (driver none bawaan).
        $this->app->forgetInstance(MessageGateway::class);
        $this->app->offsetUnset(MessageGateway::class);
        $this->app->bind(MessageGateway::class, fn () => new NullGateway);
        FeatureSetting::toggle('otp_auto', true);
        $hasil = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '08123456789');
        $this->assertNotNull($hasil['otp']);
        $this->assertFalse($hasil['sent']);

        $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('settings/company'))->assertOk()
            ->assertSee(__('OTP bukti terima otomatis'))->assertSee(__('Kanal WhatsApp/SMS belum diatur oleh platform; sampai itu, OTP tetap disampaikan driver.'));
    }

    #[Test]
    public function tc_sj_20d_kirim_ulang_dibatasi_dan_mematikan_kode_lama(): void
    {
        $this->nyalakan();
        $hasil = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '08123456789');
        $token = $hasil['token'];
        $otpLama = $this->otpTerkirim();
        $url = $this->tenantUrl('terima/'.$token->token);

        // Terlalu cepat: ditolak.
        $this->from($url)->post($url.'/kirim-ulang')->assertRedirect($url)->assertSessionHasErrors('otp');
        $this->assertCount(1, $this->gateway->terkirim);

        $this->travel(2)->minutes();
        $this->from($url)->post($url.'/kirim-ulang')->assertRedirect($url)->assertSessionHas('status');
        $this->assertCount(2, $this->gateway->terkirim);
        $otpBaru = $this->otpTerkirim();
        $this->assertSame(2, $token->refresh()->otp_send_count);

        if ($otpBaru !== $otpLama) {
            $this->from($url)->post($url.'/otp', ['otp' => $otpLama])->assertSessionHasErrors('otp');
        }

        // Batas total kiriman per tautan.
        foreach ([3, 4] as $_) {
            $this->travel(2)->minutes();
            $this->from($url)->post($url.'/kirim-ulang')->assertSessionHas('status');
        }
        $this->travel(2)->minutes();
        $this->from($url)->post($url.'/kirim-ulang')->assertSessionHasErrors('otp');
        $this->assertCount(4, $this->gateway->terkirim);
        $this->get($url)->assertOk()->assertDontSee(__('Kode belum masuk? Kirim ulang'));

        $this->post($url.'/otp', ['otp' => $this->otpTerkirim()])->assertRedirect($url);

        // Tautan manual (tanpa kiriman otomatis) tidak bisa kirim ulang.
        FeatureSetting::toggle('otp_auto', false);
        $manual = app(IssueDeliveryToken::class)->handle($this->sjDikirim(), '08123456789');
        $this->travel(2)->minutes();
        $this->from($this->tenantUrl('terima/'.$manual['token']->token))->post($this->tenantUrl('terima/'.$manual['token']->token.'/kirim-ulang'))
            ->assertSessionHasErrors('otp');
    }

    #[Test]
    public function tc_sj_20e_gateway_http_umum_dan_nomor_hp(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('81234567890'));
        $this->assertNull(PhoneNumber::normalize('021-555-1234'));
        $this->assertNull(PhoneNumber::normalize(''));

        Http::fake([
            'gateway.test/ok' => Http::response(['status' => true]),
            'gateway.test/tolak' => Http::response(['status' => false, 'reason' => 'invalid token']),
            'gateway.test/rusak' => Http::response('x', 500),
        ]);
        $cfg = ['url' => 'https://gateway.test/ok', 'token' => 'RAHASIA', 'phone_field' => 'target', 'message_field' => 'message', 'extra' => '{"countryCode":"62"}'];

        (new HttpGateway($cfg))->send('6281234567890', 'Halo');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://gateway.test/ok'
            && $r->header('Authorization')[0] === 'RAHASIA'
            && $r['target'] === '6281234567890' && $r['message'] === 'Halo' && $r['countryCode'] === '62');

        (new HttpGateway(['format' => 'json', 'phone_field' => 'phone'] + $cfg))->send('6281234567890', 'Halo');
        Http::assertSent(fn (HttpRequest $r) => $r->isJson() && $r['phone'] === '6281234567890');

        foreach (['tolak', 'rusak'] as $akhir) {
            try {
                (new HttpGateway(['url' => 'https://gateway.test/'.$akhir] + $cfg))->send('6281234567890', 'Halo');
                $this->fail('Seharusnya MessageNotSent.');
            } catch (MessageNotSent) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertFalse((new HttpGateway(['url' => ''] + $cfg))->available());
    }
}

/** Kanal palsu yang mencatat pesan (atau menolak) untuk uji. */
class FakeGateway implements MessageGateway
{
    /** @var list<array{phone: string, text: string}> */
    public array $terkirim = [];

    public function __construct(private readonly bool $gagal = false) {}

    public function available(): bool
    {
        return true;
    }

    public function send(string $phone, string $text): void
    {
        if ($this->gagal) {
            throw new MessageNotSent('Gateway uji menolak.');
        }

        $this->terkirim[] = ['phone' => $phone, 'text' => $text];
    }
}

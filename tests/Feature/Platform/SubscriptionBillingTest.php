<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Notification\Models\Notification;
use App\Domain\Platform\Actions\VerifySubscriptionPayment;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SubscriptionInvoice;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Domain\Platform\Support\SubscriptionLifecycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-04 s.d. TC-PLT-07 & TC-PLT-12 — siklus langganan harian, tagihan, unggah &
 * verifikasi bukti bayar, dan gerbang akses per status (BR-SUB-01–03,
 * A-177–A-179, A-181).
 */
class SubscriptionBillingTest extends TenantTestCase
{
    private function centralUrl(string $path): string
    {
        return 'http://'.config('tenancy.central_domains.0').'/'.ltrim($path, '/');
    }

    private function superAdmin(): PlatformUser
    {
        return PlatformUser::create(['email' => 'billing-admin@wms.test', 'name' => 'Super Admin Tagihan', 'password' => Hash::make('Rahasia#2026!')]);
    }

    /** Masa berjalan berakhir 5 hari lagi, harga paket Rp 500.000. */
    private function siapkanPeriode(): void
    {
        $this->company->plan->forceFill(['monthly_price' => 500000])->save();
        $this->subscription()->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(25)->toDateString(),
            'current_period_end' => now()->addDays(5)->toDateString(),
        ])->save();
    }

    private function tagihan(): SubscriptionInvoice
    {
        return SubscriptionInvoice::query()->where('subscription_id', $this->subscription()->id)->latest('id')->firstOrFail();
    }

    #[Test]
    public function tc_plt_04_siklus_tagihan_jatuh_tempo_tangguh_akhir(): void
    {
        $this->siapkanPeriode();
        $siklus = app(SubscriptionLifecycle::class);
        $admin = $this->makeUser('company_admin');
        $kabar = fn (string $kanal = 'in_app') => Notification::query()->where('user_id', $admin->id)->where('type', 'subscription.billing')->where('channel', $kanal)->count();

        // H-7: tagihan periode berikutnya terbit sekali; Admin Company diberi tahu, email menyala bawaan (A-202).
        $this->assertSame(1, $siklus->run()['invoiced']);
        $this->assertSame(1, $kabar());
        $this->assertSame(1, $kabar('email'));
        $this->assertSame(0, $siklus->run()['invoiced'], 'Tidak terbit dua kali.');
        $inv = $this->tagihan();
        $this->assertMatchesRegularExpression('#^INV/\d{4}/0001$#', $inv->number);
        $this->assertSame(now()->addDays(5)->toDateString(), $inv->due_date->toDateString());
        $this->assertSame(now()->addDays(6)->toDateString(), $inv->period_start->toDateString());
        $this->assertSame(500000.0, (float) $inv->amount);

        // Lewat jatuh tempo → past_due dengan tenggang 7 hari.
        $this->assertSame(1, $siklus->run(now()->addDays(6))['past_due']);
        $sub = $this->subscription();
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);
        $this->assertSame(now()->addDays(12)->toDateString(), $sub->grace_ends_at->toDateString());
        $this->assertSame(InvoiceStatus::Overdue, $inv->refresh()->status);
        $this->assertSame(2, $kabar(), 'Jatuh tempo juga diberitahukan.');

        // Tenggang habis → suspended; 30 hari kemudian → terminated, company ikut diakhiri.
        $this->assertSame(1, $siklus->run(now()->addDays(13))['suspended']);
        $this->assertSame(SubscriptionStatus::Suspended, $this->subscription()->status);
        $this->assertSame(0, $siklus->run(now()->addDays(40))['terminated'], 'Belum 30 hari.');
        $this->assertSame(1, $siklus->run(now()->addDays(44))['terminated']);

        $sub = $this->subscription();
        $this->assertSame(SubscriptionStatus::Terminated, $sub->status);
        $this->assertSame(now()->addDays(44 + 90)->toDateString(), $sub->purge_after->toDateString());
        $this->assertSame(CompanyStatus::Terminated, $this->company->refresh()->status);

        $this->artisan('subscriptions:cycle')->assertSuccessful();
        $this->company->forceFill(['status' => CompanyStatus::Active])->save();
    }

    #[Test]
    public function tc_plt_05_bukti_bayar_diunggah_ditolak_lalu_diverifikasi(): void
    {
        Storage::fake('local');
        $this->siapkanPeriode();
        app(SubscriptionLifecycle::class)->run();
        $inv = $this->tagihan();
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('billing'))->assertOk()->assertSee($inv->number)->assertSee(__('Kirim bukti'));

        // Validasi: tanpa bukti, jumlah nol, tanggal masa depan.
        $url = $this->tenantUrl('billing/invoices/'.$inv->id.'/payments');
        $this->actingAs($admin)->post($url, ['amount' => 500000, 'paid_at' => now()->toDateString()])->assertSessionHasErrors('proof');
        $this->actingAs($admin)->post($url, ['amount' => 0, 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post($url, ['amount' => 500000, 'paid_at' => now()->addDay()->toDateString(), 'proof' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasErrors('paid_at');

        $this->actingAs($admin)->post($url, ['amount' => 500000, 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('bukti.jpg')])
            ->assertRedirect($this->tenantUrl('billing'));
        $bayar = SubscriptionPayment::query()->where('invoice_id', $inv->id)->sole();
        $this->assertSame(PaymentStatus::Pending, $bayar->status);
        $this->assertSame($admin->name, $bayar->uploaded_by_name);
        $this->actingAs($admin)->get($this->tenantUrl('billing/payments/'.$bayar->id.'/proof'))->assertOk();

        // Satu bukti menunggu per tagihan.
        $this->actingAs($admin)->post($url, ['amount' => 500000, 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('dua.jpg')])->assertSessionHasErrors();

        // Super Admin: tolak tanpa alasan gagal, dengan alasan berhasil.
        $sa = $this->superAdmin();
        $this->actingAs($sa, 'platform')->get($this->centralUrl('admin/payments'))->assertOk()->assertSee($inv->number);
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/payments/'.$bayar->id.'/reject'), ['reject_reason' => ''])->assertSessionHasErrors('reject_reason');
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/payments/'.$bayar->id.'/reject'), ['reject_reason' => 'Nominal tidak terbaca'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Rejected, $bayar->refresh()->status);
        $this->assertSame(InvoiceStatus::Open, $inv->refresh()->status);

        // Unggah ulang saat langganan sudah ditangguhkan tetap boleh (BR-SUB-02), lalu diverifikasi.
        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);
        $this->subscription()->forceFill(['suspended_at' => now(), 'grace_ends_at' => now()->subDay()])->save();
        $this->actingAs($admin)->post($url, ['amount' => 500000, 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('ulang.png')])->assertRedirect();
        $ulang = SubscriptionPayment::query()->where('invoice_id', $inv->id)->where('status', 'pending')->sole();

        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/payments/'.$ulang->id.'/verify'))->assertSessionHasNoErrors();
        $sub = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $sub->status);
        $this->assertSame($inv->period_end->toDateString(), $sub->current_period_end->toDateString());
        $this->assertNull($sub->suspended_at);
        $this->assertSame(InvoiceStatus::Paid, $inv->refresh()->status);
        $this->assertSame((int) $sa->id, (int) $ulang->refresh()->verified_by);

        // Diputus dua kali ditolak.
        $this->expectException(PlatformRuleException::class);
        app(VerifySubscriptionPayment::class)->verify($ulang, $sa);
    }

    #[Test]
    public function tc_plt_12_bayar_terlambat_memulai_periode_baru_dari_hari_verifikasi(): void
    {
        // Tagihan periode yang sudah lewat seluruhnya, baru dibayar saat ditangguhkan.
        $this->company->plan->forceFill(['monthly_price' => 500000])->save();
        $this->subscription()->forceFill([
            'status' => SubscriptionStatus::Suspended,
            'current_period_start' => now()->subDays(70)->toDateString(),
            'current_period_end' => now()->subDays(41)->toDateString(),
            'suspended_at' => now()->subDays(20),
        ])->save();
        $inv = SubscriptionInvoice::create([
            'subscription_id' => $this->subscription()->id, 'number' => 'INV/0000/0099',
            'period_start' => now()->subDays(40)->toDateString(), 'period_end' => now()->subDays(11)->toDateString(),
            'amount' => 500000, 'due_date' => now()->subDays(41)->toDateString(), 'status' => InvoiceStatus::Overdue,
        ]);
        $bayar = SubscriptionPayment::create([
            'invoice_id' => $inv->id, 'amount' => 500000, 'paid_at' => now()->toDateString(),
            'proof_path' => 'bukti/x.jpg', 'status' => PaymentStatus::Pending,
        ]);

        app(VerifySubscriptionPayment::class)->verify($bayar, $this->superAdmin());

        $sub = $this->subscription();
        $this->assertSame(SubscriptionStatus::Active, $sub->status);
        $this->assertSame(now()->toDateString(), $sub->current_period_start->toDateString());
        $this->assertSame(now()->addMonthNoOverflow()->subDay()->toDateString(), $sub->current_period_end->toDateString());

        // Siklus harian tidak langsung menjatuhkan tempo lagi.
        $hasil = app(SubscriptionLifecycle::class)->run();
        $this->assertSame(0, $hasil['past_due']);
        $this->assertSame(SubscriptionStatus::Active, $this->subscription()->status);
    }

    #[Test]
    public function tc_plt_06_gerbang_akses_per_status_langganan_dan_company(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff', ScopeType::All);

        // Hanya Admin Company yang melihat tagihan.
        $this->actingAs($staf)->get($this->tenantUrl('billing'))->assertForbidden();
        $this->actingAs($admin)->get($this->tenantUrl('/'))->assertOk()->assertSee(route('billing.index'));

        // Penangguhan manual Super Admin = hanya-baca (A-179).
        $this->company->forceFill(['status' => CompanyStatus::Suspended])->save();
        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()->assertSee(__('Langganan ditangguhkan: data hanya bisa dibaca sampai pembayaran diverifikasi.'));
        $this->actingAs($staf)->put($this->tenantUrl('/profile'), ['name' => 'X'])->assertForbidden();

        // Diakhiri: Admin Company hanya layar ekspor, tagihan, profil (A-181).
        $this->company->forceFill(['status' => CompanyStatus::Active])->save();
        $this->setSubscriptionStatus(SubscriptionStatus::Terminated);
        $this->actingAs($admin)->get($this->tenantUrl('reports'))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('billing'))->assertOk()->assertDontSee(__('Kirim bukti'));
        $this->actingAs($admin)->get($this->tenantUrl('items'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('reports'))->assertForbidden();

        // Masih disiapkan: belum bisa dipakai.
        $this->setSubscriptionStatus(SubscriptionStatus::Active);
        $this->company->forceFill(['status' => CompanyStatus::Provisioning])->save();
        $this->actingAs($admin)->get($this->tenantUrl('/'))->assertStatus(503);
        $this->company->forceFill(['status' => CompanyStatus::Active])->save();
    }

    #[Test]
    public function tc_plt_07_langganan_diakhiri_tidak_menerima_pembayaran(): void
    {
        $this->siapkanPeriode();
        app(SubscriptionLifecycle::class)->run();
        $inv = $this->tagihan();
        $this->setSubscriptionStatus(SubscriptionStatus::Terminated);

        $this->actingAs($this->makeUser('company_admin'))
            ->post($this->tenantUrl('billing/invoices/'.$inv->id.'/payments'), ['amount' => 1, 'paid_at' => now()->toDateString()])
            ->assertForbidden();

        $bayar = SubscriptionPayment::create(['invoice_id' => $inv->id, 'amount' => 500000, 'status' => PaymentStatus::Pending]);
        $sa = $this->superAdmin();
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/payments/'.$bayar->id.'/verify'))->assertSessionHasErrors('platform');
        $this->assertSame(PaymentStatus::Pending, $bayar->refresh()->status);
    }
}

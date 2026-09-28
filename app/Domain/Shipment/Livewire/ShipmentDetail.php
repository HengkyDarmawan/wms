<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Livewire\Concerns\FillsDeliveryProof;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryOtpSender;
use App\Domain\Shipment\Support\DeliveryRecipients;
use App\Domain\Shipment\Support\ProofFiles;
use App\Domain\Shipment\Support\ShipmentLineOrigins;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 15-picking-shipment §6 — detail surat jalan.
 *
 * Tiga keputusan ada di sini: memberangkatkan (staf gudang asal, A-311),
 * membatalkan selama belum berangkat, dan mengisi bukti terima (penerima
 * atau cadangan Kepala Gudang asal, A-312). Yang terakhir adalah yang paling banyak
 * aturannya, karena di situlah selisih lahir.
 */
class ShipmentDetail extends Component
{
    use FillsDeliveryProof;
    use HandlesShipmentRules;
    use WithFileUploads;

    #[Locked]
    public int $shipmentId;

    /** '', 'batal', 'terima', 'tautan' */
    public string $dialog = '';

    public string $reasonCode = '';

    /** @var array<string, mixed> */
    public array $form = [
        'received_by_name' => '',
        'notes' => '',
        'phone' => '',
        'client_gr_number' => '',
    ];

    /** Tautan penerima bertoken; hanya ditampilkan sekali bersama OTP-nya. */
    public ?string $tautanSekali = null;

    /** OTP tautan bertoken; hanya ditampilkan sekali setelah diterbitkan. */
    public ?string $otpSekali = null;

    /** A-273: nomor (disamarkan) yang menerima OTP otomatis; null = OTP manual. */
    public ?string $otpTerkirimKe = null;

    /** A-273: alasan OTP otomatis gagal terkirim, bila ada. */
    public ?string $otpGagal = null;

    /** A-279: `whatsapp` = kode lewat template autentikasi (tanpa tautan) — tautan tetap dibagikan staf. */
    public ?string $otpVia = null;

    public function mount(Shipment $shipment): void
    {
        $this->authorize('view', $shipment);

        $this->shipmentId = (int) $shipment->id;
    }

    public function render(): View
    {
        $sj = $this->shipment();
        $lines = $sj->lines()->with('item:id,code,name', 'serial:id,serial_no', 'piece:id,piece_no', 'pickTaskLine.bin:id,code', 'pickTaskLine.pickTask')->orderBy('id')->get();

        return view('livewire.shipment.shipment-detail', [
            'sj' => $sj,
            'lines' => $lines,
            // Asal per baris: REQ/SJ asal, AST, atau DSC (A-248).
            'asal' => app(ShipmentLineOrigins::class)->for($sj, $lines),
            'bukti' => $sj->proof()->with('lines.units')->first(),
            'selisih' => $sj->discrepancies()->with('lines.shipmentLine.item')->orderByDesc('id')->get(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => $this->riwayat($sj),
            'otpOtomatis' => app(DeliveryOtpSender::class)->enabled(),
            // A-312/A-316: jalur pengisian bukti terima bagi user ini (null = tidak boleh).
            'kanal' => $sj->status->value === 'shipped' ? app(DeliveryRecipients::class)->channelFor(auth()->user(), $sj) : null,
        ]);
    }

    public function berangkatkan(ShipShipment $action): void
    {
        $sj = $this->shipment();

        $this->authorize('ship', $sj);

        if ($this->jalankan(fn () => $action->handle($sj, $this->form['notes'] ?: null, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Surat jalan diberangkatkan.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $sj = $this->shipment();

        $izin = match ($dialog) {
            'batal' => 'cancel',
            'terima' => 'confirmDelivery',
            'tautan' => 'issueToken',
            default => 'view',
        };

        $this->authorize($izin, $sj);

        $this->dialog = $dialog;
        $this->reasonCode = '';
        $this->otpSekali = null;
        $this->tautanSekali = null;
        $this->otpTerkirimKe = null;
        $this->otpGagal = null;
        $this->otpVia = null;
        $this->ruleError = '';
        $this->resetValidation();

        if ($dialog === 'terima') {
            $this->siapkanBuktiTerima($sj);
        }
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->terima = [];
        $this->otpSekali = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function batalkan(ShipShipment $action): void
    {
        $sj = $this->shipment();

        $this->authorize('cancel', $sj);

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        if (! $this->jalankan(fn () => $action->cancel($sj, $this->alasanId($this->reasonCode), auth()->user()))) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Surat jalan dibatalkan.'));
    }

    public function simpanBuktiTerima(ConfirmDelivery $action, ProofFiles $berkas): void
    {
        $sj = $this->shipment();

        $this->authorize('confirmDelivery', $sj);

        if (! $this->simpanBukti($sj, $action, $berkas)) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Bukti terima tersimpan.'));
    }

    /**
     * Menerbitkan tautan bertoken untuk penerima tanpa akun.
     *
     * Bila OTP otomatis aktif (A-273), OTP dikirim ke HP penerima dan tidak
     * tampil di sini; kalau tidak atau gagal terkirim, OTP ditampilkan sekali
     * dan tidak pernah tersimpan sebagai teks — staf menyampaikannya sendiri.
     */
    public function terbitkanTautan(IssueDeliveryToken $action): void
    {
        $sj = $this->shipment();

        $this->authorize('issueToken', $sj);

        $hasil = null;

        $berhasil = $this->jalankan(function () use ($action, $sj, &$hasil) {
            $hasil = $action->handle($sj, $this->form['phone'] ?: null, auth()->user());
        });

        if (! $berhasil || $hasil === null) {
            return;
        }

        $this->otpSekali = $hasil['otp'];
        $this->otpTerkirimKe = $hasil['sent'] ? $hasil['phone'] : null;
        $this->otpVia = $hasil['sent'] ? ($hasil['via'] ?? null) : null;
        $this->otpGagal = $hasil['error'];
        $this->tautanSekali = route('terima.show', $hasil['token']->token);
        $this->dispatch('pesan', teks: $hasil['sent']
            ? __('Tautan & OTP dikirim ke :hp.', ['hp' => $hasil['phone']])
            : __('Tautan bukti terima diterbitkan.'));
    }

    private function alasanId(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $id = ReasonCode::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function shipment(): Shipment
    {
        return Shipment::query()->withoutGlobalScopes()
            ->with(
                'warehouse:id,code,name',
                'destinationProject:id,code,name,client_id',
                'destinationWarehouse:id,code,name',
                'destinationVendor:id,name',
                'vehicle:id,plate_no',
                'carrier:id,name',
            )
            ->findOrFail($this->shipmentId);
    }

    /** @return Collection<int, Activity> */
    private function riwayat(Shipment $sj): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where('log_name', 'shipment')
            ->where('subject_type', $sj->getMorphClass())
            ->where('subject_id', $sj->id)
            ->latest('id')
            ->limit(30)
            ->get();
    }
}

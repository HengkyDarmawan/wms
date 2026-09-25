<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 15-picking-shipment §6 — detail surat jalan.
 *
 * Tiga keputusan ada di sini: memberangkatkan, membatalkan selama belum
 * berangkat, dan mengisi bukti terima. Yang terakhir adalah yang paling banyak
 * aturannya, karena di situlah selisih lahir.
 */
class ShipmentDetail extends Component
{
    use HandlesShipmentRules;

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
    ];

    /**
     * Isian bukti terima per baris SJ: baik, rusak, kurang, dan fotonya.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $terima = [];

    /** OTP tautan bertoken; hanya ditampilkan sekali setelah diterbitkan. */
    public ?string $otpSekali = null;

    public function mount(Shipment $shipment): void
    {
        $this->authorize('view', $shipment);

        $this->shipmentId = (int) $shipment->id;
    }

    public function render(): View
    {
        $sj = $this->shipment();

        return view('livewire.shipment.shipment-detail', [
            'sj' => $sj,
            'lines' => $sj->lines()->with('pickTaskLine.item:id,code,name', 'pickTaskLine.bin:id,code')->orderBy('id')->get(),
            'bukti' => $sj->proof()->with('lines')->first(),
            'selisih' => $sj->discrepancies()->with('lines.shipmentLine.pickTaskLine.item')->orderByDesc('id')->get(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => $this->riwayat($sj),
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
            'tautan' => 'ship',
            default => 'view',
        };

        $this->authorize($izin, $sj);

        $this->dialog = $dialog;
        $this->reasonCode = '';
        $this->otpSekali = null;
        $this->ruleError = '';
        $this->resetValidation();

        if ($dialog === 'terima') {
            $this->terima = $sj->lines()->orderBy('id')->get()
                ->mapWithKeys(fn ($l) => [$l->id => [
                    // Bawaannya seluruhnya baik: yang paling sering terjadi.
                    'qty_good' => (string) (float) $l->qty_shipped,
                    'qty_damaged' => '0',
                    'qty_missing' => '0',
                    'damage_photo_path' => '',
                    'notes' => '',
                ]])->all();
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

    public function simpanBuktiTerima(ConfirmDelivery $action): void
    {
        $sj = $this->shipment();

        $this->authorize('confirmDelivery', $sj);

        $this->validate(
            ['form.received_by_name' => ['required', 'string', 'max:100']],
            attributes: ['form.received_by_name' => __('Nama penerima')],
        );

        $baris = [];

        foreach ($this->terima as $id => $isi) {
            $baris[] = [
                'shipment_line_id' => $id,
                'qty_good' => (float) ($isi['qty_good'] ?? 0),
                'qty_damaged' => (float) ($isi['qty_damaged'] ?? 0),
                'qty_missing' => (float) ($isi['qty_missing'] ?? 0),
                'damage_photo_path' => $isi['damage_photo_path'] ?? null,
                'notes' => $isi['notes'] ?? null,
            ];
        }

        $berhasil = $this->jalankan(fn () => $action->handle($sj, [
            'received_by_name' => $this->form['received_by_name'],
            'notes' => $this->form['notes'] ?: null,
            'channel' => 'driver_pwa',
            'received_by_user_id' => auth()->id(),
        ], $baris, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Bukti terima tersimpan.'));
        // A-193: draf bukti terima di perangkat sudah terkirim.
        $this->dispatch('draft-clear', key: 'pod-'.$this->shipmentId);
    }

    /**
     * Menerbitkan tautan bertoken untuk penerima tanpa akun.
     *
     * OTP-nya ditampilkan sekali di layar ini dan tidak pernah tersimpan
     * sebagai teks; staf menyampaikannya sendiri sampai pengiriman lewat
     * WhatsApp atau SMS tersedia.
     */
    public function terbitkanTautan(IssueDeliveryToken $action): void
    {
        $sj = $this->shipment();

        $this->authorize('ship', $sj);

        $hasil = null;

        $berhasil = $this->jalankan(function () use ($action, $sj, &$hasil) {
            $hasil = $action->handle($sj, $this->form['phone'] ?: null, auth()->user());
        });

        if (! $berhasil || $hasil === null) {
            return;
        }

        $this->otpSekali = $hasil['otp'];
        $this->dispatch('pesan', teks: __('Tautan bukti terima diterbitkan.'));
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
        return Shipment::query()
            ->with(
                'warehouse:id,code,name',
                'destinationProject:id,code,name,client_id',
                'destinationWarehouse:id,code,name',
                'destinationVendor:id,name',
                'vehicle:id,plate_no',
                'driver:id,name',
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

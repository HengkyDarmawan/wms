<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Livewire\Concerns\FillsDeliveryProof;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;
use App\Domain\Shipment\Support\ProofFiles;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Layar 15-picking-shipment §6 — bukti terima di portal klien (A-312).
 *
 * Admin site klien memeriksa barang, mengisi per baris baik/rusak/kurang,
 * mengunggah foto SJ bertanda tangan & cap, dan (opsional) No. GR di sistem
 * kliennya. Karena yang mengisi pihak klien sendiri, konfirmasi pemohon
 * langsung tercatat (A-317); rusak/kurang tetap menjadi DSC.
 */
class PortalDeliveryProof extends Component
{
    use FillsDeliveryProof;
    use HandlesShipmentRules;
    use WithFileUploads;

    #[Locked]
    public int $shipmentId;

    /** @var array<string, mixed> */
    public array $form = [
        'received_by_name' => '',
        'notes' => '',
        'client_gr_number' => '',
    ];

    public function mount(Shipment $shipment): void
    {
        $this->authorize('confirmDelivery', $shipment);

        $this->shipmentId = (int) $shipment->id;
        $this->siapkanBuktiTerima($shipment);
    }

    public function simpanBuktiTerima(ConfirmDelivery $action, ProofFiles $berkas): void
    {
        $sj = $this->shipment();

        $this->authorize('confirmDelivery', $sj);

        if (! $this->simpanBukti($sj, $action, $berkas)) {
            return;
        }

        session()->flash('status', __('Bukti terima :sj tersimpan.', ['sj' => $sj->number]));

        $req = $sj->materialRequestIds()[0] ?? null;

        $req === null
            ? $this->redirectRoute('portal.dashboard', navigate: true)
            : $this->redirectRoute('portal.requests.show', $req, navigate: true);
    }

    public function kembali(): void
    {
        $this->redirectRoute('portal.dashboard', navigate: true);
    }

    public function render(): View
    {
        $sj = $this->shipment();

        return view('livewire.shipment.portal-delivery-proof', [
            'sj' => $sj,
            'lines' => $sj->lines()->with('item:id,code,name', 'serial:id,serial_no', 'piece:id,piece_no')->orderBy('id')->get(),
            'kanal' => app(DeliveryRecipients::class)->channelFor(auth()->user(), $sj),
            'poKlien' => $sj->clientPoNumbers(),
        ]);
    }

    private function shipment(): Shipment
    {
        return Shipment::query()->withoutGlobalScopes()
            ->with('warehouse:id,code,name', 'destinationProject:id,code,name,client_id', 'vehicle:id,plate_no')
            ->findOrFail($this->shipmentId);
    }
}

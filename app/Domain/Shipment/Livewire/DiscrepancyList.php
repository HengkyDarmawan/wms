<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Actions\ResolveDiscrepancy;
use App\Domain\Shipment\Enums\ClientDecision;
use App\Domain\Shipment\Enums\DiscrepancyDisposition;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 15-picking-shipment §6 — selisih pengiriman dan penyelesaiannya.
 *
 * Daftar dan dialog penyelesaian disatukan: selisih hampir selalu diputuskan
 * berurutan dalam satu duduk, dan memaksa berpindah halaman untuk setiap
 * dokumen hanya menambah langkah.
 */
class DiscrepancyList extends Component
{
    use HandlesShipmentRules;
    use WithPagination;

    /** Ambang hari sebelum selisih dianggap menggantung (BR-SJ-10). */
    public const AMBANG = 'discrepancy_alert_days';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'open')]
    public string $statusFilter = 'open';

    #[Url(except: false)]
    public bool $hanyaMenggantung = false;

    #[Locked]
    public ?int $dscId = null;

    /**
     * Keputusan per baris selisih: disposisi, keputusan klien, alasan, klaim.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $keputusan = [];

    public string $catatan = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DeliveryDiscrepancy::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page' && ! str_starts_with($property, 'keputusan')) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.shipment.discrepancy-list', [
            'items' => $this->daftar(),
            'ambang' => $this->ambangHari(),
            'statuses' => DiscrepancyStatus::options(),
            'dispositions' => DiscrepancyDisposition::options(),
            'decisions' => ClientDecision::options(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Adjustment),
            'dipilih' => $this->dscId === null ? null : $this->dsc(),
        ]);
    }

    /** A-64: ambang bawaan 7 hari, bisa disetel per company. */
    public function ambangHari(): int
    {
        $nilai = (int) CompanySetting::get(self::AMBANG, 7);

        return $nilai > 0 ? $nilai : 7;
    }

    public function mintaSelesaikan(int $id): void
    {
        $dsc = DeliveryDiscrepancy::query()->with('lines')->findOrFail($id);

        $this->authorize('resolve', $dsc);

        $this->dscId = $dsc->id;
        $this->catatan = '';
        $this->ruleError = '';
        $this->resetValidation();

        $this->keputusan = $dsc->lines->mapWithKeys(fn ($l) => [$l->id => [
            'disposition' => '',
            'client_decision' => ClientDecision::StillNeeded->value,
            'reason_code' => '',
            'claim_ref' => '',
        ]])->all();
    }

    public function tutupDialog(): void
    {
        $this->dscId = null;
        $this->keputusan = [];
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function selesaikan(ResolveDiscrepancy $action): void
    {
        $dsc = $this->dsc();

        $this->authorize('resolve', $dsc);

        $keputusan = [];

        foreach ($this->keputusan as $id => $k) {
            $keputusan[] = [
                'line_id' => $id,
                'disposition' => (string) ($k['disposition'] ?? ''),
                'client_decision' => (string) ($k['client_decision'] ?? ''),
                'reason_code_id' => $this->alasanId((string) ($k['reason_code'] ?? '')),
                'claim_ref' => $k['claim_ref'] ?? null,
            ];
        }

        if (! $this->jalankan(fn () => $action->handle($dsc, $keputusan, $this->catatan ?: null, auth()->user()))) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Selisih pengiriman diselesaikan.'));
    }

    private function daftar(): LengthAwarePaginator
    {
        $ambang = $this->ambangHari();

        return DeliveryDiscrepancy::query()
            ->with('shipment:id,number,warehouse_id,destination_type,destination_project_id', 'resolver:id,name')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('shipment', fn (Builder $s) => $s->where('number', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->hanyaMenggantung, fn (Builder $q) => $q->stale($ambang))
            ->orderBy('created_at')
            ->paginate(20);
    }

    private function alasanId(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $id = ReasonCode::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function dsc(): DeliveryDiscrepancy
    {
        return DeliveryDiscrepancy::query()
            ->with('shipment:id,number', 'lines.shipmentLine.pickTaskLine.item:id,code,name')
            ->findOrFail($this->dscId);
    }
}

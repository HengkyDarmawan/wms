<?php

declare(strict_types=1);

namespace App\Domain\Asset\Livewire;

use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Asset\Actions\MarkAssetLost;
use App\Domain\Asset\Actions\UpdateAssetProfile;
use App\Domain\Asset\Livewire\Concerns\HandlesAssetRules;
use App\Domain\Asset\Support\AssetQuery;
use App\Domain\Master\Enums\MeterUnit;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 25-aset §6 — detail aset: posisi & state, profil masa pakai (A-66),
 * riwayat serah terima dan pemeriksaan (riwayat kondisi), kartu stok serial,
 * tandai hilang (BR-AST-04) atau ditemukan kembali.
 */
class AssetDetail extends Component
{
    use HandlesAssetRules;

    #[Locked]
    public int $serialId;

    /** '', 'profil', 'hilang' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['acquired_at' => '', 'meter_unit' => 'none', 'meter_total' => '', 'expected_life_days' => '', 'expected_life_hours' => '', 'reason' => '', 'notes' => ''];

    public function mount(Serial $serial): void
    {
        abort_unless(app(AssetQuery::class)->for(auth()->user())->whereKey($serial->id)->exists(), 404);
        $this->authorize('view', $serial);

        $this->serialId = (int) $serial->id;
    }

    public function render(): View
    {
        $aset = $this->aset();

        return view('livewire.asset.asset-detail', [
            'aset' => $aset,
            'saldo' => StockBalance::query()->with('bin:id,code,bin_type,warehouse_id,project_id', 'bin.warehouse:id,code')
                ->where('serial_id', $aset->id)->where('qty_base', '>', 0)->first(),
            'handovers' => $aset->handovers()->with('project:id,code,name', 'shipment:id,number', 'goodsReturn:id,number', 'lostReason:id,label')->orderByDesc('id')->get(),
            'inspections' => $aset->inspections()->with('inspector:id,name', 'handover:id,number')->orderByDesc('id')->get(),
            'movements' => StockMovement::query()->with('fromBin:id,code', 'toBin:id,code')->where('serial_id', $aset->id)->orderByDesc('id')->limit(20)->get(),
            'adjustments' => StockAdjustment::query()->withoutGlobalScopes()->where('origin', 'asset_lost')
                ->whereHas('lines', fn ($q) => $q->where('serial_id', $aset->id))->orderByDesc('id')->get(['id', 'number', 'status']),
            'units' => MeterUnit::options(),
            'alasanHilang' => $this->pilihanAlasan(ReasonContext::Lost),
            'ambang' => Serial::lifeAlertPercent(),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'asset')
                ->where('subject_type', $aset->getMorphClass())->where('subject_id', $aset->id)
                ->latest('id')->limit(20)->get(),
        ]);
    }

    public function mintaDialog(string $dialog): void
    {
        $aset = $this->aset();
        $this->authorize($dialog === 'hilang' ? 'markLost' : 'update', $aset);

        $this->dialog = $dialog;
        $this->form = [
            'acquired_at' => $aset->acquired_at?->toDateString() ?? '',
            'meter_unit' => $aset->meter_unit?->value ?? 'none',
            'meter_total' => (string) (float) $aset->meter_total,
            'expected_life_days' => (string) ($aset->expected_life_days ?? ''),
            'expected_life_hours' => $aset->expected_life_hours !== null ? (string) (float) $aset->expected_life_hours : '',
            'reason' => '',
            'notes' => '',
        ];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function simpanProfil(UpdateAssetProfile $action): void
    {
        $aset = $this->aset();
        $this->authorize('update', $aset);

        if ($this->jalankan(fn () => $action->handle($aset, $this->form, auth()->user()))) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Profil aset disimpan.'));
        }
    }

    public function tandaiHilang(MarkAssetLost $action): void
    {
        $aset = $this->aset();
        $this->authorize('markLost', $aset);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $aset,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Lost),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Aset ditandai hilang; penyesuaian stok menunggu approval.'));
        }
    }

    public function ditemukan(MarkAssetLost $action): void
    {
        $aset = $this->aset();
        $this->authorize('found', $aset);

        if ($this->jalankan(fn () => $action->found($aset, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tanda hilang dibatalkan.'));
        }
    }

    private function aset(): Serial
    {
        return Serial::query()->with('item:id,code,name,ownership_model', 'currentProject:id,code,name')->findOrFail($this->serialId);
    }
}

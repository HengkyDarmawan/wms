<?php

declare(strict_types=1);

namespace App\Domain\Asset\Livewire;

use App\Domain\Asset\Actions\UpdateAssetHandover;
use App\Domain\Asset\Enums\ConditionGrade;
use App\Domain\Asset\Livewire\Concerns\HandlesAssetRules;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Shared\Attachments\Models\Attachment;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 25-aset §6 — detail AST: data serah terima keluar (dilengkapi lewat
 * `asset.manage`), hari & meter pakai, form pemeriksaan (POST dengan foto,
 * `asset.inspect`), hasil pemeriksaan, riwayat, cetak BA serah terima.
 */
class HandoverDetail extends Component
{
    use HandlesAssetRules;

    #[Locked]
    public int $handoverId;

    public bool $ubah = false;

    /** @var array<string, string> */
    public array $form = ['due_return_date' => '', 'meter_out' => '', 'condition_out' => '', 'notes' => ''];

    public function mount(AssetHandover $assetHandover): void
    {
        $this->authorize('view', $assetHandover);

        $this->handoverId = (int) $assetHandover->id;
    }

    public function render(): View
    {
        $ast = $this->ast();

        return view('livewire.asset.handover-detail', [
            'ast' => $ast,
            'inspections' => $ast->inspections()->with('inspector:id,name')->orderByDesc('id')->get(),
            'fotoKeluar' => Attachment::query()->with('uploader:id,name')->whereKey($ast->photo_out_id ?? 0)->get(),
            'grades' => ConditionGrade::options(),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'asset')
                ->where('subject_type', $ast->getMorphClass())->where('subject_id', $ast->id)
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function mulaiUbah(): void
    {
        $ast = $this->ast();
        $this->authorize('update', $ast);

        $this->ubah = true;
        $this->form = [
            'due_return_date' => $ast->due_return_date?->toDateString() ?? '',
            'meter_out' => $ast->meter_out !== null ? (string) (float) $ast->meter_out : '',
            'condition_out' => (string) $ast->condition_out,
            'notes' => (string) $ast->notes,
        ];
        $this->resetValidation();
    }

    public function batalUbah(): void
    {
        $this->ubah = false;
        $this->resetValidation();
    }

    public function simpan(UpdateAssetHandover $action): void
    {
        $ast = $this->ast();
        $this->authorize('update', $ast);

        if ($this->jalankan(fn () => $action->handle($ast, $this->form, auth()->user()))) {
            $this->ubah = false;
            $this->dispatch('pesan', teks: __('Serah terima disimpan.'));
        }
    }

    private function ast(): AssetHandover
    {
        return AssetHandover::query()
            ->with('serial', 'item:id,code,name', 'project:id,code,name', 'warehouse:id,code,name', 'shipment:id,number', 'goodsReturn:id,number',
                'lostReason:id,label', 'adjustment:id,number,status', 'updater:id,name')
            ->findOrFail($this->handoverId);
    }
}

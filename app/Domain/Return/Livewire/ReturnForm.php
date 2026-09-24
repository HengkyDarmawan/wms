<?php

declare(strict_types=1);

namespace App\Domain\Return\Livewire;

use App\Domain\Master\Models\Project;
use App\Domain\Return\Actions\CreateGoodsReturn;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Support\ReturnableStock;
use App\Domain\Transfer\Livewire\Concerns\HandlesTransferRules;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 22-retur-transfer §6 — RET baru: proyek `*`, gudang tujuan `*`,
 * pengangkutan (diantar sendiri / SJ balik dari Gudang Site), lalu jumlah per
 * barang yang boleh diretur (A-110). Klien hanya melihat barang Terkirim ke
 * Klien, aset di proyeknya, dan barang rusak yang ditinggal ekspedisi (BR-RET-05).
 */
class ReturnForm extends Component
{
    use HandlesTransferRules;

    /** @var array<string, string> */
    public array $form = ['project_id' => '', 'to_warehouse_id' => '', 'self_delivered' => '1', 'notes' => ''];

    /** @var array<string, string> kunci calon (':' → '_') => jumlah */
    public array $qty = [];

    #[Locked]
    public bool $portal = false;

    public function mount(): void
    {
        $this->authorize('create', GoodsReturn::class);

        $this->portal = request()->routeIs('portal.*');

        $proyek = $this->proyek()->first();
        $this->form['project_id'] = $proyek === null ? '' : (string) $proyek->id;
    }

    public function updatedFormProjectId(): void
    {
        $this->qty = [];
    }

    public function simpan(CreateGoodsReturn $action): void
    {
        $this->authorize('create', GoodsReturn::class);

        $this->validate([
            'form.project_id' => ['required'],
            'form.to_warehouse_id' => ['required'],
        ], attributes: ['form.project_id' => __('Proyek'), 'form.to_warehouse_id' => __('Gudang tujuan')]);

        $baris = [];

        foreach ($this->qty as $kunci => $jumlah) {
            if ((float) $jumlah > 0) {
                $baris[] = ['key' => str_replace('_', ':', (string) $kunci), 'qty_base' => $jumlah];
            }
        }

        $ret = null;

        $ok = $this->jalankan(function () use ($action, $baris, &$ret) {
            $ret = $action->handle($this->form, $baris, auth()->user());
        });

        if ($ok && $ret !== null) {
            $this->redirectRoute($this->portal ? 'portal.returns.show' : 'returns.show', $ret, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.return.return-form', [
            'projects' => $this->proyek(),
            'warehouses' => $this->gudangTujuan(),
            'calon' => $this->calon(),
            'rute' => $this->portal ? 'portal.returns' : 'returns',
        ]);
    }

    /**
     * Gudang penerima retur: gudang company aktif, bukan Gudang Site. Klien hanya
     * ditawari gudang yang pernah mengirim ke proyeknya — ia tidak melihat gudang
     * company lain (BR-PRJ-06).
     *
     * @return Collection<int, Warehouse>
     */
    private function gudangTujuan(): Collection
    {
        $semua = Warehouse::query()->withoutGlobalScopes()->with('type')->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'warehouse_type_id'])->reject(fn (Warehouse $w) => $w->isSite())->values();

        if (auth()->user()?->client_id === null || (int) $this->form['project_id'] === 0) {
            return $semua;
        }

        $pengirim = \App\Domain\Shipment\Models\Shipment::query()->withoutGlobalScopes()
            ->where('destination_project_id', (int) $this->form['project_id'])
            ->pluck('warehouse_id')->unique()->all();

        return $semua->filter(fn (Warehouse $w) => in_array((int) $w->id, array_map('intval', $pengirim), true))->values();
    }

    /** @return Collection<int, Project> proyek aktif dalam cakupan pengguna */
    private function proyek(): Collection
    {
        return Project::query()->active()->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** @return Collection<string, array<string, mixed>> */
    private function calon(): Collection
    {
        $proyek = (int) $this->form['project_id'] > 0
            ? Project::query()->active()->find((int) $this->form['project_id'])
            : null;

        if ($proyek === null) {
            return collect();
        }

        $klien = auth()->user()?->client_id !== null;

        return app(ReturnableStock::class)->forProject($proyek)
            ->filter(fn (array $c) => ! $klien || $c['source']->allowedForClient())
            ->mapWithKeys(fn (array $c) => [str_replace(':', '_', $c['key']) => $c])
            ->sortBy(fn (array $c) => array_search($c['source'], ReturnSource::cases(), true).'|'.$c['item_code']);
    }
}

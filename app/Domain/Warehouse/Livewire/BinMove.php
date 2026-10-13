<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Support\QtyFormat;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Actions\MoveBinStock;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar **Pindah bin** (A-416, 12-warehouse §6.10): bin asal → barang & jumlah
 * → bin tujuan → Simpan. Bin boleh dipilih atau dipindai (QR tautan Isi Bin /
 * kode pendek, A-373). Aksi: {@see MoveBinStock}.
 */
class BinMove extends Component
{
    use HandlesWarehouseRules;

    #[Url(as: 'gudang', except: '')]
    public string $gudangId = '';

    #[Url(as: 'asal', except: '')]
    public string $asalId = '';

    /** @var array{balance_id: string, qty: string, to_bin_id: string, notes: string} */
    public array $form = ['balance_id' => '', 'qty' => '', 'to_bin_id' => '', 'notes' => ''];

    public string $pindaiAsal = '';

    public string $pindaiTujuan = '';

    public string $terakhir = '';

    /** @var array<int, string> */
    public array $peringatan = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('putaway.complete'), 403);

        $gudang = $this->gudangList();

        if ($this->gudangId === '' && $gudang->count() === 1) {
            $this->gudangId = (string) $gudang->first()->id;
        }
    }

    public function updatedGudangId(): void
    {
        $this->asalId = '';
        $this->kosongkan();
    }

    public function updatedAsalId(): void
    {
        $this->kosongkan();
    }

    public function updatedFormBalanceId(): void
    {
        $saldo = $this->saldoAsal()->firstWhere('id', (int) $this->form['balance_id']);
        // Bawaan: pindah semua isi baris itu; boleh dikurangi.
        $this->form['qty'] = $saldo !== null ? (string) round((float) $saldo->qty_base, 4) : '';
        $this->resetErrorBag();
    }

    /** A-373: QR bin (tautan Isi Bin) atau kode pendek yang diketik. */
    public function pindai(string $peran): void
    {
        $gudang = $this->gudang();
        $kode = $peran === 'asal' ? $this->pindaiAsal : $this->pindaiTujuan;

        if ($gudang === null || trim($kode) === '') {
            return;
        }

        $bin = BinCode::cocokkan($kode, app(PutawaySuggester::class)->storageBins($gudang));
        $field = $peran === 'asal' ? 'pindaiAsal' : 'pindaiTujuan';

        if ($bin === null) {
            $this->addError($field, __('Bin ":kode" bukan bin penyimpanan gudang ini.', ['kode' => BinCode::dariPindai($kode)]));

            return;
        }

        $this->resetErrorBag($field);

        if ($peran === 'asal') {
            $this->asalId = (string) $bin->id;
            $this->kosongkan();
        } else {
            $this->form['to_bin_id'] = (string) $bin->id;
        }

        $this->{$field} = '';
    }

    public function simpan(MoveBinStock $aksi): void
    {
        $gudang = $this->gudang();

        if ($gudang === null) {
            $this->addError('gudangId', __('Pilih gudang dulu.'));

            return;
        }

        $this->terakhir = '';
        $this->peringatan = [];
        $gerak = null;

        $ok = $this->jalankan(function () use ($aksi, $gudang, &$gerak) {
            $gerak = $aksi->handle($gudang, $this->form, auth()->user());
        });

        if ($ok && $gerak instanceof StockMovement) {
            $gerak->loadMissing('item.baseUom', 'fromBin', 'toBin');
            $this->terakhir = __('Dipindah: :qty :uom :item dari :asal ke :tujuan.', [
                'qty' => QtyFormat::number($gerak->qty_base),
                'uom' => $gerak->item->baseUom?->code,
                'item' => $gerak->item->code,
                'asal' => BinCode::pendekUntuk($gerak->fromBin),
                'tujuan' => BinCode::pendekUntuk($gerak->toBin),
            ]);
            $this->peringatan = $aksi->warnings();
            $this->kosongkan();
        }
    }

    public function render(): View
    {
        $gudang = $this->gudang();
        $bins = $gudang !== null ? app(PutawaySuggester::class)->storageBins($gudang) : collect();
        $pendek = BinCode::pendekBanyak($bins);
        $berisi = $gudang !== null
            ? StockBalance::query()->withoutGlobalScopes()->nonZero()->whereIn('bin_id', $bins->pluck('id'))->distinct()->pluck('bin_id')->map(fn ($v) => (int) $v)->all()
            : [];
        $saldo = $this->saldoAsal();
        $terpilih = $saldo->firstWhere('id', (int) $this->form['balance_id']);

        return view('livewire.warehouse.bin-move', [
            'gudangList' => $this->gudangList(),
            'opsiAsal' => $bins->filter(fn (Bin $b) => in_array((int) $b->id, $berisi, true))
                ->map(fn (Bin $b) => ['value' => $b->id, 'text' => $pendek[$b->id] ?? $b->code, 'sub' => $b->code])->values()->all(),
            'opsiTujuan' => $bins->reject(fn (Bin $b) => (string) $b->id === $this->asalId)
                ->map(fn (Bin $b) => ['value' => $b->id, 'text' => $pendek[$b->id] ?? $b->code, 'sub' => $b->code])->values()->all(),
            'saldo' => $saldo,
            'terpilih' => $terpilih,
            'utuh' => $terpilih !== null && ($terpilih->serial_id !== null || $terpilih->piece_id !== null),
            'riwayat' => $gudang !== null ? $this->riwayat($gudang) : collect(),
        ]);
    }

    private function kosongkan(): void
    {
        $this->form = ['balance_id' => '', 'qty' => '', 'to_bin_id' => '', 'notes' => ''];
        $this->resetErrorBag();
    }

    private function gudang(): ?Warehouse
    {
        return $this->gudangId !== '' ? $this->gudangList()->firstWhere('id', (int) $this->gudangId) : null;
    }

    /** @return Collection<int, Warehouse> gudang dalam cakupan (scope global Warehouse) */
    private function gudangList(): Collection
    {
        return once(fn () => Warehouse::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']));
    }

    /** @return Collection<int, StockBalance> isi bin asal (saldo > 0) */
    private function saldoAsal(): Collection
    {
        if ($this->asalId === '' || $this->gudang() === null) {
            return collect();
        }

        return StockBalance::query()->withoutGlobalScopes()->nonZero()
            ->where('bin_id', (int) $this->asalId)
            ->whereHas('bin', fn ($q) => $q->where('warehouse_id', (int) $this->gudangId))
            ->with('item:id,code,name,base_uom_id,tracking_mode', 'item.baseUom:id,code', 'lot:id,lot_no,expiry_date', 'serial:id,serial_no', 'piece')
            ->orderBy('item_id')->orderBy('id')->get();
    }

    /** @return Collection<int, StockMovement> 10 pindah bin terakhir di gudang ini */
    private function riwayat(Warehouse $gudang): Collection
    {
        return StockMovement::query()->where('document_type', 'bin_move')
            ->whereHas('toBin', fn ($q) => $q->withoutGlobalScopes()->where('warehouse_id', $gudang->id))
            ->with('item:id,code,base_uom_id', 'item.baseUom:id,code', 'fromBin:id,code', 'toBin:id,code', 'performer:id,name')
            ->latest('id')->limit(10)->get();
    }
}

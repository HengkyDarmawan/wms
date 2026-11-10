<?php

declare(strict_types=1);

namespace App\Domain\Label\Livewire;

use App\Domain\Label\Actions\SaveRfidTag;
use App\Domain\Label\Models\RfidTag;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar **Tag RFID** `/rfid-tags` (A-437): daftarkan tag ke barang/serial/
 * potongan/bin dengan reader keyboard wedge (atau ketik EPC), nonaktifkan, dan
 * uji pindai. Hanya bila fitur `rfid` company menyala.
 */
class RfidTagManager extends Component
{
    /** @var array{epc: string, target_type: string, target_code: string, notes: string} */
    public array $form = ['epc' => '', 'target_type' => 'item', 'target_code' => '', 'notes' => ''];

    public string $cari = '';

    public string $uji = '';

    public string $hasilUji = '';

    public function mount(): void
    {
        abort_unless(self::tersedia(), 404);
        abort_unless(auth()->user()->hasPermission('rfid.manage'), 403);
    }

    public static function tersedia(): bool
    {
        return (bool) tenant()?->isFeatureEnabled('rfid');
    }

    public function render(): View
    {
        $kata = RfidTag::normalize($this->cari);
        $tags = RfidTag::query()->when($kata !== '', fn ($q) => $q->where('epc', 'like', '%'.$kata.'%'))->latest('id')->limit(100)->get();

        return view('livewire.label.rfid-tag-manager', [
            'tags' => $tags,
            'target' => RfidTag::TARGET,
            'kode' => $tags->mapWithKeys(fn (RfidTag $t) => [$t->id => $t->kodeTarget()]),
        ]);
    }

    public function simpan(SaveRfidTag $action): void
    {
        try {
            $action->handle(null, $this->form, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($v, $k) => ['form.'.$k => $v])->all());

            return;
        }

        $this->resetErrorBag();
        // Siap untuk tag berikutnya dengan target yang sama (daftar beruntun).
        $this->form['epc'] = '';
        $this->dispatch('pesan', teks: __('Tag RFID terdaftar.'));
    }

    public function aktifkan(int $id, bool $aktif, SaveRfidTag $action): void
    {
        $t = RfidTag::query()->findOrFail($id);
        $action->handle($t, ['epc' => $t->epc, 'target_type' => $t->target_type, 'target_code' => $t->kodeTarget(), 'notes' => $t->notes, 'is_active' => $aktif], auth()->user());
        $this->dispatch('pesan', teks: $aktif ? __('Tag diaktifkan.') : __('Tag dinonaktifkan.'));
    }

    /** Uji pindai: apa arti EPC/kode ini bagi layar pindai barang dan bin. */
    public function ujiPindai(): void
    {
        $tag = RfidTag::aktif($this->uji);
        $barang = collect(ScanCode::resolve($this->uji))->map(fn ($r) => Item::query()->whereKey($r['item_id'])->value('code'))->filter()->unique()->implode(', ');
        $bin = BinCode::dariPindai($this->uji);

        $this->hasilUji = $tag === null
            ? __('Bukan tag RFID terdaftar. Dibaca sebagai kode biasa: barang :b.', ['b' => $barang ?: '-'])
            : __('Tag :epc → :jenis :kode', ['epc' => $tag->epc, 'jenis' => RfidTag::TARGET[$tag->target_type], 'kode' => $tag->target_type === 'bin' ? $bin : ($barang ?: $tag->kodeTarget())]);
        $this->uji = '';
    }
}

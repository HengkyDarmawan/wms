<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Conversion\Actions\SaveConversionRecipe;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionRecipe;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar **Resep konversi** `/conversion-recipes` (BR-CNV-06, A-431): daftar +
 * form satu kartu. Lihat: `conversion.view`; ubah: `conversion.recipe`.
 */
class RecipeManager extends Component
{
    use CariPilihan;

    public string $cari = '';

    /** 0 = form tertutup, -1 = resep baru, >0 = id resep yang diubah. */
    public int $ubah = 0;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $galat = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Conversion::class);
    }

    public function render(): View
    {
        $kata = trim($this->cari);
        $resep = ConversionRecipe::query()
            ->when($kata !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$kata.'%')->orWhere('name', 'like', '%'.$kata.'%')))
            ->orderByDesc('is_active')->orderBy('code')->get();

        $item = SumberPilihan::item();

        return view('livewire.conversion.recipe-manager', [
            'resep' => $resep,
            'dipakai' => Conversion::query()->whereNotNull('recipe_id')->groupBy('recipe_id')->selectRaw('recipe_id, count(*) as n')->pluck('n', 'recipe_id'),
            'jenis' => ConversionType::options(),
            'bisaUbah' => auth()->user()->hasPermission('conversion.recipe'),
            'opsiInput' => $this->ubah !== 0 ? $item->awalDengan($this->form['input_item_id'] ?? '') : [],
            'opsiPotong' => $this->ubah !== 0 ? $item->awalPerBaris(array_column($this->form['potong'] ?? [], 'item_id')) : [],
            'opsiHasil' => $this->ubah !== 0 ? $item->awalPerBaris(array_column($this->form['hasil'] ?? [], 'item_id')) : [],
        ]);
    }

    public function bukaForm(int $id = -1): void
    {
        abort_unless(auth()->user()->hasPermission('conversion.recipe'), 403);
        $r = $id > 0 ? ConversionRecipe::query()->findOrFail($id) : null;
        $this->ubah = $id;
        $this->galat = '';
        $this->resetValidation();
        $this->form = [
            'code' => (string) ($r?->code ?? ''),
            'name' => (string) ($r?->name ?? ''),
            'conversion_type' => $r?->type()?->value ?? ConversionType::Cut->value,
            'input_item_id' => (string) ($r?->definition['input_item_id'] ?? ''),
            'potong' => array_map(fn ($p) => ['id' => (string) Str::uuid(), 'item_id' => (string) ($p['item_id'] ?? ''), 'length' => (string) $p['length'], 'count' => (string) $p['count']], $r?->potong() ?? []) ?: [$this->barisPotong()],
            'hasil' => array_map(fn ($h) => ['id' => (string) Str::uuid(), 'kind' => $h['kind'], 'item_id' => (string) $h['item_id'], 'qty' => (string) $h['qty']], $r?->hasil() ?? []) ?: [$this->barisHasil()],
            'is_active' => $r?->is_active ?? true,
        ];
    }

    public function tutupForm(): void
    {
        $this->ubah = 0;
        $this->form = [];
        $this->resetValidation();
    }

    public function tambahPotong(): void
    {
        $this->form['potong'][] = $this->barisPotong();
    }

    public function hapusPotong(int $i): void
    {
        unset($this->form['potong'][$i]);
        $this->form['potong'] = array_values($this->form['potong']) ?: [$this->barisPotong()];
    }

    public function tambahHasil(string $kind = 'output'): void
    {
        $this->form['hasil'][] = $this->barisHasil($kind);
    }

    public function hapusHasil(int $i): void
    {
        unset($this->form['hasil'][$i]);
        $this->form['hasil'] = array_values($this->form['hasil']) ?: [$this->barisHasil()];
    }

    public function simpan(SaveConversionRecipe $action): void
    {
        $this->galat = '';
        $this->resetValidation();
        $r = $this->ubah > 0 ? ConversionRecipe::query()->findOrFail($this->ubah) : null;

        try {
            $action->handle($r, $this->form, auth()->user());
        } catch (ConversionRuleException $e) {
            $this->galat = $e->getMessage();
            foreach ($e->fieldErrors as $kunci => $pesan) {
                $this->addError('form.'.$kunci, $pesan);
            }

            return;
        }

        $this->tutupForm();
        $this->dispatch('pesan', teks: __('Resep konversi disimpan.'));
    }

    public function aktifkan(int $id, bool $aktif, SaveConversionRecipe $action): void
    {
        $r = ConversionRecipe::query()->findOrFail($id);

        try {
            $action->handle($r, ['code' => $r->code, 'name' => $r->name, 'conversion_type' => $r->type()?->value, 'input_item_id' => $r->definition['input_item_id'] ?? null,
                'potong' => $r->potong(), 'hasil' => $r->hasil(), 'is_active' => $aktif], auth()->user());
        } catch (ConversionRuleException $e) {
            $this->galat = $e->getMessage();

            return;
        }

        $this->dispatch('pesan', teks: $aktif ? __('Resep diaktifkan.') : __('Resep dinonaktifkan.'));
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        return auth()->user()?->hasPermission('conversion.recipe') && str_ends_with($model, 'item_id') ? SumberPilihan::item() : null;
    }

    /** @return array<string, string> */
    private function barisPotong(): array
    {
        return ['id' => (string) Str::uuid(), 'item_id' => '', 'length' => '', 'count' => '1'];
    }

    /** @return array<string, string> */
    private function barisHasil(string $kind = 'output'): array
    {
        return ['id' => (string) Str::uuid(), 'kind' => $kind === 'waste' ? 'waste' : 'output', 'item_id' => '', 'qty' => ''];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\PackagingSentence;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Master\Support\StockFeatures;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 11-master §6 — form item.
 *
 * A-283: pengguna memilih satu *Jenis barang*; mode pelacakan, kepemilikan,
 * sifat baris, kedaluwarsa, dan strategi pengambilan diturunkan oleh
 * {@see SaveItem}. Item lama dengan kombinasi lain tampil sebagai *Jenis
 * khusus*: pengaturan teknisnya hanya bisa dilihat. Isian potong dan vendor
 * tetap tidak lagi ada di form; datanya tidak disentuh (P-03).
 */
class ItemForm extends Component
{
    use HandlesMasterRules;

    /** Kolom teknis yang dikirim apa adanya untuk item Jenis khusus. */
    private const KOLOM_TEKNIS = [
        'tracking_mode', 'ownership_model', 'default_line_ownership', 'has_expiry',
        'removal_strategy', 'is_cuttable', 'min_offcut_length', 'kerf',
    ];

    #[Locked]
    public ?int $itemId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'name' => '',
        'item_category_id' => '',
        'status' => 'active',
        'item_kind' => 'standard',
        'base_uom_id' => '',
        'requires_qc' => false,
        'reorder_point' => '',
        'min_stock' => '',
        'barcode' => '',
        'weight' => '',
        'weight_uom_id' => '',
    ];

    /**
     * A-355: satu baris = kalimat "1 [uom_id] berisi [content_qty] [content_uom_id]";
     * `content_uom_id` '' = satuan dasar.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $conversions = [];

    /**
     * A-355: isi tersimpan per kemasan (satuan dasar), untuk memberi tahu bila
     * hitung ulang kemasan bertingkat mengubahnya.
     *
     * @var array<int, float>
     */
    #[Locked]
    public array $isiTersimpan = [];

    /**
     * A-294: satuan kemasan yang dimuat form; hanya ini yang dinonaktifkan bila dilepas,
     * supaya kemasan yang diingat dari GRN saat form terbuka tidak ikut mati.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $kemasanDimuat = [];

    public bool $baseUomLocked = false;

    /** BR-MST-02 (A-356): alasan kunci satuan dasar, tampil di bawah isiannya. */
    #[Locked]
    public ?string $alasanKunciDasar = null;

    /** Item lama di luar tiga jenis barang (A-283). */
    #[Locked]
    public bool $jenisKhusus = false;

    /** BR-MST-06: jenis tidak bisa diganti setelah ada pergerakan stok. */
    #[Locked]
    public bool $jenisTerkunci = false;

    /** Tab bagian opsional yang terbuka: stok | konversi. */
    public string $tabTambahan = 'stok';

    public function mount(?Item $item = null): void
    {
        if ($item !== null && $item->exists) {
            $this->authorize('update', $item);
            $this->isiDari($item);

            return;
        }

        $this->authorize('create', Item::class);
    }

    private function isiDari(Item $item): void
    {
        $jenis = ItemKind::fromItem($item);

        $this->itemId = $item->id;
        $this->alasanKunciDasar = $item->baseUomLockReason();
        $this->baseUomLocked = $this->alasanKunciDasar !== null;
        $this->jenisKhusus = $jenis === null;
        $this->jenisTerkunci = $item->hasStockMovements();

        $this->form = [
            'code' => (string) $item->code,
            'name' => (string) $item->name,
            'item_category_id' => (string) $item->item_category_id,
            'status' => $item->status->value,
            'item_kind' => $jenis?->value ?? '',
            'base_uom_id' => (string) $item->base_uom_id,
            'requires_qc' => (bool) $item->requires_qc,
            'reorder_point' => (string) $item->reorder_point,
            'min_stock' => (string) $item->min_stock,
            'barcode' => (string) $item->barcode,
            'weight' => (string) $item->weight,
            'weight_uom_id' => (string) $item->weight_uom_id,
        ];

        // Kemasan nonaktif tidak dimuat: menyimpan ulang tidak boleh menghidupkannya lagi.
        // Data lama tanpa satuan isi tampil sebagai "berisi N <satuan dasar>" (A-355).
        $kemasan = $item->activeConversions()->get();

        $this->conversions = $kemasan
            ->sortBy('qty_base')
            ->map(fn ($k) => [
                'uom_id' => (string) $k->uom_id,
                'content_qty' => rtrim(rtrim(number_format((float) ($k->content_qty ?? $k->qty_base), 4, ',', ''), '0'), ','),
                'content_uom_id' => $k->content_uom_id === null ? '' : (string) $k->content_uom_id,
                'is_nominal_piece' => (bool) $k->is_nominal_piece,
            ])
            ->values()
            ->all();

        $this->isiTersimpan = $kemasan->mapWithKeys(fn ($k) => [(int) $k->uom_id => (float) $k->qty_base])->all();
        $this->kemasanDimuat = array_map(fn (array $k) => (int) $k['uom_id'], $this->conversions);
    }

    public function updatedTabTambahan(string $nilai): void
    {
        if (! in_array($nilai, ['stok', 'konversi'], true)) {
            $this->tabTambahan = 'stok';
        }
    }

    public function tambahKonversi(): void
    {
        $this->conversions[] = ['uom_id' => '', 'content_qty' => '', 'content_uom_id' => '', 'is_nominal_piece' => false];
    }

    public function hapusKonversi(int $index): void
    {
        unset($this->conversions[$index]);
        $this->conversions = array_values($this->conversions);
    }

    public function simpan(SaveItem $action): void
    {
        $item = $this->itemId === null ? null : Item::findOrFail($this->itemId);

        $this->authorize($item === null ? 'create' : 'update', $item ?? Item::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:40'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.base_uom_id' => ['required'],
            'form.item_kind' => $this->jenisKhusus
                ? ['nullable']
                : ['required', Rule::in(array_keys($this->pilihanJenis($item)))],
            'form.status' => ['required', Rule::enum(ItemStatus::class)],
            'form.reorder_point' => ['nullable', 'numeric', 'min:0'],
            'form.min_stock' => ['nullable', 'numeric', 'min:0'],
            'form.weight' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'form.code' => __('Kode item'),
            'form.name' => __('Nama item'),
            'form.base_uom_id' => __('Satuan dasar'),
            'form.item_kind' => __('Jenis barang'),
        ]);

        $data = $this->form + ['conversion_uoms_loaded' => $this->kemasanDimuat];

        if ($this->jenisKhusus && $item !== null) {
            // Jenis khusus: pengaturan teknis dibaca ulang dari database, bukan dari layar.
            unset($data['item_kind']);

            foreach (self::KOLOM_TEKNIS as $kolom) {
                $nilai = $item->getAttribute($kolom);
                $data[$kolom] = $nilai instanceof BackedEnum ? $nilai->value : $nilai;
            }
        }

        // A-284: saklar QC mati → isian disembunyikan, nilai item tidak diubah.
        if (! StockFeatures::qc()) {
            unset($data['requires_qc']);
        }

        $tersimpan = null;

        $berhasil = $this->jalankan(function () use ($action, $item, $data, &$tersimpan): void {
            $tersimpan = $action->handle(
                $item,
                $data,
                array_values($this->conversions),
                null, // vendor tetap tidak lagi diubah dari form (A-283); data lama dipertahankan
                auth()->user(),
            );
        });

        if (! $berhasil) {
            return;
        }

        session()->flash('pesan', __('Item disimpan.'));

        $this->redirectRoute('items.show', $tersimpan, navigate: true);
    }

    /** @return array<string, ItemKind> */
    private function pilihanJenis(?Item $item): array
    {
        return StockFeatures::kindOptions($item === null ? null : ItemKind::fromItem($item));
    }

    /**
     * A-355: hasil kalimat kemasan untuk layar — "= 480 BOX (40 × 12)" per
     * baris, contoh penerimaan, dan kemasan tersimpan yang ikut berubah.
     *
     * @return array{hasil: array<int, array{hasil: string, rincian: ?string}>, contoh: ?string, berubah: array<int, string>}
     */
    private function kalimatKemasan(?Item $item, Collection $uoms): array
    {
        $dasarId = (int) ($this->form['base_uom_id'] ?: 0);

        if ($dasarId === 0 || $this->conversions === []) {
            return ['hasil' => [], 'contoh' => null, 'berubah' => []];
        }

        $kalimat = PackagingSentence::resolve($this->conversions, $dasarId, $item?->tracksPiece() ?? false, $this->isiTersimpan);
        $kode = $uoms->pluck('code', 'id');
        $dasar = (string) ($kode[$dasarId] ?? '');

        $terbesar = collect($kalimat['rows'])->reject(fn (array $k) => $k['is_nominal_piece'])->sortByDesc('qty_base')->first();
        $contoh = $terbesar === null ? null : __('Contoh: terima :jumlah → stok bertambah :hasil.', [
            'jumlah' => QtyFormat::withUnit(5, $kode[$terbesar['uom_id']] ?? ''),
            'hasil' => QtyFormat::withUnit(5 * $terbesar['qty_base'], $dasar),
        ]);

        $berubah = collect($kalimat['rows'])
            ->filter(fn (array $k) => isset($this->isiTersimpan[$k['uom_id']]) && abs($this->isiTersimpan[$k['uom_id']] - $k['qty_base']) > 0.00005)
            ->map(fn (array $k) => __(':kemasan berubah dari :lama menjadi :baru.', [
                'kemasan' => $kode[$k['uom_id']] ?? '',
                'lama' => QtyFormat::withUnit($this->isiTersimpan[$k['uom_id']], $dasar),
                'baru' => QtyFormat::withUnit($k['qty_base'], $dasar),
            ]))
            ->values()->all();

        return ['hasil' => $kalimat['results'], 'contoh' => $contoh, 'berubah' => $berubah];
    }

    public function render(): View
    {
        $item = $this->itemId === null ? null : Item::query()->with('category')->find($this->itemId);
        $uoms = Uom::query()->active()->with('category:id,name')->orderBy('code')->get();

        return view('livewire.master.item-form', [
            'kemasan' => $this->kalimatKemasan($item, $uoms),
            'categories' => ItemCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'uoms' => $uoms,
            'statuses' => ItemStatus::options(),
            'jenisOpsi' => $this->pilihanJenis($item),
            'jenisTerpilih' => ItemKind::tryFrom((string) $this->form['item_kind']),
            'itemKhusus' => $this->jenisKhusus ? $item : null,
            'qcAktif' => StockFeatures::qc(),
            'pieceAktif' => StockFeatures::piece(),
        ]);
    }
}

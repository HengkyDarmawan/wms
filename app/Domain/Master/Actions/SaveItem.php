<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\EnumInput;
use App\Domain\Master\Support\MasterCode;
use App\Domain\Master\Support\StockFeatures;
use App\Domain\Master\Support\TrackingCombination;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `item.create` / `item.update`.
 *
 * Menjaga seluruh aturan item dalam satu tempat: matriks kombinasi pelacakan
 * (BR-STK-08, BR-STK-09, BR-STK-11, BR-STK-12, BR-CNV-03), kunci satuan dasar
 * (BR-MST-02), dan kode huruf besar yang tidak bisa diubah (BR-MST-01).
 *
 * Konversi satuan khusus dan vendor tetap ikut disimpan bila dikirim, supaya
 * form item cukup satu kali simpan.
 */
class SaveItem
{
    public function __construct(private readonly TrackingCombination $combination) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $conversions
     * @param  array<int, array<string, mixed>>|null  $vendors  diabaikan sejak A-305 (vendor tetap tidak dipakai lagi)
     */
    public function handle(
        ?Item $item,
        array $attributes,
        ?array $conversions = null,
        ?array $vendors = null,
        ?User $actor = null,
    ): Item {
        $baru = $item === null || ! $item->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::fields(['name' => 'Nama item wajib diisi.'], 'BR-GEN-11');
        }

        $kode = MasterCode::resolve($item, (string) ($attributes['code'] ?? ''), 'item');

        $bentrok = Item::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($item->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::fields(['code' => 'Kode item "'.$kode.'" sudah dipakai.'], 'BR-MST-01');
        }

        // A-283: form & impor mengirim Jenis barang; kolom teknis diturunkan darinya.
        $jenis = EnumInput::optional(ItemKind::class, $attributes['item_kind'] ?? null, 'item_kind');

        if ($jenis !== null) {
            $attributes = $this->terapkanJenis($jenis, $baru ? null : $item, $attributes);
        }

        $pelacakan = EnumInput::required(TrackingMode::class, $attributes['tracking_mode'] ?? null, $item?->tracking_mode ?? TrackingMode::None, 'tracking_mode');
        $kepemilikan = EnumInput::required(OwnershipModel::class, $attributes['ownership_model'] ?? null, $item?->ownership_model ?? OwnershipModel::Consumable, 'ownership_model');
        $status = EnumInput::required(ItemStatus::class, $attributes['status'] ?? null, $item?->status ?? ItemStatus::Active, 'status');

        $strategi = EnumInput::optional(RemovalStrategy::class, $attributes['removal_strategy'] ?? null, 'removal_strategy');
        $barisKepemilikan = EnumInput::optional(LineOwnership::class, $attributes['default_line_ownership'] ?? null, 'default_line_ownership');

        $adaKedaluwarsa = (bool) ($attributes['has_expiry'] ?? $item?->has_expiry ?? false);
        $bisaDipotong = (bool) ($attributes['is_cuttable'] ?? $item?->is_cuttable ?? false);
        // Nilai lama dipertahankan bila field-nya tidak dikirim, supaya simpan
        // ulang dari layar lain tidak diam-diam menghapus aturan potong.
        $minOffcut = $this->angkaAtauNull($attributes['min_offcut_length'] ?? null)
            ?? ($item?->min_offcut_length === null ? null : (float) $item->min_offcut_length);

        $satuanDasar = $this->resolveBaseUom($item, $attributes['base_uom_id'] ?? null);

        $pelanggaran = $this->combination->violations(
            $pelacakan,
            $kepemilikan,
            $strategi,
            $adaKedaluwarsa,
            $bisaDipotong,
            $minOffcut,
            $satuanDasar->category?->isLength(),
        );

        if ($pelanggaran !== []) {
            throw MasterRuleException::fields($pelanggaran);
        }

        $this->pastikanSaklarAktif($baru ? null : $item, $pelacakan, $adaKedaluwarsa, $strategi);

        $data = [
            'code' => $kode,
            'name' => $nama,
            'item_category_id' => $this->idAtauNull($attributes['item_category_id'] ?? null),
            'status' => $status,
            'ownership_model' => $kepemilikan,
            'default_line_ownership' => $barisKepemilikan,
            'tracking_mode' => $pelacakan,
            'has_expiry' => $adaKedaluwarsa,
            'base_uom_id' => $satuanDasar->id,
            'is_cuttable' => $bisaDipotong,
            'min_offcut_length' => $bisaDipotong ? $minOffcut : null,
            'kerf' => $bisaDipotong
                ? ($this->angkaAtauNull($attributes['kerf'] ?? null)
                    ?? ($item?->kerf === null ? null : (float) $item->kerf))
                : null,
            'requires_qc' => (bool) ($attributes['requires_qc'] ?? $item?->requires_qc ?? false),
            'removal_strategy' => $strategi,
            'reorder_point' => $this->angkaAtauNull($attributes['reorder_point'] ?? null),
            'min_stock' => $this->angkaAtauNull($attributes['min_stock'] ?? null),
            'barcode' => $this->kosongJadiNull($attributes['barcode'] ?? null),
            'qr_payload' => $this->kosongJadiNull($attributes['qr_payload'] ?? null),
            'photo_path' => $this->kosongJadiNull($attributes['photo_path'] ?? null),
            'dimensions' => $attributes['dimensions'] ?? $item?->dimensions,
            'weight' => $this->angkaAtauNull($attributes['weight'] ?? null),
            'weight_uom_id' => $this->idAtauNull($attributes['weight_uom_id'] ?? null),
        ];

        // A-294: form item mengirim satuan kemasan yang dimuatnya; hanya kemasan itu yang boleh
        // dinonaktifkan, supaya kemasan yang baru diingat dari GRN tidak ikut mati.
        $kemasanDimuat = isset($attributes['conversion_uoms_loaded']) && is_array($attributes['conversion_uoms_loaded'])
            ? array_map('intval', $attributes['conversion_uoms_loaded'])
            : null;

        $item = DB::transaction(function () use ($item, $baru, $data, $conversions, $satuanDasar, $kemasanDimuat): Item {
            if ($baru) {
                $item = Item::create($data);
            } else {
                $item->fill($data)->save();
            }

            if ($conversions !== null) {
                $this->syncConversions($item, $conversions, $satuanDasar, $kemasanDimuat);
            }

            return $item;
        });

        activity('master')
            ->performedOn($item)
            ->causedBy($actor)
            ->withProperties(['tracking_mode' => $pelacakan->value, 'ownership_model' => $kepemilikan->value])
            ->log($baru ? 'Item dibuat' : 'Item diubah');

        return $item->refresh();
    }

    /**
     * A-283: Jenis barang → kolom teknis. Jenis terkunci setelah item punya
     * pergerakan stok (BR-MST-06); jenis yang saklarnya mati tidak bisa dipilih
     * untuk item baru atau sebagai jenis pengganti (BR-GEN-12). Strategi lama
     * dipertahankan bila masih sah untuk jenis itu.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function terapkanJenis(ItemKind $jenis, ?Item $item, array $attributes): array
    {
        $jenisLama = $item === null ? null : ItemKind::fromItem($item);

        if ($item !== null && $jenisLama !== $jenis && $item->hasStockMovements()) {
            throw MasterRuleException::fields(
                ['item_kind' => 'Jenis barang tidak bisa diubah karena item ini sudah punya pergerakan stok.'], // BR-MST-06
                'BR-MST-06',
            );
        }

        if ($jenisLama !== $jenis && ! StockFeatures::kindAvailable($jenis)) {
            throw MasterRuleException::fields(
                ['item_kind' => 'Jenis "'.$jenis->label().'" tidak aktif di company ini; nyalakan saklarnya di Pengaturan company.'], // BR-GEN-12
                'BR-GEN-12',
            );
        }

        $strategiLama = $item?->removal_strategy;
        $strategiSah = $strategiLama !== null
            && in_array($strategiLama, $jenis->trackingMode()->allowedRemovalStrategies(), true)
            && (! $strategiLama->requiresExpiry() || $jenis->hasExpiry());

        $strategi = match (true) {
            $item === null => $jenis->defaultStrategy(StockFeatures::fefo()),
            $strategiSah => $strategiLama,
            $strategiLama === null && $jenisLama === $jenis => null,
            default => $jenis->defaultStrategy(StockFeatures::fefo()),
        };

        return array_merge($attributes, [
            'tracking_mode' => $jenis->trackingMode()->value,
            'ownership_model' => $jenis->ownershipModel()->value,
            'has_expiry' => $jenis->hasExpiry(),
            'default_line_ownership' => null,
            'removal_strategy' => $strategi?->value,
        ]);
    }

    /**
     * BR-GEN-12: kombinasi teknis yang saklarnya mati ditolak untuk item baru
     * atau bila kombinasinya diubah; item lama yang tidak diubah tetap bisa
     * disimpan (P-03).
     */
    private function pastikanSaklarAktif(?Item $item, TrackingMode $pelacakan, bool $adaKedaluwarsa, ?RemovalStrategy $strategi): void
    {
        $berubah = $item === null
            || $item->tracking_mode !== $pelacakan
            || (bool) $item->has_expiry !== $adaKedaluwarsa
            || $item->removal_strategy !== $strategi;

        if (! $berubah) {
            return;
        }

        $mati = StockFeatures::inactiveFor($pelacakan, $adaKedaluwarsa, $strategi);

        if ($mati !== []) {
            throw MasterRuleException::fields(
                ['tracking_mode' => 'Fitur '.implode(', ', $mati).' dimatikan di Pengaturan company; item baru tidak bisa memakainya.'], // BR-GEN-12
                'BR-GEN-12',
            );
        }
    }

    /**
     * BR-MST-02: satuan dasar terkunci begitu item punya lot, serial, atau
     * potongan. Perubahan setelah itu ditolak, bukan diam-diam diabaikan.
     */
    private function resolveBaseUom(?Item $item, mixed $input): Uom
    {
        $idBaru = $this->idAtauNull($input);

        if ($item !== null && $item->exists) {
            if ($idBaru !== null && $idBaru !== (int) $item->base_uom_id && $item->baseUomIsLocked()) {
                throw MasterRuleException::fields(
                    ['base_uom_id' => 'Satuan dasar tidak bisa diubah karena item ini sudah punya lot, serial, atau potongan.'], // BR-MST-02
                    'BR-MST-02',
                );
            }

            $idBaru ??= (int) $item->base_uom_id;
        }

        if ($idBaru === null) {
            throw MasterRuleException::fields(['base_uom_id' => 'Satuan dasar wajib dipilih.'], 'BR-GEN-11');
        }

        $uom = Uom::query()->with('category')->find($idBaru);

        if ($uom === null) {
            throw MasterRuleException::fields(['base_uom_id' => 'Satuan dasar tidak ditemukan.'], 'BR-GEN-11');
        }

        return $uom;
    }

    /**
     * BR-STK-09: konversi kemasan hanya dipakai untuk **input** transaksi, mis.
     * "1 batang = 6 m". Satuannya boleh beda kategori dengan satuan dasar karena
     * kemasan memang bukan besaran yang sama; yang dilarang hanya mengulang
     * satuan dasar itu sendiri dan angka nol atau negatif.
     *
     * @param  array<int, array<string, mixed>>  $conversions
     */
    /** @param  array<int, int>|null  $dimuat  satuan kemasan yang dimuat form; null = semua kemasan item */
    private function syncConversions(Item $item, array $conversions, Uom $baseUom, ?array $dimuat = null): void
    {
        $idDipakai = [];

        foreach ($conversions as $baris) {
            $uomId = $this->idAtauNull($baris['uom_id'] ?? null);
            $qty = $this->angkaAtauNull($baris['qty_base'] ?? null);

            if ($uomId === null || $qty === null || $qty <= 0) {
                continue;
            }

            if ($uomId === (int) $baseUom->id) {
                throw MasterRuleException::fields(
                    ['conversions' => 'Satuan dasar tidak perlu dimasukkan sebagai konversi.'],
                    'BR-STK-09',
                );
            }

            if (! Uom::query()->whereKey($uomId)->exists()) {
                throw MasterRuleException::fields(
                    ['conversions' => 'Satuan konversi tidak ditemukan.'],
                    'BR-STK-09',
                );
            }

            $item->uomConversions()->updateOrCreate(
                ['uom_id' => $uomId],
                [
                    'qty_base' => $qty,
                    'is_nominal_piece' => (bool) ($baris['is_nominal_piece'] ?? false),
                    'is_active' => true,
                ],
            );

            $idDipakai[] = $uomId;
        }

        // P-03: baris yang dilepas dari form dinonaktifkan, tidak dihapus,
        // karena dokumen yang sudah terjadi memakainya sebagai dasar hitung.
        $item->uomConversions()
            ->whereNotIn('uom_id', $idDipakai ?: [0])
            ->when($dimuat !== null, fn ($q) => $q->whereIn('uom_id', $dimuat ?: [0]))
            ->update(['is_active' => false]);
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }

    private function angkaAtauNull(mixed $value): ?float
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : (float) str_replace(',', '.', $teks);
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Label\Livewire\Concerns;

use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\PackageLabelLedger;

/**
 * Pindai label kemasan saat barang keluar gudang (A-299), dipakai layar PCK dan ISU.
 *
 * Label isi langsung diklaim utuh; label induk membuka dialog jumlah isi yang
 * diambil (bawaan: sisa kebutuhan baris, dibatasi isi label). Layar pemakai
 * menyediakan `klaimLabel($baris, $label, $qty)` untuk menyimpan klaimnya.
 */
trait CapturesPackageLabels
{
    /** Label induk yang menunggu jumlah isi. */
    public ?int $labelInduk = null;

    /** Kunci baris (id baris PCK atau indeks baris ISU) yang akan menerima klaim. */
    public ?string $labelBaris = null;

    public string $labelIsi = '';

    abstract protected function klaimLabel(string $baris, PackageLabel $label, float $qty): bool;

    /**
     * Menangani label yang dipindai untuk baris $baris; $kebutuhan = sisa jumlah
     * baris yang belum tertutup label.
     */
    protected function pindaiLabelKemasan(PackageLabel $label, string $baris, float $kebutuhan, string $errorField): void
    {
        try {
            $isi = app(PackageLabelLedger::class)->claimable($label);
        } catch (LabelRuleException $e) {
            $this->addError($errorField, $e->getMessage());

            return;
        }

        if (! $label->isParent()) {
            if ($this->klaimLabel($baris, $label, $isi)) {
                $this->dispatch('pesan', teks: __('Label :kode dipindai (:qty).', ['kode' => $label->code, 'qty' => PackageLabelLedger::angka($isi)]));
            }

            return;
        }

        $this->labelInduk = (int) $label->id;
        $this->labelBaris = $baris;
        $this->labelIsi = PackageLabelLedger::angka(max(0.0, min($isi, $kebutuhan > 0 ? $kebutuhan : $isi)));
        $this->resetErrorBag('labelIsi');
    }

    public function simpanIsiLabel(): void
    {
        $label = $this->labelInduk === null ? null : PackageLabel::query()->find($this->labelInduk);

        if ($label === null || $this->labelBaris === null) {
            $this->tutupIsiLabel();

            return;
        }

        $this->validate(
            ['labelIsi' => ['required', 'numeric', 'gt:0']],
            attributes: ['labelIsi' => __('Jumlah isi diambil')],
        );

        if ($this->klaimLabel($this->labelBaris, $label, round((float) $this->labelIsi, 4))) {
            $this->dispatch('pesan', teks: __('Label :kode dipindai (:qty).', ['kode' => $label->code, 'qty' => $this->labelIsi]));
            $this->tutupIsiLabel();
        }
    }

    public function tutupIsiLabel(): void
    {
        $this->labelInduk = null;
        $this->labelBaris = null;
        $this->labelIsi = '';
        $this->resetErrorBag('labelIsi');
    }

    /**
     * Kode label untuk chip klaim.
     *
     * @param  iterable<array{id: int|string, qty: float|string}>  $klaim
     * @return array<int, string>
     */
    protected function kodeLabel(iterable $klaim): array
    {
        $ids = [];

        foreach ($klaim as $c) {
            $ids[] = (int) $c['id'];
        }

        return $ids === [] ? [] : PackageLabel::query()->whereIn('id', $ids)->pluck('code', 'id')->all();
    }

    protected function labelDialog(): ?PackageLabel
    {
        return $this->labelInduk === null ? null
            : PackageLabel::query()->with('item.baseUom:id,code')->find($this->labelInduk);
    }
}

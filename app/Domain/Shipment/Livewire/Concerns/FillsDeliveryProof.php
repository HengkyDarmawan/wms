<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire\Concerns;

use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;
use App\Domain\Shipment\Support\ProofFiles;

/**
 * Isian bukti terima (BR-SJ-05, A-64, A-244) untuk layar SJ internal dan
 * halaman portal klien (A-312): per baris baik/rusak/kurang, foto kerusakan,
 * foto serah terima, tanda tangan, foto SJ bertanda tangan & cap (A-316),
 * dan No. GR klien (A-313). Kanal ditentukan `DeliveryRecipients`, bukan isian.
 *
 * Komponen pemakai menyediakan `$form` berkunci `received_by_name`, `notes`,
 * `client_gr_number`, memakai `HandlesShipmentRules` dan `WithFileUploads`.
 */
trait FillsDeliveryProof
{
    /**
     * Isian bukti terima per baris SJ: baik, rusak, kurang, dan fotonya.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $terima = [];

    /** Berkas bukti terima (A-231): foto serah terima, tanda tangan (data URL kanvas), foto rusak per baris. */
    public $foto = null;

    public string $tandaTangan = '';

    /** @var array<int, mixed> shipment_line_id => berkas sementara */
    public array $fotoRusak = [];

    /** A-316: foto SJ bertanda tangan & cap penerima. */
    public $fotoSj = null;

    protected function siapkanBuktiTerima(Shipment $sj): void
    {
        $kanal = app(DeliveryRecipients::class)->channelFor(auth()->user(), $sj);

        $this->foto = null;
        $this->fotoSj = null;
        $this->tandaTangan = '';
        $this->fotoRusak = [];
        // Penerima berakun menerima atas namanya sendiri; cadangan gudang menyalin nama dari SJ bertanda tangan.
        if (trim((string) ($this->form['received_by_name'] ?? '')) === '' && $kanal !== ProofChannel::SignedDocument) {
            $this->form['received_by_name'] = (string) auth()->user()?->name;
        }

        $this->form['client_gr_number'] = '';
        $this->terima = $sj->lines()->orderBy('id')->get()
            ->mapWithKeys(fn ($l) => [$l->id => [
                // Bawaannya seluruhnya baik: yang paling sering terjadi.
                'qty_good' => (string) (float) $l->qty_shipped,
                'qty_damaged' => '0',
                'qty_missing' => '0',
                'damage_photo_path' => '',
                'notes' => '',
                // A-244: serial/potongan dinilai per unit — satu pilihan kondisi.
                'kondisi' => $l->isUnit() ? 'good' : null,
                'qty' => (float) $l->qty_shipped,
            ]])->all();
    }

    /** Menyimpan berkas lalu menjalankan `ConfirmDelivery`; true bila berhasil. */
    protected function simpanBukti(Shipment $sj, ConfirmDelivery $action, ProofFiles $berkas): bool
    {
        $kanal = app(DeliveryRecipients::class)->channelFor(auth()->user(), $sj);
        abort_if($kanal === null, 403);

        $this->validate(
            [
                'form.received_by_name' => ['required', 'string', 'max:100'],
                'form.client_gr_number' => ['nullable', 'string', 'max:60'],
                'foto' => ['nullable', ...StoreUpload::ATURAN_FOTO],
                'fotoSj' => [$kanal->requiresSignedDocument() ? 'required' : 'nullable', ...StoreUpload::ATURAN_FOTO],
                'fotoRusak.*' => ['nullable', ...StoreUpload::ATURAN_FOTO],
                'tandaTangan' => ['nullable', 'string', 'max:2000000'],
            ],
            attributes: [
                'form.received_by_name' => __('Nama penerima'),
                'form.client_gr_number' => __('No. GR klien'),
                'foto' => __('Foto serah terima'),
                'fotoSj' => __('Foto SJ bertanda tangan & cap'),
                'fotoRusak.*' => __('Foto kerusakan'),
            ],
        );

        $disimpan = null;

        if (! $this->jalankan(function () use ($berkas, $sj, &$disimpan) {
            $disimpan = $berkas->simpan($sj, $this->foto, $this->tandaTangan, $this->fotoRusak, $this->fotoSj);
        }) || $disimpan === null) {
            return false;
        }

        $baris = [];

        foreach ($this->terima as $id => $isi) {
            // Unit serial/potongan: kondisi yang dipilih memegang seluruh jumlahnya.
            if (in_array($isi['kondisi'] ?? null, ['good', 'damaged', 'missing'], true)) {
                foreach (['good', 'damaged', 'missing'] as $k) {
                    $isi['qty_'.$k] = $isi['kondisi'] === $k ? (float) $isi['qty'] : 0;
                }
            }

            $baris[] = [
                'shipment_line_id' => $id,
                'qty_good' => (float) ($isi['qty_good'] ?? 0),
                'qty_damaged' => (float) ($isi['qty_damaged'] ?? 0),
                'qty_missing' => (float) ($isi['qty_missing'] ?? 0),
                // Path lama tetap diterima (uji & draf luring); unggahan baru menggantikannya.
                'damage_photo_path' => $disimpan['lines'][(int) $id] ?? ($isi['damage_photo_path'] ?: null),
                'notes' => $isi['notes'] ?? null,
            ];
        }

        $berhasil = $this->jalankan(fn () => $action->handle($sj, [
            'received_by_name' => $this->form['received_by_name'],
            'notes' => ($this->form['notes'] ?? '') ?: null,
            'channel' => $kanal->value,
            'received_by_user_id' => auth()->id(),
            'photo_path' => $disimpan['photo_path'],
            'signature_path' => $disimpan['signature_path'],
            'signed_document_path' => $disimpan['signed_document_path'],
            'client_gr_number' => $this->form['client_gr_number'] ?? null,
        ], $baris, auth()->user()));

        if (! $berhasil) {
            $berkas->hapus($disimpan);

            return false;
        }

        // A-193: draf bukti terima di perangkat sudah terkirim.
        $this->dispatch('draft-clear', key: 'pod-'.$sj->id);

        return true;
    }
}

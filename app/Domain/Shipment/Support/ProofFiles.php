<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\Shipment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Berkas bukti terima (BR-SJ-05, A-231): foto serah terima, tanda tangan
 * penerima (gambar dari kanvas atau unggahan), dan foto kerusakan per baris.
 *
 * Dipakai layar driver (Livewire) dan halaman penerima bertoken supaya nama
 * dan folder berkasnya sama: `pod/sj-<id>/…` di disk company (NFR-14).
 * Berkas disimpan **sebelum** `ConfirmDelivery`; bila aksi itu gagal, pemanggil
 * membuangnya lewat `hapus()` supaya tidak ada berkas yatim.
 */
class ProofFiles
{
    public function __construct(private readonly StoreUpload $upload) {}

    /**
     * @param  array<int|string, UploadedFile|null>  $fotoRusak  shipment_line_id => foto
     * @return array{photo_path: ?string, signature_path: ?string, lines: array<int, string>}
     */
    public function simpan(Shipment $shipment, ?UploadedFile $foto, ?string $tandaTangan, array $fotoRusak): array
    {
        $folder = 'pod/sj-'.$shipment->id;
        $hasil = ['photo_path' => null, 'signature_path' => null, 'lines' => []];

        try {
            if ($foto instanceof UploadedFile) {
                $hasil['photo_path'] = $this->upload->handle($foto, $folder, 'foto-'.$this->acak());
            }

            if ($tandaTangan !== null && trim($tandaTangan) !== '') {
                $hasil['signature_path'] = $this->upload->handleDataUrl($tandaTangan, $folder, 'ttd-'.$this->acak());
            }

            foreach ($fotoRusak as $lineId => $berkas) {
                if ($berkas instanceof UploadedFile) {
                    $hasil['lines'][(int) $lineId] = $this->upload->handle($berkas, $folder, 'rusak-'.(int) $lineId.'-'.$this->acak());
                }
            }
        } catch (RuntimeException $e) {
            $this->hapus($hasil);

            throw ShipmentRuleException::rule('NFR-14', $e->getMessage());
        }

        return $hasil;
    }

    /** @param  array{photo_path: ?string, signature_path: ?string, lines: array<int, string>}  $berkas */
    public function hapus(array $berkas): void
    {
        $this->upload->delete($berkas['photo_path']);
        $this->upload->delete($berkas['signature_path']);

        foreach ($berkas['lines'] as $path) {
            $this->upload->delete($path);
        }
    }

    private function acak(): string
    {
        return Str::lower(Str::random(6));
    }
}

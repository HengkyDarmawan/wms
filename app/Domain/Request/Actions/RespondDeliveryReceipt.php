<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\DiscrepancyOrigin;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\DiscrepancyType;
use App\Domain\Shipment\Enums\ReceiptConfirmation;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use App\Domain\Shipment\Models\ProofOfDelivery;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission: `request.confirm_receipt` / `request.dispute_receipt` —
 * pemohon (internal atau klien) menanggapi bukti terima SJ untuk REQ-nya
 * dalam `receipt_confirm_days` (BR-REQ-10, A-63, A-188):
 *
 * - **Terima** → `confirmation = confirmed`;
 * - **Keberatan** per baris (kurang/rusak, rusak wajib foto) → DSC
 *   `client_dispute` baru atau baris tambahan pada DSC `open` SJ itu;
 *   `confirmation = disputed`. Barang sudah di tangan penerima, jadi DSC ini
 *   tidak memindah stok saat diselesaikan (klaim/kirim ulang/penyesuaian
 *   dicatat, `still_needed` mengembalikan kebutuhan ke REQ);
 * - **Diam** sampai batas → `auto_confirmed` lewat `deliveries:auto-confirm`.
 *
 * Keberatan hanya untuk SJ ke proyek/klien (jual putus); barang ke Gudang
 * Site diperiksa lewat GRN transfer (A-188). Pada SJ gabungan beberapa REQ,
 * pemohon hanya bisa memberatkan baris REQ-nya; tanggapan tetap satu per SJ
 * (tanggapan pertama berlaku, A-197).
 */
class RespondDeliveryReceipt
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly StoreUpload $upload,
    ) {}

    public function confirm(MaterialRequest $request, ProofOfDelivery $proof, User $actor): ProofOfDelivery
    {
        $this->pastikan($request, $proof);

        return DB::transaction(function () use ($request, $proof, $actor) {
            $proof = $this->kunci($proof);
            $proof->forceFill(['confirmation' => ReceiptConfirmation::Confirmed, 'requester_confirmed_at' => now()])->save();

            activity('request')->performedOn($request)->causedBy($actor)
                ->withProperties(['sj' => $proof->shipment?->number])
                ->log('Penerimaan dikonfirmasi pemohon ('.$proof->shipment?->number.')');

            return $proof->refresh();
        });
    }

    /**
     * @param  array<int|string, array{qty_missing?: mixed, qty_damaged?: mixed}>  $lines  shipment_line_id => isian
     * @param  array<int|string, UploadedFile|null>  $photos  shipment_line_id => foto kerusakan
     */
    public function dispute(MaterialRequest $request, ProofOfDelivery $proof, array $lines, array $photos, ?string $notes, User $actor): DeliveryDiscrepancy
    {
        $this->pastikan($request, $proof);

        if ($proof->shipment->destination_type !== DestinationType::ProjectClient) {
            throw RequestRuleException::rule('BR-REQ-10', 'Keberatan hanya untuk kiriman langsung ke proyek/klien; barang ke Gudang Site diperiksa saat diterima gudang.');
        }

        // Satu SJ boleh membawa beberapa REQ: hanya baris milik REQ ini yang bisa diberatkan.
        $barisBukti = $proof->lines()->with('shipmentLine.pickTaskLine.item:id,code')
            ->whereHas('shipmentLine.pickTaskLine.pickTask', fn ($q) => $q->where('source_type', 'material_request')->where('source_id', $request->id))
            ->get()->keyBy('shipment_line_id');
        $isian = [];

        foreach ($lines as $id => $l) {
            $kurang = is_numeric($l['qty_missing'] ?? null) ? round((float) $l['qty_missing'], 4) : 0.0;
            $rusak = is_numeric($l['qty_damaged'] ?? null) ? round((float) $l['qty_damaged'], 4) : 0.0;

            if ($kurang == 0.0 && $rusak == 0.0) {
                continue;
            }

            $bukti = $barisBukti->get((int) $id) ?? throw RequestRuleException::rule('BR-REQ-10', 'Baris keberatan bukan milik permintaan ini pada surat jalan ini.');
            $kode = $bukti->shipmentLine?->pickTaskLine?->item?->code ?? '#'.$id;

            if ($kurang < 0 || $rusak < 0 || $kurang + $rusak - (float) $bukti->qty_good > 0.00005) {
                throw RequestRuleException::rule('BR-REQ-10', $kode.': kurang + rusak tidak boleh melebihi jumlah yang diterima baik ('.(float) $bukti->qty_good.').');
            }

            $foto = $photos[$id] ?? $photos[(string) $id] ?? null;

            if ($rusak > 0 && ! $foto instanceof UploadedFile) {
                throw RequestRuleException::rule('BR-REQ-10', $kode.': barang rusak wajib disertai foto.');
            }

            $isian[] = ['line' => $bukti, 'missing' => $kurang, 'damaged' => $rusak, 'photo' => $foto];
        }

        if ($isian === []) {
            throw RequestRuleException::rule('BR-GEN-11', 'Isi jumlah kurang atau rusak minimal satu baris.');
        }

        $dsc = DB::transaction(function () use ($request, $proof, $isian, $notes, $actor) {
            $proof = $this->kunci($proof);
            $sj = $proof->shipment;
            // Baris keberatan hanya ditambahkan ke DSC keberatan yang masih terbuka;
            // DSC dari bukti terima menyangkut barang yang masih di Dalam Perjalanan.
            $dsc = DeliveryDiscrepancy::query()->where('shipment_id', $sj->id)->where('status', DiscrepancyStatus::Open->value)
                ->where('origin', DiscrepancyOrigin::ClientDispute->value)->lockForUpdate()->first()
                ?? DeliveryDiscrepancy::create([
                    'number' => $this->nomor->next('DSC', (string) $sj->warehouse->code),
                    'shipment_id' => $sj->id,
                    'origin' => DiscrepancyOrigin::ClientDispute,
                    'status' => DiscrepancyStatus::Open,
                    'notes' => $this->teks($notes),
                ]);

            foreach ($isian as $i) {
                foreach ([DiscrepancyType::Missing->value => $i['missing'], DiscrepancyType::Damaged->value => $i['damaged']] as $jenis => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }

                    $foto = null;

                    if ($jenis === DiscrepancyType::Damaged->value) {
                        try {
                            $foto = $this->upload->handle($i['photo'], 'discrepancies/'.$dsc->id, 'keberatan-'.$i['line']->shipment_line_id.'-'.Str::lower(Str::random(6)));
                        } catch (\RuntimeException $e) {
                            throw RequestRuleException::rule('NFR-14', $e->getMessage());
                        }
                    }

                    DeliveryDiscrepancyLine::create([
                        'delivery_discrepancy_id' => $dsc->id,
                        'shipment_line_id' => $i['line']->shipment_line_id,
                        'discrepancy_type' => $jenis,
                        'qty_base' => $qty,
                        'photo_path' => $foto,
                    ]);
                }
            }

            $proof->forceFill(['confirmation' => ReceiptConfirmation::Disputed, 'requester_confirmed_at' => now()])->save();

            activity('request')->performedOn($request)->causedBy($actor)
                ->withProperties(['sj' => $sj->number, 'dsc' => $dsc->number])
                ->log('Keberatan penerimaan diajukan ('.$sj->number.' → '.$dsc->number.')');
            activity('shipment')->performedOn($dsc)->causedBy($actor)->log('Keberatan pemohon dicatat');

            return $dsc->refresh();
        });

        app(DomainNotifications::class)->discrepancyOpened($dsc, $actor);

        return $dsc;
    }

    /** Job harian: bukti terima yang lewat batas tanpa tanggapan dianggap diterima. */
    public function autoConfirm(): int
    {
        $jumlah = 0;

        foreach (ProofOfDelivery::query()->confirmationOverdue()->pluck('id') as $id) {
            // Kunci & periksa ulang: keberatan yang baru saja tersimpan tidak boleh tertimpa.
            $jumlah += DB::transaction(function () use ($id) {
                $proof = ProofOfDelivery::query()->lockForUpdate()->find($id);

                if ($proof === null || ! $proof->isPending()) {
                    return 0;
                }

                $proof->forceFill(['confirmation' => ReceiptConfirmation::AutoConfirmed])->save();
                activity('shipment')->performedOn($proof->shipment)->log('Penerimaan dikonfirmasi otomatis (lewat batas konfirmasi)');

                return 1;
            });
        }

        return $jumlah;
    }

    private function pastikan(MaterialRequest $request, ProofOfDelivery $proof): void
    {
        $milik = $proof->shipment?->lines()
            ->whereHas('pickTaskLine.pickTask', fn ($q) => $q->where('source_type', 'material_request')->where('source_id', $request->id))
            ->exists();

        if (! $milik) {
            abort(404);
        }

        if (! $proof->isPending()) {
            throw RequestRuleException::rule('BR-REQ-10', 'Penerimaan surat jalan ini sudah ditanggapi ('.$proof->confirmation?->label().').');
        }

        if ($proof->confirm_deadline_at !== null && $proof->confirm_deadline_at->isPast()) {
            throw RequestRuleException::rule('BR-REQ-10', 'Batas konfirmasi sudah lewat; penerimaan dianggap diterima.');
        }
    }

    /** Cek ulang di dalam transaksi supaya kirim ganda tidak menanggapi dua kali. */
    private function kunci(ProofOfDelivery $proof): ProofOfDelivery
    {
        $proof = ProofOfDelivery::query()->lockForUpdate()->findOrFail($proof->id);

        if (! $proof->isPending()) {
            throw RequestRuleException::rule('BR-REQ-10', 'Penerimaan surat jalan ini sudah ditanggapi ('.$proof->confirmation?->label().').');
        }

        return $proof;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Access\Models\User;
use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shared\Models\SyncItem;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Support\BinCode;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pemroses `/offline/sync` (32-pwa-offline, AD-16, A-438, A-439). Server acuan
 * akhir: setiap transaksi diposting lewat kelas Action yang sama dengan layar
 * online, dengan policy yang sama atas nama pengirim.
 *
 * - Idempoten: `uuid` dari perangkat; kiriman ulang mengembalikan status lama.
 * - Bentrok aturan (dokumen sudah berubah, bin beku, saldo kurang, dll.) →
 *   `review` (antrean tinjauan), tidak diposting otomatis (BR-OPN-03).
 * - Jenis tak dikenal / data rusak → `rejected`.
 *
 * Foto (data URL) tidak pernah masuk `sync_queue`: disimpan sebagai berkas dan
 * payload hanya memegang path-nya, sehingga "Coba lagi" tidak perlu kirim ulang.
 */
class OfflineSync
{
    public const MAKS_PER_KIRIM = 100;

    public function __construct(private readonly RecordCount $hitung) {}

    /**
     * @param  array<int, array{uuid?: mixed, kind?: mixed, payload?: mixed, recorded_at?: mixed, berkas?: mixed}>  $items
     * @return array<int, array{uuid: string, status: string, message: ?string}>
     */
    public function handle(User $user, array $items): array
    {
        $hasil = [];

        foreach (array_slice($items, 0, self::MAKS_PER_KIRIM) as $raw) {
            $uuid = (string) ($raw['uuid'] ?? '');

            if (! Str::isUuid($uuid)) {
                $hasil[] = ['uuid' => $uuid, 'status' => SyncItem::REJECTED, 'message' => 'UUID tidak sah.'];

                continue;
            }

            $ada = SyncItem::query()->where('uuid', $uuid)->first();

            if ($ada !== null) {
                $hasil[] = ['uuid' => $uuid, 'status' => $ada->status, 'message' => $ada->message];

                continue;
            }

            $item = SyncItem::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'kind' => mb_substr((string) ($raw['kind'] ?? ''), 0, 30),
                'payload' => is_array($raw['payload'] ?? null) ? $raw['payload'] : [],
                'recorded_at' => $this->waktu($raw['recorded_at'] ?? null),
                'status' => SyncItem::RECEIVED,
            ]);

            $this->proses($item, $user, is_array($raw['berkas'] ?? null) ? $raw['berkas'] : []);
            $hasil[] = ['uuid' => $uuid, 'status' => $item->status, 'message' => $item->message];
        }

        return $hasil;
    }

    /**
     * Posting satu transaksi; dipakai juga oleh tombol "Coba lagi" di antrean tinjauan.
     *
     * @param  array<string, mixed>  $berkas  foto data URL, hanya pada kiriman pertama
     */
    public function proses(SyncItem $item, User $pelaku, array $berkas = []): SyncItem
    {
        try {
            match ($item->kind) {
                'count_record' => $this->hitungOpname($item->payload, $pelaku),
                'putaway_place' => $this->taruhBarang($item->payload, $pelaku),
                'pick_record' => $this->catatPicking($item->payload, $pelaku),
                'receipt_receive' => $this->terimaBarang($item->payload, $pelaku),
                'shipment_ship' => $this->konfirmasiMuat($item->payload, $pelaku),
                'delivery_confirm' => $this->buktiTerima($item, $berkas, $pelaku),
                default => throw new \InvalidArgumentException('Jenis transaksi tidak dikenal.'),
            };
            $item->update(['status' => SyncItem::POSTED, 'message' => null]);
        } catch (CountRuleException|ReceiptRuleException|ShipmentRuleException|LedgerException|WarehouseRuleException|AuthorizationException $e) {
            $item->update(['status' => SyncItem::REVIEW, 'message' => mb_substr($e->getMessage(), 0, 255)]);
        } catch (ModelNotFoundException) {
            // Peninjau di luar cakupan dokumen tidak boleh mengubah antrean menjadi ditolak.
            $item->update(['status' => $item->status === SyncItem::REVIEW ? SyncItem::REVIEW : SyncItem::REJECTED, 'message' => 'Dokumen tidak ditemukan atau di luar cakupan.']);
        } catch (\InvalidArgumentException|\TypeError $e) {
            $item->update(['status' => SyncItem::REJECTED, 'message' => mb_substr($e->getMessage(), 0, 255)]);
        }

        activity('sync')->performedOn($item)->causedBy($pelaku)
            ->withProperties(['jenis' => $item->kind, 'status' => $item->status])
            ->log('Sinkron offline '.$item->kind.': '.(SyncItem::LABEL[$item->status] ?? $item->status));

        return $item;
    }

    /** Foto transaksi yang ditolak peninjau tidak dipakai lagi. */
    public function buangBerkas(SyncItem $item): void
    {
        $path = (array) ($item->payload['path'] ?? []);
        $upload = app(StoreUpload::class);
        $upload->delete($path['photo'] ?? null);
        $upload->delete($path['signed_document'] ?? null);

        foreach ((array) ($path['lines'] ?? []) as $p) {
            $upload->delete(is_string($p) ? $p : null);
        }
    }

    /** @param  array<string, mixed>  $p  {assignment_id, qtys: {line_id: qty}, finish: bool} */
    private function hitungOpname(array $p, User $pelaku): void
    {
        $tugas = CountAssignment::query()->find((int) ($p['assignment_id'] ?? 0))
            ?? throw new \InvalidArgumentException('Penugasan hitung tidak ditemukan.');

        $this->hitung->save($tugas, (array) ($p['qtys'] ?? []), $pelaku);

        if (! empty($p['finish'])) {
            $this->hitung->finish($tugas->refresh(), $pelaku);
        }
    }

    /** @param  array<string, mixed>  $p  {task_id, line_id, bin_code?, override_reason?} */
    private function taruhBarang(array $p, User $pelaku): void
    {
        $tugas = PutawayTask::query()->findOrFail((int) ($p['task_id'] ?? 0));
        Gate::forUser($pelaku)->authorize('complete', $tugas);

        $isi = ['override_reason' => $p['override_reason'] ?? null];
        $kode = trim((string) ($p['bin_code'] ?? ''));

        if ($kode !== '') {
            $bin = BinCode::cocokkan($kode, app(PutawaySuggester::class)->storageBins($tugas->warehouse))
                ?? throw ReceiptRuleException::rule('BR-GRN-03', 'Bin "'.mb_substr($kode, 0, 60).'" bukan bin penyimpanan gudang ini.');
            $isi['bin_id'] = $bin->id;
        }

        app(CompletePutaway::class)->placeLine($tugas, (int) ($p['line_id'] ?? 0), $isi, $pelaku);
    }

    /**
     * Satu transaksi: gagal di baris mana pun, tugas kembali seperti semula
     * sehingga "Coba lagi" mengulang dari awal.
     *
     * @param  array<string, mixed>  $p  {task_id, lines: [{line_id, qty_picked, short_reason?}], finish}
     */
    private function catatPicking(array $p, User $pelaku): void
    {
        $tugas = PickTask::query()->findOrFail((int) ($p['task_id'] ?? 0));
        $aksi = app(ProcessPickTask::class);

        DB::transaction(function () use ($tugas, $p, $aksi, $pelaku) {
            if ($tugas->status->value === 'pending') {
                Gate::forUser($pelaku)->authorize('start', $tugas);
                $tugas = $aksi->start($tugas, $pelaku)->refresh();
            }

            Gate::forUser($pelaku)->authorize('complete', $tugas);

            foreach ((array) ($p['lines'] ?? []) as $b) {
                $baris = $tugas->lines()->whereKey((int) ($b['line_id'] ?? 0))->firstOrFail();
                $alasan = (string) ($b['short_reason'] ?? '');
                $aksi->recordLine(
                    $baris,
                    (float) ($b['qty_picked'] ?? 0),
                    shortReasonId: $alasan === '' ? null : ReasonCode::query()->where('context', ReasonContext::ShortPick->value)->where('code', $alasan)->value('id'),
                    actor: $pelaku,
                );
            }

            if (! empty($p['finish'])) {
                $aksi->complete($tugas->refresh(), $pelaku);
            }
        });
    }

    /**
     * Hanya GRN draf yang dibuat saat online, jadi tidak ada dokumen lahir offline.
     *
     * @param  array<string, mixed>  $p  {receipt_id}
     */
    private function terimaBarang(array $p, User $pelaku): void
    {
        $grn = GoodsReceipt::query()->findOrFail((int) ($p['receipt_id'] ?? 0));
        Gate::forUser($pelaku)->authorize('receive', $grn);
        app(ReceiveGoodsReceipt::class)->handle($grn, $pelaku);
    }

    /** @param  array<string, mixed>  $p  {shipment_id, notes?} */
    private function konfirmasiMuat(array $p, User $pelaku): void
    {
        $sj = Shipment::query()->findOrFail((int) ($p['shipment_id'] ?? 0));
        Gate::forUser($pelaku)->authorize('ship', $sj);
        app(ShipShipment::class)->handle($sj, ($p['notes'] ?? '') ?: null, $pelaku);
    }

    /**
     * Penerima bisa di luar cakupan gudang asal, jadi otorisasinya `channelFor`
     * (sama dengan policy `confirmDelivery`), bukan global scope. Status SJ
     * diperiksa `ConfirmDelivery`; foto disimpan lebih dulu agar "Coba lagi" bisa jalan.
     *
     * @param  array<string, mixed>  $berkas  {photo?, signed_document?, lines?: {line_id: data URL}}
     */
    private function buktiTerima(SyncItem $item, array $berkas, User $pelaku): void
    {
        $p = $item->payload;
        $sj = Shipment::query()->withoutGlobalScopes()->findOrFail((int) ($p['shipment_id'] ?? 0));
        $kanal = app(DeliveryRecipients::class)->channelFor($pelaku, $sj)
            ?? throw new AuthorizationException('Anda bukan penerima surat jalan ini.');

        if ($berkas !== []) {
            $p['path'] = $this->simpanFoto($sj, $berkas);
            $item->update(['payload' => $p]);
        }

        $path = (array) ($p['path'] ?? []);
        $baris = array_map(fn ($b) => [
            'shipment_line_id' => (int) ($b['shipment_line_id'] ?? 0),
            'qty_good' => (float) ($b['qty_good'] ?? 0),
            'qty_damaged' => (float) ($b['qty_damaged'] ?? 0),
            'qty_missing' => (float) ($b['qty_missing'] ?? 0),
            'damage_photo_path' => $path['lines'][(int) ($b['shipment_line_id'] ?? 0)] ?? null,
            'notes' => $b['notes'] ?? null,
        ], (array) ($p['lines'] ?? []));

        app(ConfirmDelivery::class)->handle($sj, [
            'received_by_name' => $p['received_by_name'] ?? '',
            'notes' => ($p['notes'] ?? '') ?: null,
            'channel' => $kanal->value,
            'received_by_user_id' => $pelaku->id,
            'photo_path' => $path['photo'] ?? null,
            'signature_path' => null,
            'signed_document_path' => $path['signed_document'] ?? null,
            'client_gr_number' => null,
        ], $baris, $pelaku);
    }

    /**
     * @param  array<string, mixed>  $berkas
     * @return array{photo: ?string, signed_document: ?string, lines: array<int, string>}
     */
    private function simpanFoto(Shipment $sj, array $berkas): array
    {
        $upload = app(StoreUpload::class);
        $folder = 'pod/sj-'.$sj->id;
        $simpan = fn (mixed $data, string $nama): ?string => is_string($data) && $data !== ''
            ? $upload->handleDataUrl($data, $folder, $nama.'-'.Str::lower(Str::random(6)))
            : null;

        try {
            $hasil = ['photo' => $simpan($berkas['photo'] ?? null, 'foto'), 'signed_document' => $simpan($berkas['signed_document'] ?? null, 'sj-ttd'), 'lines' => []];

            foreach ((array) ($berkas['lines'] ?? []) as $id => $data) {
                $path = $simpan($data, 'rusak-'.(int) $id);

                if ($path !== null) {
                    $hasil['lines'][(int) $id] = $path;
                }
            }
        } catch (RuntimeException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        return $hasil;
    }

    private function waktu(mixed $nilai): ?Carbon
    {
        try {
            return is_string($nilai) && $nilai !== '' ? Carbon::parse($nilai)->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

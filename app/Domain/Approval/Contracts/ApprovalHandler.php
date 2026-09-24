<?php

declare(strict_types=1);

namespace App\Domain\Approval\Contracts;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Kontrak kecil antara mesin approval dan satu jenis dokumen (D-28).
 *
 * Mesin tidak tahu apa pun tentang REQ, RTV, atau PO. Setiap modul dokumen
 * mendaftarkan satu penangan di `ApprovalRegistry` (lewat service
 * provider-nya); mesin memanggil penangan itu untuk membaca data dokumen dan
 * untuk menjalankan akibat keputusan akhir. Modul Purchasing kelak memasang
 * penangan `purchase_order` dengan cara yang sama.
 */
interface ApprovalHandler
{
    public function documentType(): ApprovalDocumentType;

    /**
     * Permission Katalog untuk transisi `pending_approval → approved/rejected`
     * (mis. `request.approve`). Approver yang ditunjuk aturan harus
     * memegangnya (A-86).
     */
    public function approvePermission(): string;

    /** Dokumen tanpa global scope cakupan: mesin bekerja atas nama sistem. */
    public function find(int $documentId): ?Model;

    /** Untuk simulasi atas dokumen contoh (BR-APR-11); ikut cakupan pengguna. */
    public function findByNumber(string $number): ?Model;

    public function number(Model $document): string;

    public function url(Model $document): ?string;

    /** Nama log aktivitas dokumen, agar jejak approval tampil di riwayatnya. */
    public function logName(): string;

    public function context(Model $document): ApprovalContext;

    /** Menautkan (atau melepas) snapshot yang berlaku ke dokumen. */
    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void;

    /**
     * Lapis minimum bila tidak ada aturan yang cocok. Kosong = disetujui
     * otomatis (A-08); ADJ manual kelak mengembalikan satu lapis (A-09).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fallbackSteps(Model $document): array;

    /** Semua lapis setuju (atau tanpa aturan): jalankan akibatnya. */
    public function onApproved(Model $document, ?User $actor): void;

    /** Ditolak approver: alasan wajib (BR-GEN-11). */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void;
}

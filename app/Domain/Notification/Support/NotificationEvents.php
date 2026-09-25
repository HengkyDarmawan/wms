<?php

declare(strict_types=1);

namespace App\Domain\Notification\Support;

use App\Domain\Access\Models\User;

/**
 * Daftar kejadian notifikasi Fase 1 (Blueprint §10, A-189). Kunci stabil
 * dipakai tabel `notifications.type` dan `notification_preferences.event_key`.
 * Email bawaan mati kecuali kejadian yang menuntut tindakan segera.
 */
final class NotificationEvents
{
    /** @var array<string, array{label: string, email: bool, permission?: string}> */
    public const ALL = [
        'approval.task_assigned' => ['label' => 'Tugas approval baru untuk saya', 'email' => true],
        'approval.decided' => ['label' => 'Dokumen yang saya ajukan diputus', 'email' => false],
        'request.under_review' => ['label' => 'Permintaan klien menunggu tinjauan', 'email' => false],
        'delivery.received' => ['label' => 'Barang permintaan saya diterima — konfirmasi atau keberatan', 'email' => false],
        'discrepancy.opened' => ['label' => 'Selisih pengiriman baru', 'email' => false],
        'purchase_request.approved' => ['label' => 'PRQ disetujui dan menunggu pemesanan', 'email' => false],
        'purchase_request.reorder_draft' => ['label' => 'Draf PRQ titik pesan ulang dibuat', 'email' => false],
        'asset.overdue' => ['label' => 'Aset lewat jatuh tempo kembali', 'email' => true],
        'asset.life_alert' => ['label' => 'Sisa umur aset di bawah ambang', 'email' => false, 'permission' => 'asset.manage'],
        'item.provisional_created' => ['label' => 'Item sementara dibuat dari permintaan', 'email' => false, 'permission' => 'item.create'],
        'project.closed' => ['label' => 'Proyek ditutup atau dibatalkan', 'email' => false],
        'stock.period_locked' => ['label' => 'Periode stok dikunci', 'email' => false, 'permission' => 'warehouse.update'],
        'stock.balance_mismatch' => ['label' => 'Saldo stok tidak cocok dengan kartu stok', 'email' => true, 'permission' => 'stock.lock_period'],
        'stock.reservation_stale' => ['label' => 'Reservasi menggantung melewati ambang', 'email' => false],
        'request.review_overdue' => ['label' => 'Permintaan melewati SLA tinjau', 'email' => false, 'permission' => 'request.review'],
        'request.decided' => ['label' => 'Permintaan saya disetujui atau ditolak', 'email' => false, 'permission' => 'request.create'],
        'request.line_substituted' => ['label' => 'Barang permintaan saya diganti item lain', 'email' => false, 'permission' => 'request.respond_substitution'],
        'request.promise_changed' => ['label' => 'Tanggal janji permintaan saya berubah', 'email' => false, 'permission' => 'request.create'],
        'request.line_cancel_requested' => ['label' => 'Klien meminta pembatalan baris', 'email' => false, 'permission' => 'request.confirm_cancel'],
        'request.line_cancel_decided' => ['label' => 'Permintaan pembatalan baris saya diputus', 'email' => false, 'permission' => 'request.request_cancel'],
        'subscription.billing' => ['label' => 'Tagihan & status langganan company', 'email' => true, 'permission' => 'billing.view'],
    ];

    /**
     * Kejadian yang relevan bagi user (layar preferensi): kejadian berizin
     * khusus hanya tampil bagi pemegang izinnya.
     *
     * @return array<string, array{label: string, email: bool, permission?: string}>
     */
    public static function forUser(User $user): array
    {
        return array_filter(self::ALL, fn (array $e) => ! isset($e['permission']) || $user->hasPermission($e['permission']));
    }

    public static function label(string $key): string
    {
        return self::ALL[$key]['label'] ?? $key;
    }

    public static function emailByDefault(string $key): bool
    {
        return self::ALL[$key]['email'] ?? false;
    }
}

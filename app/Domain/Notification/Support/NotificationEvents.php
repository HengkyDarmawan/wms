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
        'asset.overdue' => ['label' => 'Aset lewat jatuh tempo kembali', 'email' => false],
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

<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;

/**
 * Titik sambung notifikasi approval (alur 9 langkah 5, Blueprint §10) —
 * **stub** (A-91).
 *
 * Modul notifikasi in-app (lonceng, tabel `notifications`) belum ada dan
 * email di Blueprint §10 hanya untuk ringkasan/undangan/tagihan, sehingga di
 * Fase 1 approver menemukan tugasnya di layar "Tugas approval saya" (angka di
 * menu). WhatsApp bertombol + token sekali pakai adalah Fase 2a (BR-APR-10).
 * Kelas ini sengaja tidak mengirim apa pun; modul notifikasi cukup mengisi
 * kedua metode.
 */
class ApprovalNotifier
{
    public function taskAssigned(ApprovalTask $task): void
    {
        // Stub: in-app/email menunggu modul notifikasi; WA menunggu Fase 2a.
    }

    public function documentDecided(ApprovalSnapshot $snapshot): void
    {
        // Stub: pemberitahuan ke pengaju menunggu modul notifikasi.
    }
}

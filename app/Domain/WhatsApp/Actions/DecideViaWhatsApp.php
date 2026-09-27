<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalToken;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Shared\Messaging\PhoneNumber;
use App\Domain\WhatsApp\Support\WhatsAppRecipients;

/**
 * Keputusan dari tombol WhatsApp (BR-APR-10, BR-WA-01, A-277) — dijalankan
 * dalam konteks tenant setelah webhook pusat memverifikasi tanda tangan.
 *
 * *Setujui* memutus lewat {@see ApprovalEngine::approve()} dengan kanal
 * `whatsapp`, nomor pengirim, id pesan, dan token (sekali pakai). *Tolak*
 * tidak memutus karena alasan wajib (BR-GEN-11): balasannya tautan ke layar
 * tugas. Semua penolakan dibalas singkat, tanpa percakapan (riset §2.6).
 *
 * @return array{decided: bool, reply: string}
 */
class DecideViaWhatsApp
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    public function handle(string $token, string $action, string $from, string $waMessageId, string $inboxUrl): array
    {
        $baris = ApprovalToken::query()->with('task.snapshot')->where('token', $token)->first();

        if ($baris === null || $baris->used_at !== null || $baris->expires_at?->isPast()) {
            return $this->jawab(false, 'Tombol ini sudah tidak berlaku. Buka aplikasi untuk melihat tugas approval Anda: '.$inboxUrl);
        }

        $task = $baris->task;
        $snapshot = $task?->snapshot;
        $dokumen = $snapshot === null ? 'dokumen' : $snapshot->document_type->code().' '.$snapshot->document_number;

        if ($task === null || $snapshot === null || $task->status !== ApprovalTaskStatus::Open || $snapshot->status !== ApprovalSnapshotStatus::Pending) {
            $baris->forceFill(['used_at' => now()])->save();

            return $this->jawab(false, 'Tugas approval '.$dokumen.' sudah diputus atau dialihkan.');
        }

        $approver = User::query()->find($task->approver_user_id);

        if ($approver === null || ! WhatsAppRecipients::matches($approver, $from)) {
            activity('approval')->withProperties(['token' => $baris->id, 'nomor' => PhoneNumber::mask((string) PhoneNumber::normalize($from))])
                ->log('Tombol WhatsApp ditolak: nomor bukan approver tugas');

            return $this->jawab(false, 'Nomor ini tidak terdaftar sebagai approver '.$dokumen.'. Keputusan tidak dicatat.');
        }

        if ($action === 'R') {
            return $this->jawab(false, 'Untuk menolak '.$dokumen.', buka '.$inboxUrl.' lalu pilih alasan penolakan (wajib).');
        }

        try {
            $this->engine->approve($task, $approver, 'Disetujui lewat WhatsApp', [
                'channel' => 'whatsapp',
                'wa_from_number' => PhoneNumber::normalize($from),
                'wa_message_id' => $waMessageId,
                'approval_token_id' => $baris->id,
            ]);
        } catch (ApprovalRuleException $e) {
            return $this->jawab(false, $dokumen.': '.$e->getMessage());
        }

        $baris->forceFill(['used_at' => now()])->save();

        return $this->jawab(true, '✅ '.$dokumen.' disetujui. Terima kasih.');
    }

    /** @return array{decided: bool, reply: string} */
    private function jawab(bool $diputus, string $pesan): array
    {
        return ['decided' => $diputus, 'reply' => $pesan];
    }
}

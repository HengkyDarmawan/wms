<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Models\ApprovalToken;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Approval bertombol lewat WhatsApp (Blueprint §8.2, BR-APR-10, A-277).
 *
 * Tawaran dikirim bila lapis rencana ber-kanal `both` (Admin Company memilih
 * *Web & WhatsApp* di aturan), fitur company aktif, dan approver punya nomor
 * terverifikasi yang tidak ia matikan untuk kejadian `approval.task_assigned`.
 * Setiap tugas mendapat token sekali pakai (berlaku sampai batas waktu tugas,
 * maks `approval_token_hours`); payload tombol `APR|<company>|<token>|A|R`.
 */
class ApprovalWhatsApp
{
    public const PREFIX = 'APR';

    public const EVENT = 'approval.task_assigned';

    public function __construct(private readonly WhatsAppChannel $channel) {}

    /** Kirim `wms_approval`; true bila terkirim (notifikasi WA generik tidak perlu lagi). */
    public function offer(ApprovalTask $task, User $approver, ApprovalSnapshot $snapshot, string $summary): bool
    {
        if (! $this->lapisWhatsApp($snapshot, (int) $task->step_no) || ! $this->channel->enabled()) {
            return false;
        }

        $nomor = WhatsAppRecipients::number($approver);
        $pref = NotificationPreference::query()->where('user_id', $approver->id)->where('event_key', self::EVENT)->value('whatsapp');

        if ($nomor === null || $pref === false || $pref === 0) {
            return false;
        }

        $batas = now()->addHours((int) config('wms.whatsapp.approval_token_hours', 72));
        $token = ApprovalToken::create([
            'approval_task_id' => $task->id,
            'token' => Str::random(40),
            'expires_at' => $task->due_at !== null && $task->due_at->lt($batas) && $task->due_at->isFuture() ? $task->due_at : $batas,
        ]);

        $payload = fn (string $aksi) => implode('|', [self::PREFIX, (string) $this->tenantKey(), $token->token, $aksi]);

        try {
            $id = $this->channel->template($nomor, 'approval', [
                $this->channel->companyName(),
                $snapshot->document_type->code().' '.$snapshot->document_number.' — lapis '.$task->step_no,
                $summary,
            ], [
                ['type' => 'quick_reply', 'value' => $payload('A')],
                ['type' => 'quick_reply', 'value' => $payload('R')],
                ['type' => 'url', 'value' => $this->channel->linkSuffix(route('approval.inbox', absolute: false))],
            ], ['event' => self::EVENT, 'user_id' => $approver->id, 'approval_task_id' => $task->id]);
        } catch (WhatsAppNotSent $e) {
            Log::warning('WhatsApp approval gagal: '.$e->getMessage(), ['task' => $task->id]);
            $token->forceFill(['used_at' => now()])->save(); // token tak terkirim tidak boleh hidup

            return false;
        }

        $token->forceFill(['wa_message_id' => $id])->save();

        return true;
    }

    /** @return array{0: string, 1: int, 2: string, 3: string}|null  [prefix, company, token, A|R] */
    public static function parse(string $payload): ?array
    {
        $bagian = explode('|', $payload);

        if (count($bagian) !== 4 || $bagian[0] !== self::PREFIX || ! ctype_digit($bagian[1]) || ! in_array($bagian[3], ['A', 'R'], true)) {
            return null;
        }

        return [$bagian[0], (int) $bagian[1], $bagian[2], $bagian[3]];
    }

    private function lapisWhatsApp(ApprovalSnapshot $snapshot, int $stepNo): bool
    {
        foreach ((array) $snapshot->steps as $step) {
            if ((int) ($step['step_no'] ?? 0) === $stepNo) {
                return ($step['channel'] ?? 'web') === 'both';
            }
        }

        return false;
    }

    private function tenantKey(): int|string
    {
        return tenant()?->getTenantKey() ?? 0;
    }
}

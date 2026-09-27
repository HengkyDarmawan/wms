<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\SignatureSeal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Segel tanda tangan (A-264): setiap kotak tanda tangan yang pelakunya
 * diketahui mendapat token acak 40 karakter. QR di cetakan berisi tautan
 * `/verifikasi/{token}` di subdomain company; halaman itu publik dan hanya
 * menampilkan identitas dokumen dan penanda tangan — tanpa jumlah dan harga.
 *
 * Segel yang sama dipakai ulang di cetakan berikutnya selama penanda tangan
 * dan waktu tindakannya sama, jadi QR di cetakan lama tetap sah.
 */
class SignatureSeals
{
    public function issue(DocumentTemplateType $type, Model $model, string $number, int $blockNo, string $label, ?User $user, string $name, ?CarbonInterface $actedAt): SignatureSeal
    {
        $ada = SignatureSeal::query()
            ->where('document_type', $type->value)->where('document_id', $model->getKey())
            ->where('block_no', $blockNo)->where('signer_name', mb_substr($name, 0, 120))
            ->when($actedAt === null, fn ($q) => $q->whereNull('acted_at'), fn ($q) => $q->where('acted_at', $actedAt->copy()->utc()->format('Y-m-d H:i:s')))
            ->orderBy('id')->first();

        if ($ada !== null) {
            return $ada;
        }

        $segel = new SignatureSeal([
            'token' => Str::random(40),
            'document_type' => $type,
            'document_id' => $model->getKey(),
            'document_number' => mb_substr($number, 0, 60),
            'block_no' => $blockNo,
            'block_label' => mb_substr($label, 0, 40),
            'signer_user_id' => $user?->id,
            'signer_name' => mb_substr($name, 0, 120),
            'signer_title' => $user?->position?->name !== null ? mb_substr((string) $user->position->name, 0, 120) : null,
            'acted_at' => $actedAt,
            'sealed_at' => now(),
        ]);
        $segel->fingerprint = $this->fingerprint($segel);
        $segel->save();

        return $segel;
    }

    public function fingerprint(SignatureSeal $segel): string
    {
        return hash_hmac('sha256', implode('|', [
            $segel->token,
            $segel->document_type instanceof DocumentTemplateType ? $segel->document_type->value : (string) $segel->document_type,
            (string) $segel->document_id,
            (string) $segel->document_number,
            (string) $segel->block_no,
            (string) $segel->block_label,
            (string) $segel->signer_name,
            $segel->acted_at?->copy()->utc()->format('Y-m-d H:i:s') ?? '-',
            $segel->sealed_at?->copy()->utc()->format('Y-m-d H:i:s') ?? '-',
        ]), (string) config('app.key'));
    }

    /** Baris segel belum diubah sejak diterbitkan. */
    public function intact(SignatureSeal $segel): bool
    {
        return hash_equals($segel->fingerprint, $this->fingerprint($segel));
    }

    public function url(SignatureSeal $segel): string
    {
        return route('signature.verify', $segel->token);
    }
}

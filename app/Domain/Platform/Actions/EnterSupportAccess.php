<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Access\Actions\StartSupportSession;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SupportAccess;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * BR-SUB-04, A-27, A-180: Super Admin membuka data company hanya lewat akses
 * dukungan yang sedang berlaku. Hasilnya tautan bertanda tangan berumur
 * pendek ke subdomain company; di sana {@see StartSupportSession}
 * membuka sesi hanya-baca atas nama Admin Company pemberi izin. Tautan sekali
 * pakai (nonce) dan gugur saat tautan baru dibuat.
 */
class EnterSupportAccess
{
    public const LINK_MINUTES = 5;

    public function handle(Company $company, PlatformUser $actor, string $scheme = 'http', ?int $port = null): string
    {
        $akses = SupportAccess::query()->active()
            ->where('company_id', $company->getTenantKey())
            ->where('platform_user_id', $actor->id)
            ->latest('ends_at')->first();

        if ($akses === null) {
            throw PlatformRuleException::rule('BR-SUB-04', 'Tidak ada akses dukungan yang berlaku untuk company ini. Minta Admin Company memberikannya.');
        }

        // Tautan sekali pakai: hanya hash nonce tautan terakhir yang disimpan.
        $nonce = Str::random(40);
        $akses->forceFill(['link_nonce_hash' => hash('sha256', $nonce), 'link_used_at' => null])->save();

        $akar = $scheme.'://'.$company->host().($port !== null && ! in_array($port, [80, 443], true) ? ':'.$port : '');

        URL::forceRootUrl($akar);

        try {
            $tautan = URL::temporarySignedRoute('support.enter', now()->addMinutes(self::LINK_MINUTES), [
                'supportAccess' => $akses->id,
                'admin' => $actor->id,
                'nonce' => $nonce,
            ]);
        } finally {
            URL::forceRootUrl(null);
        }

        PlatformAudit::record('Tautan akses dukungan dibuat', $company, $actor, ['support_access_id' => $akses->id, 'ends_at' => $akses->ends_at->toIso8601String()]);

        return $tautan;
    }
}

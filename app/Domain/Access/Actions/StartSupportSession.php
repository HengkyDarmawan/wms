<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SupportAccess;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Membuka sesi akses dukungan di subdomain company (BR-SUB-04, A-27, A-180):
 * Super Admin masuk atas nama Admin Company pemberi izin, **hanya-baca**
 * (ditegakkan `EnsureSubscriptionState`), sampai izin berakhir atau dicabut.
 * Tercatat di audit log tenant dan pusat.
 */
class StartSupportSession
{
    public const SESSION_KEY = 'support_access_id';

    public const SESSION_ADMIN = 'support_admin_name';

    public function handle(Request $request, Company $company, int $supportAccessId, int $platformUserId, string $nonce): User
    {
        [$akses, $pemberi] = DB::connection('central')->transaction(function () use ($company, $supportAccessId, $platformUserId, $nonce) {
            $akses = SupportAccess::query()->whereKey($supportAccessId)
                ->where('company_id', $company->getTenantKey())
                ->where('platform_user_id', $platformUserId)
                ->lockForUpdate()->first();

            if ($akses === null || ! $akses->isActive()) {
                throw AccessRuleException::rule('BR-SUB-04', 'Akses dukungan tidak berlaku atau sudah berakhir.');
            }

            // Tautan sekali pakai: nonce harus cocok dengan tautan terakhir dan belum dipakai.
            if ($akses->link_nonce_hash === null || $akses->link_used_at !== null || ! hash_equals($akses->link_nonce_hash, hash('sha256', $nonce))) {
                throw AccessRuleException::rule('BR-SUB-04', 'Tautan akses dukungan sudah dipakai atau diganti. Buat tautan baru dari layar Super Admin.');
            }

            $pemberi = User::query()->whereKey($akses->granted_by_tenant_user_id)->where('is_active', true)->first();

            if ($pemberi === null) {
                throw AccessRuleException::rule('BR-SUB-04', 'Admin Company pemberi izin sudah tidak aktif.');
            }

            $akses->forceFill(['link_used_at' => now()])->save();

            return [$akses, $pemberi];
        });

        $admin = PlatformUser::query()->find($platformUserId);

        Auth::guard('web')->login($pemberi);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $akses->id);
        $request->session()->put(self::SESSION_ADMIN, $admin?->name ?? 'Super Admin');

        activity('access')->performedOn($pemberi)
            ->withProperties(['support_access_id' => $akses->id, 'platform_user' => $admin?->email, 'ends_at' => $akses->ends_at->toIso8601String()])
            ->log('Super Admin masuk lewat akses dukungan (hanya-baca)');
        PlatformAudit::record('Masuk ke company lewat akses dukungan', $akses, $admin, ['company' => $company->code]);

        return $pemberi;
    }
}

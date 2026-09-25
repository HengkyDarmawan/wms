<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;

/**
 * Super Admin menangguhkan company secara manual atau mengaktifkannya kembali
 * (Blueprint §4.1 "menangguhkan company", A-179). Penangguhan manual
 * berefek sama dengan langganan `suspended` (hanya-baca, BR-SUB-02) dan
 * terpisah dari siklus tagihan; company `terminated` tidak bisa dipulihkan
 * dari sini.
 */
class ChangeCompanyStatus
{
    public function suspend(Company $company, string $reason, PlatformUser $actor): Company
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw PlatformRuleException::field('BR-GEN-11', 'reason', 'Alasan penangguhan wajib diisi.');
        }

        if ($company->status !== CompanyStatus::Active) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Hanya company Aktif yang bisa ditangguhkan (sekarang '.$company->status->label().').');
        }

        $company->status = CompanyStatus::Suspended;
        $company->setAttribute('status_reason', mb_substr($reason, 0, 255));
        $company->save();

        PlatformAudit::record('Company ditangguhkan', $company, $actor, ['reason' => $reason]);

        return $company->refresh();
    }

    public function reactivate(Company $company, PlatformUser $actor): Company
    {
        if ($company->status !== CompanyStatus::Suspended) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Hanya company Ditangguhkan yang bisa diaktifkan kembali.');
        }

        $company->status = CompanyStatus::Active;
        $company->setAttribute('status_reason', null);
        $company->save();

        PlatformAudit::record('Company diaktifkan kembali', $company, $actor);

        return $company->refresh();
    }
}

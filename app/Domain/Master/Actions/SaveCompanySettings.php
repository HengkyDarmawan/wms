<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Support\CompanySettingCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `company_setting.manage` — menyimpan ambang hari/persen, saklar
 * fitur lapis 1 (P-08), dan zona waktu company (BR-GEN-07) dari satu layar
 * (11-master §6, A-230).
 *
 * Hanya kunci yang berubah yang ditulis, supaya jejak audit
 * `CompanySetting::put()` / `FeatureSetting::toggle()` (BR-GEN-05) tidak
 * dipenuhi baris yang nilainya sama. Mematikan saklar tidak menghapus data
 * item yang memakainya (P-03); layar hanya memperingatkan.
 */
class SaveCompanySettings
{
    /**
     * @param  array<string, mixed>  $nilai  kunci company_settings => angka
     * @param  array<string, mixed>  $fitur  kunci feature_settings => bool
     * @return array{nilai: array<string, int|float>, fitur: array<string, bool>, timezone: string}
     */
    public function handle(array $nilai, array $fitur, ?string $timezone, User $actor): array
    {
        $katalog = CompanySettingCatalog::nilai();
        $galat = [];
        $bersih = [];

        foreach ($katalog as $kunci => $def) {
            $mentah = $nilai[$kunci] ?? null;

            if ($mentah === null || $mentah === '' || ! is_numeric($mentah)) {
                $galat['nilai.'.$kunci] = $def['label'].' wajib diisi angka.';

                continue;
            }

            $angka = $def['desimal'] ? round((float) $mentah, 4) : (int) $mentah;

            if ($angka < $def['min'] || $angka > $def['max']) {
                $galat['nilai.'.$kunci] = $def['label'].' harus antara '.$def['min'].' dan '.$def['max'].' '.$def['satuan'].'.';

                continue;
            }

            $bersih[$kunci] = $angka;
        }

        if ($timezone !== null && ! array_key_exists($timezone, CompanySettingCatalog::ZONA_WAKTU)) {
            $galat['timezone'] = 'Zona waktu tidak dikenal.';
        }

        if ($galat !== []) {
            throw MasterRuleException::fields($galat, 'BR-GEN-11');
        }

        $fiturBersih = [];

        foreach (CompanySettingCatalog::fitur() as $kunci => $def) {
            $fiturBersih[$kunci] = $def['tetap'] ? CompanySettingCatalog::fiturSaatIni()[$kunci] : (bool) ($fitur[$kunci] ?? false);
        }

        DB::connection('tenant')->transaction(function () use ($bersih, $fiturBersih): void {
            $lama = CompanySettingCatalog::nilaiSaatIni();

            foreach ($bersih as $kunci => $angka) {
                if ($lama[$kunci] != $angka) {
                    CompanySetting::put($kunci, $angka);
                }
            }

            $fiturLama = CompanySettingCatalog::fiturSaatIni();

            foreach ($fiturBersih as $kunci => $nyala) {
                if (! FeatureSetting::query()->whereKey($kunci)->exists() || $fiturLama[$kunci] !== $nyala) {
                    FeatureSetting::toggle($kunci, $nyala);
                }
            }
        });

        $company = tenant();

        if ($timezone !== null && $company !== null && $company->timezone !== $timezone) {
            $dari = $company->timezone;
            $company->forceFill(['timezone' => $timezone])->save();

            activity('master')
                ->causedBy($actor)
                ->withProperties(['dari' => $dari, 'ke' => $timezone])
                ->log('Zona waktu company diubah');
        }

        return [
            'nilai' => $bersih,
            'fitur' => $fiturBersih,
            'timezone' => (string) ($timezone ?? $company?->timezone ?? 'Asia/Jakarta'),
        ];
    }
}

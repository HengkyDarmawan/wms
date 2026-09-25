<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Support\Facades\Validator;

/**
 * Super Admin membuat company baru (Blueprint §4.1, alur 10 langkah 2–3,
 * Arsitektur §3 langkah 5): baris `companies` + langganan `trial`, lalu
 * {@see ProvisionCompany} membuat database, migrasi, data acuan, dan
 * undangan Admin Company (A-176).
 */
class CreateCompany
{
    /** Subdomain yang dipakai platform sendiri. */
    public const RESERVED_SUBDOMAINS = ['www', 'admin', 'auth', 'api', 'app', 'mail', 'static', 'status', 'platform'];

    public function __construct(private readonly ProvisionCompany $provision) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data, PlatformUser $actor): Company
    {
        $v = $this->validasi($data);
        $plan = Plan::query()->whereKey($v['plan_id'])->where('is_active', true)->first()
            ?? throw PlatformRuleException::field('BR-GEN-11', 'plan_id', 'Paket tidak aktif.');

        $trial = (int) ($v['trial_days'] ?? $plan->trial_days);

        // Company::create memicu TenantCreated → buat database + migrasi (TenancyServiceProvider).
        // Kegagalan di sana tidak menghapus baris company: status tetap `provisioning`
        // dan Super Admin bisa mengulang dari layar detail.
        $company = new Company([
            'code' => $v['code'],
            'name' => $v['name'],
            'subdomain' => $v['subdomain'],
            'db_name' => config('tenancy.database.prefix').mb_strtolower($v['code']),
            'timezone' => $v['timezone'],
            'status' => CompanyStatus::Provisioning,
            'plan_id' => $plan->id,
        ]);
        $company->setAttribute('admin_name', $v['admin_name']);
        $company->setAttribute('admin_email', mb_strtolower($v['admin_email']));

        try {
            $company->save();
        } catch (\Throwable $e) {
            if (! $company->exists) {
                throw $e;
            }

            $this->provision->gagal($company, $e);
        }

        Subscription::create([
            'company_id' => $company->getTenantKey(),
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays($trial)->endOfDay(),
        ]);

        PlatformAudit::record('Company dibuat', $company, $actor, [
            'code' => $company->code, 'subdomain' => $company->subdomain, 'plan' => $plan->code, 'trial_days' => $trial,
        ]);

        if ($company->provisioningError() === null) {
            $this->provision->handle($company->refresh(), $actor, databaseReady: true);
        }

        return $company->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validasi(array $data): array
    {
        $data['code'] = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $data['subdomain'] = mb_strtolower(trim((string) ($data['subdomain'] ?? '')));

        $validator = Validator::make($data, [
            'code' => ['required', 'regex:/^[A-Z0-9]{2,10}$/', 'unique:central.companies,code'],
            'name' => ['required', 'string', 'max:150'],
            'subdomain' => ['required', 'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', 'not_in:'.implode(',', self::RESERVED_SUBDOMAINS), 'unique:central.companies,subdomain'],
            'timezone' => ['required', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'plan_id' => ['required', 'integer'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:150'],
        ], [
            'code.regex' => 'Kode 2–10 huruf besar atau angka.',
            'subdomain.regex' => 'Subdomain hanya huruf kecil, angka, dan tanda hubung.',
            'subdomain.not_in' => 'Subdomain ini dipakai platform.',
        ], [
            'code' => 'Kode', 'name' => 'Nama', 'subdomain' => 'Subdomain', 'timezone' => 'Zona waktu',
            'plan_id' => 'Paket', 'trial_days' => 'Durasi trial', 'admin_name' => 'Nama Admin Company', 'admin_email' => 'Email Admin Company',
        ]);

        if ($validator->fails()) {
            $pesan = $validator->errors()->first();
            $field = (string) array_key_first($validator->errors()->messages());

            throw new PlatformRuleException('BR-GEN-11', $pesan, collect($validator->errors()->messages())->map(fn ($m) => $m[0])->all() ?: [$field => $pesan]);
        }

        // Nama database = prefiks + kode; tidak boleh sudah dipakai company lain.
        if (Company::query()->where('db_name', config('tenancy.database.prefix').mb_strtolower($data['code']))->exists()) {
            throw PlatformRuleException::field('BR-GEN-11', 'code', 'Database untuk kode ini sudah ada.');
        }

        return $validator->validated();
    }
}

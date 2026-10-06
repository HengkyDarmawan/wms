<?php

declare(strict_types=1);

namespace App\Domain\Platform\Console;

use App\Domain\Access\Actions\SetInitialPassword;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Actions\CreateCompany;
use App\Domain\Platform\Actions\ProvisionCompany;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Console\Command;

/**
 * `companies:create-sample` — satu contoh company siap masuk di server baru
 * (A-408): jalur yang sama dengan layar Super Admin — {@see CreateCompany} +
 * {@see ProvisionCompany} (data acuan, akun & aturan dasar) — lalu password
 * Admin Company dibuatkan seperti *Buatkan password* (A-335).
 *
 * Aman diulang: company yang masih `provisioning` (mis. cPanel menolak
 * CREATE DATABASE dan databasenya baru dibuat manual) dilanjutkan; password
 * hanya dibuat bila Admin Company belum pernah bisa masuk (BR-SUB-04).
 */
class CreateSampleCompanyCommand extends Command
{
    protected $signature = 'companies:create-sample
        {--code=DEMO : Kode company (2–10 huruf besar/angka)}
        {--subdomain=demo : Subdomain company}
        {--name=PT Contoh WMS : Nama company}
        {--admin-name=Admin Contoh : Nama Admin Company}
        {--email=admin@demo.wms.test : Email Admin Company}
        {--password= : Password Admin Company (min. 10 karakter); kosong = ditanyakan}';

    protected $description = 'Buat satu contoh company siap login (company + akun & aturan dasar + password Admin Company) — A-408';

    public function handle(CreateCompany $buat, ProvisionCompany $siapkan, SetInitialPassword $password): int
    {
        $aktor = PlatformUser::query()->orderBy('id')->first();
        $paket = Plan::query()->where('is_active', true)->orderBy('id')->first();

        if ($aktor === null || $paket === null) {
            $this->error('Super Admin atau paket belum ada. Jalankan dulu: php artisan db:seed --class="Database\Seeders\ProductionSeeder" --force');

            return self::FAILURE;
        }

        $kode = mb_strtoupper(trim((string) $this->option('code')));
        $company = Company::query()->where('code', $kode)->first();

        try {
            if ($company === null) {
                $this->line('Membuat company '.$kode.' …');
                $company = $buat->handle([
                    'code' => $kode,
                    'name' => (string) $this->option('name'),
                    'subdomain' => (string) $this->option('subdomain'),
                    'timezone' => 'Asia/Jakarta',
                    'plan_id' => $paket->id,
                    'admin_name' => (string) $this->option('admin-name'),
                    'admin_email' => (string) $this->option('email'),
                ], $aktor);
            }

            // Pembuatan database ditolak hosting → database dibuat manual → lanjutkan.
            if ($company->status === CompanyStatus::Provisioning) {
                $this->line('Melanjutkan penyiapan company '.$kode.' …');
                $company = $siapkan->handle($company->refresh(), $aktor);
            }
        } catch (PlatformRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($company->status !== CompanyStatus::Active) {
            $this->error('Penyiapan gagal: '.$company->provisioningError());
            $this->warn('Bila pesan berisi "Access denied" / CREATE DATABASE: buat database "'.$company->db_name
                .'" di cPanel > MySQL Databases, tambahkan user database aplikasi (ALL PRIVILEGES), lalu jalankan perintah ini lagi.');

            return self::FAILURE;
        }

        $email = mb_strtolower((string) $company->getAttribute('admin_email'));
        $admin = $company->run(fn () => User::query()->where('email', $email)->first());

        if ($admin === null || $admin->password !== null) {
            $this->info('Company '.$kode.' sudah aktif; password Admin Company tidak diubah.');
        } else {
            $sandi = (string) ($this->option('password') ?: $this->secret('Password Admin Company (min. 10 karakter)'));

            try {
                $company->run(fn () => $password->handle($admin, $sandi));
            } catch (\Throwable $e) {
                $this->error($e->getMessage().' Jalankan lagi perintah ini dengan password lain.');

                return self::FAILURE;
            }

            PlatformAudit::record('Password Admin Company pertama dibuatkan', $company, $aktor, ['via' => 'companies:create-sample']);
        }

        $this->newLine();
        $this->info('Company siap. Masuk di: '.$company->url('/login'));
        $this->line('Email Admin Company: '.$email);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Company = tenant (D-02, D-03). Tabel `companies` di database pusat; setiap company
 * punya database sendiri yang namanya disimpan di kolom `db_name`.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $subdomain
 * @property string $db_name
 * @property string $timezone
 * @property string $status
 */
class Company extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $table = 'companies';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Kolom nyata di tabel; atribut lain disimpan di kolom JSON `data`.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'code',
            'name',
            'subdomain',
            'db_name',
            'timezone',
            'status',
            'plan_id',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'plan_id' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function supportAccesses(): HasMany
    {
        return $this->hasMany(SupportAccess::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(FeatureFlag::class);
    }

    /** Host penuh company, mis. demo.wms.test (A-01). */
    public function host(): string
    {
        return $this->subdomain.'.'.config('tenancy.central_domains.0', 'wms.test');
    }

    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(SubscriptionInvoice::class, Subscription::class);
    }

    /**
     * Atribut tambahan di kolom JSON `data` (VirtualColumn): `admin_name`,
     * `admin_email` (Admin Company pertama), `provisioning_error`,
     * `provisioned_at`, `status_reason` (A-176, A-179).
     */
    public function provisioningError(): ?string
    {
        $pesan = $this->getAttribute('provisioning_error');

        return is_string($pesan) && $pesan !== '' ? $pesan : null;
    }

    public function isFeatureEnabled(string $key): bool
    {
        return (bool) $this->featureFlags()->where('key', $key)->value('enabled');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\SaveCompanySettings;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Support\CompanySettingCatalog;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar Pengaturan company (11-master §6, A-230): ambang hari/persen, saklar
 * fitur, zona waktu. Pemegang `company_setting.view` melihat; hanya
 * `company_setting.manage` yang boleh menyimpan.
 */
class CompanySettingsForm extends Component
{
    use HandlesMasterRules;

    /** @var array<string, int|float|string> */
    public array $nilai = [];

    /** @var array<string, bool> */
    public array $fitur = [];

    public string $timezone = 'Asia/Jakarta';

    public function mount(): void
    {
        $this->authorize('company_setting.view');
        $this->isi();
    }

    public function simpan(SaveCompanySettings $action): void
    {
        $this->authorize('company_setting.manage');
        $this->resetValidation();

        $this->ruleError = '';

        try {
            $action->handle($this->nilai, $this->fitur, $this->timezone, auth()->user());
        } catch (MasterRuleException $e) {
            // Kunci galat sudah memuat awalan properti (`nilai.<kunci>`, `timezone`).
            foreach ($e->fieldErrors as $field => $pesan) {
                $this->addError($field, $pesan);
            }

            if ($e->fieldErrors === []) {
                $this->ruleError = $e->getMessage();
            }

            return;
        }

        $this->isi();
        $this->dispatch('pesan', teks: __('Pengaturan company disimpan.'));
    }

    public function render(): View
    {
        return view('livewire.master.company-settings-form', [
            'katalog' => CompanySettingCatalog::nilai(),
            'grup' => collect(CompanySettingCatalog::nilai())->groupBy('grup', true)->map(fn ($k) => $k->keys()->all())->all(),
            'daftarFitur' => CompanySettingCatalog::fitur(),
            'pemakai' => CompanySettingCatalog::pemakaiFitur(),
            'zona' => CompanySettingCatalog::ZONA_WAKTU,
            'kunciPeriode' => CompanySetting::get('stock_lock_date'),
            'bolehUbah' => auth()->user()?->hasPermission('company_setting.manage') ?? false,
        ]);
    }

    private function isi(): void
    {
        $this->nilai = CompanySettingCatalog::nilaiSaatIni();
        $this->fitur = CompanySettingCatalog::fiturSaatIni();
        $this->timezone = (string) (tenant()?->timezone ?? 'Asia/Jakarta');
    }
}

<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $isBaru ? __('Permintaan baru') : __('Ubah permintaan') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Baris boleh menyebut item katalog atau ditulis bebas; staf gudang yang memetakannya nanti.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('requests.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Keterangan permintaan') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="req-proyek">{{ __('Proyek') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.project_id') is-invalid @enderror" id="req-proyek"
                        wire:model="form.project_id" @disabled(! $isBaru)>
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($projects as $proyek)
                        <option value="{{ $proyek->id }}">{{ $proyek->code }} — {{ $proyek->name }}</option>
                    @endforeach
                </select>
                @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @unless ($isBaru)
                    <div class="form-text">{{ __('Proyek tidak bisa diubah setelah REQ dibuat.') }}</div>
                @endunless
            </div>
            <div class="col-md-3">
                <label class="form-label" for="req-tanggal">
                    {{ __('Tanggal dibutuhkan') }} <span class="wajib">*</span>
                </label>
                <input class="form-control @error('form.required_date') is-invalid @enderror" id="req-tanggal"
                       type="date" wire:model="form.required_date">
                @error('form.required_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="req-catatan">{{ __('Catatan') }}</label>
                <input class="form-control" id="req-catatan" type="text" wire:model="form.notes"
                       placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <strong>{{ __('Baris permintaan') }}</strong>
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">
                {{ __('Tambah baris') }}
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 30%">{{ __('Item katalog') }}</th>
                        <th scope="col" style="width: 25%">{{ __('Atau tulis sendiri') }}</th>
                        <th scope="col" style="width: 12%">{{ __('Jumlah') }} <span class="wajib">*</span></th>
                        <th scope="col" style="width: 13%">{{ __('Kepemilikan') }}</th>
                        <th scope="col" style="width: 15%">{{ __('Dibutuhkan') }}</th>
                        <th scope="col" style="width: 5%"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $baris)
                        <tr wire:key="baris-{{ $i }}">
                            <td>
                                <select class="form-select form-select-sm" wire:model="lines.{{ $i }}.item_id">
                                    <option value="">{{ __('— tidak dari katalog —') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm" type="text"
                                       wire:model="lines.{{ $i }}.non_catalog_text"
                                       placeholder="{{ __('Nama barang') }}">
                            </td>
                            <td>
                                <input class="form-control form-control-sm @error('form.qty_base') is-invalid @enderror"
                                       type="number" step="0.0001" min="0" wire:model="lines.{{ $i }}.qty_base">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="lines.{{ $i }}.line_ownership">
                                    @foreach ($ownerships as $nilai => $label)
                                        <option value="{{ $nilai }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm" type="date"
                                       wire:model="lines.{{ $i }}.required_date">
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" type="button"
                                        wire:click="hapusBaris({{ $i }})" title="{{ __('Hapus baris') }}">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('form.item_id') <div class="card-footer text-danger">{{ $message }}</div> @enderror
        @error('form.qty_base') <div class="card-footer text-danger">{{ $message }}</div> @enderror
        @error('form.line_ownership') <div class="card-footer text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" type="button" wire:click="simpan">{{ __('Simpan draf') }}</button>
        <button class="btn btn-primary" type="button" wire:click="simpanDanAjukan">
            {{ __('Simpan dan ajukan') }}
        </button>
    </div>
</div>

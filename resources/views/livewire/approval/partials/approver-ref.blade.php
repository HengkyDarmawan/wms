{{-- Pilihan rujukan approver: user / jabatan / role. Jenis lain tidak butuh rujukan. --}}
@if ($jenis === 'user')
    <select class="form-select form-select-sm" wire:model="{{ $model }}" aria-label="{{ __('User') }}">
        <option value="">{{ __('Pilih user…') }}</option>
        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
    </select>
@elseif ($jenis === 'position')
    <select class="form-select form-select-sm" wire:model="{{ $model }}" aria-label="{{ __('Jabatan') }}">
        <option value="">{{ __('Pilih jabatan…') }}</option>
        @foreach ($positions as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
    </select>
@elseif ($jenis === 'role')
    <select class="form-select form-select-sm" wire:model="{{ $model }}" aria-label="{{ __('Role') }}">
        <option value="">{{ __('Pilih role…') }}</option>
        @foreach ($roles as $r) <option value="{{ $r->id }}">{{ $r->name }}</option> @endforeach
    </select>
@endif

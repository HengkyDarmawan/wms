{{-- Pilihan rujukan approver: user / jabatan / role. Jenis lain tidak butuh rujukan. --}}
{{-- A-388: user dicari ke server (A-384); jabatan berkelompok per unit; role dimuat sekaligus. --}}
@if ($jenis === 'user')
    <x-pilih :model="$model" kecil server kunci="user" :aria="__('User')" :kosong="__('Pilih user…')"
             :options="$opsiOrang(data_get(['steps' => $steps], $model))" />
@elseif ($jenis === 'position')
    <x-pilih :model="$model" kecil :aria="__('Jabatan')" :kosong="__('Pilih jabatan…')" :options="$opsiJabatan" />
@elseif ($jenis === 'role')
    <x-pilih :model="$model" kecil :aria="__('Role')" :kosong="__('Pilih role…')"
             :options="$roles->map(fn ($r) => ['value' => $r->id, 'text' => $r->name])->all()" />
@endif

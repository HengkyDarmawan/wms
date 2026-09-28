{{-- A-320: satu objek denah (tanpa stok). Variabel: $o, $S, $edit, $terpilih, $tumpuk. --}}
@php($pilihO = $terpilih === 'obj:'.$o['id'])
@php($merahO = isset($tumpuk['obj:'.$o['id']]))
@php($ikon = ['door' => '🚪', 'dock' => '🚚', 'forklift_lane' => '⇄', 'pillar' => '', 'office' => '🏢', 'open_area' => ''][$o['type']] ?? '')
<g wire:key="obj-{{ $o['id'] }}" data-jenis="obj" data-id="{{ $o['id'] }}" data-objek="{{ $o['type'] }}"
   data-x="{{ $o['x'] }}" data-y="{{ $o['y'] }}" data-p="{{ $o['p'] }}" data-l="{{ $o['l'] }}"
   transform="translate({{ $o['x'] * $S }},{{ $o['y'] * $S }})" style="cursor: {{ $edit ? 'move' : 'default' }}">
    <rect data-badan x="0" y="0" width="{{ $o['p'] * $S }}" height="{{ $o['l'] * $S }}" rx="{{ $o['type'] === 'pillar' ? 2 : 5 }}"
          fill="{{ $o['fill'] }}" stroke="{{ $merahO ? '#e03131' : ($pilihO ? '#6366f1' : $o['stroke']) }}" stroke-width="{{ $pilihO || $merahO ? 4 : 1.5 }}"
          @if (in_array($o['type'], ['forklift_lane', 'open_area'], true) || $merahO) stroke-dasharray="{{ $merahO ? '8 4' : '10 6' }}" @endif><title>{{ $o['label'] }} · {{ $o['name'] }}</title></rect>
    @if ($o['type'] === 'pillar')
        <text x="{{ $o['p'] * $S + 4 }}" y="-3" font-size="{{ 12 * $F }}" fill="#495057" pointer-events="none">{{ $o['name'] }}</text>
    @else
        <text x="8" y="{{ min($o['l'] * $S - 6, 20 * $F) }}" font-size="{{ 14 * $F }}" fill="#495057" pointer-events="none">{{ $ikon }} {{ \Illuminate\Support\Str::limit($o['name'], 24) }}</text>
    @endif
    @if ($edit && $pilihO)
        <rect data-handle x="{{ $o['p'] * $S - 7 }}" y="{{ $o['l'] * $S - 7 }}" width="14" height="14" fill="#6366f1" style="cursor: nwse-resize" />
    @endif
</g>

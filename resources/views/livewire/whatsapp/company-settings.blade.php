<div>
    @if ($aktif)
        {{-- Fase 2a (31-whatsapp §6, BR-WA-02): lapis 2 — kejadian yang boleh lewat WhatsApp. --}}
        <div class="card mb-3" id="whatsapp-company">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong><i class="bi bi-whatsapp" aria-hidden="true"></i> {{ __('WhatsApp') }}</strong>
                <span class="small text-muted">
                    {{ __('Pemakaian bulan ini') }}: {{ $kuota['used'] }} {{ $kuota['limit'] !== null ? '/ '.$kuota['limit'] : __('(tanpa batas)') }} {{ __('pesan template') }}
                </span>
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('Pilih kejadian yang boleh dikirim lewat WhatsApp. Langsung = satu pesan per kejadian; Ringkasan harian = digabung satu pesan pukul 07.00. User tetap bisa mematikannya di preferensi notifikasi, dan hanya nomor terverifikasi yang menerima. Tombol Setujui/Tolak diatur per lapis di aturan approval.') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-3">
                        <thead><tr><th>{{ __('Kejadian') }}</th><th class="text-center">{{ __('Mati') }}</th><th class="text-center">{{ __('Langsung') }}</th><th class="text-center">{{ __('Ringkasan harian') }}</th></tr></thead>
                        <tbody>
                            @foreach ($daftar as $k => $e)
                                @php($kk = str_replace('.', '__', $k))
                                <tr wire:key="wa-{{ $kk }}">
                                    <td>{{ __($e['label']) }}</td>
                                    @foreach (['off', 'instant', 'digest'] as $m)
                                        <td class="text-center"><input class="form-check-input" type="radio" name="wa-{{ $kk }}" value="{{ $m }}" wire:model="events.{{ $kk }}" @disabled(! $bolehUbah) aria-label="{{ __($e['label']) }} {{ $m }}"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="wa-confirm" wire:model="confirmReply" @disabled(! $bolehUbah)>
                    <label class="form-check-label" for="wa-confirm">{{ __('Kirim balasan konfirmasi setelah approver menekan Setujui') }}</label>
                    <div class="small text-muted">{{ __('Satu pesan singkat; memakai kuota pesan layanan platform (riset §2.6).') }}</div>
                </div>
            </div>
            @if ($bolehUbah)
                <div class="card-footer"><button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan pengaturan WhatsApp') }}</button></div>
            @endif
        </div>
    @endif
</div>

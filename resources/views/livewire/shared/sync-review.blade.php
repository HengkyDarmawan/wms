<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Antrean tinjauan offline') }}</h1>
            <p class="text-muted mb-0">{{ __('Isian dari perangkat offline yang bentrok (mis. sesi sudah ditutup) tidak diposting otomatis. Coba lagi atas nama pengirim, atau tolak.') }}</p>
        </div>
        <select class="form-select" wire:model.live="status" aria-label="{{ __('Status') }}" style="width: auto">
            <option value="review">{{ __('Perlu ditinjau') }}</option>
            <option value="rejected">{{ __('Ditolak') }}</option>
            <option value="posted">{{ __('Diposting') }}</option>
            <option value="">{{ __('Semua') }}</option>
        </select>
    </div>

    <div class="card">
        @if ($daftar->isEmpty())
            <div class="card-body text-muted small">{{ $status === 'review' ? __('Tidak ada isian offline yang perlu ditinjau.') : __('Tidak ada data.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Dicatat') }}</th><th>{{ __('Pengirim') }}</th><th>{{ __('Isian') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Aksi') }}</th></tr></thead>
                    <tbody>
                        @foreach ($daftar as $i)
                            @php($t = $tugas[(int) ($i->payload['assignment_id'] ?? 0)] ?? null)
                            <tr wire:key="sync-{{ $i->id }}">
                                <td class="small text-nowrap">{{ ($i->recorded_at ?? $i->created_at)?->lokal()->format('d/m/Y H:i') }}</td>
                                <td>{{ $i->user?->name ?? '-' }}</td>
                                <td class="small">
                                    @if ($i->kind === 'count_record')
                                        {{ __('Hitung opname') }} · {{ $t?->stockCount?->number ?? '-' }} · {{ __('Bin') }} {{ $t?->bin?->code ?? '-' }}
                                        · {{ count((array) ($i->payload['qtys'] ?? [])) }} {{ __('baris') }}@if (! empty($i->payload['finish'])) · {{ __('selesai') }}@endif
                                    @else
                                        {{ ['putaway_place' => __('Put-away'), 'pick_record' => __('Picking'), 'receipt_receive' => __('Penerimaan'), 'shipment_ship' => __('Konfirmasi muat'), 'delivery_confirm' => __('Bukti terima')][$i->kind] ?? $i->kind }}
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ ['review' => 'warning', 'rejected' => 'danger', 'posted' => 'success'][$i->status] ?? 'secondary' }}">{{ $label[$i->status] ?? $i->status }}</span>
                                    @if ($i->message) <div class="small text-muted">{{ $i->message }}</div> @endif
                                    @if ($i->decider) <div class="small text-muted">{{ __('oleh :n', ['n' => $i->decider->name]) }}</div> @endif
                                </td>
                                <td class="text-end">
                                    @if ($i->status === 'review')
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="cobaLagi({{ $i->id }})">{{ __('Coba lagi') }}</button>
                                            <button class="btn btn-sm btn-outline-danger" type="button" wire:click="tolak({{ $i->id }})" wire:confirm="{{ __('Tolak isian ini? Tidak akan diposting.') }}">{{ __('Tolak') }}</button>
                                        </div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $daftar->links() }}</div>
        @endif
    </div>
</div>

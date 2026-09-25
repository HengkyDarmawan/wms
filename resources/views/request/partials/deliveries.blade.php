{{-- BR-REQ-10: pengiriman REQ, bukti terima, dan tanggapan pemohon (Terima / Keberatan). --}}
@php($pengiriman = app(\App\Domain\Request\Support\RequestDeliveries::class)->for($req))
@php($portal = request()->routeIs('portal.*'))

@if ($pengiriman->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Pengiriman & konfirmasi terima') }}</strong></div>
        @error('delivery') <div class="alert alert-danger m-2 py-2">{{ $message }} @if (session('ruleCode')) <span class="badge text-bg-dark">{{ session('ruleCode') }}</span> @endif</div> @enderror
        <ul class="list-group list-group-flush">
            @foreach ($pengiriman as $sj)
                @php($pod = $sj->proof)
                <li class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <strong>{{ $sj->number }}</strong> <span class="badge {{ $sj->status->badge() }}">{{ $sj->status->label() }}</span>
                            @if ($pod)
                                <span class="small text-muted">· {{ __('diterima') }} {{ $pod->received_by_name }} {{ $pod->confirmed_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') }}</span>
                                @if ($pod->confirmation)
                                    <span class="badge text-bg-secondary">{{ $pod->confirmation->label() }}</span>
                                @elseif ($pod->confirm_deadline_at)
                                    <span class="small text-muted">· {{ __('tanggapi sebelum') }} {{ $pod->confirm_deadline_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') }}</span>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($pod)
                        <div class="small mt-1">
                            @foreach ($pod->lines as $pl)
                                <span class="me-3">{{ $pl->shipmentLine?->pickTaskLine?->item?->code }}: {{ __('baik') }} {{ (float) $pl->qty_good }} @if ((float) $pl->qty_damaged > 0) · {{ __('rusak') }} {{ (float) $pl->qty_damaged }} @endif @if ((float) $pl->qty_missing > 0) · {{ __('kurang') }} {{ (float) $pl->qty_missing }} @endif</span>
                            @endforeach
                        </div>

                        @if ($pod->isPending() && $pod->confirm_deadline_at?->isFuture())
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @can('request.confirm_receipt')
                                    <form method="POST" action="{{ route($portal ? 'portal.requests.receipt.confirm' : 'requests.receipt.confirm', [$req->id, $pod->id]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success" type="submit">{{ __('Terima') }}</button>
                                    </form>
                                @endcan
                                @can('request.dispute_receipt')
                                    @if ($sj->destination_type === \App\Domain\Shipment\Enums\DestinationType::ProjectClient)
                                        <details>
                                            <summary class="btn btn-sm btn-outline-danger">{{ __('Ajukan keberatan') }}</summary>
                                            <form class="mt-2" method="POST" enctype="multipart/form-data" action="{{ route($portal ? 'portal.requests.receipt.dispute' : 'requests.receipt.dispute', [$req->id, $pod->id]) }}">
                                                @csrf
                                                @foreach ($pod->lines as $pl)
                                                    <div class="row g-2 align-items-end mb-2">
                                                        <div class="col-md-3 small">{{ $pl->shipmentLine?->pickTaskLine?->item?->code }} <span class="text-muted">({{ __('baik') }} {{ (float) $pl->qty_good }})</span></div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small" for="kurang-{{ $pl->id }}">{{ __('Kurang') }}</label>
                                                            <input class="form-control form-control-sm" id="kurang-{{ $pl->id }}" type="number" step="any" min="0" name="lines[{{ $pl->shipment_line_id }}][qty_missing]">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small" for="rusak-{{ $pl->id }}">{{ __('Rusak') }}</label>
                                                            <input class="form-control form-control-sm" id="rusak-{{ $pl->id }}" type="number" step="any" min="0" name="lines[{{ $pl->shipment_line_id }}][qty_damaged]">
                                                        </div>
                                                        <div class="col-md-5">
                                                            <label class="form-label small" for="foto-{{ $pl->id }}">{{ __('Foto kerusakan') }}</label>
                                                            <input class="form-control form-control-sm" id="foto-{{ $pl->id }}" type="file" accept="image/jpeg,image/png,image/webp" name="photos[{{ $pl->shipment_line_id }}]">
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <input class="form-control form-control-sm mb-2" type="text" name="notes" maxlength="255" placeholder="{{ __('Keterangan (opsional)') }}" aria-label="{{ __('Keterangan keberatan') }}">
                                                <button class="btn btn-sm btn-danger" type="submit">{{ __('Kirim keberatan') }}</button>
                                            </form>
                                        </details>
                                    @endif
                                @endcan
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

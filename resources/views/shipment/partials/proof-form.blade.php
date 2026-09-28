{{--
    Form bukti terima (BR-SJ-05, A-64, A-244, A-312): dipakai detail SJ internal
    dan halaman portal klien. Properti Livewire dari FillsDeliveryProof + $form.
    Variabel: $sj, $lines, $kanal (ProofChannel|null), $batal (metode tutup).
--}}
<div class="card border-success mb-3" data-draft="pod-{{ $sj->id }}">
    <div class="card-header">
        <strong>{{ __('Bukti terima') }}</strong>
        @if ($kanal)
            <span class="badge text-bg-light ms-1">{{ $kanal->label() }}</span>
        @endif
    </div>
    <div class="alert alert-warning py-2 small m-2 d-none" role="status" data-draft-status></div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="terima-nama">
                    {{ __('Nama penerima') }} <span class="wajib">*</span>
                </label>
                <input class="form-control @error('form.received_by_name') is-invalid @enderror"
                       id="terima-nama" type="text" wire:model="form.received_by_name">
                @error('form.received_by_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                {{-- A-313: nomor GR di sistem klien — referensi teks, WMS tidak menunggunya. --}}
                <label class="form-label" for="terima-gr">{{ __('No. GR klien') }}</label>
                <input class="form-control @error('form.client_gr_number') is-invalid @enderror" id="terima-gr" type="text"
                       maxlength="60" wire:model="form.client_gr_number" placeholder="{{ __('Opsional') }}">
                @error('form.client_gr_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="terima-catatan">{{ __('Catatan') }}</label>
                <input class="form-control" id="terima-catatan" type="text" wire:model="form.notes"
                       placeholder="{{ __('Opsional') }}">
            </div>
        </div>

        @if ($kanal === \App\Domain\Shipment\Enums\ProofChannel::SignedDocument)
            <div class="alert alert-info py-2 small">
                {{ __('Cadangan: Anda mengisi dari SJ yang sudah ditandatangani & dicap penerima. Salin nama penerima dan jumlah per baris dari SJ itu, lalu unggah fotonya.') }}
            </div>
        @endif

        <p class="text-muted small">
            {{ __('Jumlah baik + rusak + kurang harus sama dengan yang dikirim. Foto wajib bila ada yang rusak.') }}
        </p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                        <th scope="col">{{ __('Baik') }}</th>
                        <th scope="col">{{ __('Rusak') }}</th>
                        <th scope="col">{{ __('Kurang') }}</th>
                        <th scope="col">{{ __('Foto kerusakan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr wire:key="terima-{{ $l->id }}">
                            <td>
                                {{ $l->item?->code }}
                                @if ($l->serial) <div class="small text-muted">{{ __('Serial') }} {{ $l->serial->serial_no }}</div> @endif
                                @if ($l->piece) <div class="small text-muted">{{ __('Potongan') }} {{ $l->piece->piece_no }}</div> @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                            @if (($terima[$l->id]['kondisi'] ?? null) !== null)
                                {{-- A-244: satu unit — baik, rusak, atau kurang seluruhnya. --}}
                                <td colspan="3">
                                    <select class="form-select form-select-sm" wire:model="terima.{{ $l->id }}.kondisi" aria-label="{{ __('Kondisi unit') }}">
                                        @foreach (\App\Domain\Shipment\Enums\PodUnitCondition::cases() as $k)
                                            <option value="{{ $k->value }}">{{ $k->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @else
                                <td>
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                           wire:model="terima.{{ $l->id }}.qty_good">
                                </td>
                                <td>
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                           wire:model="terima.{{ $l->id }}.qty_damaged">
                                </td>
                                <td>
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                           wire:model="terima.{{ $l->id }}.qty_missing">
                                </td>
                            @endif
                            <td>
                                <input class="form-control form-control-sm @error('fotoRusak.'.$l->id) is-invalid @enderror" type="file"
                                       accept="image/jpeg,image/png,image/webp" capture="environment"
                                       wire:model="fotoRusak.{{ $l->id }}" aria-label="{{ __('Foto kerusakan') }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @error('form.qty_good') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        @error('form.damage_photo_path') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

        {{-- A-231: foto serah terima & tanda tangan penerima (kanvas → data URL). --}}
        <div class="row g-3 mt-1">
            {{-- A-316: foto SJ bertanda tangan & cap — wajib bagi portal klien dan cadangan gudang asal. --}}
            <div class="col-md-6">
                <label class="form-label" for="terima-foto-sj">
                    {{ __('Foto SJ bertanda tangan & cap') }}
                    @if ($kanal?->requiresSignedDocument()) <span class="wajib">*</span> @else <span class="text-muted small">({{ __('opsional') }})</span> @endif
                </label>
                <input class="form-control @error('fotoSj') is-invalid @enderror" id="terima-foto-sj" type="file"
                       accept="image/jpeg,image/png,image/webp" capture="environment" wire:model="fotoSj">
                @error('fotoSj') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @error('form.signed_document_path') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="terima-foto">{{ __('Foto serah terima') }} <span class="text-muted small">({{ __('opsional') }})</span></label>
                <input class="form-control @error('foto') is-invalid @enderror" id="terima-foto" type="file"
                       accept="image/jpeg,image/png,image/webp" capture="environment" wire:model="foto">
                @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6" data-signature wire:ignore>
                <label class="form-label">{{ __('Tanda tangan penerima') }} <span class="text-muted small">({{ __('gambar dengan jari') }})</span></label>
                <canvas class="border rounded w-100 bg-white" height="140" aria-label="{{ __('Kanvas tanda tangan') }}"></canvas>
                <input type="hidden" wire:model="tandaTangan" data-signature-target>
                <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-signature-clear>{{ __('Hapus tanda tangan') }}</button>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-success" type="button" wire:click="simpanBuktiTerima">{{ __('Simpan') }}</button>
        <button class="btn btn-outline-secondary" type="button" wire:click="{{ $batal }}">{{ __('Tutup') }}</button>
    </div>
</div>

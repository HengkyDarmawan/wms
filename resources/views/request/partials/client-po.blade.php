{{--
    A-313/A-318: No. PO klien di kepala REQ — tampil & bisa diubah selama REQ
    belum final. Properti Livewire dari EditsClientPo. Variabel: $req.
--}}
@if ($ubahPo)
    <div class="d-flex flex-wrap align-items-start gap-2 mt-2">
        <div>
            <input class="form-control form-control-sm @error('po.client_po_number') is-invalid @enderror" id="req-po-klien" type="text"
                   maxlength="60" wire:model="poKlien" placeholder="{{ __('No. PO klien') }}" aria-label="{{ __('No. PO klien') }}">
            @error('po.client_po_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button class="btn btn-sm btn-primary" type="button" wire:click="simpanPoKlien">{{ __('Simpan') }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="$set('ubahPo', false)">{{ __('Batal') }}</button>
    </div>
@else
    <div class="small mt-1">
        {{ __('No. PO klien') }}: <strong>{{ $req->client_po_number ?: '—' }}</strong>
        @can('setClientPo', $req)
            <button class="btn btn-link btn-sm p-0 ms-1 align-baseline" type="button" wire:click="mintaUbahPo">{{ $req->client_po_number ? __('Ubah') : __('Isi') }}</button>
        @endcan
    </div>
@endif

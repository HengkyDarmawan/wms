{{--
    K-J (A-353): Denah versi daftar — untuk HP dan gudang sangat besar. Zona → rak →
    tingkat (paling atas dulu) → petak; ketuk petak untuk melihat isinya.
--}}
<div class="card mb-3" data-denah-daftar>
    <div class="list-group list-group-flush">
        <template x-for="z in d.zones" :key="z.id">
            <div class="list-group-item">
                <div class="fw-semibold" x-text="'Zona ' + z.code + ' — ' + z.name"></div>
                <div class="text-muted small" x-show="!z.racks.length">{{ __('Belum ada rak.') }}</div>
                <template x-for="r in z.racks" :key="r.id">
                    <details class="mt-2 border rounded p-2" :open="cocokRak(r)">
                        <summary class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold" x-text="'Rak ' + r.code + (r.is_area ? ' ▦' : '')"></span>
                            <span class="text-muted small" x-show="r.name" x-text="r.name"></span>
                            <span class="badge text-bg-light border" x-text="r.jumlah_bin + ' bin'"></span>
                            <span class="text-muted small" x-show="r.is_area" x-text="teksKapasitasArea(r).replace(' · ', '')"></span>
                            <button class="btn btn-sm btn-outline-primary py-0 ms-auto" type="button" x-on:click.prevent="bukaRak(r.id)">{{ __('Lihat isi') }}</button>
                        </summary>
                        <template x-for="l in r.levels" :key="l.id">
                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                <span class="text-muted small fw-semibold" style="width: 2.2rem" x-text="l.code"></span>
                                {{-- A-359: bin tergabung tampil sebagai bagian bin utamanya, mis. "R01 · L1 · 01 + 02". --}}
                                <template x-for="b in l.bins.filter((x) => !x.utama)" :key="b.id">
                                    <button class="btn btn-sm border py-0" type="button" :style="'background:' + warnaBin(b)"
                                            :class="cocokBin(b) ? 'border-warning border-2' : ''" :title="labelGabung(b, r) + ' · ' + b.code"
                                            x-on:click="bukaRak(r.id, b.id)">
                                        <span x-text="b.short"></span><span class="small" x-show="(b.tergabung ?? []).length" x-text="' ⧉ ' + labelGabung(b, r)" data-gabung-label></span>
                                    </button>
                                </template>
                                <span class="text-muted small fst-italic" x-show="!l.bins.length">{{ __('belum ada bin') }}</span>
                            </div>
                        </template>
                    </details>
                </template>
            </div>
        </template>
    </div>
</div>

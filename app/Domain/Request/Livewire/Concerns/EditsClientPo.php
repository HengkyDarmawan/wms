<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire\Concerns;

use App\Domain\Request\Actions\SetClientPoNumber;

/**
 * A-318: ubah No. PO klien dari detail REQ (internal & portal). Pemakai
 * menyediakan `request(): MaterialRequest` dan `HandlesRequestRules`.
 */
trait EditsClientPo
{
    public bool $ubahPo = false;

    public string $poKlien = '';

    public function mintaUbahPo(): void
    {
        $req = $this->request();
        $this->authorize('setClientPo', $req);

        $this->poKlien = (string) $req->client_po_number;
        $this->ubahPo = true;
    }

    public function simpanPoKlien(SetClientPoNumber $action): void
    {
        $req = $this->request();
        $this->authorize('setClientPo', $req);

        if ($this->jalankan(fn () => $action->handle($req, $this->poKlien, auth()->user()), 'po')) {
            $this->ubahPo = false;
            $this->dispatch('pesan', teks: __('No. PO klien tersimpan.'));
        }
    }
}

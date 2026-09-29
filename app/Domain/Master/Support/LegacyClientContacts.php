<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use Illuminate\Support\Facades\DB;

/**
 * Isi balik kontak tunggal lama klien menjadi PIC pertama (A-326).
 *
 * Sebelum ada `client_contacts`, satu klien hanya punya satu kontak di kolom
 * `clients.contact_name/phone/email`. Kolom itu **tidak dihapus** (P-03) dan
 * nilainya disalin apa adanya ke sini, termasuk bentuk nomornya — pembakuan
 * `62…` baru terjadi saat PIC itu disimpan ulang lewat `SaveClientContact`.
 *
 * Dipakai migrasi tenant `000450` dan bisa dijalankan ulang dengan aman:
 * klien yang sudah punya PIC dilewati.
 */
class LegacyClientContacts
{
    /** @return int jumlah PIC yang dibuat */
    public static function isiBalik(): int
    {
        $sudah = DB::table('client_contacts')->distinct()->pluck('client_id')
            ->map(fn ($id) => (int) $id)->all();

        $klien = DB::table('clients')
            ->where(function ($q) {
                $q->whereNotNull('contact_name')->orWhereNotNull('phone')->orWhereNotNull('email');
            })
            ->get(['id', 'name', 'contact_name', 'phone', 'email', 'is_active']);

        $jumlah = 0;

        foreach ($klien as $c) {
            if (in_array((int) $c->id, $sudah, true)) {
                continue;
            }

            $nama = trim((string) $c->contact_name);

            DB::table('client_contacts')->insert([
                'client_id' => $c->id,
                'name' => $nama !== '' ? $nama : 'Kontak '.$c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'is_active' => (bool) $c->is_active,
                'notes' => 'Dipindahkan otomatis dari kontak klien lama.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jumlah++;
        }

        return $jumlah;
    }
}

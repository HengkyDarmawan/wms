<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Vendor;
use App\Domain\Master\Support\EnumInput;
use App\Domain\Master\Support\MasterCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission: `vendor.create` / `vendor.update`.
 *
 * A-52 jenis vendor; A-53 vendor sementara dibuat saat memesan lalu dilengkapi.
 * Tidak ada harga di sini (D-07) — penawaran dan harga milik modul Purchasing.
 */
class SaveVendor
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?Vendor $vendor, array $attributes, ?User $actor = null): Vendor
    {
        $baru = $vendor === null || ! $vendor->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama vendor wajib diisi.');
        }

        $kodeMasukan = trim((string) ($attributes['code'] ?? ''));

        // A-53: vendor sementara boleh dibuat tanpa kode; kode dibangkitkan dari nama.
        if ($baru && $kodeMasukan === '') {
            $kodeMasukan = $this->bangkitkanKode($nama);
        }

        $kode = MasterCode::resolve($vendor, $kodeMasukan, 'vendor');

        $bentrok = Vendor::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($vendor->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode vendor "'.$kode.'" sudah dipakai.');
        }

        $jenis = EnumInput::required(VendorType::class, $attributes['vendor_type'] ?? null, $vendor?->vendor_type ?? VendorType::Company, 'vendor_type');
        $status = EnumInput::required(VendorStatus::class, $attributes['status'] ?? null, $vendor?->status ?? VendorStatus::Active, 'status');

        // A-53: status aktif menuntut kontak minimal supaya vendor bisa dihubungi.
        $telepon = $this->kosongJadiNull($attributes['phone'] ?? null);
        $email = $this->kosongJadiNull($attributes['email'] ?? null);

        if ($status === VendorStatus::Active && $telepon === null && $email === null) {
            throw MasterRuleException::fields(
                ['phone' => 'Vendor aktif butuh telepon atau email. Simpan sebagai Sementara bila datanya belum lengkap.'],
                'A-53',
            );
        }

        $data = [
            'code' => $kode,
            'name' => $nama,
            'tax_id' => $this->kosongJadiNull($attributes['tax_id'] ?? null),
            'contact_name' => $this->kosongJadiNull($attributes['contact_name'] ?? null),
            'phone' => $telepon,
            'email' => $email,
            'address' => $this->kosongJadiNull($attributes['address'] ?? null),
            'payment_terms' => $this->kosongJadiNull($attributes['payment_terms'] ?? null),
            'vendor_type' => $jenis,
            'status' => $status,
        ];

        $vendor = DB::transaction(function () use ($vendor, $baru, $data, $status): Vendor {
            if ($baru) {
                return Vendor::create($data + ['is_active' => $status !== VendorStatus::Inactive]);
            }

            $vendor->fill($data + ['is_active' => $status !== VendorStatus::Inactive])->save();

            return $vendor;
        });

        activity('master')
            ->performedOn($vendor)
            ->causedBy($actor)
            ->withProperties(['vendor_type' => $jenis->value, 'status' => $status->value])
            ->log($baru ? 'Vendor dibuat' : 'Vendor diubah');

        return $vendor->refresh();
    }

    /**
     * Vendor sementara yang dibuat cepat saat memesan (A-53). Statusnya
     * `provisional` sampai Admin melengkapi datanya.
     */
    public function provisional(string $name, VendorType $type, ?User $actor = null): Vendor
    {
        return $this->handle(null, [
            'name' => $name,
            'vendor_type' => $type,
            'status' => VendorStatus::Provisional,
        ], $actor);
    }

    private function bangkitkanKode(string $nama): string
    {
        $dasar = Str::of($nama)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(20, '')->value();
        $dasar = $dasar === '' ? 'VND' : $dasar;
        $kode = $dasar;
        $urutan = 1;

        while (Vendor::query()->where('code', $kode)->exists()) {
            $urutan++;
            $kode = Str::limit($dasar, 24, '').'-'.$urutan;
        }

        return $kode;
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }
}

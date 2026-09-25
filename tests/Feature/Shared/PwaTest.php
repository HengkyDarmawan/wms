<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PWA-01 — PWA installable: manifest, ikon, service worker, halaman offline
 * tersedia; halaman memuat manifest; layar lapangan menandai draf lokal dan
 * input pindai (Blueprint §11, A-49, A-193). Perilaku kamera & localStorage
 * diperiksa manual di browser.
 */
class PwaTest extends TenantTestCase
{
    #[Test]
    public function tc_pwa_01_berkas_pwa_dan_penanda_layar(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('#6366f1', $manifest['theme_color'], 'Warna tema PWA = --bs-primary NexaDash.');

        foreach ($manifest['icons'] as $ikon) {
            $this->assertFileExists(public_path(ltrim($ikon['src'], '/')));
            [$lebar] = getimagesize(public_path(ltrim($ikon['src'], '/')));
            $this->assertSame((int) explode('x', $ikon['sizes'])[0], $lebar);
        }

        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("req.method !== 'GET'", $sw, 'POST tidak pernah disimpan.');
        $this->assertFileExists(public_path('offline.html'));

        $this->get($this->tenantUrl('login'))->assertOk()->assertSee('/manifest.webmanifest', false)
            ->assertSee('<meta name="theme-color" content="#6366f1">', false);
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('/'))->assertOk()
            ->assertSee('/manifest.webmanifest', false);

        $entri = (string) file_get_contents(resource_path('views/livewire/count/count-entry.blade.php'));
        $this->assertStringContainsString('data-draft="count-task-', $entri);
        $this->assertStringContainsString('data-scan', $entri);
        $this->assertStringContainsString('data-draft="pod-', (string) file_get_contents(resource_path('views/livewire/shipment/shipment-detail.blade.php')));
    }
}

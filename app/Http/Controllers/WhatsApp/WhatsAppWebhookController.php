<?php

declare(strict_types=1);

namespace App\Http\Controllers\WhatsApp;

use App\Domain\WhatsApp\Support\WhatsAppInbound;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook WhatsApp Cloud API di domain pusat (Arsitektur §8, 31-whatsapp §6).
 *
 * GET = verifikasi pendaftaran (`hub.verify_token` → `hub.challenge`).
 * POST = pesan masuk & status; diproses hanya bila tanda tangan
 * `X-Hub-Signature-256` (HMAC-SHA256 body mentah dengan app secret) sah
 * (BR-WA-04). Tanpa app secret, POST hanya diterima driver `log` (lokal).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('wms.whatsapp.verify_token');

        abort_unless($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppInbound $inbound): JsonResponse
    {
        abort_unless($this->sah($request), 403);

        $hasil = $inbound->process((array) $request->json()->all());

        return response()->json(['ok' => true] + $hasil);
    }

    private function sah(Request $request): bool
    {
        $rahasia = (string) config('wms.whatsapp.app_secret');

        if ($rahasia === '') {
            return config('wms.whatsapp.driver') === 'log';
        }

        $kiriman = (string) $request->header('X-Hub-Signature-256');
        $harusnya = 'sha256='.hash_hmac('sha256', $request->getContent(), $rahasia);

        return $kiriman !== '' && hash_equals($harusnya, $kiriman);
    }
}
